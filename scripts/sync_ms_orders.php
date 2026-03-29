<?php
/**
 * Синк заголовків замовлень: ms.customerorder → Papir.customerorder
 *
 * Запуск:
 *   php scripts/sync_ms_orders.php --dry-run
 *   php scripts/sync_ms_orders.php
 *
 * Що робить:
 *   1. INSERT  — нові замовлення з ms, яких ще немає (по id_ms)
 *   2. UPDATE  — змінені замовлення (ms.updated > наш updated_at)
 *   3. counterparty_id — резолвить ms.agent → counterparty.id_ms → counterparty.id
 *   4. status  — маппінг UUID стану → наш enum
 *   5. payment_status / shipment_status — розраховуються з payedSum/shippedSum/sum
 */

$_lockFp = fopen('/tmp/sync_ms_orders.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) { echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL; exit(0); }

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_orders.log';
$myPid     = getmypid();
$batchSize = 500;

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }

function e($val) { return Database::escape('Papir', (string)$val); }

function nullOrStr($val, $maxLen = 0) {
    $val = trim((string)$val);
    if ($val === '') return 'NULL';
    if ($maxLen > 0 && mb_strlen($val, 'UTF-8') > $maxLen) {
        $val = mb_substr($val, 0, $maxLen, 'UTF-8');
    }
    return "'" . e($val) . "'";
}

function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ── Маппінг UUID статусів МС → наш enum ──────────────────────────────────────
// Джерело: ms.state (doc=customerorder)

$stateMap = array(
    'bc5a77c2-d2ad-11ea-0a80-02ef0007cc9f' => 'completed',    // Виконано       72320
    'da89dea4-179c-11ec-0a80-09820031f9b6' => 'completed',    // Виконанно       (alt)
    '41c488a7-d29a-11ea-0a80-0517000f0d4d' => 'cancelled',    // Відміна        27114
    '41c487f8-d29a-11ea-0a80-0517000f0d4c' => 'cancelled',    // Відміна         2630
    'e394d392-816f-11ec-0a80-021d002fbf0c' => 'cancelled',    // Загублений         5
    '41c486a9-d29a-11ea-0a80-0517000f0d4a' => 'shipped',      // Передано у доставку 12833
    'fde33ac6-53eb-11eb-0a80-01b2004a71d4' => 'in_progress',  // Комплектується  3142
    '8b254fcf-5f64-11ec-0a80-05b1002d087c' => 'in_progress',  // Передано у сборку  258
    '5f821bb6-0877-11eb-0a80-049300051d5e' => 'in_progress',  // Передано у сборку    1
    '34fe6465-f5be-11eb-0a80-0d4800058863' => 'waiting_payment', // Очікуємо оплату  28
    '0ad0421b-64d6-11eb-0a80-095b00002e3b' => 'in_progress',  // Очікування товару
    '8b9e1475-dce9-11ea-0a80-006100019351' => 'confirmed',    // Принято           19
    'bc41b6b0-d2ad-11ea-0a80-02ef0007cc90' => 'confirmed',    // Принято (alt)      1
    '76eb0a35-d752-11ea-0a80-03cf00010e80' => 'confirmed',    // Принято (alt2)
    'c2fc692f-dd59-11ea-0a80-03fa00051f8e' => 'new',          // Новий              7
    'ad2d88b8-7abf-11eb-0a80-03f80037a302' => 'paid',         // Сплачено
    'cb14819a-d5ca-11ea-0a80-03cc0000f986' => 'paid',         // Сплачено (alt)
    '34fe603e-f5be-11eb-0a80-0d4800058862' => 'new',          // Не дозвонились
    'fc7df394-e69c-11ea-0a80-0140003a2624' => 'new',          // тендер
);

function resolveStatus($stateUuid) {
    global $stateMap;
    if (!$stateUuid) return 'new';
    return isset($stateMap[$stateUuid]) ? $stateMap[$stateUuid] : 'completed';
}

// ── Розрахунок payment_status ─────────────────────────────────────────────────

function resolvePaymentStatus($payedSum, $sum) {
    $payedSum = (float)$payedSum;
    $sum      = (float)$sum;
    if ($payedSum <= 0)           return 'not_paid';
    if ($payedSum >= $sum - 0.01) return 'paid';
    return 'partially_paid';
}

// ── Розрахунок shipment_status ────────────────────────────────────────────────

function resolveShipmentStatus($shippedSum, $reservedSum, $sum) {
    $shippedSum  = (float)$shippedSum;
    $reservedSum = (float)$reservedSum;
    $sum         = (float)$sum;
    if ($shippedSum >= $sum - 0.01 && $sum > 0) return 'shipped';
    if ($shippedSum > 0)                         return 'partially_shipped';
    if ($reservedSum > 0)                        return 'reserved';
    return 'not_shipped';
}

// ── Реєстрація в background_jobs ─────────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк замовлень з МойСклад',
        'script'   => 'scripts/sync_ms_orders.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ЗАМОВЛЕНЬ ===');

// ── Counterparty map: id_ms → id ─────────────────────────────────────────────

out('Завантаження counterparty map...');
$cpMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM counterparty WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $cpMap[$row['id_ms']] = (int)$row['id'];
out('Контрагентів: ' . count($cpMap));

// ── Існуючі замовлення: id_ms → updated_at ───────────────────────────────────

