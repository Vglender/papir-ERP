<?php
/**
 * МойСклад → Papir webhook.
 * Принимает события CREATE/UPDATE/DELETE для paymentin, paymentout, cashin, cashout.
 *
 * Настроить в МойСклад: Настройки → Вебхуки
 *   URL:    https://papir.officetorg.com.ua/finance/webhook/moysklad (через relay 159.69.1.229)
 *   Типы:   paymentin, paymentout, cashin, cashout
 *   Действия: CREATE, UPDATE, DELETE
 *
 * Тело запроса (JSON):
 * {
 *   "events": [
 *     {
 *       "meta": { "type": "paymentin", "href": "https://...entity/paymentin/{uuid}" },
 *       "action": "CREATE",
 *       "updatedFields": ["sum", ...]
 *     }, ...
 *   ]
 * }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';
require_once __DIR__ . '/../../moysklad/src/WebhookCpHelper.php';

function mswhk_log($msg) {
    @file_put_contents('/var/www/papir/storage/ms_webhook_finance.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mswhk_log('Non-POST request ignored');
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$raw   = file_get_contents('php://input');
$body  = json_decode($raw, true);

mswhk_log('Incoming: ' . $raw);

// Ответить МС немедленно — у них таймаут 1.5 сек, а мы ещё идём в их API за документом.
// fastcgi_finish_request() отправляет ответ и продолжает выполнение в фоне.
echo json_encode(array('ok' => true));
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    // Fallback для не-FPM окружений
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');
    ob_end_flush();
    flush();
}

if (!is_array($body) || empty($body['events'])) {
    exit;
}

$ms = new MoySkladApi();

$processed = 0;
$errors    = array();

foreach ($body['events'] as $event) {
    $action  = isset($event['action'])          ? strtoupper($event['action'])       : '';
    $type    = isset($event['meta']['type'])    ? strtolower($event['meta']['type']) : '';
    $href    = isset($event['meta']['href'])    ? (string)$event['meta']['href']     : '';

    if (!in_array($type, array('paymentin', 'paymentout', 'cashin', 'cashout'), true)) {
        continue;
    }

    $msId = '';
    $pos  = strrpos($href, '/');
    if ($pos !== false) {
        $msId = substr($href, $pos + 1);
    }

    if ($msId === '') {
        $errors[] = 'Cannot extract UUID from href: ' . $href;
        continue;
    }

    if ($action === 'DELETE') {
        mswhk_handle_delete($msId, $type, $errors);
        $processed++;
        continue;
    }

    // CREATE or UPDATE — fetch full document from МС API
    $docRaw = $ms->query($href . '?expand=agent,organization,organizationAccount,operations,expenseItem,state');
    // query() возвращает stdClass — конвертируем в массив
    $doc = json_decode(json_encode($docRaw), true);
    if (empty($doc) || !empty($doc['errors'])) {
        $errors[] = 'Failed to fetch ' . $type . '/' . $msId . ' from МС';
        mswhk_log('Fetch error for ' . $msId . ': ' . json_encode($doc));
        continue;
    }

    try {
        mswhk_upsert($doc, $type, $errors);
        $processed++;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        mswhk_log('Exception for ' . $msId . ': ' . $e->getMessage());
    }
}

$resp = array('ok' => true, 'processed' => $processed);
if (!empty($errors)) {
    $resp['errors'] = $errors;
}
mswhk_log('Done: processed=' . $processed . ' errors=' . count($errors));
echo json_encode($resp);
exit;

/* ─────────────────────────────────────────────────────── */

// mswhk_cp_resolve() and helpers are in modules/moysklad/src/WebhookCpHelper.php

