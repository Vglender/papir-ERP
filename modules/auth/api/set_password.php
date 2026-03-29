<?php
/**
 * POST phone + otp_token + password → зберігає пароль і створює сесію.
 * otp_token — підписаний токен виданий після успішної OTP-верифікації.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';
require_once __DIR__ . '/../../shared/AlphaSmsService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$phone    = isset($_POST['phone'])     ? trim($_POST['phone'])  : '';
$token    = isset($_POST['otp_token']) ? trim($_POST['otp_token']) : '';
$password = isset($_POST['password'])  ? $_POST['password']     : '';

if ($phone === '' || $token === '' || $password === '') {
    echo json_encode(array('ok' => false, 'error' => 'Невірні параметри'));
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(array('ok' => false, 'error' => 'Пароль має бути не менше 6 символів'));
    exit;
}

// Перевіряємо OTP-токен (зберігається в auth_otp_codes як використаний, але з token)
$escPhone = \Database::escape('Papir', \AlphaSmsService::normalizePhone($phone) ?: $phone);
$escToken = \Database::escape('Papir', $token);

$r = \Database::fetchRow('Papir',
    "SELECT otp_id FROM auth_otp_codes
     WHERE target IN ('{$escPhone}', '" . \Database::escape('Papir', $phone) . "')
       AND token = '{$escToken}'
       AND token_expires_at > NOW()
       AND provider = 'sms'
     ORDER BY otp_id DESC LIMIT 1");

if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Токен недійсний або застарів. Пройдіть SMS-верифікацію знову.'));
    exit;
}

// Анулюємо токен
\Database::query('Papir',
    "UPDATE auth_otp_codes SET token = NULL, token_expires_at = NULL WHERE otp_id = " . (int)$r['row']['otp_id']);

$user = \Papir\Crm\UserRepository::findByPhone($phone);
if (!$user) {
    echo json_encode(array('ok' => false, 'error' => 'Користувача не знайдено'));
    exit;
}

// Зберігаємо пароль
$hash = password_hash($password, PASSWORD_BCRYPT);
\Database::update('Papir', 'auth_users',
    array('password_hash' => $hash, 'status' => 'active'),
    array('user_id' => (int)$user['user_id']));

\Papir\Crm\UserRepository::upsertLoginMethod($user['user_id'], 'sms', \AlphaSmsService::normalizePhone($phone) ?: $phone);

$sid = \Papir\Crm\AuthService::createSession($user['user_id']);
\Papir\Crm\AuthService::log('set_password', 'user', $user['user_id']);

$settings = \Papir\Crm\UserRepository::getSettings($user['user_id']);
$redirect = isset($settings['home_screen']) ? $settings['home_screen'] : '/catalog';

echo json_encode(array('ok' => true, 'redirect' => $redirect));
