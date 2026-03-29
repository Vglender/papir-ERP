<?php
require_once __DIR__ . '/auth_bootstrap.php';

use Papir\Crm\AuthService;
use Papir\Crm\RoleRepository;

if (!AuthService::isAdmin()) {
    http_response_code(403);
    echo 'Недостатньо прав'; exit;
}

$roles      = RoleRepository::getAll();
$modules    = RoleRepository::getModuleList();
$selectedId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selected   = null;
$perms      = array();
if ($selectedId > 0) {
    $selected = RoleRepository::getById($selectedId);
    $perms    = RoleRepository::getPermissions($selectedId);
}

$title     = 'Ролі та права';
$activeNav = 'system';
$subNav    = 'roles';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/roles.php';
require_once __DIR__ . '/../shared/layout_end.php';
