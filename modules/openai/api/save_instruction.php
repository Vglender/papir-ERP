<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$entityType  = isset($_POST['entity_type'])  ? trim($_POST['entity_type'])  : '';
$entityId    = isset($_POST['entity_id'])     ? (int)$_POST['entity_id']     : 0;
$useCase     = isset($_POST['use_case'])      ? trim($_POST['use_case'])      : 'content';
$instruction = isset($_POST['instruction'])   ? trim($_POST['instruction'])   : '';
$context     = isset($_POST['context'])       ? trim($_POST['context'])       : '';

$allowed = array('site', 'category', 'product');
if (!in_array($entityType, $allowed) || $entityId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'entity_type and entity_id required'));
    exit;
}

$data = array(
    'entity_type'  => $entityType,
    'entity_id'    => $entityId,
    'use_case'     => $useCase,
    'instruction'  => $instruction,
    'context'      => $context,
);

$r = Database::upsertOne('Papir', 'ai_instructions', $data, 'uq_entity_usecase');

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true));
