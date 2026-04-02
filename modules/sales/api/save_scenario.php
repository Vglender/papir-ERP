<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }

$id   = Request::postInt('id', 0);
$name = trim(Request::postString('name', ''));
$desc = trim(Request::postString('description', ''));

if (!$name) { echo json_encode(array('ok'=>false,'error'=>'name required')); exit; }

$scenarioId = ScenarioRepository::saveScenario(array('id'=>$id, 'name'=>$name, 'description'=>$desc));

// Steps
$stepsJson = Request::postString('steps', '');
if ($stepsJson) {
    $steps = json_decode($stepsJson, true);
    if (is_array($steps)) {
        ScenarioRepository::saveSteps($scenarioId, $steps);
    }
}

$steps = ScenarioRepository::getSteps($scenarioId);
echo json_encode(array('ok'=>true, 'scenario_id'=>$scenarioId, 'steps'=>$steps));