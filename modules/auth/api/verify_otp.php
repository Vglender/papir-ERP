<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';
require_once __DIR__ . '/../../shared/AlphaSmsService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$code  = isset($_POST['code'])  ? trim($_POST['code'])  : '';
$mode  = isset($_POST['mode'])  ? trim($_POST['mode'])  : 'login';

if ($phone === '' || $code === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть телефон і код'));
    exit;
}

$verify = \Papir\Crm\OtpService::verifySms($phone, $code);
if (!$verify['ok']) {
    echo json_encode(array('ok' => false, 'error' => $verify['error']));
    exit;
}

$user = \Papir\Crm\UserRepository::findByPhone($verify['phone']);
if (!$user) {
    echo json_encode(array('ok' => false, 'error' => 'Користувача не знайдено'));
    exit;
}

if ($user['status'] === 'blocked') {
    echo json_encode(array('ok' => false, 'error' => 'Акаунт заблоковано'));
    exit;
}

// mode=set_password — видати токен для встановлення пароля, не створювати сесію
if ($mode === 'set_password') {
    $token        = bin2hex(openssl_random_pseudo_bytes(16));
    $tokenExpires = date('Y-m-d H:i:s', time() + 600);
    $escPhone = \Database::escape('Papir', $verify['phone']);
    \Database::query('Papir',
        "UPDATE auth_otp_codes SET token = '" . \Database::escape('Papir', $token) . "',
                token_expires_at = '{$tokenExpires}'
         WHERE target = '{$escPhone}' AND used_at IS NOT NULL
         ORDER BY otp_id DESC LIMIT 1");
    echo json_encode(array(
        'ok'        => true,
        'mode'      => 'set_password',
        'otp_token' => $token,
        'phone'     => $verify['phone'],
    ));
    exit;
}

// Звичайний вхід через OTP
if ($user['status'] === 'pending') {
    \Database::update('Papir', 'auth_users',
        array('status' => 'active'),
        array('user_id' => (int)$user['user_id']));
}

\Papir\Crm\UserRepository::upsertLoginMethod($user['user_id'], 'sms', $verify['phone']);
$sid = \Papir\Crm\AuthService::createSession($user['user_id']);
\Papir\Crm\AuthService::log('login', 'user', $user['user_id'], 'otp');

$settings = \Papir\Crm\UserRepository::getSettings($user['user_id']);
$redirect = isset($settings['home_screen']) ? $settings['home_screen'] : '/catalog';

echo json_encode(array('ok' => true, 'mode' => 'login', 'redirect' => $redirect));
