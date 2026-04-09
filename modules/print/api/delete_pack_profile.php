<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$r = Database::query('Papir', "DELETE FROM print_pack_profiles WHERE id = {$id}");
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Delete failed'));
    exit;
}

echo json_encode(array('ok' => true));
