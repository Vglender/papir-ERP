<?php
require_once __DIR__ . '/auth_bootstrap.php';

use Papir\Crm\AuthService;
use Papir\Crm\UserRepository;
use Papir\Crm\RoleRepository;

// Тільки адмін
if (!AuthService::isAdmin()) {
    http_response_code(403);
    echo 'Недостатньо прав'; exit;
}

$roles = RoleRepository::getAll();

// Вибраний співробітник або користувач
$selectedEmpId  = isset($_GET['emp'])  ? (int)$_GET['emp']  : 0;
$selectedUserId = isset($_GET['id'])   ? (int)$_GET['id']   : 0;

// Всі співробітники з прив'язаним користувачем
$empRows = \Database::fetchAll('Papir',
    "SELECT e.id AS emp_id, e.full_name, e.position_name, e.phone AS emp_phone, e.email AS emp_email,
            e.status AS emp_status,
            u.user_id, u.display_name AS user_name, u.initials, u.status AS user_status,
            u.phone AS user_phone, u.email AS user_email,
            r.name AS role_name, r.is_admin, r.role_id
     FROM employee e
     LEFT JOIN auth_users u ON u.employee_id = e.id
     LEFT JOIN auth_roles r ON r.role_id = u.role_id
     ORDER BY e.status DESC, e.full_name");
$employees = $empRows['ok'] ? $empRows['rows'] : array();

// Користувачі без прив'язки до співробітника
$orphanRows = \Database::fetchAll('Papir',
    "SELECT u.user_id, u.display_name, u.initials, u.status AS user_status,
            u.phone AS user_phone, u.email AS user_email,
            r.name AS role_name, r.is_admin, r.role_id
     FROM auth_users u
     LEFT JOIN auth_roles r ON r.role_id = u.role_id
     WHERE u.employee_id IS NULL
     ORDER BY u.display_name");
$orphanUsers = $orphanRows['ok'] ? $orphanRows['rows'] : array();

// Дані вибраного рядка
$selEmp  = null;
$selUser = null;
$selLinkedUser = null;
$selSettings = array('home_screen' => '/catalog');

if ($selectedEmpId > 0) {
    foreach ($employees as $e) {
        if ((int)$e['emp_id'] === $selectedEmpId) {
            $selEmp = $e;
            if (!empty($e['user_id'])) {
                $selLinkedUser = $e;
                $selSettings = UserRepository::getSettings((int)$e['user_id']);
            }
            break;
        }
    }
}
if ($selectedUserId > 0) {
    $selUser = UserRepository::getById($selectedUserId);
    if ($selUser) {
        $selSettings = UserRepository::getSettings($selectedUserId);
    }
}

// Список співробітників без прив'язки до юзера (для лінкування в user-формі)
$freeEmpRows = \Database::fetchAll('Papir',
    "SELECT e.id, e.full_name FROM employee e
     WHERE e.status=1
       AND NOT EXISTS (SELECT 1 FROM auth_users u WHERE u.employee_id = e.id)
     ORDER BY e.full_name");
$freeEmployees = $freeEmpRows['ok'] ? $freeEmpRows['rows'] : array();

$title     = 'Співробітники та користувачі';
$activeNav = 'system';
$subNav    = 'users';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/users.php';
require_once __DIR__ . '/../shared/layout_end.php';