<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';
require_once __DIR__ . '/../../shared/AlphaSmsService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
if ($phone === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть номер телефону'));
    exit;
}

// Нормалізуємо для пошуку
$normalized = \AlphaSmsService::normalizePhone($phone);
$user = \Papir\Crm\UserRepository::findByPhone($phone);
if (!$user && $normalized) {
    $user = \Papir\Crm\UserRepository::findByPhone($normalized);
}

if (!$user) {
    echo json_encode(array('ok' => false, 'error' => 'Користувача з таким номером не знайдено'));
    exit;
}

if ($user['status'] === 'blocked') {
    echo json_encode(array('ok' => false, 'error' => 'Акаунт заблоковано'));
    exit;
}

$result = \Papir\Crm\OtpService::sendSms($phone);

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => $result['error']));
    exit;
}

echo json_encode(array(
    'ok'         => true,
    'phone'      => $result['phone'],
    'expires_at' => $result['expires_at'],
));
