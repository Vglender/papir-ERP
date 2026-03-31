<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required')); exit;
}
if (!\Papir\Crm\AuthService::can('backlog', 'edit')) {
    echo json_encode(array('ok' => false, 'error' => 'Access denied')); exit;
}

$id         = isset($_POST['id'])         ? (int)trim($_POST['id'])         : 0;
$text       = isset($_POST['text'])       ? trim($_POST['text'])             : '';
$type       = isset($_POST['type'])       ? trim($_POST['type'])             : '';
$module     = isset($_POST['module'])     ? trim($_POST['module'])           : '';
$screenshot = isset($_POST['screenshot']) ? trim($_POST['screenshot'])       : null;

// Allow screenshot-only update (text not required in that case)
if ($id <= 0 || ($text === '' && $screenshot === null)) {
    echo json_encode(array('ok' => false, 'error' => 'id required; text or screenshot required')); exit;
}

$allowedTypes = array('bug', 'plan', 'idea');
if (!in_array($type, $allowedTypes)) $type = 'plan';

$data = array();
if ($text !== '')       { $data['text'] = $text; $data['type'] = $type; }
if ($module !== '')     $data['module'] = $module;
if ($screenshot !== null) $data['screenshot'] = $screenshot !== '' ? $screenshot : null;

$r = Database::update('Papir', 'backlog', $data, array('id' => $id));

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error')); exit;
}

require_once __DIR__ . '/../backlog_export.php';
backlog_export_md();

echo json_encode(array('ok' => true));