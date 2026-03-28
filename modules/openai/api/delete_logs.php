<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../openai_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$raw = isset($_POST['ids']) ? $_POST['ids'] : '';
if ($raw === '') {
    echo json_encode(array('ok' => false, 'error' => 'ids required'));
    exit;
}

$ids = array();
foreach (explode(',', $raw) as $part) {
    $id = (int)trim($part);
    if ($id > 0) $ids[] = $id;
}

if (empty($ids)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid ids'));
    exit;
}

$idList = implode(',', $ids);
$r = Database::query('Papir', "DELETE FROM ai_generation_log WHERE id IN ({$idList})");

echo json_encode(array('ok' => $r['ok'], 'deleted' => $r['ok'] ? $r['affected_rows'] : 0));
