<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

$repo       = new PrintTemplateRepository();
$entityType = isset($_GET['entity_type']) ? trim((string)$_GET['entity_type']) : '';

// Which template type codes are relevant per entity type
$typeMap = array(
    'order' => array('invoice', 'waybill', 'act', 'contract'),
);

$allowedCodes = isset($typeMap[$entityType]) ? $typeMap[$entityType] : array();

// All active templates
$all = $repo->getList(0, 'active');

// Group by type_name, filtering to allowed type codes
$groups = array();
foreach ($all as $t) {
    if (!empty($allowedCodes) && !in_array($t['type_code'], $allowedCodes, true)) {
        continue;
    }
    $typeName = $t['type_name'];
    if (!isset($groups[$typeName])) {
        $groups[$typeName] = array();
    }
    $groups[$typeName][] = array(
        'id'        => (int)$t['id'],
        'name'      => $t['name'],
        'code'      => $t['code'],
        'type_code' => $t['type_code'],
        'type_name' => $t['type_name'],
        'version'   => (int)$t['version'],
    );
}

echo json_encode(array('ok' => true, 'groups' => $groups));