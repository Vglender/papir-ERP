<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$cityRef   = isset($_GET['city_ref'])   ? trim($_GET['city_ref'])   : '';
$q         = isset($_GET['q'])          ? trim($_GET['q'])          : '';
$senderRef = isset($_GET['sender_ref']) ? trim($_GET['sender_ref']) : '';

if (!$cityRef || strlen($q) < 2) {
    echo json_encode(array('ok' => true, 'streets' => array()));
    exit;
}

$streets = \Papir\Crm\NpReferenceRepository::searchStreets($cityRef, $q, 20);

// Fallback to API if empty
if (empty($streets) && $senderRef) {
    $sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
    if ($sender && $sender['api']) {
        $np = new \Papir\Crm\NovaPoshta($sender['api']);
        $r  = $np->call('Address', 'getStreet', array(
            'CityRef'      => $cityRef,
            'FindByString' => $q,
            'Limit'        => 50,
            'Page'         => 1,
        ));
        if ($r['ok'] && !empty($r['data'])) {
            foreach ($r['data'] as $street) {
                \Papir\Crm\NpReferenceRepository::upsertStreet($street);
            }
            $streets = \Papir\Crm\NpReferenceRepository::searchStreets($cityRef, $q, 20);
        }
    }
}

echo json_encode(array('ok' => true, 'streets' => $streets));