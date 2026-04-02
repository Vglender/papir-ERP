<?php
require_once __DIR__ . '/finance_bootstrap.php';

$repo = new FinanceBankRepository();

// Дефолти при першому відкритті (без фільтрів у URL)
if (!isset($_GET['date_from']) && !isset($_GET['date_to']) && !isset($_GET['search'])
    && !isset($_GET['direction']) && !isset($_GET['_period'])) {
    $today = date('Y-m-d');
    header('Location: /finance/bank?date_from=' . $today . '&date_to=' . $today . '&show_drafts=1&_period=today');
    exit;
}

$search      = isset($_GET['search'])       ? trim($_GET['search'])      : '';
$direction   = isset($_GET['direction'])    ? trim($_GET['direction'])   : '';
$hideMoving  = !empty($_GET['hide_moving']);
$showDrafts  = !empty($_GET['show_drafts']);
$dateFrom    = isset($_GET['date_from'])    ? trim($_GET['date_from'])   : '';
$dateTo      = isset($_GET['date_to'])      ? trim($_GET['date_to'])     : '';
$page        = isset($_GET['page'])         ? max(1, (int)$_GET['page']) : 1;
$perPage     = 50;

$params = array(
    'search'      => $search,
    'direction'   => $direction,
    'hide_moving' => $hideMoving,
    'show_drafts' => $showDrafts,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
    'limit'       => $perPage,
    'offset'      => ($page - 1) * $perPage,
);

$rows    = $repo->getList($params);
$total   = $repo->getTotal($params);
$summary = $repo->getSummary($params);
$pages   = $total > 0 ? (int)ceil($total / $perPage) : 1;

$expCatRows = Database::fetchAll('Papir',
    "SELECT id, name FROM finance_expense_category WHERE status=1 ORDER BY sort_order, name");
$expenseCategories = ($expCatRows['ok']) ? $expCatRows['rows'] : array();

$title     = 'Банк';
$activeNav = 'finance';
$subNav    = 'bank';
require_once __DIR__ . '/views/bank.php';
