<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo json_encode(array('ok'=>false,'error'=>'id required')); exit; }

$trigger = ScenarioRepository::getTrigger($id);
if (!$trigger) { echo json_encode(array('ok'=>false,'error'=>'Not found')); exit; }

$steps       = ScenarioRepository::getSteps((int)$trigger['scenario_id']);
$scenario    = ScenarioRepository::getScenario((int)$trigger['scenario_id']);
$scenarioName = $scenario ? $scenario['name'] : '';

echo json_encode(array('ok'=>true, 'trigger'=>$trigger, 'steps'=>$steps, 'scenario_name'=>$scenarioName));