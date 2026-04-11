<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../ukrposhta_bootstrap.php';
require_once __DIR__ . '/../../auth/AuthService.php';

if (!\Papir\Crm\AuthService::getCurrentUser()) {
    echo json_encode(array('ok' => false, 'error' => 'auth')); exit;
}

$filters = array(
    'search'               => isset($_GET['search'])       ? trim($_GET['search'])       : '',
    'sender_connection_id' => isset($_GET['connection_id'])? (int)$_GET['connection_id'] : 0,
    'status'               => isset($_GET['status'])       ? trim($_GET['status'])       : '',
    'date_from'            => isset($_GET['date_from'])    ? trim($_GET['date_from'])    : '',
    'date_to'              => isset($_GET['date_to'])      ? trim($_GET['date_to'])      : '',
    'in_registry'          => isset($_GET['in_registry'])  ? $_GET['in_registry']        : '',
    'only_draft'           => !empty($_GET['only_draft']),
);
$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page   = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset = ($page - 1) * $limit;

$data = \Papir\Crm\UpTtnRepository::getList($filters, $limit, $offset);
echo json_encode(array(
    'ok'    => true,
    'rows'  => $data['rows'],
    'total' => $data['total'],
    'page'  => $page,
    'limit' => $limit,
), JSON_UNESCAPED_UNICODE);