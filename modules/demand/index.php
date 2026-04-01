<?php
require_once __DIR__ . '/demand_bootstrap.php';

$activeNav = 'sales';
$subNav    = 'demands';
$title     = 'Відвантаження';

$search   = isset($_GET['search'])          ? trim($_GET['search'])          : '';
$orgId    = isset($_GET['organization_id']) ? (int)$_GET['organization_id'] : 0;
$status   = isset($_GET['status'])          ? (array)$_GET['status']        : array();
$dateFrom = isset($_GET['date_from'])       ? trim($_GET['date_from'])       : '';
$dateTo   = isset($_GET['date_to'])         ? trim($_GET['date_to'])         : '';
$page     = max(1, isset($_GET['page'])     ? (int)$_GET['page']            : 1);
$limit    = 50;

$filters = array(
    'search'          => $search,
    'organization_id' => $orgId,
    'status'          => $status,
    'date_from'       => $dateFrom,
    'date_to'         => $dateTo,
);

$repo  = new DemandRepository();
$data  = $repo->getList($filters, $page, $limit);
$rows  = $data['rows'];
$total = $data['total'];
$totalPages = $total > 0 ? ceil($total / $limit) : 1;

$orgs  = $repo->getOrganizations();
$orgs  = ($orgs['ok'] && !empty($orgs['rows'])) ? $orgs['rows'] : array();

require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/index.php';
require_once __DIR__ . '/../shared/layout_end.php';