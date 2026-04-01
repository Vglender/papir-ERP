<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$q          = isset($_GET['q'])          ? trim($_GET['q'])          : '';
$senderRef  = isset($_GET['sender_ref']) ? trim($_GET['sender_ref']) : '';

if (strlen($q) < 2) {
    echo json_encode(array('ok' => true, 'cities' => array()));
    exit;
}

// Local search first
$cities = \Papir\Crm\NpReferenceRepository::searchCities($q, 20);

// If nothing found locally — call NP API and cache results
if (empty($cities) && $senderRef) {
    $sender = \Papir\Crm\SenderRepository::getByRef($senderRef);
    if ($sender && $sender['api']) {
        $np = new \Papir\Crm\NovaPoshta($sender['api']);
        $r  = $np->call('Address', 'getCities', array(
            'FindByString' => $q,
            'Limit'        => 20,
            'Page'         => 1,
        ));
        if ($r['ok'] && !empty($r['data'])) {
            foreach ($r['data'] as $city) {
                \Papir\Crm\NpReferenceRepository::upsertCity($city);
            }
            $cities = \Papir\Crm\NpReferenceRepository::searchCities($q, 20);
        }
    }
}

echo json_encode(array('ok' => true, 'cities' => $cities));