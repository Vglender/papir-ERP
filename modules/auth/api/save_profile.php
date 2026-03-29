<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';




if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$me = \Papir\Crm\AuthService::getCurrentUser();
if (!$me) {
    echo json_encode(array('ok' => false, 'error' => 'Не авторизовано'));
    exit;
}

$homeScreen = isset($_POST['home_screen']) ? trim($_POST['home_screen']) : '/catalog';
$theme      = isset($_POST['theme'])       ? trim($_POST['theme'])       : 'light';

// Whitelist для home_screen
$allowed = array('/catalog', '/prices', '/customerorder', '/counterparties',
                 '/payments', '/action', '/manufacturers', '/categories');
if (!in_array($homeScreen, $allowed)) {
    $homeScreen = '/catalog';
}
if (!in_array($theme, array('light', 'dark'))) {
    $theme = 'light';
}

\Papir\Crm\UserRepository::saveSettings($me['user_id'], $homeScreen, $theme);

echo json_encode(array('ok' => true));
