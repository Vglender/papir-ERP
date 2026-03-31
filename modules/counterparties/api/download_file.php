<?php
/**
 * GET /counterparties/api/download_file?id=
 * Serves the file with original filename
 */
require_once __DIR__ . '/../counterparties_bootstrap.php';

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$fileId) {
    http_response_code(400); echo 'id required'; exit;
}

$r = Database::fetchRow('Papir',
    "SELECT counterparty_id, stored_name, original_name, mime_type
     FROM counterparty_files WHERE id = {$fileId} LIMIT 1");

if (!$r['ok'] || empty($r['row'])) {
    http_response_code(404); echo 'Not found'; exit;
}

$row  = $r['row'];
$path = '/var/www/papir/storage/cp_files/' . (int)$row['counterparty_id'] . '/' . $row['stored_name'];

if (!file_exists($path)) {
    http_response_code(404); echo 'File missing'; exit;
}

$mime = $row['mime_type'] ? $row['mime_type'] : 'application/octet-stream';
$name = $row['original_name'] ? $row['original_name'] : $row['stored_name'];

// Inline for PDF/images, attachment for others
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$inline = in_array($ext, array('pdf','jpg','jpeg','png','gif','svg','webp'));
$disposition = $inline ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($name) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-cache');
readfile($path);
exit;