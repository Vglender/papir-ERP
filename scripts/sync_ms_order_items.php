<?php
/**
 * Синк позицій замовлень: ms.customerorder_positions → Papir.customerorder_item
 *
 * Запуск:
 *   php scripts/sync_ms_order_items.php --dry-run
 *   php scripts/sync_ms_order_items.php
 *
 * Зв'язок: customerorder_positions.meta = customerorder.meta (= наш id_ms)
 *
 * Логіка ідемпотентності (per order):
 *   - Замовлення ще немає в нашій БД → пропустити (крон добере пізніше)
 *   - Кількість позицій співпадає → пропустити
 *   - Кількість відрізняється (або 0) → DELETE + INSERT всіх позицій замовлення
 */

$_lockFp = fopen('/tmp/sync_ms_order_items.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) { echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL; exit(0); }

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_order_items.log';
$myPid     = getmypid();
$batchSize = 200; // замовлень за раз (у кожного може бути кілька позицій)

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

// ── Реєстрація в background_jobs ─────────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк позицій замовлень з МойСклад',
        'script'   => 'scripts/sync_ms_order_items.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ПОЗИЦІЙ ЗАМОВЛЕНЬ ===');

// ── Map: customerorder.id_ms → customerorder.id ───────────────────────────────

out('Завантаження map замовлень...');
$orderMap = array(); // id_ms → id
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM customerorder WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $orderMap[$row['id_ms']] = (int)$row['id'];
out('Замовлень в нашій БД: ' . count($orderMap));

// ── Map: product_papir.id_ms → product_id ────────────────────────────────────

