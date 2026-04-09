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
$imageId    = isset($_POST['image_id'])    ? (int)$_POST['image_id']    : 0;

$allowedTypes = array('category', 'product', 'manufacturer');
if (!in_array($entityType, $allowedTypes)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid entity_type'));
    exit;
}
if ($imageId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'image_id required'));
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $uploadErr = isset($_FILES['image']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
    echo json_encode(array('ok' => false, 'error' => 'File upload error: ' . $uploadErr));
    exit;
}

$tmpPath  = (string)$_FILES['image']['tmp_name'];
$fileSize = (int)$_FILES['image']['size'];

if ($fileSize > 5 * 1024 * 1024) {
    echo json_encode(array('ok' => false, 'error' => 'File too large (max 5MB)'));
    exit;
}

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

if ($entityType === 'product') {
    $service = new ProductImageService();
    echo json_encode($service->replace($imageId, $tmpPath, $imageMime, $fileSize));
    exit;
}

$imageBase = '/var/www/menufold/data/www/officetorg.com.ua/image/';

// Load existing record
if ($entityType === 'category') {
    $r = Database::fetchRow('Papir',
        "SELECT image_id, image, category_id FROM category_images WHERE image_id = {$imageId}"
    );
    if (!$r['ok'] || empty($r['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Image not found'));
        exit;
    }
    $oldRelImage = (string)$r['row']['image'];
    $entityId    = (int)$r['row']['category_id'];
    $subdir      = 'catalog/category/';
}

$uploadDir = $imageBase . $subdir;

// Load and resize new image
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

// Update DB record
$safeImage = Database::escape('Papir', $relImage);
$updRes = Database::query('Papir',
    "UPDATE category_images SET image = '{$safeImage}' WHERE image_id = {$imageId}"
);
if (!$updRes['ok']) {
    @unlink($destPath);
    echo json_encode(array('ok' => false, 'error' => 'DB update failed'));
    exit;
}

// Delete old file
if ($oldRelImage !== '') {
    $oldPath = $imageBase . ltrim($oldRelImage, '/');
    if (file_exists($oldPath)) {
        @unlink($oldPath);
    }
}

// Cascade: update categoria.image + off/mff oc_category.image
Database::update('Papir', 'categoria',
    array('image' => $relImage),
    array('category_id' => $entityId)
);
require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
$sync = new SiteSyncService();
$siteMappings = Database::fetchAll('Papir',
    "SELECT csm.site_id, csm.site_category_id FROM category_site_mapping csm
     JOIN sites s ON s.site_id = csm.site_id AND s.status = 1
     WHERE csm.category_id = {$entityId}");
if ($siteMappings['ok']) {
    foreach ($siteMappings['rows'] as $sm) {
        $sync->categoryUpdate((int)$sm['site_id'], (int)$sm['site_category_id'],
            array('image' => $relImage));
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
