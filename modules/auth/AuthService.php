<?php
namespace Papir\Crm;



/**
 * AuthService — управління сесіями і правами.
 * Підключається з index.php через session_start() до обробки маршруту.
 */
class AuthService
{
    const SESSION_COOKIE = 'papir_sid';
    const SESSION_TTL    = 86400 * 30; // 30 днів

    // ── Поточний користувач (кеш на запит) ──────────────────────────────────
    private static $_user = null;
    private static $_loaded = false;

    /**
     * Завантажити поточного користувача з сесії.
     * Викликається автоматично при першому зверненні до getCurrentUser().
     */
    public static function load()
    {
        if (self::$_loaded) { return; }
        self::$_loaded = true;

        $sid = isset($_COOKIE[self::SESSION_COOKIE]) ? trim($_COOKIE[self::SESSION_COOKIE]) : '';
        if ($sid === '') { return; }

        $sid = preg_replace('/[^a-zA-Z0-9]/', '', $sid);
        if (strlen($sid) !== 64) { return; }

        $r = \Database::fetchRow('Papir',
            "SELECT s.user_id, s.expires_at,
                    u.display_name, u.initials, u.email, u.phone, u.status,
                    u.employee_id,
                    COALESCE(NULLIF(e.full_name,''), u.display_name) AS full_name,
                    r.role_id, r.name AS role_name, r.is_admin
             FROM auth_sessions s
             JOIN auth_users u ON u.user_id = s.user_id
             JOIN auth_roles r ON r.role_id = u.role_id
             LEFT JOIN employee e ON e.id = u.employee_id
             WHERE s.session_id = '" . \Database::escape('Papir', $sid) . "'
               AND s.expires_at > NOW()
               AND u.status = 'active'
             LIMIT 1");

        if (!$r['ok'] || empty($r['row'])) {
            // Видаляємо протухлу куку
            setcookie(self::SESSION_COOKIE, '', time() - 3600, '/', '', false, true);
            return;
        }

        self::$_user = $r['row'];
        self::$_user['session_id'] = $sid;

        // Оновити last_active_at (не частіше ніж раз на 5 хвилин)
        \Database::query('Papir',
            "UPDATE auth_sessions SET last_active_at = NOW()
             WHERE session_id = '" . \Database::escape('Papir', $sid) . "'
               AND last_active_at < NOW() - INTERVAL 5 MINUTE");
    }

    /** Повертає масив з даними поточного користувача або null */
    public static function getCurrentUser()
    {
        self::load();
        return self::$_user;
    }

    /** Чи є активна сесія */
    public static function isLoggedIn()
    {
        return self::getCurrentUser() !== null;
    }

    /** Чи є адмін */
    public static function isAdmin()
    {
        $u = self::getCurrentUser();
        return $u && !empty($u['is_admin']);
    }

    /**
     * Перевірка права на ресурс.
     * @param string $resourceKey  Ключ модуля ('catalog', 'prices', ...)
     * @param string $right        'read' | 'edit' | 'delete'
     */
    public static function can($resourceKey, $right = 'read')
    {
        $u = self::getCurrentUser();
        if (!$u) { return false; }
        if (!empty($u['is_admin'])) { return true; }

        $col = 'can_' . $right;
        $r = \Database::fetchRow('Papir',
            "SELECT {$col} FROM auth_permissions
             WHERE role_id = " . (int)$u['role_id'] . "
               AND resource_type = 'module'
               AND resource_key = '" . \Database::escape('Papir', $resourceKey) . "'
             LIMIT 1");

        return $r['ok'] && !empty($r['row'][$col]);
    }

    /**
     * Створити нову сесію після успішної аутентифікації.
     */
    public static function createSession($userId)
    {
        $sid = \bin2hex(\openssl_random_pseudo_bytes(32)); // 64 символи, PHP 5.6 сумісно
        $expires = date('Y-m-d H:i:s', time() + self::SESSION_TTL);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : '';

        \Database::insert('Papir', 'auth_sessions', array(
            'session_id'     => $sid,
            'user_id'        => $userId,
            'ip_address'     => $ip,
            'user_agent'     => $ua,
            'expires_at'     => $expires,
        ));

        setcookie(self::SESSION_COOKIE, $sid, time() + self::SESSION_TTL, '/', '', false, true);
        return $sid;
    }

    /**
     * Завершити поточну сесію.
     */
    public static function logout()
    {
        $sid = isset($_COOKIE[self::SESSION_COOKIE]) ? $_COOKIE[self::SESSION_COOKIE] : '';
        if ($sid !== '') {
            $sid = preg_replace('/[^a-zA-Z0-9]/', '', $sid);
            \Database::query('Papir',
                "DELETE FROM auth_sessions WHERE session_id = '" . \Database::escape('Papir', $sid) . "'");
        }
        setcookie(self::SESSION_COOKIE, '', time() - 3600, '/', '', false, true);
        self::$_user   = null;
        self::$_loaded = false;
    }

    /**
     * Записати подію у лог активності.
     */
    public static function log($action, $resourceType = null, $resourceId = null, $detail = null)
    {
        $u   = self::getCurrentUser();
        $sid = $u ? $u['session_id'] : null;
        $uid = $u ? $u['user_id']    : null;
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        \Database::insert('Papir', 'auth_activity_log', array(
            'user_id'       => $uid,
            'session_id'    => $sid,
            'action'        => $action,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'ip_address'    => $ip,
            'detail'        => $detail,
        ));
    }
}
