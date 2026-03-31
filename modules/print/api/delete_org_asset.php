<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orgId     = isset($_POST['org_id'])     ? (int)$_POST['org_id']          : 0;
$assetType = isset($_POST['asset_type']) ? trim($_POST['asset_type'])     : '';

$allowed = array('logo', 'stamp', 'signature');
if ($orgId <= 0 || !in_array($assetType, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid params'));
    exit;
}

$repo      = new OrganizationRepository();
$org       = $repo->getById($orgId);
$fieldName = $assetType . '_path';
$oldPath   = ($org && isset($org[$fieldName])) ? $org[$fieldName] : null;

if ($oldPath) {
    $absPath = '/var/www/papir/' . ltrim($oldPath, '/');
    if (file_exists($absPath)) {
        @unlink($absPath);
    }
}

$repo->updateImageField($orgId, $fieldName, null);
echo json_encode(array('ok' => true));