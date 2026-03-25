<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';


$search  = isset($_GET['search'])   ? trim($_GET['search'])   : '';
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

$r = AttributeRepository::getList($search, $groupId);
echo json_encode(array('ok' => $r['ok'], 'rows' => $r['ok'] ? $r['rows'] : array()));
