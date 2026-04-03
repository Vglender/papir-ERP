<?php
/**
 * One-time: fetch delivery_method + payment_method from МС attributes
 * and update customerorder records that have id_ms but no delivery/payment method set.
 *
 * Run: nohup php scripts/update_delivery_payment_from_ms.php > /tmp/update_delivery_payment.log 2>&1 &
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/moysklad/moysklad_api.php';
require_once __DIR__ . '/../modules/customerorder/MsAttributesParser.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/update_delivery_payment.log';
$myPid   = getmypid();

function uplog($msg) { echo date('H:i:s') . ' ' . $msg . PHP_EOL; }

uplog('START pid=' . $myPid . ($dryRun ? ' DRY-RUN' : ''));

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Оновлення способу доставки/оплати з МС',
        'script'   => 'scripts/update_delivery_payment_from_ms.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

$ms     = new MoySkladApi();
$parser = new MsAttributesParser();

// Get orders that have id_ms but missing delivery or payment method
$rOrders = Database::fetchAll('Papir',
    "SELECT id, id_ms FROM customerorder
     WHERE id_ms IS NOT NULL AND deleted_at IS NULL
       AND (delivery_method_id IS NULL OR payment_method_id IS NULL)
     ORDER BY id DESC");

if (!$rOrders['ok']) { uplog('DB error'); exit(1); }

$total    = count($rOrders['rows']);
$updated  = 0;
$skipped  = 0;
$errors   = 0;

uplog("Found {$total} orders to process");

foreach ($rOrders['rows'] as $row) {
    $orderId = (int)$row['id'];
    $msId    = $row['id_ms'];

    $url    = $ms->getEntityBaseUrl() . 'customerorder/' . $msId . '?expand=attributes';
    $docRaw = $ms->query($url);
    $doc    = json_decode(json_encode($docRaw), true);

    if (empty($doc) || !empty($doc['errors'])) {
        uplog("SKIP id={$orderId} ms={$msId} — fetch error");
        $errors++;
        continue;
    }

    $attrs  = (!empty($doc['attributes']['rows'])) ? $doc['attributes']['rows'] : array();
    $parsed = $parser->parse($attrs);

    if ($parsed['delivery_method_id'] === null && $parsed['payment_method_id'] === null) {
        $skipped++;
        continue;
    }

    $upd = array();
    if ($parsed['delivery_method_id'] !== null && !$row['delivery_method_id']) {
        $upd['delivery_method_id'] = $parsed['delivery_method_id'];
    }
    if ($parsed['payment_method_id'] !== null && !$row['payment_method_id']) {
        $upd['payment_method_id'] = $parsed['payment_method_id'];
    }

    if (empty($upd)) { $skipped++; continue; }

    if (!$dryRun) {
        Database::update('Papir', 'customerorder', $upd, array('id' => $orderId));
    }

    uplog("id={$orderId} ms={$msId} → " . json_encode($upd));
    $updated++;
}

uplog("DONE: total={$total} updated={$updated} skipped={$skipped} errors={$errors}");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'");
}
