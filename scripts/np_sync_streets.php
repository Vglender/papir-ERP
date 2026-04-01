<?php
/**
 * np_sync_streets.php
 * Monthly sync of NP streets from NP API → street_np table.
 * Iterates over all cities in novaposhta_cities and fetches streets per city.
 *
 * Run: php /var/www/papir/scripts/np_sync_streets.php [--dry-run] [--offset=0]
 * Cron: 0 6 1 * * php /var/www/papir/scripts/np_sync_streets.php >> /tmp/np_sync_streets.log 2>&1
 *
 * NOTE: starts 3 hours after np_sync_warehouses.php to avoid overlapping load.
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

$dryRun = in_array('--dry-run', $argv);

// Support --offset=N to resume from a specific city offset (after interruption)
$startOffset = 0;
foreach ($argv as $arg) {
    if (strpos($arg, '--offset=') === 0) {
        $startOffset = (int)substr($arg, 9);
    }
}

$logFile = '/tmp/np_sync_streets.log';
$myPid   = getmypid();

function stLog($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

stLog('np_sync_streets started (pid=' . $myPid . ')' . ($dryRun ? ' DRY-RUN' : '') . ($startOffset ? " offset={$startOffset}" : ''));

if (!$dryRun) {
    \Database::insert('Papir', 'background_jobs', array(
        'title'    => 'НП: синхронізація вулиць',
        'script'   => 'scripts/np_sync_streets.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

// Get any valid API key
$r = \Database::fetchRow('Papir', "SELECT api FROM np_sender WHERE api IS NOT NULL AND api != '' LIMIT 1");
if (!$r['ok'] || !$r['row']) {
    stLog('ERROR: no sender API key found');
    exit(1);
}
$apiKey = $r['row']['api'];
$np = new \Papir\Crm\NovaPoshta($apiKey);

// Load all cities
$rCities = \Database::fetchAll('Papir',
    "SELECT Ref, Description FROM novaposhta_cities ORDER BY Ref");
if (!$rCities['ok']) {
    stLog('ERROR: cannot load cities: ' . $rCities['error']);
    exit(1);
}
$cities    = $rCities['rows'];
$cityTotal = count($cities);
stLog("Cities to process: {$cityTotal}" . ($startOffset ? " (resuming from offset {$startOffset})" : ''));

$cityIdx      = 0;
$totalStreets = 0;
$upserted     = 0;
$skipped      = 0; // cities with no streets
$errors       = 0;
$pauseEvery   = 10; // sleep after every N cities

foreach ($cities as $city) {
    $cityIdx++;

    if ($cityIdx <= $startOffset) {
        continue;
    }

    $cityRef  = $city['Ref'];
    $cityDesc = $city['Description'];

    $r = $np->call('Address', 'getStreets', array(
        'CityRef'      => $cityRef,
        'FindByString' => '',
        'Page'         => 1,
        'Limit'        => 500,
    ));

    if (!$r['ok']) {
        stLog("[{$cityIdx}/{$cityTotal}] ERROR for {$cityDesc}: " . $r['error']);
        $errors++;
        sleep(3); // extra pause on error
        continue;
    }

    $streets = $r['data'];

    if (empty($streets)) {
        $skipped++;
    } else {
        $totalStreets += count($streets);
        if (!$dryRun) {
            foreach ($streets as $street) {
                if (!isset($street['Ref']) || !$street['Ref']) continue;
                \Papir\Crm\NpReferenceRepository::upsertStreet($street);
                $upserted++;
            }
        }
    }

    // Progress log every 500 cities
    if ($cityIdx % 500 === 0) {
        stLog("[{$cityIdx}/{$cityTotal}] Progress: streets_found={$totalStreets}, upserted={$upserted}, errors={$errors}");
    }

    // Pause every N cities to avoid server overload
    if ($cityIdx % $pauseEvery === 0) {
        sleep(1);
    }
}

stLog("Sync complete: cities={$cityIdx}, streets_found={$totalStreets}, upserted={$upserted}, skipped={$skipped}, errors={$errors}" . ($dryRun ? ' (DRY-RUN)' : ''));

if (!$dryRun) {
    \Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'");
}

stLog('np_sync_streets finished');