out('Завантаження map товарів...');
$productMap = array(); // id_ms → product_id
$r = Database::fetchAll('Papir', "SELECT product_id, id_ms FROM product_papir WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $productMap[$row['id_ms']] = (int)$row['product_id'];
out('Товарів з id_ms: ' . count($productMap));

// ── Fallback map: ms.product.id_ms → product_article → product_papir.product_id
out('Завантаження fallback map (артикул)...');
$articleToProductId = array(); // product_article → product_id
$r = Database::fetchAll('Papir', "SELECT product_id, product_article FROM product_papir WHERE product_article IS NOT NULL AND product_article != ''");
if ($r['ok']) foreach ($r['rows'] as $row) $articleToProductId[$row['product_article']] = (int)$row['product_id'];

$msIdToArticle = array(); // ms id_ms → product_article
$r = Database::fetchAll('ms', "SELECT id_ms, product_article FROM product WHERE id_ms IS NOT NULL AND product_article IS NOT NULL AND product_article != ''");
if ($r['ok']) foreach ($r['rows'] as $row) $msIdToArticle[$row['id_ms']] = $row['product_article'];
out('Fallback: MS товарів з артикулом: ' . count($msIdToArticle));

// ── Кількість наших позицій по замовленням ────────────────────────────────────

out('Завантаження кількості існуючих позицій...');
$ourCounts = array(); // customerorder_id → count
$r = Database::fetchAll('Papir',
    "SELECT customerorder_id, COUNT(*) as cnt FROM customerorder_item GROUP BY customerorder_id"
);
if ($r['ok']) foreach ($r['rows'] as $row) $ourCounts[(int)$row['customerorder_id']] = (int)$row['cnt'];
$ordersWithItems = count($ourCounts);
out("Замовлень з позиціями: {$ordersWithItems}");

// ── Кількість позицій в ms по замовленням ─────────────────────────────────────

out('Завантаження кількості позицій ms...');
$msCounts = array(); // order_meta → count
$r = Database::fetchAll('ms',
    "SELECT meta, COUNT(*) as cnt FROM customerorder_positions
     WHERE meta IS NOT NULL AND meta != ''
     GROUP BY meta"
);
if ($r['ok']) foreach ($r['rows'] as $row) $msCounts[$row['meta']] = (int)$row['cnt'];
out('Замовлень з позиціями в ms: ' . count($msCounts));

// ── Визначаємо які замовлення потребують синку ────────────────────────────────

$toSync = array(); // order_meta → customerorder_id (ті що треба оновити)
foreach ($msCounts as $orderMeta => $msCount) {
    if (!isset($orderMap[$orderMeta])) continue; // замовлення ще не імпортовано
    $orderId  = $orderMap[$orderMeta];
    $ourCount = isset($ourCounts[$orderId]) ? $ourCounts[$orderId] : 0;
    if ($ourCount !== $msCount) {
        $toSync[$orderMeta] = $orderId;
    }
}

out('Замовлень для синку позицій: ' . count($toSync));

if (empty($toSync)) {
    out('Нічого синкувати.');
    if (!$dryRun) {
        Database::query('Papir',
            "UPDATE background_jobs SET status='done', finished_at=NOW()
             WHERE pid={$myPid} AND status='running'"
        );
    }
    exit(0);
}

// ── Синк батчами ──────────────────────────────────────────────────────────────

$stats = array('orders' => 0, 'items' => 0, 'skipped_orders' => 0, 'errors' => 0);

$toSyncMetas  = array_keys($toSync);
$totalToSync  = count($toSyncMetas);
$offset       = 0;

while ($offset < $totalToSync) {
    $batch = array_slice($toSyncMetas, $offset, $batchSize);

    // Завантажуємо позиції ms для цього батчу замовлень
    $inList = implode(',', array_map(function($m) { return "'" . Database::escape('ms', $m) . "'"; }, $batch));
    $rows = Database::fetchAll('ms',
        "SELECT meta, quantity, price, product_id
         FROM customerorder_positions
         WHERE meta IN ({$inList})
         ORDER BY meta, id"
    );

    if (!$rows['ok']) {
        out("Помилка завантаження позицій ms для батчу offset={$offset}");
        $stats['errors'] += count($batch);
        $offset += $batchSize;
        continue;
    }

    // Групуємо позиції по замовленню
    $byOrder = array(); // meta → [positions]
    foreach ($rows['rows'] as $row) {
        $m = $row['meta'];
        if (!isset($byOrder[$m])) $byOrder[$m] = array();
        $byOrder[$m][] = $row;
    }

    // Обробляємо кожне замовлення
    foreach ($batch as $orderMeta) {
        $orderId   = $toSync[$orderMeta];
        $positions = isset($byOrder[$orderMeta]) ? $byOrder[$orderMeta] : array();

        if (empty($positions)) {
            $stats['skipped_orders']++;
            continue;
        }

        if (!$dryRun) {
            // Видаляємо старі позиції
            Database::query('Papir',
                "DELETE FROM customerorder_item WHERE customerorder_id={$orderId}"
            );
        }

        // Вставляємо нові
        $lineNo   = 1;
        $orderOk  = true;
        $totalSum = 0.0; // накопичуємо суму для оновлення заголовку

        foreach ($positions as $pos) {
            $qty    = (float)$pos['quantity'];
            $price  = (float)$pos['price'];
            $sumRow = round($qty * $price, 2);

            $productMsId = trim((string)$pos['product_id']);
            $productId   = ($productMsId !== '' && isset($productMap[$productMsId]))
                           ? $productMap[$productMsId] : null;
            // Fallback: resolve via ms.product article → product_papir
            if ($productId === null && $productMsId !== '' && isset($msIdToArticle[$productMsId])) {
                $art = $msIdToArticle[$productMsId];
                if (isset($articleToProductId[$art])) {
                    $productId = $articleToProductId[$art];
                }
            }
            $productIdSql  = $productId ? $productId : 'NULL';
            $productMsIdSql = nullOrStr($productMsId, 36);

            if (!$dryRun) {
                $r2 = Database::query('Papir',
                    "INSERT INTO customerorder_item
                     (customerorder_id, line_no, product_id, product_ms_id,
                      quantity, price, sum_row, sum_without_discount)
                     VALUES
                     ({$orderId}, {$lineNo}, {$productIdSql}, {$productMsIdSql},
                      {$qty}, {$price}, {$sumRow}, {$sumRow})"
                );
                if (!$r2['ok']) { $orderOk = false; break; }
            }

            $totalSum += $sumRow;
            $lineNo++;
            $stats['items']++;
        }

        // Оновлюємо sum_total в заголовку замовлення — він завжди = сума позицій
        if ($orderOk && !$dryRun) {
            $totalSumRounded = round($totalSum, 2);
            Database::query('Papir',
                "UPDATE customerorder
                 SET sum_items={$totalSumRounded}, sum_total={$totalSumRounded}, sum_discount=0
                 WHERE id={$orderId}"
            );
        }

        if ($orderOk) {
            $stats['orders']++;
        } else {
            $stats['errors']++;
        }
    }

    $offset += $batchSize;
    $done = $stats['orders'] + $stats['errors'] + $stats['skipped_orders'];
    out("~{$done}/{$totalToSync} | orders={$stats['orders']} items={$stats['items']} err={$stats['errors']}");
}

out('');
out('=== ГОТОВО ===');
out("Замовлень оновлено: {$stats['orders']}");
out("Позицій вставлено: {$stats['items']}");
out("Пропущено (порожні): {$stats['skipped_orders']}");
out("Помилки: {$stats['errors']}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}