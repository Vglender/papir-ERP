<?php
/**
 * МойСклад → Papir webhook для counterparty.
 * Принимает события CREATE/UPDATE/DELETE для counterparty.
 *
 * Relay: http://159.69.1.229/conterparty.php → https://papir.officetorg.com.ua/counterparties/webhook/moysklad
 * Логи: /var/www/papir/storage/ms_webhook_counterparty.log
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';
require_once __DIR__ . '/../../moysklad/src/WebhookCpHelper.php';

function mswhk_cp_log($msg) {
    @file_put_contents('/var/www/papir/storage/ms_webhook_counterparty.log',
        date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

mswhk_cp_log('Incoming: ' . $raw);

echo json_encode(array('ok' => true));
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');
    ob_end_flush();
    flush();
}

if (!is_array($body) || empty($body['events'])) exit;

$ms        = new MoySkladApi();
$processed = 0;
$errors    = array();

foreach ($body['events'] as $event) {
    $action = isset($event['action'])       ? strtoupper($event['action'])       : '';
    $type   = isset($event['meta']['type']) ? strtolower($event['meta']['type']) : '';
    $href   = isset($event['meta']['href']) ? (string)$event['meta']['href']     : '';

    if ($type !== 'counterparty') continue;

    $pos  = strrpos($href, '/');
    $msId = ($pos !== false) ? substr($href, $pos + 1) : '';
    if ($msId === '') { $errors[] = 'No UUID in href: ' . $href; continue; }

    if ($action === 'DELETE') {
        $r = Database::query('Papir',
            "UPDATE counterparty SET status = 0
             WHERE id_ms = '" . Database::escape('Papir', $msId) . "' AND status = 1");
        mswhk_cp_log('DELETE counterparty ms=' . $msId
            . ' affected=' . (isset($r['affected_rows']) ? $r['affected_rows'] : '?'));
        $processed++;
        continue;
    }

    // Fetch full document from МС
    $docRaw = $ms->query($href);
    $doc    = json_decode(json_encode($docRaw), true);

    if (empty($doc) || !empty($doc['errors'])) {
        $errors[] = 'Fetch failed for counterparty/' . $msId;
        mswhk_cp_log('Fetch error ' . $msId . ': ' . json_encode($doc));
        continue;
    }

    try {
        $localId = mswhk_cp_upsert($doc, $errors);
        mswhk_cp_log(($action === 'CREATE' ? 'Created' : 'Updated')
            . ' counterparty id=' . $localId . ' ms=' . $msId);
        $processed++;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        mswhk_cp_log('Exception ' . $msId . ': ' . $e->getMessage());
    }
}

mswhk_cp_log('Done: processed=' . $processed . ' errors=' . count($errors));
exit;

/* ─────────────────────────────────────────────────────── */

function mswhk_cp_upsert(array $doc, array &$errors)
{
    $msId        = isset($doc['id'])           ? trim((string)$doc['id'])           : '';
    $name        = isset($doc['name'])         ? trim((string)$doc['name'])         : '';
    $legalTitle  = isset($doc['legalTitle'])   ? trim((string)$doc['legalTitle'])   : '';
    $companyType = isset($doc['companyType'])  ? trim((string)$doc['companyType'])  : '';
    $inn         = isset($doc['inn'])          ? trim((string)$doc['inn'])          : '';
    $phone       = isset($doc['phone'])        ? trim((string)$doc['phone'])        : '';
    $email       = isset($doc['email'])        ? trim((string)$doc['email'])        : '';
    $updatedAt   = isset($doc['updated'])      ? substr((string)$doc['updated'], 0, 19) : date('Y-m-d H:i:s');
    $createdAt   = isset($doc['created'])      ? substr((string)$doc['created'], 0, 19) : $updatedAt;

    if ($msId === '') { $errors[] = 'Missing id'; return null; }
    if ($name === '') $name = $legalTitle ?: '(без назви)';

    // Определяем тип
    if ($companyType === 'individual' || $legalTitle === '') {
        $cpType      = 'person';
        $displayName = $name;
    } else {
        $isFop = ($companyType === 'entrepreneur');
        if (!$isFop && $legalTitle !== '') {
            $isFop = (bool)preg_match('/^фоп\s/iu', $legalTitle);
            if (!$isFop) {
                $cleanInn = preg_replace('/\D/', '', $inn);
                if (strlen($cleanInn) === 10) $isFop = true;
            }
        }
        $cpType      = $isFop ? 'fop' : 'company';
        $displayName = $legalTitle !== '' ? $legalTitle : $name;
    }

    $okpo = preg_replace('/\D/', '', $inn);
    if ($okpo === '') $okpo = null;

    // Проверить — уже есть?
    $existing = Database::fetchRow('Papir',
        "SELECT id, type FROM counterparty WHERE id_ms = '" . Database::escape('Papir', $msId) . "' LIMIT 1");

    if ($existing['ok'] && !empty($existing['row'])) {
        $localId = (int)$existing['row']['id'];

        // Обновляем основную запись
        Database::update('Papir', 'counterparty', array(
            'name'       => mb_substr($displayName, 0, 255, 'UTF-8'),
            'updated_at' => $updatedAt,
        ), array('id' => $localId));

        // Обновляем детали
        if ($cpType === 'person') {
            $parts    = preg_split('/\s+/u', trim($displayName));
            $lastName  = isset($parts[0]) ? $parts[0] : null;
            $firstName = isset($parts[1]) ? $parts[1] : null;
            $midName   = isset($parts[2]) ? $parts[2] : null;

            $check = Database::fetchRow('Papir',
                "SELECT counterparty_id FROM counterparty_person WHERE counterparty_id = {$localId} LIMIT 1");
            if ($check['ok'] && !empty($check['row'])) {
                $upd = array('full_name' => mb_substr($displayName, 0, 255, 'UTF-8'));
                if ($lastName)  $upd['last_name']   = $lastName;
                if ($firstName) $upd['first_name']  = $firstName;
                if ($midName)   $upd['middle_name'] = $midName;
                if ($phone)     $upd['phone']        = $phone;
                if ($email)     $upd['email']        = $email;
                Database::update('Papir', 'counterparty_person', $upd, array('counterparty_id' => $localId));
            }
        } else {
            $check = Database::fetchRow('Papir',
                "SELECT counterparty_id FROM counterparty_company WHERE counterparty_id = {$localId} LIMIT 1");
            if ($check['ok'] && !empty($check['row'])) {
                $upd = array('full_name' => mb_substr($displayName, 0, 255, 'UTF-8'));
                if ($okpo)  $upd['okpo']  = $okpo;
                if ($phone) $upd['phone'] = $phone;
                if ($email) $upd['email'] = $email;
                Database::update('Papir', 'counterparty_company', $upd, array('counterparty_id' => $localId));
            }
        }

        return $localId;
    }

    // Создаём через shared helper (который делает всё правильно)
    $localId = mswhk_cp_resolve($msId, $doc, 'mswhk_cp_log');
    return $localId;
}