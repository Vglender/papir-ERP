<?php
/**
 * Map Prom.ua products to Papir products.
 *
 * Strategy:
 *   1. Fetch ALL products from Prom API (paginated by last_id)
 *   2. Match by external_id → product_id (primary key in Papir)
 *   3. Fallback: match by sku → product_article
 *   4. Update product_papir.id_prom with the real Prom product ID
 *
 * Usage: php modules/prom/scripts/map_products.php [--dry-run]
 */

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
require_once __DIR__ . '/../PromApi.php';

AppRegistry::guard('prom');

$dryRun = in_array('--dry-run', $argv ? $argv : array());

echo "=== Prom.ua Product Mapping ===" . PHP_EOL;
echo "Mode: " . ($dryRun ? "DRY RUN" : "LIVE") . PHP_EOL . PHP_EOL;

// ── Step 1: Load all Papir articles into lookup maps ─────────────────────
echo "Loading Papir products..." . PHP_EOL;

$byId      = array(); // product_id => product_article
$byArticle = array(); // product_article => product_id

$r = \Database::fetchAll('Papir',
    "SELECT product_id, product_article FROM product_papir
     WHERE product_article != '' AND product_article IS NOT NULL"
);
if (!$r['ok']) {
    echo "ERROR: Cannot load products: " . $r['error'] . PHP_EOL;
    exit(1);
}
foreach ($r['rows'] as $row) {
    $byId[$row['product_id']] = $row['product_article'];
    $art = trim($row['product_article']);
    if ($art !== '') {
        $byArticle[$art] = $row['product_id'];
    }
}
echo "  Loaded " . count($byId) . " products with articles." . PHP_EOL;

// ── Step 2: Fetch all Prom products (pagination via last_id desc) ────────
echo PHP_EOL . "Fetching Prom.ua catalog..." . PHP_EOL;

$api = new PromApi();
$promProducts = array();
$lastId = null;
$page = 0;
$limit = 100;

while (true) {
    $page++;
    $params = array('limit' => $limit);
    if ($lastId !== null) {
        $params['last_id'] = $lastId;
    }

    $result = $api->getProducts($params);
    if (empty($result['ok']) || !isset($result['products'])) {
        $err = isset($result['error']) ? $result['error'] : 'Unknown error';
        echo "  API error on page {$page}: {$err}" . PHP_EOL;
        break;
    }

    $batch = $result['products'];
    if (empty($batch)) {
        break;
    }

    foreach ($batch as $p) {
        $promProducts[] = array(
            'prom_id'     => $p['id'],
            'external_id' => isset($p['external_id']) ? trim($p['external_id']) : '',
            'sku'         => isset($p['sku']) ? trim($p['sku']) : '',
            'name'        => isset($p['name']) ? $p['name'] : '',
            'status'      => isset($p['status']) ? $p['status'] : '',
        );
        $lastId = $p['id'];
    }

    echo "  Page {$page}: fetched " . count($batch) . " products (total: " . count($promProducts) . ")" . PHP_EOL;

    if (count($batch) < $limit) {
        break; // last page
    }

    // Rate limit safety
    usleep(200000); // 200ms
}

echo PHP_EOL . "Total Prom products: " . count($promProducts) . PHP_EOL;

// ── Step 3: Match and map ────────────────────────────────────────────────
echo PHP_EOL . "Matching products..." . PHP_EOL;

$matched      = 0;
$matchedById  = 0;
$matchedBySku = 0;
$notFound     = 0;
$updates      = array(); // product_id => prom_id

foreach ($promProducts as $pp) {
    $promId     = $pp['prom_id'];
    $externalId = $pp['external_id'];
    $sku        = $pp['sku'];

    // Primary: match by external_id → product_id
    if ($externalId !== '' && isset($byId[(int)$externalId])) {
        $papirId = (int) $externalId;
        $updates[$papirId] = $promId;
        $matched++;
        $matchedById++;
        continue;
    }

    // Fallback: match by sku → product_article
    if ($sku !== '' && isset($byArticle[$sku])) {
        $papirId = $byArticle[$sku];
        // Don't overwrite if already matched by ID
        if (!isset($updates[$papirId])) {
            $updates[$papirId] = $promId;
            $matched++;
            $matchedBySku++;
        }
        continue;
    }

    $notFound++;
}

echo "  Matched by external_id: {$matchedById}" . PHP_EOL;
echo "  Matched by sku/article: {$matchedBySku}" . PHP_EOL;
echo "  Total matched: {$matched}" . PHP_EOL;
echo "  Not found in Papir: {$notFound}" . PHP_EOL;

// ── Step 4: Update product_papir.id_prom ─────────────────────────────────
if ($dryRun) {
    echo PHP_EOL . "DRY RUN — no updates applied." . PHP_EOL;

    // Show sample
    $sample = array_slice($updates, 0, 10, true);
    echo PHP_EOL . "Sample updates:" . PHP_EOL;
    foreach ($sample as $pid => $promId) {
        $art = isset($byId[$pid]) ? $byId[$pid] : '?';
        echo "  product_id={$pid} (art={$art}) → id_prom={$promId}" . PHP_EOL;
    }
} else {
    echo PHP_EOL . "Applying " . count($updates) . " updates..." . PHP_EOL;

    // Reset all id_prom first
    \Database::query('Papir', "UPDATE product_papir SET id_prom = 0");

    $done = 0;
    $batchSize = 500;
    $keys = array_keys($updates);

    for ($i = 0; $i < count($keys); $i += $batchSize) {
        $chunk = array_slice($keys, $i, $batchSize);
        $cases = array();
        foreach ($chunk as $pid) {
            $cases[] = "WHEN " . (int)$pid . " THEN " . (int)$updates[$pid];
        }
        $idList = implode(',', $chunk);
        $sql = "UPDATE product_papir SET id_prom = CASE product_id "
             . implode(' ', $cases) . " END "
             . "WHERE product_id IN ({$idList})";
        \Database::query('Papir', $sql);
        $done += count($chunk);
        echo "  Updated {$done} / " . count($updates) . PHP_EOL;
    }

    echo PHP_EOL . "Done! Mapped {$done} products." . PHP_EOL;

    // Verify
    $r = \Database::fetchAll('Papir', 'SELECT COUNT(*) cnt FROM product_papir WHERE id_prom > 0');
    echo "Products with id_prom set: " . $r['rows'][0]['cnt'] . PHP_EOL;

    $r2 = \Database::fetchAll('Papir', 'SELECT COUNT(*) cnt FROM product_papir WHERE id_prom > 0 AND id_prom = product_id');
    echo "Fake mappings (id_prom = product_id): " . $r2['rows'][0]['cnt'] . PHP_EOL;
}
