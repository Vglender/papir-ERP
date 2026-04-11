<?php
/**
 * Ukrposhta: refresh tracking statuses.
 *
 * Usage:
 *   php modules/ukrposhta/cron/refresh_tracking.php                 # default batch of 200
 *   php modules/ukrposhta/cron/refresh_tracking.php --limit=500
 *   php modules/ukrposhta/cron/refresh_tracking.php --dry-run
 *
 * Crontab (every 30 minutes):
 *   (star)/30 (star) (star) (star) (star) php /var/www/papir/modules/ukrposhta/cron/refresh_tracking.php >> /tmp/up_tracking.log 2>&1
 */
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/IntegrationSettingsService.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';

AppRegistry::guard('ukrposhta');

require_once __DIR__ . '/../ukrposhta_bootstrap.php';

$limit   = 200;
$dryRun  = in_array('--dry-run', $argv);
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $limit = max(1, min(1000, (int)$m[1]));
}

$start = microtime(true);
$log = function ($m) { echo '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL; };

$log('up_tracking start (limit=' . $limit . ($dryRun ? ', DRY-RUN' : '') . ')');

$ttns = \Papir\Crm\UpTtnRepository::getForTracking($limit);
$log('Picked ' . count($ttns) . ' TTNs for tracking refresh');

if (!$ttns) {
    $log('Nothing to do.');
    exit(0);
}

if ($dryRun) {
    foreach ($ttns as $t) {
        $log('  would track ' . $t['barcode'] . ' (id=' . $t['id'] . ', status=' . ($t['lifecycle_status'] ?: '-') . ')');
    }
    exit(0);
}

$res = \Papir\Crm\TrackingService::trackBatch($ttns);
$log('Updated: ' . $res['updated']);
if (!empty($res['errors'])) {
    foreach ($res['errors'] as $e) $log('ERROR: ' . $e);
}
$log('Done in ' . round(microtime(true) - $start, 2) . 's');
