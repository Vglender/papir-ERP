<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if (!\Papir\Crm\AuthService::can('backlog', 'read')) {
    echo json_encode(array('ok' => false, 'error' => 'Access denied')); exit;
}

$module = isset($_GET['module']) ? trim($_GET['module']) : '';
$type   = isset($_GET['type'])   ? trim($_GET['type'])   : '';
$done   = isset($_GET['done'])   ? (bool)$_GET['done']   : false;

$where = $done ? 'resolved_at IS NOT NULL' : 'resolved_at IS NULL';
if ($module !== '') $where .= " AND module = '" . Database::escape('Papir', $module) . "'";
if ($type   !== '') $where .= " AND type = '"   . Database::escape('Papir', $type)   . "'";

$r = Database::fetchAll('Papir',
    "SELECT id, module, type, text, created_at, resolved_at
     FROM backlog WHERE {$where} ORDER BY module, type, id"
);

echo json_encode(array('ok' => $r['ok'], 'items' => $r['ok'] ? $r['rows'] : array()));
