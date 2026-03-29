<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';





if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

// Тільки адмін
if (!\Papir\Crm\AuthService::isAdmin()) {
    echo json_encode(array('ok' => false, 'error' => 'Недостатньо прав'));
    exit;
}

$userId         = isset($_POST['user_id'])         ? (int)$_POST['user_id']          : 0;
$displayName    = isset($_POST['display_name'])    ? trim($_POST['display_name'])    : '';
$phone          = isset($_POST['phone'])           ? trim($_POST['phone'])            : '';
$email          = isset($_POST['email'])           ? strtolower(trim($_POST['email'])): '';
$roleId         = isset($_POST['role_id'])         ? (int)$_POST['role_id']          : 0;
$status         = isset($_POST['status'])          ? trim($_POST['status'])           : 'active';
$employeeId     = isset($_POST['employee_id'])     ? (int)$_POST['employee_id']       : 0;
$createEmployee = isset($_POST['create_employee']) && $_POST['create_employee'] == '1';

if ($displayName === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть ім\'я користувача'));
    exit;
}
if ($roleId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть роль'));
    exit;
}
if (!in_array($status, array('active', 'blocked', 'pending'))) {
    $status = 'active';
}

$initials = \Papir\Crm\UserRepository::makeInitials($displayName);

$data = array(
    'display_name' => $displayName,
    'initials'     => $initials,
    'role_id'      => $roleId,
    'status'       => $status,
    'email'        => $email !== '' ? $email : null,
    'phone'        => $phone !== '' ? $phone : null,
    'employee_id'  => $employeeId > 0 ? $employeeId : null,
);

if ($userId > 0) {
    // Оновлення
    $r = \Database::update('Papir', 'auth_users', $data, array('user_id' => $userId));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
        exit;
    }
    \Papir\Crm\AuthService::log('edit', 'auth_user', $userId);
    echo json_encode(array('ok' => true, 'user_id' => $userId));
} else {
    // Створення
    // Якщо потрібно — створити запис співробітника
    if ($createEmployee && $employeeId <= 0) {
        $empR = \Database::insert('Papir', 'employee', array(
            'uuid'      => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
                mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
                mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)),
            'full_name' => $displayName,
            'email'     => $email !== '' ? $email : null,
            'phone'     => $phone !== '' ? $phone : null,
            'status'    => 1,
        ));
        if ($empR['ok'] && $empR['insert_id']) {
            $data['employee_id'] = (int)$empR['insert_id'];
        }
    }

    $r = \Database::insert('Papir', 'auth_users', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка створення. Можливо, такий email/телефон вже існує.'));
        exit;
    }
    $newId = $r['insert_id'];
    // Ініціалізувати налаштування
    \Database::insert('Papir', 'auth_user_settings',
        array('user_id' => $newId, 'home_screen' => '/catalog', 'theme' => 'light'));
    \Papir\Crm\AuthService::log('create', 'auth_user', $newId, $displayName);
    echo json_encode(array('ok' => true, 'user_id' => $newId));
}
