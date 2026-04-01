<?php
/**
 * Синк фінансових операцій: МойСклад API → Papir.finance_cash / finance_bank
 *
 * Запуск:
 *   php scripts/sync_ms_finance.php --dry-run
 *   php scripts/sync_ms_finance.php
 *   php scripts/sync_ms_finance.php --full   # всі документи без фільтру дати
 *
 * Що робить:
 *   1. INSERT  — нові документи з МС API, яких ще немає в нашій БД (по id_ms)
 *   2. UPDATE  — змінені документи (ms.updated > наш updated_at)
 *   3. cp_id   — резолвить agent_ms → counterparty.id_ms при INSERT і UPDATE
 *   4. BACKFILL — заповнює cp_id для існуючих записів де cp_id IS NULL
 *
 * Джерела (МС API, не зеркало ms.*):
 *   /entity/cashin   / /entity/cashout    → finance_cash  (direction in/out)
 *   /entity/paymentin / /entity/paymentout → finance_bank  (direction in/out)
 *
 * Суми: МС API повертає в копійках — ділимо на 100.
 * Інкремент: за замовчуванням за останні 48 годин. --full — повний синк.
 */

$_lockFp = fopen('/tmp/sync_ms_finance.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) {
    echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL;
    exit(0);
}

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/moysklad/moysklad_api.php';

$dryRun   = in_array('--dry-run', $argv);
$fullSync = in_array('--full', $argv);
$logFile  = '/tmp/sync_ms_finance.log';
$myPid    = getmypid();

// Інкрементальний синк: документи оновлені за останні 48 годин
$updatedFrom = $fullSync ? null : date('Y-m-d H:i:s', strtotime('-48 hours'));

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }

function nullOrStr($val) {
    $val = trim((string)$val);
    return ($val === '') ? 'NULL' : "'" . Database::escape('Papir', $val) . "'";
}

/**
 * Витягує UUID з href МойСклад: .../entity/TYPE/UUID → UUID
 */
function uuidFromHref($href) {
    if (empty($href)) return '';
    $pos = strrpos($href, '/');
    return ($pos !== false) ? substr($href, $pos + 1) : '';
}

// ── Реєстрація в background_jobs ─────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк фінансових операцій з МойСклад',
        'script'   => 'scripts/sync_ms_finance.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ФІНАНСІВ ===');
out($fullSync ? 'Режим: ПОВНИЙ (всі документи)' : 'Режим: інкрементальний (з ' . $updatedFrom . ')');

// ── МойСклад API ──────────────────────────────────────────────────────────

$ms         = new MoySkladApi();
$entityBase = $ms->getEntityBaseUrl(); // .../entity/

// ── Завантажити map: agent_ms (UUID контрагента МС) → counterparty.id ────

out('Завантаження counterparty map...');
$cpMap = array(); // uuid → counterparty.id
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM counterparty WHERE id_ms IS NOT NULL");
if ($r['ok']) {
    foreach ($r['rows'] as $row) {
        $cpMap[$row['id_ms']] = (int)$row['id'];
    }
}
out('Контрагентів у map: ' . count($cpMap));

// ── Завантажити існуючі записи: id_ms → updated_at ───────────────────────

out('Завантаження існуючих записів...');

