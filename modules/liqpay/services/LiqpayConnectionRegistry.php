<?php
/**
 * LiqpayConnectionRegistry — мапа public_key → з'єднання + site.
 *
 * Читає активні з'єднання app_key='liqpay' з integration_connections.
 * metadata має містити: {"public_key": "i...", "site_id": 1, "site_code": "off"}.
 * api_key зберігає private_key (секрет).
 *
 * Використання:
 *   $conn = LiqpayConnectionRegistry::findByPublicKey('i82261156247');
 *   if ($conn) { $client = new LiqpayClient($conn['public_key'], $conn['private_key']); ... }
 */
class LiqpayConnectionRegistry
{
    private static $cache = null;

    /**
     * Усі активні LiqPay з'єднання (нормалізовано).
     * @return array each: ['id', 'name', 'private_key', 'public_key', 'site_id', 'site_code']
     */
    public static function getAll()
    {
        if (self::$cache !== null) return self::$cache;

        $r = Database::fetchAll('Papir',
            "SELECT id, name, api_key, metadata FROM integration_connections
             WHERE app_key='liqpay' AND is_active=1");
        $out = array();
        if ($r['ok']) {
            foreach ($r['rows'] as $row) {
                $meta = array();
                if (!empty($row['metadata'])) {
                    $decoded = json_decode($row['metadata'], true);
                    if (is_array($decoded)) $meta = $decoded;
                }
                $out[] = array(
                    'id'          => (int)$row['id'],
                    'name'        => $row['name'],
                    'private_key' => $row['api_key'],
                    'public_key'  => isset($meta['public_key']) ? $meta['public_key'] : '',
                    'site_id'     => isset($meta['site_id'])    ? (int)$meta['site_id'] : 0,
                    'site_code'   => isset($meta['site_code'])  ? $meta['site_code'] : '',
                );
            }
        }
        self::$cache = $out;
        return $out;
    }

    public static function findByPublicKey($publicKey)
    {
        foreach (self::getAll() as $conn) {
            if ($conn['public_key'] === $publicKey) return $conn;
        }
        return null;
    }

    public static function findBySiteId($siteId)
    {
        $siteId = (int)$siteId;
        foreach (self::getAll() as $conn) {
            if ($conn['site_id'] === $siteId) return $conn;
        }
        return null;
    }

    public static function clearCache()
    {
        self::$cache = null;
    }
}