<?php
/**
 * np_init_data.php
 * One-time (and repeatable) initialization script for Nova Poshta data:
 *  1. Refresh sender info (Counterparty) from NP API for each sender
 *  2. Load sender addresses (warehouses/branches) from NP API
 *  3. Load sender contact persons from NP API
 *  4. Sync scan sheets (registries) from NP API for each sender
 *  5. Update TTN statuses from NP API (batch, non-final only)
 *
 * Run: php /var/www/papir/scripts/np_init_data.php [--dry-run] [--skip-ttns] [--skip-scansheats]
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../src/ViewHelper.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

$dryRun      = in_array('--dry-run',        $argv);
$skipTtns    = in_array('--skip-ttns',      $argv);
$skipSheets  = in_array('--skip-scansheats',$argv);
$logFile     = '/tmp/np_init_data.log';
$myPid       = getmypid();

function npLog($msg) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

npLog('np_init_data started (pid=' . $myPid . ')' . ($dryRun ? ' DRY-RUN' : ''));

// Register in background_jobs
if (!$dryRun) {
    \Database::insert('Papir', 'background_jobs', array(
        'title'    => 'НП: ініціалізація даних',
        'script'   => 'scripts/np_init_data.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

$senders = \Papir\Crm\SenderRepository::getAll();
npLog('Found ' . count($senders) . ' senders');

// ─────────────────────────────────────────────────────────────────────────────
// Step 1 & 2 & 3: For each sender — refresh counterparty info, addresses, contact persons
// ─────────────────────────────────────────────────────────────────────────────
foreach ($senders as $sender) {
    $sRef   = $sender['Ref'];
    $sDesc  = $sender['Description'];
    $apiKey = $sender['api'];

    if (!$apiKey) {
        npLog("[$sDesc] SKIP — no API key");
        continue;
    }

    npLog("[$sDesc] Processing sender...");
    $np = new \Papir\Crm\NovaPoshta($apiKey);

    // ── Step 1: Refresh sender (Counterparty) info ──────────────────────────
    npLog("[$sDesc] Fetching Counterparty info...");
    $r = $np->call('Counterparty', 'getCounterparties', array(
        'CounterpartyProperty' => 'Sender',
        'Page' => 1,
    ));
    if ($r['ok'] && !empty($r['data'])) {
        foreach ($r['data'] as $cp) {
            if (isset($cp['Ref']) && $cp['Ref'] === $sRef) {
                if (!$dryRun) {
                    $upd = array();
                    if (!empty($cp['Description']))          $upd['Description']          = $cp['Description'];
                    if (!empty($cp['FirstName']))            $upd['FirstName']             = $cp['FirstName'];
                    if (!empty($cp['LastName']))             $upd['LastName']              = $cp['LastName'];
                    if (!empty($cp['MiddleName']))           $upd['MiddleName']            = $cp['MiddleName'];
                    if (!empty($cp['CounterpartyFullName'])) $upd['CounterpartyFullName']  = $cp['CounterpartyFullName'];
                    if (!empty($cp['EDRPOU']))               $upd['EDRPOU']                = $cp['EDRPOU'];
                    if (!empty($cp['CounterpartyType']))     $upd['CounterpartyType']      = $cp['CounterpartyType'];
                    if (!empty($cp['City']))                 $upd['City']                  = $cp['City'];
                    if (!empty($upd)) {
                        \Database::update('Papir', 'np_sender', $upd, array('Ref' => $sRef));
                    }
                }
                npLog("[$sDesc] Counterparty info updated");
                break;
            }
        }
    } else {
        npLog("[$sDesc] Counterparty fetch error: " . $r['error']);
    }

    // ── Step 2: Load sender addresses ───────────────────────────────────────
    npLog("[$sDesc] Fetching sender addresses...");
    $ra = $np->call('Counterparty', 'getCounterpartyAddresses', array(
        'Ref'          => $sRef,
        'ContragenType'=> 'Sender',
    ));
    if ($ra['ok'] && !empty($ra['data'])) {
        $addrCount = 0;
        foreach ($ra['data'] as $addr) {
            if (!$dryRun) {
                \Papir\Crm\SenderRepository::upsertAddress($sRef, $addr);
                $addrCount++;
            }
        }
        // Mark first as default if none is default
        if (!$dryRun && $addrCount > 0) {
            $existing = \Papir\Crm\SenderRepository::getDefaultAddress($sRef);
            if (!$existing && !empty($ra['data'][0]['Ref'])) {
                \Papir\Crm\SenderRepository::setDefaultAddress($sRef, $ra['data'][0]['Ref']);
            }
        }
        npLog("[$sDesc] Addresses: " . count($ra['data']) . " loaded");
    } else {
        npLog("[$sDesc] Address fetch error: " . $ra['error']);
    }

    // ── Step 3: Load contact persons ─────────────────────────────────────────
    npLog("[$sDesc] Fetching contact persons...");
    $rcp = $np->call('Counterparty', 'getCounterpartyContactPersons', array(
        'Ref' => $sRef,
    ));
    if ($rcp['ok'] && !empty($rcp['data'])) {
        $cpCount = 0;
        foreach ($rcp['data'] as $cp) {
            if (!$dryRun && !empty($cp['Ref'])) {
                $cpRef      = $cp['Ref'];
                $cpFullName = isset($cp['Description']) ? $cp['Description'] : '';
                $cpPhone    = isset($cp['Phones'])      ? $cp['Phones']      : '';
                // Upsert into np_sender_contact_persons if table exists, else just log
                $tableCheck = \Database::fetchRow('Papir',
                    "SELECT COUNT(*) AS cnt FROM information_schema.tables
                     WHERE table_schema='Papir' AND table_name='np_sender_contact_persons'");
                if ($tableCheck['ok'] && $tableCheck['row']['cnt'] > 0) {
                    \Database::upsertOne('Papir', 'np_sender_contact_persons', array(
                        'Ref'        => $cpRef,
                        'sender_ref' => $sRef,
                        'full_name'  => $cpFullName,
                        'phone'      => $cpPhone,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ), array('Ref'));
                }
                $cpCount++;
            }
        }
        npLog("[$sDesc] Contact persons: " . count($rcp['data']) . " found" . ($dryRun ? ' (dry-run, not saved)' : ' saved'));
    } else {
        npLog("[$sDesc] Contact persons fetch: " . ($rcp['error'] ?: 'empty'));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 4: Sync scan sheets for each sender
// ─────────────────────────────────────────────────────────────────────────────
if (!$skipSheets) {
    npLog('--- Step 4: Sync scan sheets ---');
    foreach ($senders as $sender) {
        $sRef   = $sender['Ref'];
        $sDesc  = $sender['Description'];
        $apiKey = $sender['api'];

        if (!$apiKey) continue;

        npLog("[$sDesc] Syncing scan sheets...");
        if (!$dryRun) {
            $result = \Papir\Crm\ScanSheetService::syncList($sRef);
            if ($result['ok']) {
                npLog("[$sDesc] Scan sheets synced: " . $result['count'] . " registries");
            } else {
                npLog("[$sDesc] Scan sheet sync error: " . $result['error']);
            }
        } else {
            npLog("[$sDesc] [DRY-RUN] Would sync scan sheets");
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 5: Update TTN statuses (batch, up to 500 per sender)
// ─────────────────────────────────────────────────────────────────────────────
if (!$skipTtns) {
    npLog('--- Step 5: Track TTN statuses ---');
    $batchSize = 100;
    $totalUpdated = 0;
    $passes = 0;
    $maxPasses = 700; // safety: max 70000 TTNs per run (full initial sync)

    do {
        $ttns = \Papir\Crm\TtnRepository::getForTracking($batchSize);
        if (empty($ttns)) break;

        npLog("Tracking batch of " . count($ttns) . " TTNs (pass " . ($passes + 1) . ")...");

        if (!$dryRun) {
            $result = \Papir\Crm\TrackingService::trackBatch($ttns);
            $totalUpdated += $result['updated'];
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    npLog("[ERROR] " . $err);
                }
            }
            npLog("Batch done: updated=" . $result['updated']);
        } else {
            npLog("[DRY-RUN] Would track " . count($ttns) . " TTNs");
            break;
        }

        $passes++;
        // Small pause to respect NP rate limits
        if ($passes < $maxPasses && count($ttns) === $batchSize) {
            sleep(1);
        }
    } while (count($ttns) === $batchSize && $passes < $maxPasses);

    npLog("TTN tracking complete: total updated=" . $totalUpdated . ", passes=" . $passes);
}

// ─────────────────────────────────────────────────────────────────────────────
// Done
// ─────────────────────────────────────────────────────────────────────────────
if (!$dryRun) {
    \Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'");
}

npLog('np_init_data finished');
