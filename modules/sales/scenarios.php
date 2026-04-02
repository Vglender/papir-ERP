<?php
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/../counterparties/counterparties_bootstrap.php';

$triggers  = ScenarioRepository::getAllTriggers();
$scenarios = ScenarioRepository::getAllScenarios();

require_once __DIR__ . '/views/scenarios.php';