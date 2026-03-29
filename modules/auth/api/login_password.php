<?php
/**
 * POST phone + password → створює сесію.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$phone    = isset($_POST['phone'])    ? trim($_POST['phone'])    : '';
$password = isset($_POST['password']) ? $_POST['password']       : '';

if ($phone === '' || $password === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть телефон і пароль'));
    exit;
}

$user = \Papir\Crm\UserRepository::findByPhone($phone);
if (!$user) {
    // Не розкриваємо чи існує такий номер
    echo json_encode(array('ok' => false, 'error' => 'Невірний телефон або пароль'));
    exit;
}

if ($user['status'] === 'blocked') {
    echo json_encode(array('ok' => false, 'error' => 'Акаунт заблоковано'));
    exit;
}

if (empty($user['password_hash'])) {
    echo json_encode(array('ok' => false, 'error' => 'Пароль не встановлено. Увійдіть через SMS-код.'));
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний телефон або пароль'));
    exit;
}

// Активуємо якщо pending
if ($user['status'] === 'pending') {
    \Database::update('Papir', 'auth_users',
        array('status' => 'active'),
        array('user_id' => (int)$user['user_id']));
}

$sid = \Papir\Crm\AuthService::createSession($user['user_id']);
\Papir\Crm\AuthService::log('login', 'user', $user['user_id'], 'password');

$settings = \Papir\Crm\UserRepository::getSettings($user['user_id']);
$redirect = isset($settings['home_screen']) ? $settings['home_screen'] : '/catalog';

echo json_encode(array(
    'ok'       => true,
    'redirect' => $redirect,
));
