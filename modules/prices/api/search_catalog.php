<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode(array('ok' => true, 'rows' => array()));
    exit;
}

$repo = new PricelistItemRepository();
$rows = $repo->searchCatalog($q, 20);

echo json_encode(array('ok' => true, 'rows' => $rows));
