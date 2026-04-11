<?php
/**
 * Test Prom.ua API connection by fetching order status options.
 * Returns company info snippet to confirm token works.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../auth/AuthService.php';
require_once __DIR__ . '/../PromApi.php';

$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user || empty($user['is_admin'])) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = isset($input['token']) ? trim($input['token']) : '';

if ($token === '') {
    echo json_encode(array('ok' => false, 'error' => 'Токен не вказано'));
    exit;
}

$api = new PromApi($token);
$result = $api->getOrderStatusOptions();

if (!empty($result['ok'])) {
    echo json_encode(array('ok' => true, 'message' => 'З\'єднання успішне'));
} else {
    $err = isset($result['error']) ? $result['error'] : 'Невідома помилка';
    echo json_encode(array('ok' => false, 'error' => $err));
}
