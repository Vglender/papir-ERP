<?php
/**
 * Синк відвантажень: ms.demand + ms.demand_positions → Papir.demand + demand_item
 *
 * Запуск:
 *   php scripts/sync_ms_demand.php --dry-run
 *   php scripts/sync_ms_demand.php
 *
 * Логіка:
 *   - INSERT нові відвантаження (по id_ms)
 *   - UPDATE змінені (ms.updated > наш updated_at)
 *   - Позиції: delete+reinsert якщо кількість відрізняється від ms
 *   - counterparty_id: agent → counterparty.id_ms
 *   - customerorder_id: demand.customerOrder → customerorder.id_ms
 */

// ── Захист від паралельного запуску ──────────────────────────────────────────
$_lockFp = fopen('/tmp/sync_ms_demand.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) {
    echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL;
    exit(0);
}

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_demand.log';
$myPid     = getmypid();
$batchSize = 500;

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }
function e($v)     { return Database::escape('Papir', (string)$v); }
function nullOrStr($v, $max = 0) {
    $v = trim((string)$v);
    if ($v === '') return 'NULL';
    if ($max > 0 && mb_strlen($v, 'UTF-8') > $max) $v = mb_substr($v, 0, $max, 'UTF-8');
    return "'" . e($v) . "'";
}
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

// ── Маппінг статусів ms.demand.state → наш enum ──────────────────────────────
$stateMap = array(
    'ac913c39-eaa9-11eb-0a80-064900024c02' => 'shipped',     // Відправленно  70267
    '1786f816-7890-11ed-0a80-01d4001fe449' => 'robot',       // Робот           385
    '313e4d03-eaad-11eb-0a80-0d7d0002b683' => 'assembling',  // Зібрати          54
    'ac9137e7-eaa9-11eb-0a80-064900024c01' => 'assembled',   // Зібранно          2
    '67350236-916f-11ec-0a80-084e0018453e' => 'transfer',    // Переміщення      91
    '2e2bc3dc-5be1-11ee-0a80-0677000179a7' => 'arrived',     // Прибув          169
);
function resolveStatus($uuid) {
    global $stateMap;
    return isset($stateMap[$uuid]) ? $stateMap[$uuid] : 'shipped'; // fallback
}

// ── Реєстрація в background_jobs ─────────────────────────────────────────────
if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк відвантажень з МойСклад',
        'script'   => 'scripts/sync_ms_demand.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ВІДВАНТАЖЕНЬ ===');

// ── Maps ──────────────────────────────────────────────────────────────────────
out('Завантаження map...');

$cpMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM counterparty WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $cpMap[$row['id_ms']] = (int)$row['id'];
out('Контрагентів: ' . count($cpMap));

$orderMap = array(); // customerorder.id_ms → customerorder.id
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM customerorder WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $orderMap[$row['id_ms']] = (int)$row['id'];
out('Замовлень: ' . count($orderMap));

$productMap = array();
$r = Database::fetchAll('Papir', "SELECT product_id, id_ms FROM product_papir WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $productMap[$row['id_ms']] = (int)$row['product_id'];
out('Товарів: ' . count($productMap));

// ── Існуючі відвантаження: id_ms → updated_at ────────────────────────────────
out('Завантаження існуючих відвантажень...');
$existing = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms, updated_at FROM demand WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) {
    $existing[$row['id_ms']] = array('id' => (int)$row['id'], 'updated_at' => $row['updated_at']);
}
out('В нашій БД: ' . count($existing));

// ── Кількість позицій в ms (для порівняння) ───────────────────────────────────
out('Завантаження кількості позицій ms...');
$msItemCounts = array(); // demand_meta → count
$r = Database::fetchAll('ms',
    "SELECT meta, COUNT(*) as cnt FROM demand_positions
     WHERE meta IS NOT NULL AND meta != '' GROUP BY meta"
);
if ($r['ok']) foreach ($r['rows'] as $row) $msItemCounts[$row['meta']] = (int)$row['cnt'];

// ── Кількість наших позицій ───────────────────────────────────────────────────
$ourItemCounts = array(); // demand_id → count
$r = Database::fetchAll('Papir',
    "SELECT demand_id, COUNT(*) as cnt FROM demand_item GROUP BY demand_id"
);
if ($r['ok']) foreach ($r['rows'] as $row) $ourItemCounts[(int)$row['demand_id']] = (int)$row['cnt'];

