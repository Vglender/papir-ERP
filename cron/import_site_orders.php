<?php
/**
 * Cron: Import new orders from OpenCart sites into Papir.
 *
 * Checks each active site for orders newer than the last imported.
 * Creates customerorder + items + shipping + payment receipts.
 *
 * Run every 5 minutes (crontab: every-5-min * * * *):
 *   php /var/www/papir/cron/import_site_orders.php >> /var/log/papir/import_site_orders.log 2>&1
 */

define('CRON_MODE', true);

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/customerorder/services/SiteOrderImporter.php';

// Load TriggerEngine if available
$triggerPath = __DIR__ . '/../modules/triggers/TriggerEngine.php';
if (file_exists($triggerPath)) {
    require_once $triggerPath;
}

$start = microtime(true);

echo '[' . date('Y-m-d H:i:s') . '] === START SITE ORDER IMPORT ===' . PHP_EOL;

$importer = new SiteOrderImporter(function($msg) {
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
});

$result = $importer->importAll();

$elapsed = round(microtime(true) - $start, 2);

echo '[' . date('Y-m-d H:i:s') . '] Done.'
    . ' imported=' . $result['imported']
    . ' errors=' . count($result['errors'])
    . ' time=' . $elapsed . 's'
    . PHP_EOL;

if (!empty($result['errors'])) {
    foreach ($result['errors'] as $err) {
        echo '[ERROR] ' . $err . PHP_EOL;
    }
}
