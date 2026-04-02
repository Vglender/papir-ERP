<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }

$id = Request::postInt('id', 0);
if (!$id) { echo json_encode(array('ok'=>false,'error'=>'id required')); exit; }

ScenarioRepository::deleteTrigger($id);
echo json_encode(array('ok'=>true));