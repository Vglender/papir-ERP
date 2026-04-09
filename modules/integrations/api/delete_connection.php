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
$id = isset($input['id']) ? (int)$input['id'] : 0;
if (!$id) {
    echo json_encode(array('ok' => false, 'error' => 'ID не вказано'));
    exit;
}

IntegrationSettingsService::deleteConnection($id);
echo json_encode(array('ok' => true));