<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

$result = Database::fetchAll('Papir',
    "SELECT manufacturer_id, name FROM manufacturers ORDER BY name ASC"
);

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true, 'manufacturers' => $result['rows']));
