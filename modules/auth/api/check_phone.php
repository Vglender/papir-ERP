<?php
/**
 * POST phone → повертає чи є у користувача пароль.
 * Логін-форма використовує це щоб показати поле пароля або OTP.
 */
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

$user = \Papir\Crm\UserRepository::findByPhone($phone);
if (!$user) {
    echo json_encode(array('ok' => false, 'error' => 'Користувача з таким номером не знайдено'));
    exit;
}

if ($user['status'] === 'blocked') {
    echo json_encode(array('ok' => false, 'error' => 'Акаунт заблоковано'));
    exit;
}

echo json_encode(array(
    'ok'           => true,
    'has_password' => !empty($user['password_hash']),
    'name'         => $user['display_name'],
));