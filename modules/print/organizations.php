<?php
require_once __DIR__ . '/print_bootstrap.php';

$repo      = new OrganizationRepository();
$orgs      = $repo->getList();
$selected  = isset($_GET['selected']) ? (int)$_GET['selected'] : 0;
$org       = $selected > 0 ? $repo->getById($selected) : null;

$title     = 'Організації';
$activeNav = 'system';
$subNav    = 'organizations';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/organizations.php';
require_once __DIR__ . '/../shared/layout_end.php';