<?php
/**
 * Імпорт фінансових операцій з ms → Papir.finance_cash / finance_bank
 *
 * Джерела:
 *   ms.cashin    → finance_cash  (direction=in)
 *   ms.cashout   → finance_cash  (direction=out)
 *   ms.paymentin  → finance_bank (direction=in)
 *   ms.paymentout → finance_bank (direction=out)
 *
 * Атрибут moving=1 → is_moving=1 інтегрований в основний рядок.
 * Записи з moment < 2000-01-01 ігноруються (артефакти 1970 року).
 *
 * Запуск:
 *   php scripts/import_ms_finance.php --dry-run
 *   php scripts/import_ms_finance.php
 */

require_once __DIR__ . '/../modules/database/database.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/import_ms_finance.log';
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
        'title'    => 'Імпорт фінансових операцій з МойСклад',
        'script'   => 'scripts/import_ms_finance.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== РЕАЛЬНИЙ ІМПОРТ ===');

// ── Завантажити вже імпортовані id_ms (для ідемпотентності) ──────────────

out('Завантаження вже імпортованих записів...');

$importedCash = array();
$r = Database::fetchAll('Papir', "SELECT id_ms FROM finance_cash WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $importedCash[$row['id_ms']] = true;

$importedBank = array();
$r = Database::fetchAll('Papir', "SELECT id_ms FROM finance_bank WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $importedBank[$row['id_ms']] = true;

out('В finance_cash: ' . count($importedCash) . ', в finance_bank: ' . count($importedBank));

// ── Завантажити атрибут moving для paymentin / paymentout ────────────────

out('Завантаження атрибутів moving...');

$movingPaymentin  = array();
$r = Database::fetchAll('ms', "SELECT meta FROM paymentin_attributes WHERE name_main='moving' AND value='1'");
if ($r['ok']) foreach ($r['rows'] as $row) $movingPaymentin[$row['meta']] = true;

$movingPaymentout = array();
$r = Database::fetchAll('ms', "SELECT meta FROM paymentout_attributes WHERE name_main='moving' AND value='1'");
if ($r['ok']) foreach ($r['rows'] as $row) $movingPaymentout[$row['meta']] = true;

out('moving в paymentin: ' . count($movingPaymentin) . ', в paymentout: ' . count($movingPaymentout));

// ─────────────────────────────────────────────────────────────────────────
// Допоміжна функція вставки
// ─────────────────────────────────────────────────────────────────────────

$stats = array(
    'cash_in'  => 0, 'cash_out'  => 0,
    'bank_in'  => 0, 'bank_out'  => 0,
    'skipped'  => 0, 'errors'    => 0,
);

function importRows($msTable, $targetTable, $direction, $movingSet, &$importedSet, &$stats, $dryRun) {
    $batchSize = 1000;
    $offset    = 0;

    // Визначаємо поля залежно від таблиці
    $hasAgentAccount  = ($msTable === 'paymentin');
    $hasExpenseItem   = in_array($msTable, array('cashout', 'paymentout'));
    $hasPaymentPurpose = in_array($msTable, array('cashout', 'paymentin', 'paymentout'));
    $hasState         = in_array($msTable, array('cashin', 'paymentin', 'paymentout'));

    $statKey = str_replace(array('cashin','cashout','paymentin','paymentout'),
                           array('cash_in','cash_out','bank_in','bank_out'), $msTable);

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
            $idMs = trim((string)$row['meta']);

            if (isset($importedSet[$idMs])) {
                $stats['skipped']++;
                continue;
            }

            $isMoving    = isset($movingSet[$idMs]) ? 1 : 0;
            $moment      = $row['moment'] ? "'" . Database::escape($targetTable === 'finance_cash' ? 'Papir' : 'Papir', $row['moment']) . "'" : 'NULL';
            $sum         = nullOrDec($row['sum']);
            $operations  = nullOrDec($row['operations']);
            $applicable  = isset($row['applicable']) ? (int)$row['applicable'] : 1;

            $idMsS       = nullOrStr('Papir', $idMs);
            $docNumS     = nullOrStr('Papir', $row['name']);
            $agentS      = nullOrStr('Papir', $row['agent']);
            $orgS        = nullOrStr('Papir', $row['organization']);
            $descS       = nullOrStr('Papir', $row['description']);
            $extCodeS    = nullOrStr('Papir', $row['externalCode']);
            $stateS      = ($hasState && !empty($row['state'])) ? nullOrStr('Papir', $row['state']) : 'NULL';
            $expItemS    = ($hasExpenseItem && !empty($row['expenseItem'])) ? nullOrStr('Papir', $row['expenseItem']) : 'NULL';
            $purposeS    = ($hasPaymentPurpose && !empty($row['paymentPurpose'])) ? nullOrStr('Papir', $row['paymentPurpose']) : 'NULL';
            $agentAccS   = ($hasAgentAccount && !empty($row['agentAccount'])) ? nullOrStr('Papir', $row['agentAccount']) : 'NULL';

            if ($targetTable === 'finance_cash') {
                $sql = "INSERT INTO finance_cash
                    (id_ms, direction, moment, doc_number, sum, agent_ms, organization_ms,
                     is_posted, is_moving, expense_item_ms, description, payment_purpose,
                     external_code, state_ms, operations, source)
                    VALUES
                    ({$idMsS}, '{$direction}', {$moment}, {$docNumS}, {$sum}, {$agentS}, {$orgS},
                     {$applicable}, {$isMoving}, {$expItemS}, {$descS}, {$purposeS},
                     {$extCodeS}, {$stateS}, {$operations}, 'moysklad')";
            } else {
                $sql = "INSERT INTO finance_bank
                    (id_ms, direction, moment, doc_number, sum, agent_ms, organization_ms,
                     is_posted, is_moving, expense_item_ms, agent_account_ms, description,
                     payment_purpose, external_code, state_ms, operations, source)
                    VALUES
                    ({$idMsS}, '{$direction}', {$moment}, {$docNumS}, {$sum}, {$agentS}, {$orgS},
                     {$applicable}, {$isMoving}, {$expItemS}, {$agentAccS}, {$descS},
                     {$purposeS}, {$extCodeS}, {$stateS}, {$operations}, 'moysklad')";
            }

            if (!$dryRun) {
                $r = Database::query('Papir', $sql);
                if (!$r['ok']) {
                    $stats['errors']++;
                    continue;
                }
            }

            $importedSet[$idMs] = true;
            $stats[$statKey]++;
        }

        $offset += $batchSize;
    }
}

