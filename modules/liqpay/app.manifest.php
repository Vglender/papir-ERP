<?php
/**
 * LiqPay — App Manifest.
 *
 * Онлайн-оплати через LiqPay. Інтеграція складається з:
 *   1. Webhook: приймає колбеки від LiqPay → записує в order_payment_receipt
 *      + створює cashin (source='liqpay') + recalc payment_status.
 *   2. Cron backfill: регулярно тягне reports API як страховка від пропущених
 *      webhook'ів, а також одноразово при початковому підключенні.
 *   3. Multi-merchant: декілька LiqPay-аккаунтів (один на сайт) через
 *      integration_connections (metadata.public_key + metadata.site_id).
 *
 * Вмикається/вимикається через integration_settings.is_active — при inactive:
 *   - webhook відповідає 200 OK і не робить нічого (AppRegistry::guard)
 *   - cron тихо виходить
 */

return array(
    'key'         => 'liqpay',
    'name'        => 'LiqPay',
    'description' => 'Онлайн-оплати через LiqPay: webhook, reports backfill, matching до замовлень',

    // ── Routes ───────────────────────────────────────────────────────────────
    'routes' => array(
        '/liqpay/webhook/callback' => '/modules/liqpay/webhook/callback.php',
    ),

    // ── Webhooks (для експорту списку AppRegistry::getWebhooks) ──────────────
    'webhooks' => array(
        array(
            'path'        => '/liqpay/webhook/callback',
            'description' => 'LiqPay server_url callback',
            'method'      => 'POST',
        ),
    ),

    // ── Cron ─────────────────────────────────────────────────────────────────
    // Страхувальний backfill на випадок пропущених webhook'ів (1 раз на годину,
    // забирає транзакції за останні 2 дні). При вимкненому модулі тихий exit.
    'cron' => array(
        array(
            'script'   => '/modules/liqpay/cron/backfill_transactions.php',
            'args'     => '--days=2',
            'schedule' => '17 * * * *',
            'label'    => 'LiqPay: backfill за 2 дні (страховка webhook)',
        ),
    ),

    // ── Tables owned ─────────────────────────────────────────────────────────
    'tables' => array(
        array('db' => 'Papir', 'table' => 'order_payment_receipt',
              'description' => 'LiqPay receipts (мультипровайдер, але зараз лише liqpay)'),
    ),

    // ── Service classes ──────────────────────────────────────────────────────
    'services' => array(
        '/modules/liqpay/LiqpayClient.php' => 'LiqpayClient',
    ),
);