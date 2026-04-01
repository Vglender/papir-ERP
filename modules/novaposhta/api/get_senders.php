<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$senders = \Papir\Crm\SenderRepository::getAll();

// If sender_ref requested — also return addresses
$senderRef = isset($_GET['sender_ref']) ? trim($_GET['sender_ref']) : '';
$addresses = array();
if ($senderRef) {
    $addresses = \Papir\Crm\SenderRepository::getAddresses($senderRef);

    // If no local addresses — fetch from NP API and cache
    if (empty($addresses)) {
        $sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
        if ($sender && $sender['api']) {
            $np = new \Papir\Crm\NovaPoshta($sender['api']);
            $r  = $np->call('Counterparty', 'getCounterpartyAddresses', array(
                'Ref'                  => $senderRef,
                'CounterpartyProperty' => 'Sender',
            ));
            if ($r['ok']) {
                foreach ($r['data'] as $addr) {
                    \Papir\Crm\SenderRepository::upsertAddress($senderRef, $addr);
                }
                $addresses = \Papir\Crm\SenderRepository::getAddresses($senderRef);
            }
        }
    }
}

echo json_encode(array('ok' => true, 'senders' => $senders, 'addresses' => $addresses));