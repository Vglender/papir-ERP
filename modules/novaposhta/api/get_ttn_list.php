<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$filters = array(
    'search'       => isset($_GET['search'])      ? trim($_GET['search'])      : '',
    'sender_ref'   => isset($_GET['sender_ref'])  ? trim($_GET['sender_ref'])  : '',
    'state_define' => isset($_GET['state_define'])? trim($_GET['state_define']): '',
    'date_from'    => isset($_GET['date_from'])   ? trim($_GET['date_from'])   : '',
    'date_to'      => isset($_GET['date_to'])     ? trim($_GET['date_to'])     : '',
);

$limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'],  200) : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

$data = \Papir\Crm\TtnRepository::getList($filters, $limit, $offset);
echo json_encode(array('ok' => true, 'rows' => $data['rows'], 'total' => $data['total']));