<?php
$title     = 'Додатки';
$activeNav = 'integr';
// Match subnav highlight to category filter
$_catMap = array(
    'communications' => 'communications',
    'delivery'       => 'delivery',
    'finance'        => 'finance-int',
    'advertising'    => 'advertising',
    'analytics'      => 'analytics-int',
    'social'         => 'social',
    'sites'          => 'sites-int',
);
$_catParam = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$subNav = isset($_catMap[$_catParam]) ? $_catMap[$_catParam] : 'catalog';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/catalog.php';
require_once __DIR__ . '/../shared/layout_end.php';
