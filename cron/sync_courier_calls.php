<?php
/**
 * Cron: sync_courier_calls.php
 * Links TTNs to courier calls via the car_call field (populated by sync_ttns_from_np).
 * Auto-creates courier call records from barcodes if not yet in DB.
 *
 * Run after sync_ttns_from_np.php.
 * Crontab: 10 * * * * php /var/www/papir/cron/sync_courier_calls.php >> /tmp/sync_courier_calls.log 2>&1
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/sync_courier_calls.log';
$myPid   = getmypid();

echo '[' . date('Y-m-d H:i:s') . '] sync_courier_calls started (pid=' . $myPid . ')' . PHP_EOL;

if (!$dryRun) {
    \Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синхронізація викликів кур\'єра',
        'script'   => 'cron/sync_courier_calls.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

// Find all TTNs with car_call field set
$rTtns = \Database::fetchAll('Papir',
    "SELECT t.id, t.int_doc_number, t.car_call, t.weight, t.sender_ref
     FROM ttn_novaposhta t
     WHERE t.car_call IS NOT NULL AND t.car_call != ''
       AND t.deletion_mark = 0");

if (!$rTtns['ok'] || empty($rTtns['rows'])) {
    echo '[' . date('Y-m-d H:i:s') . '] No TTNs with car_call, exiting' . PHP_EOL;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] Found ' . count($rTtns['rows']) . ' TTNs with car_call' . PHP_EOL;

    $linked  = 0;
    $created = 0;

    foreach ($rTtns['rows'] as $ttn) {
        $barcode   = $ttn['car_call'];
        $senderRef = $ttn['sender_ref'];
        $eb        = \Database::escape('Papir', $barcode);

        // Find or auto-create courier call record
        $rCall = \Database::fetchRow('Papir',
            "SELECT id, status FROM np_courier_calls WHERE Barcode = '{$eb}' LIMIT 1");

        if ($rCall['ok'] && $rCall['row']) {
            if ($rCall['row']['status'] === 'done' || $rCall['row']['status'] === 'cancelled') continue;
            $callId = (int)$rCall['row']['id'];
        } else {
            if ($dryRun) {
                echo '[DRY-RUN] Would create courier call ' . $barcode . PHP_EOL;
                $callId = 0;
            } else {
                $ins = \Database::insert('Papir', 'np_courier_calls', array(
                    'Barcode'    => $barcode,
                    'sender_ref' => $senderRef,
                    'status'     => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                ));
                if (!$ins['ok']) continue;
                $callId = (int)$ins['id'];
                $created++;
                echo '[' . date('Y-m-d H:i:s') . '] Auto-created courier call ' . $barcode . ' (id=' . $callId . ')' . PHP_EOL;
            }
        }

        if ($callId) {
            if ($dryRun) {
                echo '[DRY-RUN] Would link TTN ' . $ttn['int_doc_number'] . ' → call ' . $barcode . PHP_EOL;
            } else {
                \Papir\Crm\CourierCallRepository::upsertTtn(
                    $callId,
                    $ttn['int_doc_number'],
                    (int)$ttn['id'],
                    $ttn['weight']
                );
            }
            $linked++;
        }
    }

    echo '[' . date('Y-m-d H:i:s') . '] Created: ' . $created . ', Linked TTNs: ' . $linked . PHP_EOL;
}

// ── Sync courier call statuses from NP API ──────────────────────────────────
// Update local statuses and clean up TTN links for done/cancelled calls.

$npStatusMap = array('Done' => 'done', 'Cancelled' => 'cancelled', 'Rejection' => 'cancelled');
$rPending = \Database::fetchAll('Papir',
    "SELECT cc.id, cc.Barcode, cc.sender_ref FROM np_courier_calls cc WHERE cc.status = 'pending'");

if ($rPending['ok'] && !empty($rPending['rows'])) {
    // Group by sender to minimize API calls
    $bySender = array();
    foreach ($rPending['rows'] as $row) {
        $bySender[$row['sender_ref']][] = $row;
    }

    $statusUpdated = 0;

    foreach ($bySender as $sr => $calls) {
        $sender = \Papir\Crm\SenderRepository::getByRef($sr);
        if (!$sender || !$sender['api']) continue;

        $np = new \Papir\Crm\NovaPoshta($sender['api']);
        $r = $np->call('CarCallGeneral', 'getOrdersListCourierCall', array(
            'DateFrom' => date('d.m.Y', strtotime('-30 days')),
            'DateTo'   => date('d.m.Y'),
        ));
        if (!$r['ok']) continue;

        // Build NP status map by Number
        $npCalls = array();
        foreach ($r['data'] as $c) {
            if (is_array($c) && isset($c['Number'])) {
                $npCalls[$c['Number']] = $c['Status'];
            }
        }

        foreach ($calls as $call) {
            $npStatus = isset($npCalls[$call['Barcode']]) ? $npCalls[$call['Barcode']] : null;
            if (!$npStatus) continue;

            $localStatus = isset($npStatusMap[$npStatus]) ? $npStatusMap[$npStatus] : null;
            if (!$localStatus) continue; // still active

            if (!$dryRun) {
                $callId = (int)$call['id'];
                \Database::query('Papir',
                    "UPDATE np_courier_calls SET status = '{$localStatus}', updated_at = NOW() WHERE id = {$callId}");
                $statusUpdated++;
            }
            echo '[' . date('Y-m-d H:i:s') . '] Call ' . $call['Barcode'] . ' → ' . $localStatus . PHP_EOL;
        }
    }

    echo '[' . date('Y-m-d H:i:s') . '] Status sync: updated=' . $statusUpdated . PHP_EOL;
}

if (!$dryRun) {
    \Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}

echo '[' . date('Y-m-d H:i:s') . '] sync_courier_calls finished' . PHP_EOL;
