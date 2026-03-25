<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../MffFtpSync.php';
require_once __DIR__ . '/../ProductImageService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$entityType = isset($_POST['entity_type']) ? trim($_POST['entity_type']) : '';
$entityId   = isset($_POST['entity_id'])   ? (int)$_POST['entity_id']   : 0;

$allowedTypes = array('category', 'product', 'manufacturer');
if (!in_array($entityType, $allowedTypes)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid entity_type'));
    exit;
}
if ($entityId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'entity_id required'));
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = isset($_FILES['image']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
    echo json_encode(array('ok' => false, 'error' => 'File upload error: ' . $uploadErr));
    exit;
}

$tmpPath  = (string)$_FILES['image']['tmp_name'];
$fileSize = (int)$_FILES['image']['size'];

// Max 5MB
if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(array('ok' => false, 'error' => 'File too large (max 5MB)'));
    exit;
}

// Validate by actual image content (not user-supplied mime)
$imgInfo = @getimagesize($tmpPath);
if ($imgInfo === false) {
    echo json_encode(array('ok' => false, 'error' => 'Not a valid image'));
    exit;
}
$imageMime    = $imgInfo['mime'];
$allowedMimes = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
if (!in_array($imageMime, $allowedMimes)) {
    echo json_encode(array('ok' => false, 'error' => 'Unsupported image format'));
    exit;
}

// Product: delegate entirely to ProductImageService (handles file save + DB + cascade)
if ($entityType === 'product') {
    $service = new ProductImageService();
    $result  = $service->upload($entityId, $tmpPath, $imageMime, $fileSize);
    echo json_encode($result);
    exit;
}

// Determine storage subdir
$imageBase = '/var/www/menufold/data/www/officetorg.com.ua/image/';
if ($entityType === 'category') {
    $subdir = 'catalog/category/';
} elseif ($entityType === 'product') {
    $hex2   = sprintf('%02x', $entityId & 0xff);
    $subdir = 'catalog/product/' . $hex2 . '/' . $hex2 . '/';
} else {
    $subdir = 'catalog/' . $entityType . '/';
}

$uploadDir = $imageBase . $subdir;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    echo json_encode(array('ok' => false, 'error' => 'Upload directory not found'));
    exit;
}

// Load source image
$src = null;
switch ($imageMime) {
    case 'image/jpeg': $src = @imagecreatefromjpeg($tmpPath); break;
    case 'image/png':  $src = @imagecreatefrompng($tmpPath);  break;
    case 'image/webp': $src = @imagecreatefromwebp($tmpPath); break;
    case 'image/gif':  $src = @imagecreatefromgif($tmpPath);  break;
}
if (!$src) {
    echo json_encode(array('ok' => false, 'error' => 'Failed to open image'));
    exit;
}

// Resize to max 1200px if needed
$origW  = imagesx($src);
$origH  = imagesy($src);
$maxDim = 1200;
if ($origW > $maxDim || $origH > $maxDim) {
    if ($origW >= $origH) {
        $newW = $maxDim;
        $newH = (int)round($origH * $maxDim / $origW);
    } else {
        $newH = $maxDim;
        $newW = (int)round($origW * $maxDim / $origH);
    }
    $dst = imagecreatetruecolor($newW, $newH);
    // White background (for transparent PNG/GIF converted to JPEG)
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($src);
    $src = $dst;
}

$filename = $entityType . '_' . $entityId . '_' . uniqid() . '.jpg';
$destPath = $uploadDir . $filename;
$relImage = $subdir . $filename;

$saved = imagejpeg($src, $destPath, 85);
imagedestroy($src);

if (!$saved) {
    echo json_encode(array('ok' => false, 'error' => 'Failed to save image'));
    exit;
}

$imageId = 0;
if ($entityType === 'category') {
    $sortRes = Database::fetchRow('Papir',
        "SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_sort FROM category_images WHERE category_id = {$entityId}"
    );
    $nextSort  = ($sortRes['ok'] && !empty($sortRes['row'])) ? (int)$sortRes['row']['next_sort'] : 1;
    $safeImage = Database::escape('Papir', $relImage);
    $insRes = Database::query('Papir',
        "INSERT INTO category_images (category_id, image, sort_order) VALUES ({$entityId}, '{$safeImage}', {$nextSort})"
    );
    if (!$insRes['ok']) {
        @unlink($destPath);
        echo json_encode(array('ok' => false, 'error' => 'DB insert failed'));
        exit;
    }
    $idRes   = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS image_id");
    $imageId = ($idRes['ok'] && !empty($idRes['row'])) ? (int)$idRes['row']['image_id'] : 0;

    // Cascade: update main image in categoria + oc_category on linked sites
    Database::update('Papir', 'categoria',
        array('image' => $relImage),
        array('category_id' => $entityId)
    );

    $catRes = Database::fetchRow('Papir',
        "SELECT category_off, category_mf FROM categoria WHERE category_id = {$entityId}"
    );
    if ($catRes['ok'] && !empty($catRes['row'])) {
        $offCatId = (int)$catRes['row']['category_off'];
        $mffCatId = (int)$catRes['row']['category_mf'];
        if ($offCatId > 0) {
            Database::update('off', 'oc_category',
                array('image' => $relImage),
                array('category_id' => $offCatId)
            );
        }
        if ($mffCatId > 0) {
            Database::update('mff', 'oc_category',
                array('image' => $relImage),
                array('category_id' => $mffCatId)
            );
        }
    }
}

echo json_encode(array(
    'ok'   => true,
    'data' => array(
        'image_id' => $imageId,
        'image'    => $relImage,
        'url'      => 'https://officetorg.com.ua/image/' . $relImage,
    )
));
