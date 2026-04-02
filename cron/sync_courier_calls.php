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
            "SELECT id FROM np_courier_calls WHERE Barcode = '{$eb}' LIMIT 1");

        if ($rCall['ok'] && $rCall['row']) {
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

if (!$dryRun) {
    \Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}

echo '[' . date('Y-m-d H:i:s') . '] sync_courier_calls finished' . PHP_EOL;
