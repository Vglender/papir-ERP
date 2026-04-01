<?php
require_once __DIR__ . '/novaposhta_bootstrap.php';

$activeNav = 'logistics';
$subNav    = 'np-scan';
$title     = 'Реєстри Нова Пошта';

$senders    = \Papir\Crm\SenderRepository::getAll();
$defaultSender = \Papir\Crm\SenderRepository::getDefault();
$senderRef = isset($_GET['sender_ref']) ? trim($_GET['sender_ref']) : '';
if (!$senderRef && $defaultSender) {
    $senderRef = $defaultSender['Ref'];
}
$dateFrom  = isset($_GET['date_from'])  ? trim($_GET['date_from'])  : '';
$dateTo    = isset($_GET['date_to'])    ? trim($_GET['date_to'])    : '';
$page      = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit     = 50;
$offset    = ($page - 1) * $limit;

$filters = array(
    'sender_ref' => $senderRef,
    'date_from'  => $dateFrom,
    'date_to'    => $dateTo,
);

$data       = \Papir\Crm\ScanSheetRepository::getList($filters, $limit, $offset);
$rows       = $data['rows'];
$total      = $data['total'];
$totalPages = $total > 0 ? ceil($total / $limit) : 1;


require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/scansheets.php';
require_once __DIR__ . '/../shared/layout_end.php';