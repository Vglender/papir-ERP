<?php

require_once __DIR__ . '/../database/database.php';

class IntegrationSettingsService
{
    const DB = 'Papir';

    /**
     * Get all settings for an app.
     * @return array  key => value
     */
    public static function getAll($appKey)
    {
        $db  = Database::connection(self::DB);
        $esc = $db->real_escape_string($appKey);
        $res = $db->query("SELECT setting_key, setting_value, is_secret FROM integration_settings WHERE app_key = '$esc'");
        $out = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[$row['setting_key']] = array(
                    'value'  => $row['setting_value'],
                    'secret' => (int)$row['is_secret'],
                );
            }
            $res->free();
        }
        return $out;
    }

    /**
     * Get a single setting value.
     */
    public static function get($appKey, $settingKey, $default = null)
    {
        $db   = Database::connection(self::DB);
        $app  = $db->real_escape_string($appKey);
        $skey = $db->real_escape_string($settingKey);
        $res  = $db->query("SELECT setting_value FROM integration_settings WHERE app_key = '$app' AND setting_key = '$skey' LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $res->free();
            return $row['setting_value'];
        }
        return $default;
    }

    /**
     * Save multiple settings for an app.
     * @param string $appKey
     * @param array  $settings  array of ['key'=>..., 'value'=>..., 'secret'=>0|1]
     * @param int|null $userId
     */
    public static function saveAll($appKey, $settings, $userId = null)
    {
        $db  = Database::connection(self::DB);
        $app = $db->real_escape_string($appKey);
        $uid = $userId ? (int)$userId : 'NULL';

        foreach ($settings as $item) {
            $skey = $db->real_escape_string($item['key']);
            $sval = $db->real_escape_string($item['value']);
            $sec  = !empty($item['secret']) ? 1 : 0;

            $db->query("INSERT INTO integration_settings (app_key, setting_key, setting_value, is_secret, updated_by)
                        VALUES ('$app', '$skey', '$sval', $sec, $uid)
                        ON DUPLICATE KEY UPDATE setting_value = '$sval', is_secret = $sec, updated_by = $uid");
        }
    }

    /**
     * Get the registry entry for an app.
     */
    public static function getRegistryEntry($appKey)
    {
        $registry = require __DIR__ . '/registry.php';
        return isset($registry[$appKey]) ? $registry[$appKey] : null;
    }

    /**
     * Get all registry entries.
     */
    public static function getRegistry()
    {
        return require __DIR__ . '/registry.php';
    }

    /**
     * Get categories config.
     */
    public static function getCategories()
    {
        return array(
            'all'            => array('label' => 'Усі',          'icon' => 'grid'),
            'communications' => array('label' => 'Комунікації',  'icon' => 'message-circle'),
            'delivery'       => array('label' => 'Доставка',     'icon' => 'truck'),
            'finance'        => array('label' => 'Фінанси',      'icon' => 'credit-card'),
            'advertising'    => array('label' => 'Реклама',      'icon' => 'megaphone'),
            'analytics'      => array('label' => 'Аналітика',    'icon' => 'bar-chart'),
            'social'         => array('label' => 'Соцмережі',    'icon' => 'share-2'),
            'sites'          => array('label' => 'Сайти',        'icon' => 'globe'),
        );
    }

    /**
     * Check if app has any saved settings (i.e. is "connected").
     */
    public static function isConnected($appKey)
    {
        $db  = Database::connection(self::DB);
        $esc = $db->real_escape_string($appKey);
        $res = $db->query("SELECT 1 FROM integration_settings WHERE app_key = '$esc' AND setting_value IS NOT NULL AND setting_value != '' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $res->free();
            return true;
        }
        return false;
    }

    /**
     * Get connection status for all apps.
     * Checks both integration_settings and integration_connections.
     * @return array  appKey => bool
     */
    public static function getConnectionStatuses()
    {
        $db  = Database::connection(self::DB);
        $out = array();

        // Apps with settings
        $res = $db->query("SELECT DISTINCT app_key FROM integration_settings WHERE setting_value IS NOT NULL AND setting_value != ''");
        if ($res) {
            while ($row = $res->fetch_assoc()) { $out[$row['app_key']] = true; }
            $res->free();
        }
        // Apps with connections
        $res = $db->query("SELECT DISTINCT app_key FROM integration_connections WHERE api_key IS NOT NULL AND api_key != ''");
        if ($res) {
            while ($row = $res->fetch_assoc()) { $out[$row['app_key']] = true; }
            $res->free();
        }
        return $out;
    }

    /**
     * Check if app is active.
     */
    public static function isActive($appKey)
    {
        return self::get($appKey, 'is_active', '1') === '1';
    }

    // ── Connections (API keys / accounts) ────────────────────────────────────

    /**
     * Get all connections for an app.
     */
    public static function getConnections($appKey)
    {
        $db  = Database::connection(self::DB);
        $esc = $db->real_escape_string($appKey);
        $res = $db->query("SELECT * FROM integration_connections WHERE app_key = '$esc' ORDER BY is_default DESC, name");
        $out = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if ($row['metadata']) $row['metadata'] = json_decode($row['metadata'], true);
                $out[] = $row;
            }
            $res->free();
        }
        return $out;
    }

    /**
     * Get a single connection by id.
     */
    public static function getConnection($id)
    {
        $db  = Database::connection(self::DB);
        $res = $db->query("SELECT * FROM integration_connections WHERE id = " . (int)$id . " LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['metadata']) $row['metadata'] = json_decode($row['metadata'], true);
            $res->free();
            return $row;
        }
        return null;
    }

    /**
     * Get default connection for an app.
     */
    public static function getDefaultConnection($appKey)
    {
        $db  = Database::connection(self::DB);
        $esc = $db->real_escape_string($appKey);
        $res = $db->query("SELECT * FROM integration_connections WHERE app_key = '$esc' AND is_default = 1 LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['metadata']) $row['metadata'] = json_decode($row['metadata'], true);
            $res->free();
            return $row;
        }
        return null;
    }

    /**
     * Find connection by metadata field value (e.g. sender_ref).
     */
    public static function findConnectionByMeta($appKey, $metaKey, $metaValue)
    {
        $conns = self::getConnections($appKey);
        foreach ($conns as $c) {
            if (isset($c['metadata'][$metaKey]) && $c['metadata'][$metaKey] === $metaValue) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Save (insert or update) a connection.
     */
    public static function saveConnection($data)
    {
        $db = Database::connection(self::DB);
        $id = isset($data['id']) ? (int)$data['id'] : 0;

        $app  = $db->real_escape_string($data['app_key']);
        $name = $db->real_escape_string($data['name']);
        $key  = $db->real_escape_string(isset($data['api_key']) ? $data['api_key'] : '');
        $act  = !empty($data['is_active']) ? 1 : 0;
        $def  = !empty($data['is_default']) ? 1 : 0;
        $meta = isset($data['metadata']) ? $db->real_escape_string(json_encode($data['metadata'], JSON_UNESCAPED_UNICODE)) : 'null';

        if ($def) {
            // Reset other defaults
            $db->query("UPDATE integration_connections SET is_default = 0 WHERE app_key = '$app'");
        }

        if ($id) {
            $db->query("UPDATE integration_connections SET
                name = '$name', api_key = '$key', is_active = $act, is_default = $def,
                metadata = '$meta'
                WHERE id = $id");
            return $id;
        } else {
            $db->query("INSERT INTO integration_connections (app_key, name, api_key, is_active, is_default, metadata)
                        VALUES ('$app', '$name', '$key', $act, $def, '$meta')");
            return $db->insert_id;
        }
    }

    /**
     * Delete a connection.
     */
    public static function deleteConnection($id)
    {
        $db = Database::connection(self::DB);
        $db->query("DELETE FROM integration_connections WHERE id = " . (int)$id);
    }
}
