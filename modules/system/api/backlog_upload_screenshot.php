<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../modules/database/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required')); exit;
}
if (!\Papir\Crm\AuthService::can('backlog', 'edit')) {
    echo json_encode(array('ok' => false, 'error' => 'Access denied')); exit;
}

if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(array('ok' => false, 'error' => 'Файл не отримано')); exit;
}

$file = $_FILES['screenshot'];

// Max 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(array('ok' => false, 'error' => 'Файл занадто великий (макс 10MB)')); exit;
}

// Only images
$allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
$mime    = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Дозволені тільки зображення (jpg, png, gif, webp)')); exit;
}

$ext      = $mime === 'image/png' ? 'png' : ($mime === 'image/gif' ? 'gif' : ($mime === 'image/webp' ? 'webp' : 'jpg'));
$filename = 'bl_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
$dir      = '/var/www/menufold/data/www/officetorg.com.ua/image/crm/backlog/';
$savePath = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $savePath)) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалося зберегти файл')); exit;
}

$publicUrl = 'https://officetorg.com.ua/image/crm/backlog/' . $filename;
$localPath = 'image/crm/backlog/' . $filename; // зберігаємо відносний шлях у БД

echo json_encode(array('ok' => true, 'path' => $localPath, 'url' => $publicUrl));
