<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$packIds = isset($_POST['pack_ids']) ? trim($_POST['pack_ids']) : '';
if (empty($packIds)) {
    echo json_encode(array('ok' => false, 'error' => 'pack_ids required'));
    exit;
}

$ids = array_filter(array_map('intval', explode(',', $packIds)));
if (empty($ids)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid IDs'));
    exit;
}

$idList = implode(',', $ids);
Database::query('Papir',
    "UPDATE print_pack_jobs SET queued = 0, printed_at = NOW() WHERE id IN ({$idList})");

echo json_encode(array('ok' => true, 'removed' => count($ids)));
