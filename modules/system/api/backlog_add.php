<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required')); exit;
}
if (!\Papir\Crm\AuthService::can('backlog', 'edit')) {
    echo json_encode(array('ok' => false, 'error' => 'Access denied')); exit;
}

$module     = isset($_POST['module'])     ? trim($_POST['module'])     : 'general';
$type       = isset($_POST['type'])       ? trim($_POST['type'])       : 'plan';
$text       = isset($_POST['text'])       ? trim($_POST['text'])       : '';
$screenshot = isset($_POST['screenshot']) ? trim($_POST['screenshot']) : '';

if ($text === '') {
    echo json_encode(array('ok' => false, 'error' => 'text required')); exit;
}

$allowedTypes = array('bug', 'plan', 'idea');
if (!in_array($type, $allowedTypes)) $type = 'plan';

$insertData = array('module' => $module, 'type' => $type, 'text' => $text);
if ($screenshot !== '') $insertData['screenshot'] = $screenshot;

$r = Database::insert('Papir', 'backlog', $insertData);

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error')); exit;
}

// Regenerate docs/backlog.md
require_once __DIR__ . '/../backlog_export.php';
backlog_export_md();

echo json_encode(array('ok' => true, 'id' => $r['insert_id']));