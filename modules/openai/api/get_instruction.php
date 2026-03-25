<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

$entityType = isset($_GET['entity_type']) ? trim($_GET['entity_type']) : '';
$entityId   = isset($_GET['entity_id'])   ? (int)$_GET['entity_id']   : 0;
$useCase    = isset($_GET['use_case'])     ? trim($_GET['use_case'])    : 'content';

$allowed = array('site', 'category', 'product');
if (!in_array($entityType, $allowed) || $entityId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'entity_type and entity_id required'));
    exit;
}

$type   = Database::escape('Papir', $entityType);
$uc     = Database::escape('Papir', $useCase);

$r = Database::fetchRow('Papir',
    "SELECT instruction, context, updated_at
     FROM ai_instructions
     WHERE entity_type = '{$type}' AND entity_id = {$entityId} AND use_case = '{$uc}'"
);

if ($r['ok'] && !empty($r['row'])) {
    echo json_encode(array(
        'ok'   => true,
        'data' => array(
            'instruction' => (string)$r['row']['instruction'],
            'context'     => (string)$r['row']['context'],
            'updated_at'  => (string)$r['row']['updated_at'],
        ),
    ));
} else {
    echo json_encode(array(
        'ok'   => true,
        'data' => array('instruction' => '', 'context' => '', 'updated_at' => ''),
    ));
}
