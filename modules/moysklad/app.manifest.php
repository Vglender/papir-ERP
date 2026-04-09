<?php
/**
 * MoySklad (МойСклад) — App Manifest.
 *
 * Declares everything this integration brings into the system:
 * routes, navigation, cron jobs, webhooks, tables, sync scripts.
 *
 * When is_active = 0 in integration_settings:
 *   - routes return 404
 *   - nav items hidden
 *   - crons exit silently
 *   - webhooks respond 200 but skip processing
 */

return array(
    'key'         => 'moysklad',
    'name'        => 'МойСклад',
    'description' => 'Синхронізація з МойСклад ERP: замовлення, відвантаження, фінанси, залишки, ціни',

    // ── Routes ───────────────────────────────────────────────────────────────
    'routes' => array(
        // Dashboard / tools
        '/docum/attr'                   => '/modules/moysklad/tools/dashboard.php',

        // Webhook management API
        '/moysklad/api/webhooks_list'   => '/modules/moysklad/api/webhooks_list.php',
        '/moysklad/api/webhook_create'  => '/modules/moysklad/api/webhook_create.php',
        '/moysklad/api/webhook_delete'  => '/modules/moysklad/api/webhook_delete.php',
    ),

    // ── Navigation ───────────────────────────────────────────────────────────
    'nav' => array(
        'group' => 'tools',
        'items' => array(
            array('key' => 'ms-attrs', 'label' => 'МС атрибути', 'url' => '/docum/attr'),
        ),
    ),

    // ── Webhooks (incoming from MoySklad) ────────────────────────────────────
    'webhooks' => array(
        array(
            'path'    => '/customerorder/webhook/moysklad',
            'handler' => '/modules/customerorder/webhook/moysklad.php',
            'events'  => 'customerorder: CREATE, UPDATE, DELETE',
        ),
        array(
            'path'    => '/demand/webhook/moysklad',
            'handler' => '/modules/demand/webhook/moysklad.php',
            'events'  => 'demand: CREATE, UPDATE, DELETE',
        ),
        array(
            'path'    => '/finance/webhook/moysklad',
            'handler' => '/modules/finance/webhook/moysklad.php',
            'events'  => 'paymentin, paymentout, cashin, cashout: CREATE, UPDATE, DELETE',
        ),
        array(
            'path'    => '/counterparties/webhook/moysklad',
            'handler' => '/modules/counterparties/webhook/moysklad.php',
            'events'  => 'counterparty: CREATE, UPDATE',
        ),
    ),

    // ── Cron jobs ────────────────────────────────────────────────────────────
    'cron' => array(
        array('script' => '/cron/sync_stock.php',               'schedule' => '0 */4 * * *',   'description' => 'Залишки з МС API → ms.stock_ → product_stock'),
        array('script' => '/cron/sync_prices.php',              'schedule' => '0 1 * * *',     'description' => 'Експорт цін у МС'),
        array('script' => '/cron/sync_supplier_pricelists.php', 'schedule' => '0 */3 * * *',   'description' => 'Імпорт собівартості з МС'),
    ),

    // ── Sync scripts (manual / one-time) ─────────────────────────────────────
    'sync_scripts' => array(
        '/scripts/sync_ms_orders.php',
        '/scripts/sync_ms_counterparties.php',
        '/scripts/sync_ms_finance.php',
        '/scripts/sync_ms_demand.php',
        '/scripts/sync_ms_document_links.php',
        '/scripts/sync_ms_document_links_from_mirror.php',
        '/scripts/sync_ms_order_items.php',
        '/scripts/sync_ms_salesreturn.php',
        '/scripts/sync_ms_supply.php',
        '/scripts/sync_ms_purchaseorder.php',
        '/scripts/sync_ms_loss.php',
        '/scripts/sync_ms_move.php',
        '/scripts/sync_ms_np.php',
        '/scripts/import_ms_counterparties.php',
        '/scripts/import_ms_finance.php',
    ),

    // ── Database tables owned by this app ────────────────────────────────────
    'tables' => array(
        // MS-specific database
        array('db' => 'ms', 'table' => 'stock_',  'description' => 'Залишки з МС API (raw)'),
        array('db' => 'ms', 'table' => 'virtual',  'description' => 'Віртуальні залишки (виробництво)'),
        array('db' => 'ms', 'table' => 'stock',    'description' => 'Метадані синхронізації залишків'),

        // Papir tables owned by MS
        array('db' => 'Papir', 'table' => 'product_stock',            'description' => 'Синхронізована копія залишків'),
        array('db' => 'Papir', 'table' => 'order_status_ms_mapping',  'description' => 'Маппінг UUID статусів МС → локальний enum'),
        array('db' => 'Papir', 'table' => 'document_link',            'description' => 'Звʼязки документів (оплата↔замовлення)'),
    ),

    // ── Fields this app adds to shared tables ────────────────────────────────
    'shared_fields' => array(
        array('table' => 'product_papir',  'field' => 'id_ms',          'description' => 'МС product UUID'),
        array('table' => 'customerorder',  'field' => 'id_ms',          'description' => 'МС order UUID'),
        array('table' => 'customerorder',  'field' => 'external_code',  'description' => 'МС external code'),
        array('table' => 'demand',         'field' => 'id_ms',          'description' => 'МС demand UUID'),
        array('table' => 'counterparty',   'field' => 'id_ms',          'description' => 'МС agent UUID'),
        array('table' => 'finance_bank',   'fields' => 'id_ms, agent_ms', 'description' => 'МС payment UUID + agent ref'),
        array('table' => 'finance_cash',   'fields' => 'id_ms, agent_ms', 'description' => 'МС cash UUID + agent ref'),
    ),

    // ── Service classes ──────────────────────────────────────────────────────
    'services' => array(
        '/modules/moysklad/moysklad_api.php'               => 'MoySkladApi',
        '/modules/moysklad/src/MoySkladAttributesSync.php'  => 'MoySkladAttributesSync',
        '/modules/moysklad/src/WebhookCpHelper.php'         => 'WebhookCpHelper',
        '/modules/prices/services/MoySkladPriceExport.php'  => 'MoySkladPriceExport',
        '/modules/prices/services/MoySkladPriceSync.php'    => 'MoySkladPriceSync',
        '/src/lib_stock_update.php'                         => 'Stock update functions',
    ),
);