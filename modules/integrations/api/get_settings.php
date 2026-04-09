<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../IntegrationSettingsService.php';

require_once __DIR__ . '/../../auth/AuthService.php';
$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено'));
    exit;
}

$appKey = isset($_GET['app_key']) ? trim($_GET['app_key']) : '';
$registry = IntegrationSettingsService::getRegistryEntry($appKey);
if (!$registry) {
    echo json_encode(array('ok' => false, 'error' => 'Додаток не знайдено'));
    exit;
}

$settings = IntegrationSettingsService::getAll($appKey);

// Mask secret values for non-admin
$isAdmin = !empty($user['is_admin']);
$out = array();
foreach ($settings as $key => $info) {
    if ($info['secret'] && !$isAdmin) {
        $out[$key] = '••••••••';
    } else {
        $out[$key] = $info['value'];
    }
}

echo json_encode(array('ok' => true, 'settings' => $out));
