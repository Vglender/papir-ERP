<?php
require_once __DIR__ . '/print_bootstrap.php';

$repo      = new OrganizationRepository();
$orgs      = $repo->getList();
$selected  = isset($_GET['selected']) ? (int)$_GET['selected'] : 0;
$org       = $selected > 0 ? $repo->getById($selected) : null;

// Довідники для дефолтів організації
$rStores = Database::fetchAll('Papir', "SELECT id, name FROM store WHERE status=1 ORDER BY name");
$stores  = $rStores['ok'] ? $rStores['rows'] : array();

$rDM = Database::fetchAll('Papir', "SELECT id, name_uk FROM delivery_method WHERE status=1 ORDER BY sort_order");
$deliveryMethods = $rDM['ok'] ? $rDM['rows'] : array();

$rPM = Database::fetchAll('Papir', "SELECT id, code, name_uk FROM payment_method WHERE status=1 ORDER BY sort_order");
$paymentMethods = $rPM['ok'] ? $rPM['rows'] : array();

$title     = 'Організації';
$activeNav = 'system';
$subNav    = 'organizations';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/organizations.php';
require_once __DIR__ . '/../shared/layout_end.php';