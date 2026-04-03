<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }

$data = array(
    'id'            => Request::postInt('id', 0),
    'name'          => Request::postString('name', ''),
    'event_type'    => Request::postString('event_type', ''),
    'conditions'    => Request::postString('conditions', ''),
    'scenario_id'   => Request::postInt('scenario_id', 0),
    'delay_minutes' => Request::postInt('delay_minutes', 0),
    'status'        => Request::postInt('status', 1),
);

if (!$data['name'] || !$data['event_type']) {
    echo json_encode(array('ok'=>false,'error'=>'name, event_type required'));
    exit;
}
if (!$data['scenario_id']) {
    echo json_encode(array('ok'=>false,'error'=>'scenario_id required'));
    exit;
}

// Verify scenario exists
$r = Database::fetchRow('Papir', "SELECT id FROM cp_scenarios WHERE id=" . (int)$data['scenario_id']);
if (!$r['ok'] || !$r['row']) {
    echo json_encode(array('ok'=>false,'error'=>'Сценарій не знайдено'));
    exit;
}

$triggerId = ScenarioRepository::saveTrigger($data);
echo json_encode(array('ok'=>true, 'trigger_id'=>$triggerId));
