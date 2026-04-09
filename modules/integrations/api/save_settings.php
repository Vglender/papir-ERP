<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../IntegrationSettingsService.php';

// Auth check
require_once __DIR__ . '/../../auth/AuthService.php';
$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user || empty($user['is_admin'])) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['app_key']) || !isset($input['settings'])) {
    echo json_encode(array('ok' => false, 'error' => 'Невірні дані'));
    exit;
}

$appKey   = $input['app_key'];
$registry = IntegrationSettingsService::getRegistryEntry($appKey);
if (!$registry) {
    echo json_encode(array('ok' => false, 'error' => 'Додаток не знайдено'));
    exit;
}

// Validate setting keys against registry
$allowedKeys = array();
if (!empty($registry['settings'])) {
    foreach ($registry['settings'] as $field) {
        $allowedKeys[$field['key']] = !empty($field['secret']);
    }
}

$toSave = array();
foreach ($input['settings'] as $item) {
    $key = isset($item['key']) ? trim($item['key']) : '';
    if ($key === '' || !isset($allowedKeys[$key])) continue;
    $toSave[] = array(
        'key'    => $key,
        'value'  => isset($item['value']) ? $item['value'] : '',
        'secret' => $allowedKeys[$key] ? 1 : 0,
    );
}

IntegrationSettingsService::saveAll($appKey, $toSave, $user['user_id']);

echo json_encode(array('ok' => true));