// ── Імпорт ───────────────────────────────────────────────────────────────

out('--- cashin → finance_cash (in) ---');
importRows('cashin',     'finance_cash', 'in',  array(), $importedCash, $stats, $dryRun);
out("cash_in: {$stats['cash_in']}");

out('--- cashout → finance_cash (out) ---');
importRows('cashout',    'finance_cash', 'out', array(), $importedCash, $stats, $dryRun);
out("cash_out: {$stats['cash_out']}");

out('--- paymentin → finance_bank (in) ---');
importRows('paymentin',  'finance_bank', 'in',  $movingPaymentin,  $importedBank, $stats, $dryRun);
out("bank_in: {$stats['bank_in']}");

out('--- paymentout → finance_bank (out) ---');
importRows('paymentout', 'finance_bank', 'out', $movingPaymentout, $importedBank, $stats, $dryRun);
out("bank_out: {$stats['bank_out']}");

// ── Підсумок ─────────────────────────────────────────────────────────────

out('');
out('=== ГОТОВО ===');
out("Каса прихід:   {$stats['cash_in']}");
out("Каса витрати:  {$stats['cash_out']}");
out("Банк прихід:   {$stats['bank_in']}");
out("Банк витрати:  {$stats['bank_out']}");
out("Пропущено:     {$stats['skipped']}");
out("Помилки:       {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}
