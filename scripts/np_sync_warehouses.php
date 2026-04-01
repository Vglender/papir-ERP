<?php
/**
 * np_sync_warehouses.php
 * Monthly sync of all NP warehouses/branches from NP API → np_warehouses table.
 *
 * Run: php /var/www/papir/scripts/np_sync_warehouses.php [--dry-run]
 * Cron: 0 3 1 * * php /var/www/papir/scripts/np_sync_warehouses.php >> /tmp/np_sync_warehouses.log 2>&1
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/np_sync_warehouses.log';
$myPid   = getmypid();

function whLog($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

whLog('np_sync_warehouses started (pid=' . $myPid . ')' . ($dryRun ? ' DRY-RUN' : ''));

if (!$dryRun) {
    \Database::insert('Papir', 'background_jobs', array(
        'title'    => 'НП: синхронізація відділень',
        'script'   => 'scripts/np_sync_warehouses.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

// Get any valid API key from senders
$r = \Database::fetchRow('Papir', "SELECT api FROM np_sender WHERE api IS NOT NULL AND api != '' LIMIT 1");
if (!$r['ok'] || !$r['row']) {
    whLog('ERROR: no sender API key found');
    exit(1);
}
$apiKey = $r['row']['api'];
$np = new \Papir\Crm\NovaPoshta($apiKey);

$page       = 1;
$pageSize   = 500;
$total      = 0;
$upserted   = 0;
$errors     = 0;

do {
    whLog("Fetching page {$page}...");

    $r = $np->call('Address', 'getWarehouses', array(
        'Page'  => $page,
        'Limit' => $pageSize,
    ));

    if (!$r['ok']) {
        whLog('API error on page ' . $page . ': ' . $r['error']);
        $errors++;
        break;
    }

    $batch = $r['data'];
    if (empty($batch)) {
        whLog("Page {$page} empty — done");
        break;
    }

    $total += count($batch);
    whLog("Page {$page}: got " . count($batch) . " warehouses (total so far: {$total})");

    if (!$dryRun) {
        foreach ($batch as $wh) {
            \Papir\Crm\NpReferenceRepository::upsertWarehouse($wh);
            $upserted++;
        }
    }

    $page++;

    // Pause between pages to not stress the server
    if (count($batch) >= $pageSize) {
        sleep(2);
    }

} while (count($batch) >= $pageSize);

whLog("Sync complete: total=" . $total . ", upserted=" . $upserted . ", errors=" . $errors . ($dryRun ? ' (DRY-RUN)' : ''));

if (!$dryRun) {
    \Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'");
}

whLog('np_sync_warehouses finished');