<?php
require_once __DIR__ . '/finance_bootstrap.php';

$repo = new FinanceCashRepository();

$search    = isset($_GET['search'])    ? trim($_GET['search'])      : '';
$direction = isset($_GET['direction']) ? trim($_GET['direction'])   : '';
$dateFrom  = isset($_GET['date_from']) ? trim($_GET['date_from'])   : '';
$dateTo    = isset($_GET['date_to'])   ? trim($_GET['date_to'])     : '';
$page      = isset($_GET['page'])      ? max(1, (int)$_GET['page']) : 1;
$perPage   = 50;

$params = array(
    'search'    => $search,
    'direction' => $direction,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'limit'     => $perPage,
    'offset'    => ($page - 1) * $perPage,
);

$rows    = $repo->getList($params);
$total   = $repo->getTotal($params);
$summary = $repo->getSummary($params);
$pages   = $total > 0 ? (int)ceil($total / $perPage) : 1;

$title     = 'Каса';
$activeNav = 'finance';
$subNav    = 'cash';
require_once __DIR__ . '/views/cash.php';
