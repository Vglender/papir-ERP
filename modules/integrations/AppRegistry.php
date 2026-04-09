<?php
/**
 * AppRegistry — discovers app manifests, checks active state, provides
 * routes / nav / cron / webhooks for active apps only.
 *
 * Usage:
 *   AppRegistry::boot();                        // once at startup
 *   AppRegistry::isActive('moysklad');           // bool
 *   AppRegistry::getRoutes();                    // merged routes from active apps
 *   AppRegistry::getNavItems('logistics');        // nav items for a nav group
 *   AppRegistry::getCronJobs();                  // cron entries from active apps
 *   AppRegistry::getManifest('moysklad');         // raw manifest array
 *   AppRegistry::guard('moysklad');               // exit early if inactive (for webhooks/crons)
 */

require_once __DIR__ . '/IntegrationSettingsService.php';

class AppRegistry
{
    /** @var array  appKey => manifest array */
    private static $manifests = array();

    /** @var array  appKey => bool */
    private static $activeCache = array();

    /** @var bool */
    private static $booted = false;

    /**
     * Discover all manifests from modules.
     */
    public static function boot()
    {
        if (self::$booted) return;
        self::$booted = true;

        $baseDir = realpath(__DIR__ . '/..');
        $pattern = $baseDir . '/*/app.manifest.php';
        $files   = glob($pattern);

        if (!$files) return;

        foreach ($files as $file) {
            $manifest = require $file;
            if (!is_array($manifest) || empty($manifest['key'])) continue;
            self::$manifests[$manifest['key']] = $manifest;
        }
    }

    /**
     * Check if an app is active (reads from integration_settings).
     */
    public static function isActive($appKey)
    {
        if (isset(self::$activeCache[$appKey])) {
            return self::$activeCache[$appKey];
        }
        $val = IntegrationSettingsService::get($appKey, 'is_active', '1');
        self::$activeCache[$appKey] = ($val === '1');
        return self::$activeCache[$appKey];
    }

    /**
     * Guard: if app is inactive, send 200 OK and exit.
     * Use at top of webhooks and cron scripts.
     */
    public static function guard($appKey)
    {
        require_once __DIR__ . '/../database/database.php';
        self::boot();
        if (!self::isActive($appKey)) {
            if (php_sapi_name() === 'cli') {
                // Cron — just exit silently
                exit(0);
            }
            // Webhook — respond 200 so external service doesn't retry
            header('Content-Type: application/json');
            echo json_encode(array('ok' => true, 'skipped' => true, 'reason' => 'app_inactive'));
            exit;
        }
    }

    /**
     * Get manifest for an app.
     */
    public static function getManifest($appKey)
    {
        self::boot();
        return isset(self::$manifests[$appKey]) ? self::$manifests[$appKey] : null;
    }

    /**
     * Get all manifests.
     */
    public static function getAllManifests()
    {
        self::boot();
        return self::$manifests;
    }

    /**
     * Get merged routes from all active apps.
     * @return array  path => file
     */
    public static function getRoutes()
    {
        self::boot();
        $routes = array();
        foreach (self::$manifests as $appKey => $manifest) {
            if (!self::isActive($appKey)) continue;
            if (!empty($manifest['routes'])) {
                foreach ($manifest['routes'] as $path => $file) {
                    $routes[$path] = $file;
                }
            }
        }
        return $routes;
    }

    /**
     * Get nav items for a specific nav group from active apps.
     * @return array of ['key'=>..., 'label'=>..., 'url'=>...]
     */
    public static function getNavItems($group)
    {
        self::boot();
        $items = array();
        foreach (self::$manifests as $appKey => $manifest) {
            if (!self::isActive($appKey)) continue;
            if (!empty($manifest['nav']) && isset($manifest['nav']['group'])
                && $manifest['nav']['group'] === $group && !empty($manifest['nav']['items'])) {
                foreach ($manifest['nav']['items'] as $item) {
                    $items[] = $item;
                }
            }
        }
        return $items;
    }

    /**
     * Get cron jobs from all active apps.
     * @return array of ['app'=>..., 'script'=>..., 'schedule'=>...]
     */
    public static function getCronJobs()
    {
        self::boot();
        $jobs = array();
        foreach (self::$manifests as $appKey => $manifest) {
            if (!self::isActive($appKey)) continue;
            if (!empty($manifest['cron'])) {
                foreach ($manifest['cron'] as $job) {
                    $job['app'] = $appKey;
                    $jobs[] = $job;
                }
            }
        }
        return $jobs;
    }

    /**
     * Get webhook endpoints from all active apps.
     * @return array of ['app'=>..., 'path'=>..., 'handler'=>...]
     */
    public static function getWebhooks()
    {
        self::boot();
        $hooks = array();
        foreach (self::$manifests as $appKey => $manifest) {
            if (!empty($manifest['webhooks'])) {
                foreach ($manifest['webhooks'] as $wh) {
                    $wh['app'] = $appKey;
                    $wh['active'] = self::isActive($appKey);
                    $hooks[] = $wh;
                }
            }
        }
        return $hooks;
    }

    /**
     * Get tables owned by an app (for documentation/cleanup).
     */
    public static function getTables($appKey)
    {
        self::boot();
        $manifest = isset(self::$manifests[$appKey]) ? self::$manifests[$appKey] : null;
        return ($manifest && !empty($manifest['tables'])) ? $manifest['tables'] : array();
    }

    /**
     * Check if a route belongs to an inactive app.
     * Returns true if route is allowed (no manifest claims it, or app is active).
     */
    public static function isRouteAllowed($path)
    {
        self::boot();
        foreach (self::$manifests as $appKey => $manifest) {
            if (empty($manifest['routes'])) continue;
            if (isset($manifest['routes'][$path])) {
                return self::isActive($appKey);
            }
        }
        return true; // no manifest claims this route
    }
}