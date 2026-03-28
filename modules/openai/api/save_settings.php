<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$entityType  = isset($_POST['entity_type']) ? trim($_POST['entity_type']) : '';
$entityId    = isset($_POST['entity_id'])   ? (int)$_POST['entity_id']   : 0;
$useCase     = isset($_POST['use_case'])    ? trim($_POST['use_case'])    : 'content';
$instruction = isset($_POST['instruction']) ? trim($_POST['instruction']) : '';
$model       = isset($_POST['model'])       ? trim($_POST['model'])       : 'gpt-4o-mini';
$temperature = isset($_POST['temperature']) ? (float)$_POST['temperature'] : 0.7;
$maxTokens   = isset($_POST['max_tokens'])  ? (int)$_POST['max_tokens']   : 800;

$allowed       = array('site', 'category', 'product');
$allowedModels = array('gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'o1-mini');
$allowedUc     = array('content', 'seo', 'chat');

if (!in_array($entityType, $allowed) || $entityId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'entity_type and entity_id required'));
    exit;
}
if (!in_array($useCase, $allowedUc)) {
    echo json_encode(array('ok' => false, 'error' => 'invalid use_case'));
    exit;
}
if (!in_array($model, $allowedModels)) {
    $model = 'gpt-4o-mini';
}

$temperature = max(0.0, min(2.0, $temperature));
$maxTokens   = max(100, min(4000, $maxTokens));

$context = json_encode(array(
    'model'       => $model,
    'temperature' => $temperature,
    'max_tokens'  => $maxTokens,
));

// Для site-level: site_id = entity_id (своя інструкція кожного сайту)
// Для category/product: site_id береться з POST або 0 (all sites)
$siteId = ($entityType === 'site')
    ? $entityId
    : (isset($_POST['site_id']) ? (int)$_POST['site_id'] : 0);

$data = array(
    'entity_type' => $entityType,
    'entity_id'   => $entityId,
    'site_id'     => $siteId,
    'use_case'    => $useCase,
    'instruction' => $instruction,
    'context'     => $context,
);

$r = Database::upsertOne('Papir', 'ai_instructions', $data, array('entity_type', 'entity_id', 'site_id', 'use_case'));

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

echo json_encode(array('ok' => true));
