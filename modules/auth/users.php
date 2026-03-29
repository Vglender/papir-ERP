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

$users   = UserRepository::getList();
$roles   = RoleRepository::getAll();
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected = null;
$selSettings = array('home_screen' => '/catalog');
if ($selectedId > 0) {
    $selected    = UserRepository::getById($selectedId);
    $selSettings = UserRepository::getSettings($selectedId);
}

// Список співробітників для лінкування
$empRows = \Database::fetchAll('Papir',
    "SELECT id, full_name, phone, email FROM employee WHERE status=1 ORDER BY full_name");
$employees = $empRows['ok'] ? $empRows['rows'] : array();

$title     = 'Користувачі';
$activeNav = 'system';
$subNav    = 'users';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/users.php';
require_once __DIR__ . '/../shared/layout_end.php';
