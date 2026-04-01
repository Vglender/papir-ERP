<?php
/**
 * Cron: track_ttn.php
 * Updates TTN statuses from Nova Poshta API.
 * Run: every hour or every 2 hours.
 *
 * Crontab example:
 *   0 * * * * php /var/www/papir/cron/track_ttn.php >> /tmp/track_ttn.log 2>&1
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/track_ttn.log';
$myPid   = getmypid();
$batchSize = 100; // NP API allows max 100 per request

echo '[' . date('Y-m-d H:i:s') . '] track_ttn started (pid=' . $myPid . ')' . PHP_EOL;

// Register job
if (!$dryRun) {
    \Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Трекінг ТТН НП',
        'script'   => 'cron/track_ttn.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

// Fetch TTNs to track (not yet delivered/returned)
$ttns = \Papir\Crm\TtnRepository::getForTracking($batchSize);

echo '[' . date('Y-m-d H:i:s') . '] Found ' . count($ttns) . ' TTNs to track' . PHP_EOL;

if (empty($ttns)) {
    echo '[' . date('Y-m-d H:i:s') . '] Nothing to track, exiting' . PHP_EOL;
} else {
    if ($dryRun) {
        echo '[DRY-RUN] Would track ' . count($ttns) . ' TTNs' . PHP_EOL;
    } else {
        $result = \Papir\Crm\TrackingService::trackBatch($ttns);

        echo '[' . date('Y-m-d H:i:s') . '] Updated: ' . $result['updated'] . PHP_EOL;
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                echo '[ERROR] ' . $err . PHP_EOL;
            }
        }
    }
}

// Mark done
if (!$dryRun) {
    \Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}

echo '[' . date('Y-m-d H:i:s') . '] track_ttn finished' . PHP_EOL;
