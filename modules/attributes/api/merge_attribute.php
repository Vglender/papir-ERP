<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('ok'=>false,'error'=>'POST required')); exit; }

$sourceId = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;
$targetId = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;

if ($sourceId <= 0 || $targetId <= 0) {
    echo json_encode(array('ok'=>false,'error'=>'source_id and target_id required'));
    exit;
}
if ($sourceId === $targetId) {
    echo json_encode(array('ok'=>false,'error'=>'Атрибути однакові'));
    exit;
}

// Confirm both exist
$src = AttributeRepository::getOne($sourceId);
$tgt = AttributeRepository::getOne($targetId);
if (!$src['ok'] || empty($src['row'])) { echo json_encode(array('ok'=>false,'error'=>'Джерело не знайдено')); exit; }
if (!$tgt['ok'] || empty($tgt['row'])) { echo json_encode(array('ok'=>false,'error'=>'Ціль не знайдено')); exit; }

$result = AttributeRepository::merge($sourceId, $targetId);
echo json_encode($result);
