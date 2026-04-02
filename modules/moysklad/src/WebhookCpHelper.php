<?php
/**
 * Shared helper for МойСклад webhooks:
 * resolves counterparty_id by MS UUID, creating the counterparty if not found.
 *
 * Used by:
 *   - modules/finance/webhook/moysklad.php
 *   - modules/customerorder/webhook/moysklad.php
 *   - modules/demand/webhook/moysklad.php
 */

/**
 * Найти или создать контрагента по UUID из МС.
 * Возвращает local counterparty.id или null.
 * Создаёт только если agent.meta.type === 'counterparty' или 'employee'.
 *
 * @param  string $agentMs   UUID агента в МС (может быть '')
 * @param  array  $agentDoc  Раскрытый объект agent из МС API
 * @param  callable $logFn   Функция логирования (принимает строку)
 * @return int|null
 */
function mswhk_cp_resolve($agentMs, array $agentDoc, $logFn)
{
    if ($agentMs === '') return null;

    $r = Database::fetchRow('Papir',
        "SELECT id FROM counterparty WHERE id_ms = '" . Database::escape('Papir', $agentMs) . "' LIMIT 1"
    );
    if ($r['ok'] && !empty($r['row'])) {
        return (int)$r['row']['id'];
    }

    $agentType = isset($agentDoc['meta']['type']) ? (string)$agentDoc['meta']['type'] : '';

    if ($agentType !== 'counterparty' && $agentType !== 'employee') {
        return null;
    }

    if ($agentType === 'employee') {
        $fullName  = isset($agentDoc['fullName']) ? trim((string)$agentDoc['fullName']) : '';
        $shortName = isset($agentDoc['name'])     ? trim((string)$agentDoc['name'])     : '';
        $name      = $fullName !== '' ? $fullName : $shortName;
        $phone     = isset($agentDoc['phone']) ? trim((string)$agentDoc['phone']) : '';
        $email     = isset($agentDoc['email']) ? trim((string)$agentDoc['email']) : '';
        $createdAt = isset($agentDoc['created']) ? substr((string)$agentDoc['created'], 0, 19) : date('Y-m-d H:i:s');
        $updatedAt = isset($agentDoc['updated']) ? substr((string)$agentDoc['updated'], 0, 19) : $createdAt;
        if ($name === '') $name = '(сотрудник)';

        $cId = mswhk_cp_do_insert('person', $name, $agentMs, $createdAt, $updatedAt);
        if (!$cId) return null;
        mswhk_cp_do_insert_person($cId, $name, $phone, $email);
        call_user_func($logFn, 'Created counterparty person (employee) id=' . $cId . ' ms=' . $agentMs . ' name=' . $name);
        return $cId;
    }

    // counterparty
    $name        = isset($agentDoc['name'])        ? trim((string)$agentDoc['name'])        : '';
    $legalTitle  = isset($agentDoc['legalTitle'])  ? trim((string)$agentDoc['legalTitle'])  : '';
    $companyType = isset($agentDoc['companyType']) ? trim((string)$agentDoc['companyType']) : '';
    $inn         = isset($agentDoc['inn'])         ? trim((string)$agentDoc['inn'])         : '';
    $phone       = isset($agentDoc['phone'])       ? trim((string)$agentDoc['phone'])       : '';
    $email       = isset($agentDoc['email'])       ? trim((string)$agentDoc['email'])       : '';
    $createdAt   = isset($agentDoc['created'])     ? substr((string)$agentDoc['created'], 0, 19) : date('Y-m-d H:i:s');
    $updatedAt   = isset($agentDoc['updated'])     ? substr((string)$agentDoc['updated'], 0, 19) : $createdAt;

    if ($name === '') $name = $legalTitle ?: '(без назви)';

    if ($companyType === 'individual' || $legalTitle === '') {
        $cId = mswhk_cp_do_insert('person', $name, $agentMs, $createdAt, $updatedAt);
        if (!$cId) return null;
        mswhk_cp_do_insert_person($cId, $name, $phone, $email);
        call_user_func($logFn, 'Created counterparty person id=' . $cId . ' ms=' . $agentMs . ' name=' . $name);
        return $cId;
    }

    $isFop = ($companyType === 'entrepreneur');
    if (!$isFop && $legalTitle !== '') {
        $isFop = (bool)preg_match('/^фоп\s/iu', $legalTitle);
        if (!$isFop) {
            $cleanCode = preg_replace('/\D/', '', $inn);
            if (strlen($cleanCode) === 10) $isFop = true;
        }
    }

    $cpType      = $isFop ? 'fop' : 'company';
    $displayName = ($legalTitle !== '') ? $legalTitle : $name;
    $okpo        = preg_replace('/\D/', '', $inn);
    if ($okpo === '') $okpo = null;

    $cId = mswhk_cp_do_insert($cpType, $displayName, $agentMs, $createdAt, $updatedAt);
    if (!$cId) return null;
    mswhk_cp_do_insert_company($cId, $cpType, $displayName, $okpo, $phone, $email);

    if (!$isFop && $name !== '' && $name !== $legalTitle) {
        $cIdPerson = mswhk_cp_do_insert('person', $name, null, $createdAt, $updatedAt);
        if ($cIdPerson) {
            mswhk_cp_do_insert_person($cIdPerson, $name, $phone, $email);
            Database::query('Papir',
                "INSERT INTO counterparty_relation
                 (parent_counterparty_id, child_counterparty_id, relation_type, is_primary)
                 VALUES ({$cId}, {$cIdPerson}, 'contact_person', 1)"
            );
        }
    }

    call_user_func($logFn, 'Created counterparty ' . $cpType . ' id=' . $cId . ' ms=' . $agentMs . ' name=' . $displayName);
    return $cId;
}

