<?php
/**
 * MsExchangeGuard — checks CRUD-level exchange settings for MoySklad.
 *
 * Usage in webhook:
 *   require_once __DIR__ . '/../../integrations/MsExchangeGuard.php';
 *   MsExchangeGuard::allowOrSkip('order', 'C', 'from');  // CREATE from MS
 *   MsExchangeGuard::allowOrSkip('demand', 'U', 'from');  // UPDATE from MS
 *
 * Usage in push-to-MS code:
 *   if (!MsExchangeGuard::isAllowed('order', 'U', 'to')) return;
 */

require_once __DIR__ . '/IntegrationSettingsService.php';

class MsExchangeGuard
{
    private static $cache = array();

    /**
     * Check if a specific CRUD operation is allowed.
     * @param string $doc    'order' | 'demand' | 'finance'
     * @param string $op     'C' | 'U' | 'D'
     * @param string $dir    'from' (MS→Papir) | 'to' (Papir→MS)
     * @return bool
     */
    public static function isAllowed($doc, $op, $dir)
    {
        $key = "ms_{$doc}_{$op}_{$dir}";
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = IntegrationSettingsService::get('moysklad', $key, '1');
        }
        return self::$cache[$key] === '1';
    }

    /**
     * If operation not allowed — respond 200 OK and exit (for webhooks).
     */
    public static function allowOrSkip($doc, $op, $dir)
    {
        if (!self::isAllowed($doc, $op, $dir)) {
            header('Content-Type: application/json');
            echo json_encode(array(
                'ok'      => true,
                'skipped' => true,
                'reason'  => "crud_disabled:{$doc}_{$op}_{$dir}",
            ));
            exit;
        }
    }
}