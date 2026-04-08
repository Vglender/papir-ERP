<?php
/**
 * Cron: auto_courier_call.php
 * Creates a courier call for today via NP API for the default sender.
 * Skips if a call for today already exists.
 *
 * Crontab: 0 9 * * 1-5 php /var/www/papir/cron/auto_courier_call.php >> /var/log/papir/auto_courier_call.log 2>&1
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

$logFile = '/var/log/papir/auto_courier_call.log';
$myPid   = getmypid();
$today   = date('d.m.Y');
$todaySql = date('Y-m-d');

echo '[' . date('Y-m-d H:i:s') . '] auto_courier_call started (pid=' . $myPid . ')' . PHP_EOL;

\Database::insert('Papir', 'background_jobs', array(
    'title'    => 'Автовиклик кур\'єра',
    'script'   => 'cron/auto_courier_call.php',
    'log_file' => $logFile,
    'pid'      => $myPid,
    'status'   => 'running',
));

$timeIntervalMap = array(
    'CityPickingTimeInterval1'  => array('08:00', '09:00'),
    'CityPickingTimeInterval2'  => array('09:00', '10:00'),
    'CityPickingTimeInterval3'  => array('10:00', '12:00'),
    'CityPickingTimeInterval4'  => array('12:00', '14:00'),
    'CityPickingTimeInterval5'  => array('13:00', '14:00'),
    'CityPickingTimeInterval6'  => array('14:00', '16:00'),
    'CityPickingTimeInterval7'  => array('16:00', '18:00'),
    'CityPickingTimeInterval8'  => array('18:00', '19:00'),
    'CityPickingTimeInterval9'  => array('19:00', '20:00'),
    'CityPickingTimeInterval10' => array('20:00', '21:00'),
);

// Get default sender
$sender = \Papir\Crm\SenderRepository::getDefault();

if (!$sender || !$sender['api']) {
    echo '[' . date('Y-m-d H:i:s') . '] No default sender with API key found, exiting' . PHP_EOL;
    goto finish;
}

$senderRef   = $sender['Ref'];
$senderDesc  = $sender['Description'];
$contactRef  = $sender['contact_ref'];
$interval    = $sender['courier_call_interval'] ?: 'CityPickingTimeInterval7';
$weight      = $sender['courier_call_planned_weight'] ?: 300;

if (!$contactRef) {
    echo '[' . date('Y-m-d H:i:s') . '] Default sender ' . $senderDesc . ' has no contact_ref, exiting' . PHP_EOL;
    goto finish;
}

$address = \Papir\Crm\SenderRepository::getDefaultAddress($senderRef);
if (!$address) {
    echo '[' . date('Y-m-d H:i:s') . '] Default sender ' . $senderDesc . ' has no default address, exiting' . PHP_EOL;
    goto finish;
}

// Check if a call for today already exists
$es = \Database::escape('Papir', $senderRef);
$rExist = \Database::fetchRow('Papir',
    "SELECT id, Barcode FROM np_courier_calls
     WHERE sender_ref = '{$es}'
       AND preferred_delivery_date = '{$today}'
       AND status != 'cancelled'
     LIMIT 1");

if ($rExist['ok'] && $rExist['row']) {
    echo '[' . date('Y-m-d H:i:s') . '] SKIP: call already exists (Barcode=' . $rExist['row']['Barcode'] . ')' . PHP_EOL;
    goto finish;
}

// Create courier call via NP API
$np = new \Papir\Crm\NovaPoshta($sender['api']);

$r = $np->call('CarCallGeneral', 'saveCourierCall', array(
    'ContactSenderRef'      => $contactRef,
    'PreferredDeliveryDate' => $today,
    'PlanedWeight'          => (string)$weight,
    'TimeInterval'          => $interval,
    'CounterpartySender'    => $sender['Counterparty'] ?: $senderRef,
    'AddressSenderRef'      => $address['Ref'],
));

if (!$r['ok'] || empty($r['data'][0]['Barcode'])) {
    $err = $r['error'] ?: 'NP API error';
    echo '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $err . PHP_EOL;
    goto finish;
}

$barcode = $r['data'][0]['Barcode'];

$times = isset($timeIntervalMap[$interval]) ? $timeIntervalMap[$interval] : array(null, null);

\Papir\Crm\CourierCallRepository::upsert(array(
    'Barcode'                 => $barcode,
    'sender_ref'              => $senderRef,
    'counterparty_sender_ref' => $sender['Counterparty'] ?: null,
    'contact_sender_ref'      => $contactRef,
    'address_sender_ref'      => $address['Ref'],
    'preferred_delivery_date' => $today,
    'time_interval'           => $interval,
    'time_interval_start'     => $times[0],
    'time_interval_end'       => $times[1],
    'planned_weight'          => (float)$weight,
    'status'                  => 'pending',
));

echo '[' . date('Y-m-d H:i:s') . '] CREATED ' . $senderDesc . ': Barcode=' . $barcode . ', interval=' . $interval . PHP_EOL;

finish:
\Database::query('Papir',
    "UPDATE background_jobs SET status='done', finished_at=NOW()
     WHERE pid={$myPid} AND status='running'"
);

echo '[' . date('Y-m-d H:i:s') . '] auto_courier_call finished' . PHP_EOL;