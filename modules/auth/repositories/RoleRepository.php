<?php
namespace Papir\Crm;



class RoleRepository
{
    /** Всі ролі */
    public static function getAll()
    {
        $r = \Database::fetchAll('Papir',
            "SELECT r.role_id, r.name, r.description, r.is_admin, r.created_at,
                    COUNT(u.user_id) AS users_count
             FROM auth_roles r
             LEFT JOIN auth_users u ON u.role_id = r.role_id
             GROUP BY r.role_id
             ORDER BY r.is_admin DESC, r.name");
        return $r['ok'] ? $r['rows'] : array();
    }

    /** Одна роль */
    public static function getById($roleId)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM auth_roles WHERE role_id = " . (int)$roleId . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /** Права ролі (масив: resource_key => ['read', 'edit', 'delete']) */
    public static function getPermissions($roleId)
    {
        $r = \Database::fetchAll('Papir',
            "SELECT resource_type, resource_key, can_read, can_edit, can_delete
             FROM auth_permissions
             WHERE role_id = " . (int)$roleId);
        if (!$r['ok']) { return array(); }
        $out = array();
        foreach ($r['rows'] as $row) {
            $out[$row['resource_key']] = array(
                'read'   => (bool)$row['can_read'],
                'edit'   => (bool)$row['can_edit'],
                'delete' => (bool)$row['can_delete'],
            );
        }
        return $out;
    }

    /** Зберегти права ролі (повна заміна) */
    public static function savePermissions($roleId, array $permissions)
    {
        $roleId = (int)$roleId;
        \Database::query('Papir', "DELETE FROM auth_permissions WHERE role_id = {$roleId}");
        foreach ($permissions as $key => $rights) {
            $key = \Database::escape('Papir', $key);
            $r   = isset($rights['read'])   ? 1 : 0;
            $e   = isset($rights['edit'])   ? 1 : 0;
            $d   = isset($rights['delete']) ? 1 : 0;
            \Database::query('Papir',
                "INSERT INTO auth_permissions (role_id, resource_type, resource_key, can_read, can_edit, can_delete)
                 VALUES ({$roleId}, 'module', '{$key}', {$r}, {$e}, {$d})");
        }
    }

    /** Список модулів для матриці прав */
    public static function getModuleList()
    {
        return array(
            array('key' => 'catalog',         'label' => 'Каталог (товари, категорії, виробники)'),
            array('key' => 'prices',          'label' => 'Ціни та прайси'),
            array('key' => 'suppliers',       'label' => 'Постачальники'),
            array('key' => 'action',          'label' => 'Акції'),
            array('key' => 'customerorder',   'label' => 'Замовлення'),
            array('key' => 'payments',        'label' => 'Платежі'),
            array('key' => 'counterparties',  'label' => 'Контрагенти'),
            array('key' => 'attributes',      'label' => 'Атрибути'),
            array('key' => 'jobs',            'label' => 'Фонові процеси'),
            array('key' => 'integr',          'label' => 'Інтеграції'),
            array('key' => 'system',          'label' => 'Системні налаштування'),
        );
    }
}
