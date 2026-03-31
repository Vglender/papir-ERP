<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orgId     = isset($_POST['org_id'])    ? (int)$_POST['org_id']    : 0;
$assetType = isset($_POST['asset_type']) ? trim($_POST['asset_type']) : '';

$allowedTypes = array('logo', 'stamp', 'signature');
if ($orgId <= 0 || !in_array($assetType, $allowedTypes)) {
    echo json_encode(array('ok' => false, 'error' => 'org_id and valid asset_type required'));
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $err = isset($_FILES['image']) ? $_FILES['image']['error'] : UPLOAD_ERR_NO_FILE;
    echo json_encode(array('ok' => false, 'error' => 'Upload error: ' . $err));
    exit;
}

$tmpPath  = (string)$_FILES['image']['tmp_name'];
$fileSize = (int)$_FILES['image']['size'];

if ($fileSize > 3 * 1024 * 1024) {
    echo json_encode(array('ok' => false, 'error' => 'Max 3MB'));
    exit;
}

$imgInfo = @getimagesize($tmpPath);
if ($imgInfo === false) {
    echo json_encode(array('ok' => false, 'error' => 'Not a valid image'));
    exit;
}

$mime         = $imgInfo['mime'];
$allowedMimes = array('image/jpeg', 'image/png', 'image/webp');
if (!in_array($mime, $allowedMimes)) {
    echo json_encode(array('ok' => false, 'error' => 'Supported: JPG, PNG, WebP'));
    exit;
}

$uploadDir = '/var/www/papir/storage/org/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    echo json_encode(array('ok' => false, 'error' => 'Cannot create storage dir'));
    exit;
}

// Load image, keep transparency for stamp/signature (PNG), flatten logo
$src = null;
switch ($mime) {
    case 'image/jpeg': $src = @imagecreatefromjpeg($tmpPath); break;
    case 'image/png':  $src = @imagecreatefrompng($tmpPath);  break;
    case 'image/webp': $src = @imagecreatefromwebp($tmpPath); break;
}
if (!$src) {
    echo json_encode(array('ok' => false, 'error' => 'Failed to decode image'));
    exit;
}

$origW  = imagesx($src);
$origH  = imagesy($src);

// Resize: logo max 400px, stamp/signature max 300px
$maxDim = ($assetType === 'logo') ? 400 : 300;
if ($origW > $maxDim || $origH > $maxDim) {
    if ($origW >= $origH) {
        $newW = $maxDim;
        $newH = (int)round($origH * $maxDim / $origW);
    } else {
        $newH = $maxDim;
        $newW = (int)round($origW * $maxDim / $origH);
    }
    $dst = imagecreatetruecolor($newW, $newH);
    // Keep alpha for stamp/signature
    if ($assetType !== 'logo' && $mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        imagealphablending($dst, true);
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    imagedestroy($src);
    $src = $dst;
}

// Delete old file if exists
$repo = new OrganizationRepository();
$org  = $repo->getById($orgId);
if ($org) {
    $fieldName = $assetType . '_path';
    $oldPath   = isset($org[$fieldName]) ? $org[$fieldName] : null;
    if ($oldPath && file_exists('/var/www/papir/' . ltrim($oldPath, '/'))) {
        @unlink('/var/www/papir/' . ltrim($oldPath, '/'));
    }
}

$ext      = ($assetType !== 'logo' && $mime === 'image/png') ? 'png' : 'jpg';
$filename = 'org_' . $orgId . '_' . $assetType . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;
$relPath  = 'storage/org/' . $filename;

if ($ext === 'png') {
    $saved = imagepng($src, $destPath, 6);
} else {
    $saved = imagejpeg($src, $destPath, 90);
}
imagedestroy($src);

if (!$saved) {
    echo json_encode(array('ok' => false, 'error' => 'Failed to save file'));
    exit;
}

$fieldName = $assetType . '_path';
$repo->updateImageField($orgId, $fieldName, $relPath);

echo json_encode(array(
    'ok'   => true,
    'path' => $relPath,
    'url'  => '/storage/org/' . $filename,
));