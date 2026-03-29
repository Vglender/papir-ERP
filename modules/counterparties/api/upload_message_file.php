<?php
/**
 * POST /counterparties/api/upload_message_file
 * Upload image/file for chat message. Returns public URL.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

if (empty($_FILES['file'])) {
    echo json_encode(array('ok' => false, 'error' => 'file required'));
    exit;
}

$file    = $_FILES['file'];
$maxSize = 10 * 1024 * 1024; // 10 MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(array('ok' => false, 'error' => 'upload error: ' . $file['error']));
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(array('ok' => false, 'error' => 'Файл більше 10 MB'));
    exit;
}

$mime      = mime_content_type($file['tmp_name']);
$isImage   = in_array($mime, array('image/jpeg','image/png','image/gif','image/webp'));
$isAllowed = $isImage || in_array($mime, array('application/pdf','application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain'));

if (!$isAllowed) {
    echo json_encode(array('ok' => false, 'error' => 'Тип файлу не дозволено'));
    exit;
}

// Destination dir
$dir = '/var/www/menufold/data/www/officetorg.com.ua/image/crm/messages/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Safe filename
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$newName  = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
$destPath = $dir . $newName;

if ($isImage) {
    // Resize to max 1400px, JPEG 85%
    $src = null;
    if ($mime === 'image/jpeg')      $src = imagecreatefromjpeg($file['tmp_name']);
    elseif ($mime === 'image/png')   $src = imagecreatefrompng($file['tmp_name']);
    elseif ($mime === 'image/gif')   $src = imagecreatefromgif($file['tmp_name']);
    elseif ($mime === 'image/webp')  $src = imagecreatefromwebp($file['tmp_name']);

    if ($src) {
        $w = imagesx($src); $h = imagesy($src);
        $maxPx = 1400;
        if ($w > $maxPx || $h > $maxPx) {
            if ($w >= $h) { $nw = $maxPx; $nh = (int)round($h * $maxPx / $w); }
            else          { $nh = $maxPx; $nw = (int)round($w * $maxPx / $h); }
            $dst = imagecreatetruecolor($nw, $nh);
            // White background for PNG transparency
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }
        $newName = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.jpg';
        $destPath = $dir . $newName;
        imagejpeg($src, $destPath, 85);
        imagedestroy($src);
    } else {
        move_uploaded_file($file['tmp_name'], $destPath);
    }
} else {
    move_uploaded_file($file['tmp_name'], $destPath);
}

$url = 'https://officetorg.com.ua/image/crm/messages/' . $newName;

echo json_encode(array(
    'ok'       => true,
    'url'      => $url,
    'name'     => $file['name'],
    'is_image' => $isImage,
    'mime'     => $mime,
));
