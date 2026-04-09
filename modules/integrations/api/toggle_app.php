<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../IntegrationSettingsService.php';
require_once __DIR__ . '/../../auth/AuthService.php';

$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user || empty($user['is_admin'])) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['app_key'])) {
    echo json_encode(array('ok' => false, 'error' => 'Невірні дані'));
    exit;
}

$appKey  = $input['app_key'];
$isActive = !empty($input['is_active']) ? '1' : '0';

IntegrationSettingsService::saveAll($appKey, array(
    array('key' => 'is_active', 'value' => $isActive, 'secret' => 0),
), $user['user_id']);

echo json_encode(array('ok' => true, 'is_active' => $isActive));