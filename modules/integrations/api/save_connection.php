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
if (!$input || empty($input['app_key']) || empty($input['name'])) {
    echo json_encode(array('ok' => false, 'error' => 'Невірні дані'));
    exit;
}

$registry = IntegrationSettingsService::getRegistryEntry($input['app_key']);
if (!$registry) {
    echo json_encode(array('ok' => false, 'error' => 'Додаток не знайдено'));
    exit;
}

$id = IntegrationSettingsService::saveConnection($input);
echo json_encode(array('ok' => true, 'id' => $id));