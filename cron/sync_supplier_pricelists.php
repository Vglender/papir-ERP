<?php

/**
 * Cron: синхронізація прайсів постачальників + перерахунок цін.
 *
 * Режими запуску (аргумент --pricelist-id=N або --source-type=TYPE):
 *   --pricelist-id=1   → синхронізувати конкретний прайс (напр. Склад id=1)
 *   --source-type=all  → синхронізувати всі активні прайси
 *
 * Рекомендовані cron:
 *   # Склад (moy_sklad, id=1): кожні 3 години
 *   0 7-22/3 * * * php /var/www/papir/cron/sync_supplier_pricelists.php --pricelist-id=1 >> /var/log/papir/sync_supplier_sklад.log 2>&1
 *   # Виробництво (google_sheets, id=2): раз на добу о 02:00
 *   0 2 * * * php /var/www/papir/cron/sync_supplier_pricelists.php --pricelist-id=2 >> /var/log/papir/sync_supplier_vyrobn.log 2>&1
 */

define('CRON_MODE', true);
set_time_limit(600);

function logLine($text, $type = 'info')
{
    echo '[' . date('Y-m-d H:i:s') . '][' . strtoupper($type) . '] ' . $text . PHP_EOL;
}

require_once __DIR__ . '/../modules/prices/prices_bootstrap.php';

// ── Parse args ────────────────────────────────────────────────────────────────
$pricelistId = 0;
$sourceType  = '';
foreach ($argv as $arg) {
    if (preg_match('/^--pricelist-id=(\d+)$/', $arg, $m)) {
        $pricelistId = (int)$m[1];
    }
    if (preg_match('/^--source-type=(.+)$/', $arg, $m)) {
        $sourceType = trim($m[1]);
    }
}

if ($pricelistId <= 0 && $sourceType === '') {
    echo 'Usage: php sync_supplier_pricelists.php --pricelist-id=N' . PHP_EOL;
    echo '       php sync_supplier_pricelists.php --source-type=all' . PHP_EOL;
    exit(1);
}

$pricelistRepo = new PricelistRepository();
$itemRepo      = new PricelistItemRepository();

// ── Determine which pricelists to sync ───────────────────────────────────────
$pricelists = array();

if ($pricelistId > 0) {
    $pl = $pricelistRepo->getById($pricelistId);
    if (!$pl) {
        logLine("Pricelist id={$pricelistId} not found", 'error');
        exit(1);
    }
    $pricelists[] = $pl;
} else {
    $r = \Database::fetchAll('Papir',
        "SELECT id, name, source_type FROM price_supplier_pricelists WHERE is_active = 1 ORDER BY id");
    if ($r['ok']) {
        foreach ($r['rows'] as $row) {
            if ($sourceType === 'all' || $row['source_type'] === $sourceType) {
                $pricelists[] = $pricelistRepo->getById((int)$row['id']);
            }
        }
    }
}

if (empty($pricelists)) {
    logLine("No pricelists to sync", 'warn');
    exit(0);
}

$start = microtime(true);
logLine('=== START SUPPLIER PRICELIST SYNC ===');

// ── Sync each pricelist ───────────────────────────────────────────────────────
$productRepo = new ProductPriceRepository();
$engine      = PriceEngine::create($itemRepo);
$builder     = new DiscountProfileBuilder(
    $engine,
    $productRepo,
    new DiscountStrategyRepository(),
    new QuantityStrategyRepository(),
    new ProductPackageRepository(),
    new ProductDiscountProfileRepository(),
    new GlobalSettingsRepository()
);

foreach ($pricelists as $pricelist) {
    $plId   = (int)$pricelist['id'];
    $plName = $pricelist['name'];

    logLine("Syncing [{$plId}] {$plName} (source_type={$pricelist['source_type']})...");

    switch ($pricelist['source_type']) {
        case 'moy_sklad':
            require_once __DIR__ . '/../modules/moysklad/moysklad_api.php';
            $syncer = new MoySkladPriceSync(new SupplierRepository(), $pricelistRepo, $itemRepo);
            $result = $syncer->sync($plId);
            break;

        case 'google_sheets':
            $syncer = new GoogleSheetsPriceSync($pricelistRepo, $itemRepo);
            $result = $syncer->sync($plId);
            break;

        default:
            $result = array('ok' => false, 'error' => 'Sync not implemented for source_type=' . $pricelist['source_type']);
    }

    if (!$result['ok']) {
        logLine("FAILED [{$plId}] {$plName}: " . (isset($result['error']) ? $result['error'] : 'unknown error'), 'error');
        continue;
    }

    $imported = isset($result['imported']) ? (int)$result['imported'] : 0;
    $matched  = isset($result['matched'])  ? (int)$result['matched']  : 0;
    logLine("Synced [{$plId}] {$plName}: imported={$imported}, matched={$matched}");

    // Update product statuses
    $itemRepo->syncProductStatuses();
    logLine("Product statuses synced");

    // Recalculate prices for matched products
    $matchedIds   = $itemRepo->getMatchedProductIds($plId);
    $recalculated = 0;
    if (!empty($matchedIds)) {
        foreach ($matchedIds as $pid) {
            $r = $builder->build($pid);
            if ($r['ok']) $recalculated++;
        }
    }
    logLine("Recalculated {$recalculated} products");
}

$elapsed = round(microtime(true) - $start, 1);
logLine("=== DONE in {$elapsed}s ===");