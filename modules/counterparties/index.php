<?php
require_once __DIR__ . '/counterparties_bootstrap.php';

$repo = new CounterpartyRepository();

$search  = isset($_GET['search'])   ? trim($_GET['search'])  : '';
$type    = isset($_GET['type'])     ? trim($_GET['type'])    : '';
$page    = isset($_GET['page'])     ? max(1, (int)$_GET['page']) : 1;
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$params = array(
    'search'  => $search,
    'type'    => $type,
    'limit'   => $perPage,
    'offset'  => $offset,
);

$rows  = $repo->getList($params);
$total = $repo->getCount($params);
$pages = $total > 0 ? (int)ceil($total / $perPage) : 1;
$groups = $repo->getGroups();

require_once __DIR__ . '/views/index.php';
