<?php
/**
 * ClientPortalService — токени доступу та генерація публічного URL
 * для клієнтського порталу замовлення.
 *
 * Використання з TaskQueueRunner::resolveVars() через плейсхолдер
 * {{portal.link}} у текстах кроків сценаріїв.
 */

require_once __DIR__ . '/../database/src/Database.php';
require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';

class ClientPortalService
{
    const DB = 'Papir';
    const SHORT_LEN = 6;

    /**
     * Повертає готовий публічний URL для замовлення.
     * Якщо токен ще не існує — створює.
     *
     * @param int $orderId
     * @return string  повний URL або порожній рядок якщо $orderId невалідний
     */
    public static function getOrCreateUrl($orderId)
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) return '';

        $code = self::getOrCreateShortCode($orderId);
        if (!$code) return '';

        $base = IntegrationSettingsService::get('client_portal', 'portal_base_url', 'https://officetorg.com.ua');
        $base = rtrim($base, '/');
        return $base . '/p/' . $code;
    }

    /**
     * Повертає URL для субсторінки порталу (invoice, requisites, delivery_note),
     * використовуючи той самий short_code що й /p/{code}.
     *
     * @param int    $orderId
     * @param string $subpage  'invoice' | 'requisites' | 'delivery_note'
     * @return string
     */
    public static function getSubpageUrl($orderId, $subpage)
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) return '';
        $allowed = array('invoice', 'requisites', 'delivery_note');
        if (!in_array($subpage, $allowed, true)) return '';

        $code = self::getOrCreateShortCode($orderId);
        if (!$code) return '';

        $base = IntegrationSettingsService::get('client_portal', 'portal_base_url', 'https://officetorg.com.ua');
        $base = rtrim($base, '/');
        return $base . '/client_portal/' . $subpage . '?c=' . $code;
    }

    /**
     * Повертає short_code для замовлення, створюючи новий при потребі.
     */
    public static function getOrCreateShortCode($orderId)
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) return '';

        $r = Database::fetchRow(self::DB,
            "SELECT short_code FROM client_portal_tokens WHERE order_id = {$orderId} LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) {
            return $r['row']['short_code'];
        }

        // Нема — створюємо. Генеруємо унікальний код з обмеженою кількістю спроб.
        for ($i = 0; $i < 8; $i++) {
            $code  = self::randomCode(self::SHORT_LEN);
            $token = bin2hex(openssl_random_pseudo_bytes(24));

            $codeEsc  = Database::escape(self::DB, $code);
            $tokenEsc = Database::escape(self::DB, $token);

            $ins = Database::query(self::DB,
                "INSERT INTO client_portal_tokens (order_id, short_code, token)
                 VALUES ({$orderId}, '{$codeEsc}', '{$tokenEsc}')");
            if ($ins['ok']) {
                return $code;
            }
            // Duplicate short_code — пробуємо ще раз
        }
        return '';
    }

    /**
     * Знайти замовлення за short_code. Інкрементує view_count + last_viewed_at.
     *
     * @param string $code
     * @return int  order_id або 0 якщо не знайдено
     */
    public static function resolveByCode($code)
    {
        $code = preg_replace('/[^A-Za-z0-9]/', '', (string)$code);
        if ($code === '') return 0;

        $codeEsc = Database::escape(self::DB, $code);
        $r = Database::fetchRow(self::DB,
            "SELECT id, order_id FROM client_portal_tokens WHERE short_code = '{$codeEsc}' LIMIT 1");
        if (!$r['ok'] || empty($r['row'])) return 0;

        $tokenId = (int)$r['row']['id'];
        Database::query(self::DB,
            "UPDATE client_portal_tokens
             SET view_count = view_count + 1, last_viewed_at = NOW()
             WHERE id = {$tokenId}");

        return (int)$r['row']['order_id'];
    }

    /**
     * Безпечний алфавіт без легко плутаних символів (0/O, 1/l/I).
     */
    private static function randomCode($len)
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[mt_rand(0, $max)];
        }
        return $out;
    }
}