<?php
require_once __DIR__ . '/novaposhta_bootstrap.php';

$activeNav = 'logistics';
$subNav    = 'np-ttns';
$title     = 'ТТН Нова Пошта';

// Filters from GET
$search     = isset($_GET['search'])      ? trim($_GET['search'])      : '';
$senderRef  = isset($_GET['sender_ref'])  ? trim($_GET['sender_ref'])  : '';
$stateGroup = isset($_GET['state_group']) ? trim($_GET['state_group']) : '';
$dateFrom   = isset($_GET['date_from'])   ? trim($_GET['date_from'])   : '';
$dateTo     = isset($_GET['date_to'])     ? trim($_GET['date_to'])     : '';
$draft      = isset($_GET['draft'])       ? (int)$_GET['draft']        : 1; // default ON
$page       = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit      = 50;
$offset     = ($page - 1) * $limit;

$filters = array(
    'search'      => $search,
    'sender_ref'  => $senderRef,
    'state_group' => $draft ? '' : $stateGroup, // draft mode overrides state_group
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
);
if ($draft) {
    $filters['state_define'] = 1;
    $filters['draft_sort']   = 1;
}

$data       = \Papir\Crm\TtnRepository::getList($filters, $limit, $offset);
$rows       = $data['rows'];
$total      = $data['total'];
$totalPages = $total > 0 ? ceil($total / $limit) : 1;

$senders    = \Papir\Crm\SenderRepository::getAll();

require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/index.php';
require_once __DIR__ . '/../shared/layout_end.php';