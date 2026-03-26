<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';

$search  = isset($_GET['search'])   ? trim($_GET['search'])   : '';
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
$page    = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$r = AttributeRepository::getList($search, $groupId, $perPage, $offset);

echo json_encode(array(
    'ok'       => $r['ok'],
    'rows'     => $r['rows'],
    'total'    => $r['total'],
    'page'     => $page,
    'per_page' => $perPage,
    'pages'    => $r['total'] > 0 ? (int)ceil($r['total'] / $perPage) : 1,
));
