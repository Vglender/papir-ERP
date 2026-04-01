<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orgId = isset($_POST['org_id']) ? (int)$_POST['org_id'] : 0;
if ($orgId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'org_id required'));
    exit;
}

// Check org exists and is active
$r = Database::fetchRow('Papir', "SELECT id, status FROM organization WHERE id = {$orgId}");
if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Organization not found'));
    exit;
}
if (!$r['row']['status']) {
    echo json_encode(array('ok' => false, 'error' => 'Cannot set archived organization as default'));
    exit;
}

Database::query('Papir', "UPDATE organization SET is_default = 0");
Database::query('Papir', "UPDATE organization SET is_default = 1 WHERE id = {$orgId}");

echo json_encode(array('ok' => true));