function mswhk_upsert(array $doc, $type, array &$errors)
{
    $msId    = isset($doc['id'])           ? trim((string)$doc['id'])           : '';
    $extCode = isset($doc['externalCode']) ? trim((string)$doc['externalCode']) : null;
    $isCash  = ($type === 'cashin' || $type === 'cashout');
    $direction = ($type === 'paymentin' || $type === 'cashin') ? 'in' : 'out';

    if ($msId === '') {
        $errors[] = 'Document missing id field';
        return;
    }

    $sum      = isset($doc['sum']) ? round((float)$doc['sum'] / 100, 2) : 0.0;
    $isPosted = !empty($doc['applicable']) ? 1 : 0;
    $moment   = isset($doc['moment']) ? substr((string)$doc['moment'], 0, 19) : null;
    $docNumber = isset($doc['name'])        ? (string)$doc['name']        : null;
    $desc      = isset($doc['description']) ? (string)$doc['description'] : null;

    $agentMs   = '';
    $agentType = '';
    $agentDoc  = array();
    if (!empty($doc['agent']['meta']['href'])) {
        $p = strrpos($doc['agent']['meta']['href'], '/');
        $agentMs   = ($p !== false) ? substr($doc['agent']['meta']['href'], $p + 1) : '';
        $agentDoc  = isset($doc['agent']) && is_array($doc['agent']) ? $doc['agent'] : array();
        $agentType = isset($agentDoc['meta']['type']) ? (string)$agentDoc['meta']['type'] : '';
    }

    // Найти или создать контрагента (нужно и для cash — чтобы JOIN в репозитории работал)
    $cpId = mswhk_cp_resolve($agentMs, $agentDoc, 'mswhk_log');

    $orgMs = '';
    if (!empty($doc['organization']['meta']['href'])) {
        $p = strrpos($doc['organization']['meta']['href'], '/');
        $orgMs = ($p !== false) ? substr($doc['organization']['meta']['href'], $p + 1) : '';
    }

    $expItemMs = null;
    if (!empty($doc['expenseItem']['meta']['href'])) {
        $p = strrpos($doc['expenseItem']['meta']['href'], '/');
        $expItemMs = ($p !== false) ? substr($doc['expenseItem']['meta']['href'], $p + 1) : null;
    }

    $expCategoryId = null;
    if ($expItemMs) {
        $catRow = Database::fetchRow('Papir',
            "SELECT id FROM finance_expense_category
             WHERE expense_item_ms = '" . Database::escape('Papir', $expItemMs) . "'
             LIMIT 1"
        );
        if ($catRow['ok'] && !empty($catRow['row'])) {
            $expCategoryId = (int)$catRow['row']['id'];
        }
    }

    $operations = 0.0;
    if (!empty($doc['rows']) && is_array($doc['rows'])) {
        foreach ($doc['rows'] as $op) {
            $operations += isset($op['linkedSum']) ? (float)$op['linkedSum'] / 100 : 0.0;
        }
    }
    if (!empty($doc['operations']) && is_array($doc['operations'])) {
        foreach ($doc['operations'] as $op) {
            $operations += isset($op['linkedSum']) ? (float)$op['linkedSum'] / 100 : 0.0;
        }
    }

    // Общие поля обеих таблиц
    $data = array(
        'id_ms'               => $msId,
        'direction'           => $direction,
        'moment'              => $moment,
        'doc_number'          => $docNumber,
        'sum'                 => $sum,
        'agent_ms'            => $agentMs   ?: null,
        'agent_ms_type'       => $agentType ?: null,
        'organization_ms'     => $orgMs     ?: null,
        'is_posted'           => $isPosted,
        'expense_item_ms'     => $expItemMs,
        'expense_category_id' => $expCategoryId,
        'description'         => $desc,
        'external_code'       => $extCode,
        'operations'          => $operations > 0 ? $operations : null,
        'source'              => 'moysklad',
    );

    if ($isCash) {
        $table    = 'finance_cash';
        $existing = Database::fetchRow('Papir',
            "SELECT id FROM finance_cash WHERE id_ms = '" . Database::escape('Papir', $msId) . "' LIMIT 1"
        );
        // finance_cash не имеет cp_id — контрагент ищется через JOIN по agent_ms
    } else {
        $table    = 'finance_bank';
        $existing = Database::fetchRow('Papir',
            "SELECT id FROM finance_bank WHERE id_ms = '" . Database::escape('Papir', $msId) . "' LIMIT 1"
        );
        $data['cp_id'] = $cpId; // только finance_bank хранит cp_id
    }

    if ($existing['ok'] && !empty($existing['row'])) {
        $localId = (int)$existing['row']['id'];
        Database::update('Papir', $table, $data, array('id' => $localId));
        mswhk_log('Updated ' . $table . ' id=' . $localId . ' ms_id=' . $msId);
    } else {
        $ins = Database::insert('Papir', $table, $data);
        if (!$ins['ok']) {
            $errors[] = 'Insert failed for ' . $msId . ': ' . (isset($ins['error']) ? $ins['error'] : '?');
            return;
        }
        $localId = (int)$ins['insert_id'];
        mswhk_log('Inserted ' . $table . ' id=' . $localId . ' ms_id=' . $msId);
    }

    if (!empty($doc['operations']) && is_array($doc['operations'])) {
        mswhk_sync_links($localId, $msId, $type, $doc['operations']);
    }
}

