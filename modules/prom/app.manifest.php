<?php
/**
 * Prom.ua — App Manifest.
 *
 * Declares everything this integration brings into the system:
 * routes, navigation, cron jobs, API service.
 *
 * When is_active = 0 in integration_settings:
 *   - routes return 404
 *   - nav items hidden
 *   - crons exit silently
 */

return array(
    'key'         => 'prom',
    'name'        => 'Prom.ua',
    'description' => 'Маркетплейс Prom.ua: замовлення, товари, клієнти, доставка',

    // ── Routes ───────────────────────────────────────────────────────────────
    'routes' => array(
        '/prom/api/test_connection' => '/modules/prom/api/test_connection.php',
        '/prom/api/save_mapping'    => '/modules/prom/api/save_mapping.php',
    ),

    // ── Navigation ───────────────────────────────────────────────────────────
    'nav' => array(
        'group' => 'tools',
        'items' => array(),
    ),

    // ── Cron jobs ────────────────────────────────────────────────────────────
    'cron' => array(
        array(
            'script'      => '/modules/prom/cron/sync_orders.php',
            'schedule'    => '*/5 * * * *',
            'description' => 'Імпорт нових замовлень з Prom.ua',
        ),
    ),

    // ── Database tables owned by this app ────────────────────────────────────
    'tables' => array(),

    // ── Service classes ──────────────────────────────────────────────────────
    'services' => array(
        '/modules/prom/PromApi.php' => 'PromApi',
    ),
);
