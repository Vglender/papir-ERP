<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../MffFtpSync.php';
require_once __DIR__ . '/../ProductImageService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$imageId = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
$siteId  = isset($_POST['site_id'])  ? (int)$_POST['site_id']  : 0;
$enabled = isset($_POST['enabled'])  ? (int)$_POST['enabled']  : 0;

if ($imageId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'image_id required'));
    exit;
}
if ($siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'site_id required'));
    exit;
}

$service = new ProductImageService();
$result  = $service->toggleSite($imageId, $siteId, $enabled === 1);
echo json_encode($result);
