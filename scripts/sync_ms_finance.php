<?php
/**
 * Синк фінансових операцій: ms → Papir.finance_cash / finance_bank
 *
 * Запуск:
 *   php scripts/sync_ms_finance.php --dry-run
 *   php scripts/sync_ms_finance.php
 *
 * Що робить:
 *   1. INSERT  — нові записи з ms, яких ще немає в нашій БД (по id_ms)
 *   2. UPDATE  — змінені записи (ms.updated > наш updated_at)
 *   3. cp_id   — резолвить agent_ms → counterparty.id_ms при INSERT і UPDATE
 *   4. BACKFILL — заповнює cp_id для існуючих записів де cp_id IS NULL
 *
 * Джерела:
 *   ms.cashin / ms.cashout       → finance_cash  (direction in/out)
 *   ms.paymentin / ms.paymentout → finance_bank  (direction in/out)
 */

$_lockFp = fopen('/tmp/sync_ms_finance.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) { echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL; exit(0); }

require_once __DIR__ . '/../modules/database/database.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/sync_ms_finance.log';
$myPid   = getmypid();

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }

function nullOrStr($db, $val) {
    $val = trim((string)$val);
    return ($val === '') ? 'NULL' : "'" . Database::escape($db, $val) . "'";
}

function nullOrDec($val) {
    $val = trim((string)$val);
    return ($val === '' || $val === null) ? 'NULL' : (float)$val;
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

// ── Завантажити map: agent_ms (id_ms контрагента) → counterparty.id ──────

out('Завантаження counterparty map...');
$cpMap = array(); // id_ms → counterparty.id
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM counterparty WHERE id_ms IS NOT NULL");
if ($r['ok']) {
    foreach ($r['rows'] as $row) {
        $cpMap[$row['id_ms']] = (int)$row['id'];
    }
}
out('Контрагентів у map: ' . count($cpMap));

// ── Завантажити існуючі записи: id_ms → {updated_at} ────────────────────

out('Завантаження існуючих записів...');

$existingCash = array(); // id_ms → updated_at
$r = Database::fetchAll('Papir', "SELECT id_ms, updated_at FROM finance_cash WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $existingCash[$row['id_ms']] = $row['updated_at'];

$existingBank = array(); // id_ms → updated_at
$r = Database::fetchAll('Papir', "SELECT id_ms, updated_at FROM finance_bank WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $existingBank[$row['id_ms']] = $row['updated_at'];

out('finance_cash: ' . count($existingCash) . ', finance_bank: ' . count($existingBank));

// ── Завантажити атрибут moving ────────────────────────────────────────────

$movingPaymentin  = array();
$r = Database::fetchAll('ms', "SELECT meta FROM paymentin_attributes WHERE name_main='moving' AND value='1'");
if ($r['ok']) foreach ($r['rows'] as $row) $movingPaymentin[$row['meta']] = true;

$movingPaymentout = array();
$r = Database::fetchAll('ms', "SELECT meta FROM paymentout_attributes WHERE name_main='moving' AND value='1'");
if ($r['ok']) foreach ($r['rows'] as $row) $movingPaymentout[$row['meta']] = true;

out('moving: paymentin=' . count($movingPaymentin) . ', paymentout=' . count($movingPaymentout));

// ─────────────────────────────────────────────────────────────────────────

$stats = array(
    'cash_in'  => 0, 'cash_out'  => 0,
    'bank_in'  => 0, 'bank_out'  => 0,
    'updated'  => 0, 'backfill'  => 0,
    'skipped'  => 0, 'errors'    => 0,
);

function syncTable($msTable, $targetTable, $direction, $movingSet,
                   &$existingSet, &$cpMap, &$stats, $dryRun) {

    $batchSize         = 1000;
    $offset            = 0;
    $hasExpenseItem    = in_array($msTable, array('cashout', 'paymentout'));
    $hasPaymentPurpose = in_array($msTable, array('cashout', 'paymentin', 'paymentout'));
    $hasState          = in_array($msTable, array('cashin', 'paymentin', 'paymentout'));
    $hasAgentAccount   = ($msTable === 'paymentin');
    $hasCpId           = ($targetTable === 'finance_bank');

    $statKey = str_replace(
        array('cashin','cashout','paymentin','paymentout'),
        array('cash_in','cash_out','bank_in','bank_out'),
        $msTable
    );

    while (true) {
        $rows = Database::fetchAll('ms',
            "SELECT * FROM {$msTable}
             WHERE meta IS NOT NULL AND meta != ''
               AND moment >= '2000-01-01'
             ORDER BY id
             LIMIT {$batchSize} OFFSET {$offset}"
        );
        if (!$rows['ok'] || empty($rows['rows'])) break;

        foreach ($rows['rows'] as $row) {
            $idMs      = trim((string)$row['meta']);
            $updatedMs = $row['updated'] ? (string)$row['updated'] : '';
            $agentMs   = trim((string)$row['agent']);
            $cpId      = ($hasCpId && $agentMs !== '' && isset($cpMap[$agentMs]))
                         ? $cpMap[$agentMs] : null;
            $cpIdSql   = $cpId ? $cpId : 'NULL';

            $isMoving   = isset($movingSet[$idMs]) ? 1 : 0;
            $momentSql  = $row['moment'] ? "'" . Database::escape('Papir', $row['moment']) . "'" : 'NULL';
            $sum        = nullOrDec($row['sum']);
            $operations = nullOrDec($row['operations']);
            $applicable = isset($row['applicable']) ? (int)$row['applicable'] : 1;

            $idMsS    = nullOrStr('Papir', $idMs);
            $docNumS  = nullOrStr('Papir', $row['name']);
            $agentS   = nullOrStr('Papir', $agentMs);
            $orgS     = nullOrStr('Papir', $row['organization']);
            $descS    = nullOrStr('Papir', $row['description']);
            $extCodeS = nullOrStr('Papir', $row['externalCode']);
            $stateS   = ($hasState    && !empty($row['state']))          ? nullOrStr('Papir', $row['state'])          : 'NULL';
            $expItemS = ($hasExpenseItem && !empty($row['expenseItem'])) ? nullOrStr('Papir', $row['expenseItem'])    : 'NULL';
            $purposeS = ($hasPaymentPurpose && !empty($row['paymentPurpose'])) ? nullOrStr('Papir', $row['paymentPurpose']) : 'NULL';
            $agentAccS= ($hasAgentAccount  && !empty($row['agentAccount']))    ? nullOrStr('Papir', $row['agentAccount'])   : 'NULL';
            $updSql   = $updatedMs ? "'" . Database::escape('Papir', $updatedMs) . "'" : 'NOW()';

            // ── UPDATE ────────────────────────────────────────────────────
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
                             state_ms={$stateS}, operations={$operations}, updated_at={$updSql}
                             WHERE id_ms={$idMsS}"
                        );
                    } else {
                        Database::query('Papir',
                            "UPDATE finance_cash SET
                             sum={$sum}, is_posted={$applicable}, is_moving={$isMoving},
                             agent_ms={$agentS}, organization_ms={$orgS},
                             description={$descS}, payment_purpose={$purposeS},
                             expense_item_ms={$expItemS}, state_ms={$stateS},
                             operations={$operations}, updated_at={$updSql}
                             WHERE id_ms={$idMsS}"
                        );
                    }
                }
                $existingSet[$idMs] = $updatedMs;
                $stats['updated']++;
                continue;
            }

            // ── INSERT ────────────────────────────────────────────────────
            if ($targetTable === 'finance_cash') {
                $sql = "INSERT INTO finance_cash
                    (id_ms, direction, moment, doc_number, sum, agent_ms, organization_ms,
                     is_posted, is_moving, expense_item_ms, description, payment_purpose,
                     external_code, state_ms, operations, source, updated_at)
                    VALUES
                    ({$idMsS}, '{$direction}', {$momentSql}, {$docNumS}, {$sum},
                     {$agentS}, {$orgS}, {$applicable}, {$isMoving}, {$expItemS},
                     {$descS}, {$purposeS}, {$extCodeS}, {$stateS}, {$operations},
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
                     {$stateS}, {$operations}, 'moysklad', {$updSql})";
            }

            if (!$dryRun) {
                $r2 = Database::query('Papir', $sql);
                if (!$r2['ok']) { $stats['errors']++; continue; }
            }

            $existingSet[$idMs] = $updatedMs;
            $stats[$statKey]++;
        }

        $offset += $batchSize;
    }
}

// ── Синк таблиць ─────────────────────────────────────────────────────────

out('--- cashin ---');
syncTable('cashin',     'finance_cash', 'in',  array(),           $existingCash, $cpMap, $stats, $dryRun);
out('--- cashout ---');
syncTable('cashout',    'finance_cash', 'out', array(),           $existingCash, $cpMap, $stats, $dryRun);
out('--- paymentin ---');
syncTable('paymentin',  'finance_bank', 'in',  $movingPaymentin,  $existingBank, $cpMap, $stats, $dryRun);
out('--- paymentout ---');
syncTable('paymentout', 'finance_bank', 'out', $movingPaymentout, $existingBank, $cpMap, $stats, $dryRun);

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

// ── Підсумок ─────────────────────────────────────────────────────────────

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
