<?php
/**
 * POST /counterparties/api/delete_file
 * Params: id (file id)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$fileId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$fileId) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$r = Database::fetchRow('Papir',
    "SELECT counterparty_id, stored_name FROM counterparty_files WHERE id = {$fileId} LIMIT 1");

if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Файл не знайдено'));
    exit;
}

$row  = $r['row'];
$path = '/var/www/papir/storage/cp_files/' . (int)$row['counterparty_id'] . '/' . $row['stored_name'];

Database::query('Papir', "DELETE FROM counterparty_files WHERE id = {$fileId}");

if (file_exists($path)) {
    @unlink($path);
}

echo json_encode(array('ok' => true));