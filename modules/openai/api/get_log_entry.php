<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

$logId = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;

if ($logId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'log_id required'));
    exit;
}

$r = Database::fetchRow('Papir',
    "SELECT l.*, s.name AS site_name, s.badge AS site_badge
     FROM ai_generation_log l
     LEFT JOIN sites s ON s.site_id = l.site_id
     WHERE l.id = {$logId}"
);

if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Not found'));
    exit;
}

echo json_encode(array('ok' => true, 'row' => $r['row']), JSON_UNESCAPED_UNICODE);
