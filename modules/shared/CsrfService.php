<?php
/**
 * CsrfService — защита от CSRF-атак.
 *
 * Генерирует и валидирует токены на основе session_id.
 * Токен = HMAC-SHA256(session_id, secret_key), привязан к сессии пользователя.
 */
class CsrfService
{
    private static $secretKey = null;

    private static function getSecretKey()
    {
        if (self::$secretKey !== null) {
            return self::$secretKey;
        }

        $keyFile = __DIR__ . '/../../config/csrf_key.php';
        if (file_exists($keyFile)) {
            self::$secretKey = require $keyFile;
        } else {
            // Генерируем ключ при первом использовании
            $key = bin2hex(openssl_random_pseudo_bytes(32));
            $dir = dirname($keyFile);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            file_put_contents($keyFile, '<?php return ' . var_export($key, true) . ';' . "\n");
            self::$secretKey = $key;
        }

        return self::$secretKey;
    }

    /**
     * Сгенерировать CSRF-токен для текущей сессии.
     */
    public static function token()
    {
        $sid = isset($_COOKIE[\Papir\Crm\AuthService::SESSION_COOKIE])
            ? $_COOKIE[\Papir\Crm\AuthService::SESSION_COOKIE]
            : '';

        if ($sid === '') {
            return '';
        }

        return hash_hmac('sha256', $sid, self::getSecretKey());
    }

    /**
     * Проверить CSRF-токен из запроса.
     *
     * @param string|null $token Токен из POST/header. Если null — берётся из $_POST['_csrf']
     * @return bool
     */
    public static function verify($token = null)
    {
        if ($token === null) {
            $token = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
        }

        if ($token === '' || $token === null) {
            return false;
        }

        $expected = self::token();
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Вернуть HTML hidden input с токеном.
     */
    public static function field()
    {
        $token = self::token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}