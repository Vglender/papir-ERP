<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$cityRef   = isset($_GET['city_ref'])   ? trim($_GET['city_ref'])   : '';
$q         = isset($_GET['q'])          ? trim($_GET['q'])          : '';
$senderRef = isset($_GET['sender_ref']) ? trim($_GET['sender_ref']) : '';

if (!$cityRef) {
    echo json_encode(array('ok' => false, 'error' => 'city_ref required'));
    exit;
}

// Local search first
$warehouses = \Papir\Crm\NpReferenceRepository::searchWarehouses($cityRef, $q, 30);

// If empty — fetch from NP API with FindByString for targeted search
if (empty($warehouses) && $senderRef) {
    $sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
    if ($sender && $sender['api']) {
        $np = new \Papir\Crm\NovaPoshta($sender['api']);
        $apiParams = array(
            'CityRef'  => $cityRef,
            'Language' => 'uk',
            'Limit'    => 500,
            'Page'     => 1,
        );
        if ($q !== '') {
            $apiParams['FindByString'] = $q;
        }
        $r = $np->call('Address', 'getWarehouses', $apiParams);
        if ($r['ok'] && !empty($r['data'])) {
            foreach ($r['data'] as $wh) {
                \Papir\Crm\NpReferenceRepository::upsertWarehouse($wh);
            }
            $warehouses = \Papir\Crm\NpReferenceRepository::searchWarehouses($cityRef, $q, 30);
        }
    }
}

echo json_encode(array('ok' => true, 'warehouses' => $warehouses));