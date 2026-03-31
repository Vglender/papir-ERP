<?php
/**
 * POST /counterparties/api/upload_file
 * multipart/form-data: id, type_id, comment, file
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$cpId   = isset($_POST['id'])      ? (int)$_POST['id']      : 0;
$typeId = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if (!$cpId) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = isset($_FILES['file']['error']) ? $_FILES['file']['error'] : -1;
    echo json_encode(array('ok' => false, 'error' => 'Помилка завантаження файлу (код ' . $errCode . ')'));
    exit;
}

$maxBytes = 30 * 1024 * 1024; // 30 MB
if ($_FILES['file']['size'] > $maxBytes) {
    echo json_encode(array('ok' => false, 'error' => 'Файл занадто великий (макс. 30 МБ)'));
    exit;
}

$originalName = $_FILES['file']['name'];
$mimeType     = $_FILES['file']['type'];
$fileSize     = (int)$_FILES['file']['size'];

// Sanitize extension
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExt = array(
    'pdf','doc','docx','xls','xlsx','ppt','pptx',
    'jpg','jpeg','png','gif','svg','webp',
    'ai','psd','eps','indd','cdr',
    'zip','rar','7z',
    'txt','csv','odt','ods',
    'mp4','mov','avi',
);
if ($ext && !in_array($ext, $allowedExt)) {
    echo json_encode(array('ok' => false, 'error' => 'Тип файлу не дозволено'));
    exit;
}

$dir = '/var/www/papir/storage/cp_files/' . $cpId . '/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$storedName = uniqid('f_', true) . ($ext ? '.' . $ext : '');
$destPath   = $dir . $storedName;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалося зберегти файл'));
    exit;
}

$r = Database::insert('Papir', 'counterparty_files', array(
    'counterparty_id' => $cpId,
    'type_id'         => $typeId > 0 ? $typeId : 0,
    'original_name'   => $originalName,
    'stored_name'     => $storedName,
    'file_size'       => $fileSize,
    'mime_type'       => $mimeType,
    'comment'         => $comment,
    'uploaded_at'     => date('Y-m-d H:i:s'),
));

if (!$r['ok']) {
    @unlink($destPath);
    echo json_encode(array('ok' => false, 'error' => 'Помилка запису до БД'));
    exit;
}

echo json_encode(array('ok' => true, 'id' => (int)$r['insert_id']));