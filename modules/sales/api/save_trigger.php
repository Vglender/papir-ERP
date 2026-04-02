<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }

$data = array(
    'id'              => Request::postInt('id', 0),
    'name'            => Request::postString('name', ''),
    'event_type'      => Request::postString('event_type', ''),
    'condition_key'   => Request::postString('condition_key', ''),
    'condition_op'    => Request::postString('condition_op', ''),
    'condition_value' => Request::postString('condition_value', ''),
    'scenario_id'     => Request::postInt('scenario_id', 0),
    'delay_minutes'   => Request::postInt('delay_minutes', 0),
);

$scenarioName = trim(Request::postString('scenario_name', ''));
if (!$data['name'] || !$data['event_type']) {
    echo json_encode(array('ok'=>false,'error'=>'name, event_type required'));
    exit;
}

// Create or find scenario by name if no scenario_id
if (!$data['scenario_id'] && $scenarioName) {
    $data['scenario_id'] = ScenarioRepository::saveScenario(array('name' => $scenarioName));
} elseif ($data['scenario_id'] && $scenarioName) {
    ScenarioRepository::saveScenario(array('id' => $data['scenario_id'], 'name' => $scenarioName));
}
if (!$data['scenario_id']) {
    echo json_encode(array('ok'=>false,'error'=>'scenario required'));
    exit;
}

// Save trigger status
$data['status'] = Request::postInt('status', 1);

// Save trigger
$triggerId = ScenarioRepository::saveTrigger($data);

// Save scenario steps if passed (JSON array in 'steps' field)
$stepsJson = Request::postString('steps', '');
if ($stepsJson) {
    $steps = json_decode($stepsJson, true);
    if (is_array($steps)) {
        ScenarioRepository::saveSteps($data['scenario_id'], $steps);
    }
}

echo json_encode(array('ok'=>true, 'trigger_id'=>$triggerId));