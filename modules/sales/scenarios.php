<?php
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/../counterparties/counterparties_bootstrap.php';

$triggers  = ScenarioRepository::getAllTriggers();
$scenarios = ScenarioRepository::getAllScenarios();

$r = Database::fetchAll('Papir', "SELECT id, name_uk FROM payment_method WHERE status=1 ORDER BY sort_order");
$paymentMethods = $r['ok'] ? $r['rows'] : array();

$r = Database::fetchAll('Papir', "SELECT id, name_uk FROM delivery_method WHERE status=1 ORDER BY sort_order");
$deliveryMethods = $r['ok'] ? $r['rows'] : array();

require_once __DIR__ . '/views/scenarios.php';