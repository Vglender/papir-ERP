<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required')); exit;
}
if (!\Papir\Crm\AuthService::can('backlog', 'delete')) {
    echo json_encode(array('ok' => false, 'error' => 'Access denied')); exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required')); exit;
}

$r = Database::query('Papir', "DELETE FROM backlog WHERE id = {$id}");

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error')); exit;
}

require_once __DIR__ . '/../backlog_export.php';
backlog_export_md();

echo json_encode(array('ok' => true));