// ── Загальна кількість в ms ───────────────────────────────────────────────────
$totalR = Database::fetchRow('ms', "SELECT COUNT(*) as cnt FROM demand WHERE meta IS NOT NULL AND meta != ''");
$total  = ($totalR['ok'] && $totalR['row']) ? (int)$totalR['row']['cnt'] : 0;
out("В ms.demand: {$total}");

$stats = array('inserted' => 0, 'updated' => 0, 'skipped' => 0,
               'items_synced' => 0, 'items_inserted' => 0, 'errors' => 0);
$offset = 0;

while (true) {
    $rows = Database::fetchAll('ms',
        "SELECT meta, updated, name, description, externalCode, moment, applicable,
                sum, vatSum, payedSum, profit, profit_real,
                agent, customerOrder, state, salesChannel
         FROM demand
         WHERE meta IS NOT NULL AND meta != ''
         ORDER BY id
         LIMIT {$batchSize} OFFSET {$offset}"
    );
    if (!$rows['ok'] || empty($rows['rows'])) break;

    foreach ($rows['rows'] as $row) {
        $idMs      = trim((string)$row['meta']);
        $updatedMs = $row['updated'] ? (string)$row['updated'] : '';

        $agentMs   = trim((string)$row['agent']);
        $orderMs   = trim((string)$row['customerOrder']);
        $cpId      = ($agentMs  && isset($cpMap[$agentMs]))   ? $cpMap[$agentMs]   : null;
        $orderId   = ($orderMs  && isset($orderMap[$orderMs])) ? $orderMap[$orderMs] : null;

        $status    = resolveStatus(trim((string)$row['state']));
        $applicable = $row['applicable'] ? 1 : 0;

        $momentSql  = $row['moment'] ? "'" . e($row['moment']) . "'" : 'NULL';
        $updatedSql = $updatedMs ? "'" . e($updatedMs) . "'" : 'NOW()';
        $cpIdSql    = $cpId    ? $cpId    : 'NULL';
        $orderIdSql = $orderId ? $orderId : 'NULL';

        $sum        = round((float)$row['sum'], 2);
        $vatSum     = round((float)$row['vatSum'], 2);
        $payedSum   = round((float)$row['payedSum'], 2);
        $profit     = round((float)$row['profit'], 2);
        $profitReal = round((float)$row['profit_real'], 2);

        $idMsS   = nullOrStr($idMs);
        $numberS = nullOrStr($row['name'], 32);
        $extCodeS= nullOrStr($row['externalCode'], 64);
        $descS   = nullOrStr($row['description']);
        $channelS= nullOrStr($row['salesChannel'], 64);

        // ── UPDATE ────────────────────────────────────────────────────────────
        if (isset($existing[$idMs])) {
            $rec = $existing[$idMs];
            if ($updatedMs && $updatedMs <= $rec['updated_at']) {
                // Але позиції можуть потребувати синку
                $msCount  = isset($msItemCounts[$idMs]) ? $msItemCounts[$idMs] : 0;
                $ourCount = isset($ourItemCounts[$rec['id']]) ? $ourItemCounts[$rec['id']] : 0;
                if ($msCount === $ourCount) { $stats['skipped']++; continue; }
            } else {
                if (!$dryRun) {
                    Database::query('Papir',
                        "UPDATE demand SET
                         number={$numberS}, external_code={$extCodeS}, moment={$momentSql},
                         applicable={$applicable}, counterparty_id={$cpIdSql},
                         customerorder_id={$orderIdSql}, status='{$status}',
                         sum_total={$sum}, sum_vat={$vatSum}, sum_paid={$payedSum},
                         profit={$profit}, profit_real={$profitReal},
                         sales_channel={$channelS}, description={$descS},
                         sync_state='synced', updated_at={$updatedSql}
                         WHERE id_ms={$idMsS}"
                    );
                }
                $existing[$idMs]['updated_at'] = $updatedMs;
                $stats['updated']++;
            }

            // Синк позицій якщо кількість відрізняється
            $demandId = $rec['id'];
            $msCount  = isset($msItemCounts[$idMs]) ? $msItemCounts[$idMs] : 0;
            $ourCount = isset($ourItemCounts[$demandId]) ? $ourItemCounts[$demandId] : 0;
            if ($msCount !== $ourCount && $msCount > 0) {
                if (!$dryRun) syncItems($idMs, $demandId, $productMap);
                $stats['items_synced']++;
            }
            continue;
        }

        // ── INSERT ────────────────────────────────────────────────────────────
        $demandId = null;
        if (!$dryRun) {
            $uuid = generateUuid();
            $r2 = Database::query('Papir',
                "INSERT INTO demand
                 (uuid, id_ms, source, external_code, number, moment, applicable,
                  counterparty_id, customerorder_id, status,
                  sum_total, sum_vat, sum_paid, profit, profit_real,
                  sales_channel, description, sync_state, updated_at)
                 VALUES
                 ('{$uuid}', {$idMsS}, 'moysklad', {$extCodeS}, {$numberS}, {$momentSql},
                  {$applicable}, {$cpIdSql}, {$orderIdSql}, '{$status}',
                  {$sum}, {$vatSum}, {$payedSum}, {$profit}, {$profitReal},
                  {$channelS}, {$descS}, 'synced', {$updatedSql})"
            );
            if (!$r2['ok']) { $stats['errors']++; continue; }
            $r3 = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() as id");
            $demandId = ($r3['ok'] && $r3['row']) ? (int)$r3['row']['id'] : null;
        }

        $existing[$idMs] = array('id' => $demandId ?: 0, 'updated_at' => $updatedMs);
        $stats['inserted']++;

        // Позиції для нового запису
        $msCount = isset($msItemCounts[$idMs]) ? $msItemCounts[$idMs] : 0;
        if ($msCount > 0 && $demandId) {
            if (!$dryRun) {
                $inserted = syncItems($idMs, $demandId, $productMap);
                $stats['items_inserted'] += $inserted;
            } else {
                $stats['items_inserted'] += $msCount;
            }
        }
    }

    $offset += $batchSize;
    $done = $stats['inserted'] + $stats['updated'] + $stats['skipped'] + $stats['errors'];
    if ($done % 10000 < $batchSize || $offset % 10000 === 0) {
        out("~{$done}/{$total} | +{$stats['inserted']} upd={$stats['updated']} skip={$stats['skipped']} items={$stats['items_inserted']} err={$stats['errors']}");
    }
}