$existingCash = array();
$r = Database::fetchAll('Papir', "SELECT id_ms, updated_at FROM finance_cash WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $existingCash[$row['id_ms']] = $row['updated_at'];

$existingBank = array();
$r = Database::fetchAll('Papir', "SELECT id_ms, updated_at FROM finance_bank WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $existingBank[$row['id_ms']] = $row['updated_at'];

out('finance_cash: ' . count($existingCash) . ', finance_bank: ' . count($existingBank));

// ── Статистика ────────────────────────────────────────────────────────────

$stats = array(
    'cash_in' => 0, 'cash_out' => 0,
    'bank_in' => 0, 'bank_out' => 0,
    'updated' => 0, 'backfill' => 0,
    'skipped' => 0, 'errors'  => 0,
);

// ── UUID атрибутів moving (з конфігу payments_sync) ───────────────────────

$movingAttrIds = array(
    'paymentin'  => '3e58b958-92f0-11eb-0a80-00da000a0e6a',
    'paymentout' => '3e723523-92f0-11eb-0a80-06f1000a13ba',
);

// ── Завантажити документи з МС API ───────────────────────────────────────

function fetchMsFinanceDocs($ms, $entityBase, $docType, $updatedFrom) {
    $limit  = 100;
    $offset = 0;
    $all    = array();

    $filterParam = '';
    if ($updatedFrom !== null) {
        // МС API: filter=updated>=DATE (поле updated, оператор >=)
        $filterParam = '&filter=updated%3E%3D' . urlencode($updatedFrom);
    }

    out("  Завантаження {$docType}" . ($updatedFrom ? " (з {$updatedFrom})" : ' (повна)') . '...');

    while (true) {
        $url = $entityBase . $docType . '?limit=' . $limit . '&offset=' . $offset . $filterParam;
        $response = $ms->query($url);
        $response = json_decode(json_encode($response), true);

        if (!$response || !isset($response['rows'])) {
            out("  Помилка або порожня відповідь для {$docType} offset={$offset}");
            break;
        }

        if (empty($response['rows'])) break;

        foreach ($response['rows'] as $row) {
            $all[] = $row;
        }

        $size    = isset($response['meta']['size'])  ? (int)$response['meta']['size']  : 0;
        $limit_r = isset($response['meta']['limit']) ? (int)$response['meta']['limit'] : $limit;
        $offset += $limit_r;

        if (count($all) % 500 === 0 || $offset >= $size) {
            out("  {$docType}: {$offset}/{$size}...");
        }

        if ($offset >= $size) break;
    }

    out("  {$docType}: завантажено " . count($all) . ' документів.');
    return $all;
}

// ── Синк одного типу документів ──────────────────────────────────────────

function syncDocType($docType, $targetTable, $direction, $movingAttrId,
                     &$existingSet, &$cpMap, &$stats, $dryRun,
                     $ms, $entityBase, $updatedFrom) {

    $hasCpId           = ($targetTable === 'finance_bank');
    $hasExpenseItem    = in_array($docType, array('cashout', 'paymentout'));
    $hasPaymentPurpose = in_array($docType, array('cashout', 'paymentin', 'paymentout'));
    $hasState          = in_array($docType, array('cashin', 'paymentin', 'paymentout'));
    $hasAgentAccount   = ($docType === 'paymentin');

    $statKey = str_replace(
        array('cashin', 'cashout', 'paymentin', 'paymentout'),
        array('cash_in', 'cash_out', 'bank_in', 'bank_out'),
        $docType
    );

    $documents = fetchMsFinanceDocs($ms, $entityBase, $docType, $updatedFrom);
    if (empty($documents)) { return; }

    foreach ($documents as $doc) {

        // UUID документа — поле id у відповіді API
        $idMs = isset($doc['id']) ? trim((string)$doc['id']) : '';
        if ($idMs === '') { $stats['errors']++; continue; }

        // Дата оновлення в МС (формат "YYYY-MM-DD HH:MM:SS.mmm")
        $updatedMs = isset($doc['updated']) ? substr((string)$doc['updated'], 0, 19) : '';

        // ── Поля документа (спільні для INSERT і UPDATE) ─────────────────
        $agentMs   = uuidFromHref(isset($doc['agent']['meta']['href'])            ? $doc['agent']['meta']['href']            : '');
        $orgMs     = uuidFromHref(isset($doc['organization']['meta']['href'])     ? $doc['organization']['meta']['href']     : '');
        $stateMs   = uuidFromHref(isset($doc['state']['meta']['href'])            ? $doc['state']['meta']['href']            : '');
        $expItemMs = uuidFromHref(isset($doc['expenseItem']['meta']['href'])      ? $doc['expenseItem']['meta']['href']      : '');
        $agentAccMs= uuidFromHref(isset($doc['agentAccount']['meta']['href'])     ? $doc['agentAccount']['meta']['href']     : '');
        $purposeMs = isset($doc['paymentPurpose']) ? (string)$doc['paymentPurpose'] : '';
        $descMs    = isset($doc['description'])    ? (string)$doc['description']   : '';
        $applicable= !empty($doc['applicable']) ? 1 : 0;
        // Сума в МС API в копійках → гривні
        $sum       = isset($doc['sum']) ? round((float)$doc['sum'] / 100, 2) : 0.0;
        // operations[] — масив пов'язаних документів; зберігаємо суму linkedSum (в гривнях)
        // Це поле використовується в get_order_flow.php як сума оплати по документу
        $opsCount  = 0.0;
        if (!empty($doc['operations']) && is_array($doc['operations'])) {
            foreach ($doc['operations'] as $op) {
                // linkedSum в API в копійках → гривні
                $opsCount += isset($op['linkedSum']) ? (float)$op['linkedSum'] / 100 : 0.0;
            }
        }

        // Атрибут moving (внутрішній переказ)
        $isMoving = 0;
        if ($movingAttrId !== null && !empty($doc['attributes'])) {
            foreach ($doc['attributes'] as $attr) {
                if (isset($attr['id']) && $attr['id'] === $movingAttrId && !empty($attr['value'])) {
                    $isMoving = 1;
                    break;
                }
            }
        }

        $cpId    = ($hasCpId && $agentMs !== '' && isset($cpMap[$agentMs])) ? $cpMap[$agentMs] : null;
        $cpIdSql = $cpId ? $cpId : 'NULL';
        $updSql  = $updatedMs ? "'" . Database::escape('Papir', $updatedMs) . "'" : 'NOW()';

        $agentS    = nullOrStr($agentMs);
        $orgS      = nullOrStr($orgMs);
        $descS     = nullOrStr($descMs);
        $purposeS  = $hasPaymentPurpose ? nullOrStr($purposeMs)  : 'NULL';
        $expItemS  = $hasExpenseItem    ? nullOrStr($expItemMs)  : 'NULL';
        $agentAccS = $hasAgentAccount   ? nullOrStr($agentAccMs) : 'NULL';
        $stateS    = $hasState          ? nullOrStr($stateMs)    : 'NULL';
        $idMsS     = nullOrStr($idMs);

        // ── UPDATE ────────────────────────────────────────────────────────
        if (isset($existingSet[$idMs])) {
            if ($updatedMs && $updatedMs <= $existingSet[$idMs]) {
                $stats['skipped']++;
                continue;
            }

            if (!$dryRun) {
                if ($targetTable === 'finance_bank') {
                    Database::query('Papir',
                        "UPDATE finance_bank SET
                         sum={$sum}, is_posted={$applicable}, is_moving={$isMoving},
                         agent_ms={$agentS}, cp_id={$cpIdSql}, organization_ms={$orgS},
                         description={$descS}, payment_purpose={$purposeS},
                         expense_item_ms={$expItemS}, agent_account_ms={$agentAccS},
                         state_ms={$stateS}, operations={$opsCount}, updated_at={$updSql}
                         WHERE id_ms={$idMsS}"
                    );
                } else {
                    Database::query('Papir',
                        "UPDATE finance_cash SET
                         sum={$sum}, is_posted={$applicable}, is_moving={$isMoving},
                         agent_ms={$agentS}, organization_ms={$orgS},
                         description={$descS}, payment_purpose={$purposeS},
                         expense_item_ms={$expItemS}, state_ms={$stateS},
                         operations={$opsCount}, updated_at={$updSql}
                         WHERE id_ms={$idMsS}"
                    );
                }
            }

            $existingSet[$idMs] = $updatedMs;
            $stats['updated']++;
            continue;
        }

        // ── INSERT ────────────────────────────────────────────────────────
        $extCode   = isset($doc['externalCode']) ? (string)$doc['externalCode'] : '';
        $docName   = isset($doc['name'])         ? (string)$doc['name']         : '';
        $moment    = isset($doc['moment'])        ? substr((string)$doc['moment'], 0, 19) : '';
        $momentSql = $moment ? "'" . Database::escape('Papir', $moment) . "'" : 'NULL';
        $extCodeS  = nullOrStr($extCode);
        $docNumS   = nullOrStr($docName);

        if ($targetTable === 'finance_cash') {
            $sql = "INSERT INTO finance_cash
                (id_ms, direction, moment, doc_number, sum, agent_ms, organization_ms,
                 is_posted, is_moving, expense_item_ms, description, payment_purpose,
                 external_code, state_ms, operations, source, updated_at)
                VALUES
                ({$idMsS}, '{$direction}', {$momentSql}, {$docNumS}, {$sum},
                 {$agentS}, {$orgS}, {$applicable}, {$isMoving}, {$expItemS},
                 {$descS}, {$purposeS}, {$extCodeS}, {$stateS}, {$opsCount},
                 'moysklad', {$updSql})";
        } else {
            $sql = "INSERT INTO finance_bank
                (id_ms, direction, moment, doc_number, sum, agent_ms, cp_id,
                 organization_ms, is_posted, is_moving, expense_item_ms,
                 agent_account_ms, description, payment_purpose, external_code,
                 state_ms, operations, source, updated_at)
                VALUES
                ({$idMsS}, '{$direction}', {$momentSql}, {$docNumS}, {$sum},
                 {$agentS}, {$cpIdSql}, {$orgS}, {$applicable}, {$isMoving},
                 {$expItemS}, {$agentAccS}, {$descS}, {$purposeS}, {$extCodeS},
                 {$stateS}, {$opsCount}, 'moysklad', {$updSql})";
        }

        if (!$dryRun) {
            $r2 = Database::query('Papir', $sql);
            if (!$r2['ok']) { $stats['errors']++; continue; }
        }

        $existingSet[$idMs] = $updatedMs;
        $stats[$statKey]++;
    }
}

// ── Синк по типах ─────────────────────────────────────────────────────────

out('--- cashin ---');
syncDocType('cashin',     'finance_cash', 'in',  null,
            $existingCash, $cpMap, $stats, $dryRun, $ms, $entityBase, $updatedFrom);

out('--- cashout ---');
syncDocType('cashout',    'finance_cash', 'out', null,
            $existingCash, $cpMap, $stats, $dryRun, $ms, $entityBase, $updatedFrom);

out('--- paymentin ---');
syncDocType('paymentin',  'finance_bank', 'in',  $movingAttrIds['paymentin'],
            $existingBank, $cpMap, $stats, $dryRun, $ms, $entityBase, $updatedFrom);

out('--- paymentout ---');
syncDocType('paymentout', 'finance_bank', 'out', $movingAttrIds['paymentout'],
            $existingBank, $cpMap, $stats, $dryRun, $ms, $entityBase, $updatedFrom);

// ── Backfill cp_id для існуючих finance_bank без cp_id ───────────────────

out('--- backfill cp_id в finance_bank ---');
$bfR = Database::fetchAll('Papir',
    "SELECT id, agent_ms FROM finance_bank WHERE cp_id IS NULL AND agent_ms IS NOT NULL"
);
$bfCount = 0;
if ($bfR['ok']) {
    foreach ($bfR['rows'] as $row) {
        $agentMs = $row['agent_ms'];
        if (!isset($cpMap[$agentMs])) continue;
        $cpId = $cpMap[$agentMs];
        if (!$dryRun) {
            Database::query('Papir',
                "UPDATE finance_bank SET cp_id={$cpId} WHERE id=" . (int)$row['id']
            );
        }
        $bfCount++;
    }
}
$stats['backfill'] = $bfCount;
out("backfill: {$bfCount}");

// ── Підсумок ──────────────────────────────────────────────────────────────

out('');
out('=== ГОТОВО ===');
out("Каса прихід:   {$stats['cash_in']}");
out("Каса витрати:  {$stats['cash_out']}");
out("Банк прихід:   {$stats['bank_in']}");
out("Банк витрати:  {$stats['bank_out']}");
out("Оновлено:      {$stats['updated']}");
out("Backfill cp_id:{$stats['backfill']}");
out("Пропущено:     {$stats['skipped']}");
out("Помилки:       {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}