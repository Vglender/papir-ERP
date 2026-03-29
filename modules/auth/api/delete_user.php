<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';




if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

if (!\Papir\Crm\AuthService::isAdmin()) {
    echo json_encode(array('ok' => false, 'error' => 'Недостатньо прав'));
    exit;
}

$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($userId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'user_id required'));
    exit;
}

// Не можна видалити себе
$me = \Papir\Crm\AuthService::getCurrentUser();
if ($me && (int)$me['user_id'] === $userId) {
    echo json_encode(array('ok' => false, 'error' => 'Не можна видалити власний акаунт'));
    exit;
}

\Papir\Crm\AuthService::log('delete', 'auth_user', $userId);
$r = \Database::query('Papir', "DELETE FROM auth_users WHERE user_id = {$userId}");
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка видалення'));
    exit;
}
echo json_encode(array('ok' => true));
