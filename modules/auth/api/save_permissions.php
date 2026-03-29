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

$roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
if ($roleId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'role_id required'));
    exit;
}

$role = \Papir\Crm\RoleRepository::getById($roleId);
if (!$role) {
    echo json_encode(array('ok' => false, 'error' => 'Роль не знайдено'));
    exit;
}

// Адмін-роль — права не можна обмежити
if ($role['is_admin']) {
    echo json_encode(array('ok' => true, 'note' => 'Адмін-роль має повний доступ'));
    exit;
}

// Дані: permissions[catalog][read]=1&permissions[catalog][edit]=1 ...
$raw = isset($_POST['permissions']) && is_array($_POST['permissions'])
    ? $_POST['permissions']
    : array();

$permissions = array();
$modules = \Papir\Crm\RoleRepository::getModuleList();
foreach ($modules as $m) {
    $key = $m['key'];
    $p   = isset($raw[$key]) ? $raw[$key] : array();
    $permissions[$key] = array(
        'read'   => !empty($p['read'])   ? 1 : 0,
        'edit'   => !empty($p['edit'])   ? 1 : 0,
        'delete' => !empty($p['delete']) ? 1 : 0,
    );
}

\Papir\Crm\RoleRepository::savePermissions($roleId, $permissions);
\Papir\Crm\AuthService::log('edit', 'auth_role_permissions', $roleId);

echo json_encode(array('ok' => true));
