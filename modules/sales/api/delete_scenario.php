<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }

$id = Request::postInt('id', 0);
if (!$id) { echo json_encode(array('ok'=>false,'error'=>'id required')); exit; }

// Check if any triggers reference this scenario
$r = Database::fetchRow('Papir', "SELECT COUNT(*) AS cnt FROM cp_triggers WHERE scenario_id={$id}");
if ($r['ok'] && $r['row'] && (int)$r['row']['cnt'] > 0) {
    echo json_encode(array('ok'=>false,'error'=>'Сценарій використовується тригерами — спочатку видаліть або змініть тригери'));
    exit;
}

ScenarioRepository::deleteScenario($id);
echo json_encode(array('ok'=>true));