function mswhk_sync_links($localId, $fromMsId, $type, array $operations)
{
    $fromType = $type; // 'paymentin' или 'paymentout'

    foreach ($operations as $op) {
        if (empty($op['meta']['href'])) {
            continue;
        }

        $toType = isset($op['meta']['type']) ? (string)$op['meta']['type'] : '';
        $pos    = strrpos($op['meta']['href'], '/');
        $toMsId = ($pos !== false) ? substr($op['meta']['href'], $pos + 1) : '';

        if ($toMsId === '') {
            continue;
        }

        $linkedSum = isset($op['linkedSum']) ? round((float)$op['linkedSum'] / 100, 2) : null;

        // Найти Papir to_id (customerorder.id) если возможно
        $toId = null;
        if ($toType === 'customerorder') {
            $orderRow = Database::fetchRow('Papir',
                "SELECT id FROM customerorder
                 WHERE id_ms = '" . Database::escape('Papir', $toMsId) . "'
                 LIMIT 1"
            );
            if ($orderRow['ok'] && !empty($orderRow['row'])) {
                $toId = (int)$orderRow['row']['id'];
            }
        }

        // Проверить — связь уже есть?
        $dlCheck = Database::fetchRow('Papir',
            "SELECT id FROM document_link
             WHERE from_ms_id = '" . Database::escape('Papir', $fromMsId) . "'
               AND to_ms_id   = '" . Database::escape('Papir', $toMsId)   . "'
             LIMIT 1"
        );

        if ($dlCheck['ok'] && !empty($dlCheck['row'])) {
            // Обновить linked_sum если изменилась
            if ($linkedSum !== null) {
                Database::update('Papir', 'document_link',
                    array('linked_sum' => $linkedSum, 'to_id' => $toId),
                    array('id' => (int)$dlCheck['row']['id'])
                );
            }
        } else {
            Database::insert('Papir', 'document_link', array(
                'from_type'  => $fromType,
                'from_id'    => $localId,
                'from_ms_id' => $fromMsId,
                'to_type'    => $toType,
                'to_id'      => $toId,
                'to_ms_id'   => $toMsId,
                'link_type'  => 'payment',
                'linked_sum' => $linkedSum,
            ));
        }
    }
}

function mswhk_handle_delete($msId, $type, array &$errors)
{
    $table = ($type === 'cashin' || $type === 'cashout') ? 'finance_cash' : 'finance_bank';
    $r = Database::query('Papir',
        "UPDATE {$table} SET is_posted = 0
         WHERE id_ms = '" . Database::escape('Papir', $msId) . "'"
    );
    mswhk_log('DELETE event for ' . $type . '/' . $msId . ' → set is_posted=0 in ' . $table . ', affected=' . (isset($r['affected_rows']) ? $r['affected_rows'] : '?'));
}