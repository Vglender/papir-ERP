<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(array('ok'=>false,'error'=>'id required')); exit; }

$r = AttributeRepository::getOne($id);
if (!$r['ok'] || empty($r['row'])) { echo json_encode(array('ok'=>false,'error'=>'not found')); exit; }

$dupes = AttributeRepository::findDuplicates($id);
$data  = $r['row'];
$data['duplicates'] = $dupes;

echo json_encode(array('ok' => true, 'data' => $data));
