<?php
/**
 * GET /counterparties/api/get_files?id=&type_id=&sort=desc
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$cpId   = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
$sort   = (isset($_GET['sort']) && $_GET['sort'] === 'asc') ? 'ASC' : 'DESC';

if (!$cpId) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$where = "f.counterparty_id = {$cpId}";
if ($typeId > 0) {
    $where .= " AND f.type_id = {$typeId}";
}

$r = Database::fetchAll('Papir',
    "SELECT f.id, f.original_name, f.stored_name, f.file_size, f.mime_type,
            f.comment, f.uploaded_by, f.uploaded_at,
            f.type_id, COALESCE(t.name, 'Інше') AS type_name
     FROM counterparty_files f
     LEFT JOIN counterparty_file_types t ON t.id = f.type_id
     WHERE {$where}
     ORDER BY f.uploaded_at {$sort}, f.id {$sort}");

$files = $r['ok'] ? $r['rows'] : array();
foreach ($files as &$f) {
    $f['id']        = (int)$f['id'];
    $f['type_id']   = (int)$f['type_id'];
    $f['file_size'] = (int)$f['file_size'];
}
unset($f);

// File types for filter
$rt = Database::fetchAll('Papir',
    "SELECT id, name FROM counterparty_file_types WHERE status=1 ORDER BY sort_order ASC");
$types = $rt['ok'] ? $rt['rows'] : array();

echo json_encode(array('ok' => true, 'files' => $files, 'types' => $types));
