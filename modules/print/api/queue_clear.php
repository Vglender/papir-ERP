<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

Database::query('Papir',
    "UPDATE print_pack_jobs SET queued = 0, printed_at = NOW() WHERE queued = 1");

echo json_encode(array('ok' => true));
