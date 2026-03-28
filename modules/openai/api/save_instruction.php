<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$entityType  = isset($_POST['entity_type'])  ? trim($_POST['entity_type'])  : '';
$entityId    = isset($_POST['entity_id'])    ? (int)$_POST['entity_id']     : 0;
$siteId      = isset($_POST['site_id'])      ? (int)$_POST['site_id']       : 0;
$useCase     = isset($_POST['use_case'])     ? trim($_POST['use_case'])      : 'content';
$instruction = isset($_POST['instruction'])  ? trim($_POST['instruction'])   : '';

$allowed   = array('site', 'category', 'product');
$allowedUc = array('content', 'seo', 'chat');

if (!in_array($entityType, $allowed) || $entityId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'entity_type and entity_id required'));
    exit;
}
if (!in_array($useCase, $allowedUc)) {
    $useCase = 'content';
}

$data = array(
    'entity_type' => $entityType,
    'entity_id'   => $entityId,
    'site_id'     => $siteId,
    'use_case'    => $useCase,
    'instruction' => $instruction,
);

$r = Database::upsertOne('Papir', 'ai_instructions', $data,
    array('entity_type', 'entity_id', 'site_id', 'use_case'));

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true));
