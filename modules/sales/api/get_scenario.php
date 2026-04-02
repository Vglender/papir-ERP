<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo json_encode(array('ok'=>false,'error'=>'id required')); exit; }

$scenario = ScenarioRepository::getScenario($id);
$steps    = ScenarioRepository::getSteps($id);

echo json_encode(array('ok'=>true, 'scenario'=>$scenario, 'steps'=>$steps));