// ── Функція синку позицій ─────────────────────────────────────────────────────
function syncItems($demandMeta, $demandId, &$productMap) {
    Database::query('Papir', "DELETE FROM demand_item WHERE demand_id={$demandId}");

    $posRows = Database::fetchAll('ms',
        "SELECT quantity, price, product_id, discount, vat, shipped, reserve, inTransit, overhead
         FROM demand_positions WHERE meta='" . Database::escape('ms', $demandMeta) . "'
         ORDER BY id"
    );
    if (!$posRows['ok']) return 0;

    $lineNo  = 1;
    $inserted = 0;
    foreach ($posRows['rows'] as $pos) {
        $qty      = (float)$pos['quantity'];
        $price    = (float)$pos['price'];
        $sumRow   = round($qty * $price, 2);
        $discount = round((float)$pos['discount'], 3);
        $vatRate  = round((float)$pos['vat'], 3);
        $shipped  = round((float)$pos['shipped'], 3);
        $reserve  = round((float)$pos['reserve'], 3);
        $inTransit= round((float)$pos['inTransit'], 3);
        $overhead = round((float)$pos['overhead'], 2);

        $productMsId  = trim((string)$pos['product_id']);
        $productId    = ($productMsId && isset($productMap[$productMsId])) ? $productMap[$productMsId] : null;
        $productIdSql = $productId ? $productId : 'NULL';
        $productMsIdSql = $productMsId ? "'" . Database::escape('Papir', $productMsId) . "'" : 'NULL';

        $r = Database::query('Papir',
            "INSERT INTO demand_item
             (demand_id, line_no, product_id, product_ms_id,
              quantity, price, discount_percent, vat_rate, sum_row,
              shipped_quantity, reserve, in_transit, overhead)
             VALUES
             ({$demandId}, {$lineNo}, {$productIdSql}, {$productMsIdSql},
              {$qty}, {$price}, {$discount}, {$vatRate}, {$sumRow},
              {$shipped}, {$reserve}, {$inTransit}, {$overhead})"
        );
        if ($r['ok']) { $lineNo++; $inserted++; }
    }
    return $inserted;
}

out('');
out('=== ГОТОВО ===');
out("Відвантажень додано:  {$stats['inserted']}");
out("Відвантажень оновлено: {$stats['updated']}");
out("Пропущено:            {$stats['skipped']}");
out("Позицій вставлено:    {$stats['items_inserted']}");
out("Позицій оновлено (sync): {$stats['items_synced']}");
out("Помилки:              {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}
