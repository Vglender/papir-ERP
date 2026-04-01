<?php
/**
 * Sync TTNs from Nova Poshta API → ttn_novaposhta
 *
 * Usage:
 *   php cron/sync_ttns_from_np.php                        # last 30 days, all senders
 *   php cron/sync_ttns_from_np.php --days=90              # first full import
 *   php cron/sync_ttns_from_np.php --hours=3              # last 3 hours (hourly cron)
 *   php cron/sync_ttns_from_np.php --sender=UUID --days=7
 *   php cron/sync_ttns_from_np.php --dry-run
 *
 * Crontab (hourly, last 3h window):
 *   0 * * * * php /var/www/papir/cron/sync_ttns_from_np.php --hours=3 >> /tmp/sync_ttns_from_np.log 2>&1
 */

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

// ── CLI args ───────────────────────────────────────────────────────────────
$dryRun    = in_array('--dry-run', $argv);
$argDays   = 30;
$argHours  = null;
$argSender = null;

foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/',  $arg, $m)) $argDays   = min((int)$m[1], 365);
    if (preg_match('/^--hours=(\d+)$/', $arg, $m)) $argHours  = min((int)$m[1], 240);
    if (preg_match('/^--sender=(.+)$/', $arg, $m)) $argSender = trim($m[1]);
}

$logFile    = '/tmp/sync_ttns_from_np.log';
$myPid      = getmypid();
$rangeLabel = $argHours !== null ? $argHours . 'h' : $argDays . 'd';

function npLog($msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL; }

npLog('=== sync_ttns_from_np start (pid=' . $myPid . ', range=' . $rangeLabel
    . ($argSender ? ', sender=' . $argSender : '') . ($dryRun ? ', DRY-RUN' : '') . ') ===');

// ── Register in background_jobs ────────────────────────────────────────────
if (!$dryRun) {
    \Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синхронізація ТТН з НП API (' . $rangeLabel . ')',
        'script'   => 'cron/sync_ttns_from_np.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

// ── Senders ────────────────────────────────────────────────────────────────
if ($argSender) {
    $s = \Papir\Crm\SenderRepository::getByRef($argSender);
    $senders = $s ? array($s) : array();
} else {
    $senders = \Papir\Crm\SenderRepository::getAll();
}
if (empty($senders)) { npLog('No senders — exit'); exit(1); }

$dateRange = \Papir\Crm\NpDocumentMapper::buildDateRange(
    $argHours !== null ? null : $argDays,
    $argHours
);
npLog('Range: ' . $dateRange['DateTimeFrom'] . ' → ' . $dateRange['DateTimeTo'] . ', senders: ' . count($senders));

// ── Totals ─────────────────────────────────────────────────────────────────
$totalInserted = 0;
$totalUpdated  = 0;
$totalSkipped  = 0;
$totalErrors   = 0;

foreach ($senders as $sender) {
    $senderRef = $sender['Ref'];
    npLog('Sender: ' . $sender['Description']);

    $np       = new \Papir\Crm\NovaPoshta($sender['api']);
    $page     = 1;
    $pageSize = 500;
    $sIns = 0; $sUpd = 0;

    do {
        $r = $np->call('InternetDocument', 'getDocumentList', array(
            'DateTimeFrom' => $dateRange['DateTimeFrom'],
            'DateTimeTo'   => $dateRange['DateTimeTo'],
            'Page'         => $page,
            'Limit'        => $pageSize,
        ));

        if (!$r['ok']) {
            npLog('  ERROR page ' . $page . ': ' . $r['error']);
            $totalErrors++;
            break;
        }

        $docs = $r['data'];
        if (empty($docs)) { npLog('  Page ' . $page . ': empty, done'); break; }
        npLog('  Page ' . $page . ': ' . count($docs) . ' docs');

        // Batch-check existing
        $pageRefs = array();
        foreach ($docs as $doc) {
            if (!empty($doc['Ref'])) $pageRefs[] = $doc['Ref'];
        }
        $existingMap = array();
        if (!empty($pageRefs)) {
            $inList  = implode("','", array_map(function ($r) {
                return \Database::escape('Papir', $r);
            }, $pageRefs));
            $rExist = \Database::fetchAll('Papir',
                "SELECT id, ref, customerorder_id, demand_id, scan_sheet_ref, deletion_mark
                 FROM ttn_novaposhta WHERE ref IN ('{$inList}')");
            if ($rExist['ok']) {
                foreach ($rExist['rows'] as $row) $existingMap[$row['ref']] = $row;
            }
        }

        $pIns = 0; $pUpd = 0;
        foreach ($docs as $doc) {
            $mapped = \Papir\Crm\NpDocumentMapper::map($doc, $senderRef);
            if (!$mapped) { $totalSkipped++; continue; }
            $npRef = $mapped['ref'];

            if (isset($existingMap[$npRef])) {
                $existing = $existingMap[$npRef];
                // If NP says deleted, mark in DB too
                if ($mapped['deletion_mark'] && !$existing['deletion_mark']) {
                    if (!$dryRun) \Database::update('Papir', 'ttn_novaposhta',
                        array('deletion_mark' => 1, 'updated_at' => date('Y-m-d H:i:s')),
                        array('id' => (int)$existing['id']));
                    $totalSkipped++; continue;
                }
                if ($existing['deletion_mark']) { $totalSkipped++; continue; }
                if (!$dryRun) {
                    $upd = \Papir\Crm\NpDocumentMapper::updateFields($mapped, $existing['scan_sheet_ref']);
                    \Database::update('Papir', 'ttn_novaposhta', $upd, array('id' => (int)$existing['id']));
                }
                $pUpd++;
            } else {
                if (!$dryRun) \Database::insert('Papir', 'ttn_novaposhta', $mapped);
                $pIns++;
            }
        }

        npLog('    inserted=' . $pIns . ' updated=' . $pUpd);
        $sIns += $pIns; $sUpd += $pUpd;
        $page++;
        if (count($docs) < $pageSize) break;

    } while (true);

    npLog('  Sender total: inserted=' . $sIns . ' updated=' . $sUpd);
    $totalInserted += $sIns;
    $totalUpdated  += $sUpd;
}

npLog('=== DONE: inserted=' . $totalInserted . ' updated=' . $totalUpdated
    . ' skipped=' . $totalSkipped . ' errors=' . $totalErrors . ' ===');

if (!$dryRun) {
    \Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid=" . (int)$myPid . " AND status='running'");
}