function mswhk_cp_do_insert($type, $name, $idMs, $createdAt, $updatedAt)
{
    $uuid   = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
    $idMsVal = $idMs ? "'" . Database::escape('Papir', $idMs) . "'" : 'NULL';
    $nameVal = "'" . Database::escape('Papir', mb_substr($name, 0, 255, 'UTF-8')) . "'";
    $r = Database::query('Papir',
        "INSERT INTO counterparty (uuid, id_ms, type, status, name, source, created_at, updated_at)
         VALUES ('{$uuid}', {$idMsVal}, '" . Database::escape('Papir', $type) . "', 1, {$nameVal}, 'moysklad',
                 '" . Database::escape('Papir', $createdAt) . "',
                 '" . Database::escape('Papir', $updatedAt) . "')"
    );
    if (!$r['ok']) return null;
    $r2 = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() as id");
    return ($r2['ok'] && $r2['row']) ? (int)$r2['row']['id'] : null;
}

function mswhk_cp_do_insert_company($cId, $companyType, $fullName, $okpo, $phone, $email)
{
    $okpoVal  = $okpo  ? "'" . Database::escape('Papir', $okpo)  . "'" : 'NULL';
    $phoneVal = $phone ? "'" . Database::escape('Papir', $phone) . "'" : 'NULL';
    $emailVal = $email ? "'" . Database::escape('Papir', $email) . "'" : 'NULL';
    Database::query('Papir',
        "INSERT INTO counterparty_company (counterparty_id, company_type, full_name, okpo, phone, email)
         VALUES ({$cId}, '" . Database::escape('Papir', $companyType) . "',
                 '" . Database::escape('Papir', mb_substr($fullName, 0, 255, 'UTF-8')) . "',
                 {$okpoVal}, {$phoneVal}, {$emailVal})"
    );
}

function mswhk_cp_do_insert_person($cId, $fullName, $phone, $email)
{
    $parts    = preg_split('/\s+/u', trim($fullName));
    $lastName  = isset($parts[0]) ? "'" . Database::escape('Papir', $parts[0]) . "'" : 'NULL';
    $firstName = isset($parts[1]) ? "'" . Database::escape('Papir', $parts[1]) . "'" : 'NULL';
    $midName   = isset($parts[2]) ? "'" . Database::escape('Papir', $parts[2]) . "'" : 'NULL';
    $phoneVal  = $phone ? "'" . Database::escape('Papir', $phone) . "'" : 'NULL';
    $emailVal  = $email ? "'" . Database::escape('Papir', $email) . "'" : 'NULL';
    Database::query('Papir',
        "INSERT INTO counterparty_person (counterparty_id, full_name, last_name, first_name, middle_name, phone, email)
         VALUES ({$cId}, '" . Database::escape('Papir', mb_substr($fullName, 0, 255, 'UTF-8')) . "',
                 {$lastName}, {$firstName}, {$midName}, {$phoneVal}, {$emailVal})"
    );
}
