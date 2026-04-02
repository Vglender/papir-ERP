<?php
require_once __DIR__ . '/novaposhta_bootstrap.php';

$activeNav = 'logistics';
$subNav    = 'np-courier';
$title     = 'Виклики кур\'єра';

$senders       = \Papir\Crm\SenderRepository::getAll();
$defaultSender = \Papir\Crm\SenderRepository::getDefault();

$senderRef = isset($_GET['sender_ref']) ? trim($_GET['sender_ref']) : '';
if (!$senderRef && $defaultSender) {
    $senderRef = $defaultSender['Ref'];
}

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
$page     = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit    = 50;
$offset   = ($page - 1) * $limit;

$filters = array(
    'sender_ref' => $senderRef,
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
);

$data       = \Papir\Crm\CourierCallRepository::getList($filters, $limit, $offset);
$rows       = $data['rows'];
$total      = $data['total'];
$totalPages = $total > 0 ? ceil($total / $limit) : 1;

// For the create modal: contacts and addresses for selected sender
$contacts  = $senderRef ? \Papir\Crm\SenderRepository::getContacts($senderRef)  : array();
$addresses = $senderRef ? \Papir\Crm\SenderRepository::getAddresses($senderRef) : array();

require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/courier_calls.php';
require_once __DIR__ . '/../shared/layout_end.php';