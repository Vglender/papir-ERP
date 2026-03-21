<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$strategies = isset($_POST['strategy']) ? $_POST['strategy'] : array();

if (!is_array($strategies)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid data'));
    exit;
}

$updated = 0;

foreach ($strategies as $id => $fields) {
    $id = (int)$id;
    if ($id <= 0) {
        continue;
    }

    $name   = isset($fields['name'])   ? trim($fields['name'])   : '';
    $small  = isset($fields['small'])  ? trim($fields['small'])  : '';
    $medium = isset($fields['medium']) ? trim($fields['medium']) : '';
    $large  = isset($fields['large'])  ? trim($fields['large'])  : '';

    if (!is_numeric($small) || !is_numeric($medium) || !is_numeric($large)) {
        echo json_encode(array('ok' => false, 'error' => 'Non-numeric percent for strategy id=' . $id));
        exit;
    }

    $data = array(
        'name'                    => $name,
        'small_discount_percent'  => (float)$small,
        'medium_discount_percent' => (float)$medium,
        'large_discount_percent'  => (float)$large,
    );

    $ok = Database::update('Papir', 'price_discount_strategy', $data, array('id' => $id));
    if ($ok === false) {
        echo json_encode(array('ok' => false, 'error' => 'DB error on strategy id=' . $id));
        exit;
    }

    $updated++;

    if ($updated >= 10) {
        break;
    }
}

echo json_encode(array('ok' => true, 'updated' => $updated));
