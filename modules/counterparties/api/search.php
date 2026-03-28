<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$query = isset($_GET['q'])    ? trim($_GET['q'])    : '';
$type  = isset($_GET['type']) ? trim($_GET['type']) : '';

$types = null;
if ($type === 'business') {
    $types = array('company', 'fop');
} elseif ($type === 'person') {
    $types = array('person');
} elseif ($type !== '') {
    $types = array($type);
}

$repo = new CounterpartyRepository();
$rows = $repo->search($query, $types, 30);

$result = array();
foreach ($rows as $row) {
    $result[] = array(
        'id'    => (int)$row['id'],
        'name'  => $row['name'],
        'type'  => $row['type'],
        'type_label' => CounterpartyRepository::typeLabel($row['type']),
        'phone' => $row['phone'],
        'email' => $row['email'],
        'okpo'  => $row['okpo'],
    );
}

echo json_encode(array('ok' => true, 'items' => $result));
