<?php
namespace Papir\Crm;



class UserRepository
{
    /** Список всіх користувачів з роллю */
    public static function getList()
    {
        $r = \Database::fetchAll('Papir',
            "SELECT u.user_id, u.display_name, u.initials, u.email, u.phone,
                    u.status, u.employee_id, u.created_at,
                    r.role_id, r.name AS role_name, r.is_admin,
                    e.full_name AS employee_name
             FROM auth_users u
             JOIN auth_roles r ON r.role_id = u.role_id
             LEFT JOIN employee e ON e.id = u.employee_id
             ORDER BY u.display_name");
        return $r['ok'] ? $r['rows'] : array();
    }

    /** Один користувач */
    public static function getById($userId)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT u.*, r.name AS role_name, r.is_admin
             FROM auth_users u
             JOIN auth_roles r ON r.role_id = u.role_id
             WHERE u.user_id = " . (int)$userId . " LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /** Знайти за телефоном (шукає і з '+', і без — у форматі AlphaSms 380...) */
    public static function findByPhone($phone)
    {
        // Нормалізуємо: лише цифри, без ведучого '+'
        $normalized = preg_replace('/\D/', '', $phone);
        // Також варіант зі знаком '+'
        $withPlus   = '+' . $normalized;

        $esc1 = \Database::escape('Papir', $normalized);
        $esc2 = \Database::escape('Papir', $withPlus);

        $r = \Database::fetchRow('Papir',
            "SELECT u.*, r.name AS role_name, r.is_admin
             FROM auth_users u
             JOIN auth_roles r ON r.role_id = u.role_id
             WHERE u.phone IN ('{$esc1}', '{$esc2}') LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /** Знайти за email */
    public static function findByEmail($email)
    {
        $email = \Database::escape('Papir', strtolower(trim($email)));
        $r = \Database::fetchRow('Papir',
            "SELECT u.*, r.name AS role_name, r.is_admin
             FROM auth_users u
             JOIN auth_roles r ON r.role_id = u.role_id
             WHERE u.email = '{$email}' LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /** Способи входу користувача */
    public static function getLoginMethods($userId)
    {
        $r = \Database::fetchAll('Papir',
            "SELECT * FROM auth_login_methods WHERE user_id = " . (int)$userId);
        return $r['ok'] ? $r['rows'] : array();
    }

    /** Налаштування користувача */
    public static function getSettings($userId)
    {
        $r = \Database::fetchRow('Papir',
            "SELECT * FROM auth_user_settings WHERE user_id = " . (int)$userId . " LIMIT 1");
        if ($r['ok'] && $r['row']) { return $r['row']; }
        return array('user_id' => $userId, 'home_screen' => '/catalog', 'theme' => 'light');
    }

    /** Зберегти/оновити налаштування */
    public static function saveSettings($userId, $homeScreen, $theme = 'light')
    {
        \Database::upsertOne('Papir', 'auth_user_settings',
            array('user_id' => (int)$userId, 'home_screen' => $homeScreen, 'theme' => $theme),
            'user_id');
    }

    /** Зберегти/оновити метод входу */
    public static function upsertLoginMethod($userId, $provider, $providerId)
    {
        // Якщо такий provider_id вже у когось іншого — не оновлювати
        $esc = \Database::escape('Papir', $providerId);
        $prov = \Database::escape('Papir', $provider);
        $r = \Database::fetchRow('Papir',
            "SELECT user_id FROM auth_login_methods
             WHERE provider = '{$prov}' AND provider_id = '{$esc}' LIMIT 1");
        if ($r['ok'] && $r['row'] && (int)$r['row']['user_id'] !== (int)$userId) {
            return false; // вже зайнятий іншим
        }
        \Database::upsertOne('Papir', 'auth_login_methods',
            array('user_id' => (int)$userId, 'provider' => $provider,
                  'provider_id' => $providerId, 'is_verified' => 1),
            'method_id');
        return true;
    }

    /** Ініціали з імені (перші літери слів, макс 2) */
    public static function makeInitials($name)
    {
        $words = preg_split('/\s+/u', trim($name));
        $init  = '';
        foreach ($words as $w) {
            if ($w === '') { continue; }
            $init .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
            if (mb_strlen($init, 'UTF-8') >= 2) { break; }
        }
        return $init ?: '??';
    }
}