out('Завантаження існуючих замовлень...');
$existing = array(); // id_ms → updated_at
$r = Database::fetchAll('Papir', "SELECT id_ms, updated_at FROM customerorder WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $existing[$row['id_ms']] = $row['updated_at'];
out('В нашій базі: ' . count($existing));

// ── Загальна кількість в ms ───────────────────────────────────────────────────

$totalR = Database::fetchRow('ms', "SELECT COUNT(*) as cnt FROM customerorder WHERE meta IS NOT NULL AND meta != ''");
$total  = ($totalR['ok'] && $totalR['row']) ? (int)$totalR['row']['cnt'] : 0;
out("В ms.customerorder: {$total}");

$stats = array('inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0);
$offset = 0;

while (true) {
    $rows = Database::fetchAll('ms',
        "SELECT meta, updated, name, description, externalCode, moment, applicable,
                sum, agent, state, payedSum, shippedSum, reservedSum, vatSum,
                salesChannel, demands
         FROM customerorder
         WHERE meta IS NOT NULL AND meta != ''
         ORDER BY id
         LIMIT {$batchSize} OFFSET {$offset}"
    );

    if (!$rows['ok'] || empty($rows['rows'])) break;

    foreach ($rows['rows'] as $row) {
        $idMs      = trim((string)$row['meta']);
        $updatedMs = $row['updated'] ? (string)$row['updated'] : '';

        $sum         = (float)$row['sum'];
        $payedSum    = (float)$row['payedSum'];
        $shippedSum  = (float)$row['shippedSum'];
        $reservedSum = (float)$row['reservedSum'];
        $vatSum      = (float)$row['vatSum'];

        $status          = resolveStatus(trim((string)$row['state']));
        $paymentStatus   = resolvePaymentStatus($payedSum, $sum);
        $shipmentStatus  = resolveShipmentStatus($shippedSum, $reservedSum, $sum);

        $agentMs    = trim((string)$row['agent']);
        $cpId       = ($agentMs !== '' && isset($cpMap[$agentMs])) ? $cpMap[$agentMs] : null;
        $cpIdSql    = $cpId ? $cpId : 'NULL';

        $applicable  = $row['applicable'] ? 1 : 0;
        $momentSql   = $row['moment'] ? "'" . e($row['moment']) . "'" : 'NULL';
        $updatedSql  = $updatedMs ? "'" . e($updatedMs) . "'" : 'NOW()';

        $idMsS       = nullOrStr($idMs);
        $numberS     = nullOrStr($row['name'], 32);
        $extCodeS    = nullOrStr($row['externalCode'], 64);
        $descS       = nullOrStr($row['description']);
        $channelS    = nullOrStr($row['salesChannel'], 64);
        $demandsS    = nullOrStr($row['demands'], 36);

        // ── UPDATE ───────────────────────────────────────────────────────────
        if (isset($existing[$idMs])) {
            if ($updatedMs && $updatedMs <= $existing[$idMs]) {
                $stats['skipped']++;
                continue;
            }
            if (!$dryRun) {
                $r2 = Database::query('Papir',
                    "UPDATE customerorder SET
                     number={$numberS}, external_code={$extCodeS}, moment={$momentSql},
                     applicable={$applicable}, sum_total={$sum}, sum_paid={$payedSum},
                     sum_shipped={$shippedSum}, sum_reserved={$reservedSum}, sum_vat={$vatSum},
                     counterparty_id={$cpIdSql}, status='{$status}',
                     payment_status='{$paymentStatus}', shipment_status='{$shipmentStatus}',
                     sales_channel={$channelS}, description={$descS},
                     sync_state='synced', updated_at={$updatedSql}
                     WHERE id_ms={$idMsS}"
                );
                if (!$r2['ok']) { $stats['errors']++; continue; }
            }
            $existing[$idMs] = $updatedMs;
            $stats['updated']++;
            continue;
        }

        // ── INSERT ────────────────────────────────────────────────────────────
        if (!$dryRun) {
            $uuid = generateUuid();
            $r2 = Database::query('Papir',
                "INSERT INTO customerorder
                 (uuid, id_ms, source, external_code, number, moment, applicable,
                  counterparty_id, status, payment_status, shipment_status,
                  sum_total, sum_paid, sum_shipped, sum_reserved, sum_vat,
                  sales_channel, description, sync_state, updated_at)
                 VALUES
                 ('{$uuid}', {$idMsS}, 'moysklad', {$extCodeS}, {$numberS}, {$momentSql},
                  {$applicable}, {$cpIdSql}, '{$status}', '{$paymentStatus}',
                  '{$shipmentStatus}', {$sum}, {$payedSum}, {$shippedSum},
                  {$reservedSum}, {$vatSum}, {$channelS}, {$descS},
                  'synced', {$updatedSql})"
            );
            if (!$r2['ok']) { $stats['errors']++; continue; }
        }

        $existing[$idMs] = $updatedMs;
        $stats['inserted']++;
    }

    $offset += $batchSize;

    if (($stats['inserted'] + $stats['updated'] + $stats['skipped']) % 10000 === 0 || $offset % 10000 === 0) {
        $done = $stats['inserted'] + $stats['updated'] + $stats['skipped'] + $stats['errors'];
        out("~{$done}/{$total} | +{$stats['inserted']} upd={$stats['updated']} skip={$stats['skipped']} err={$stats['errors']}");
    }
}

out('');
out('=== ГОТОВО ===');
out("Додано:    {$stats['inserted']}");
out("Оновлено: {$stats['updated']}");
out("Пропущено: {$stats['skipped']}");
out("Помилки:   {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}
