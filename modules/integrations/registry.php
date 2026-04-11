<?php
/**
 * Central registry of all integration apps.
 *
 * Each entry defines metadata for the catalog tile + which settings
 * the app expects (used to render the settings form).
 *
 * Categories:
 *   communications  — SMS, messengers, email
 *   delivery        — logistics / shipping
 *   finance         — banks, ERP, payments
 *   advertising     — Google Ads, Merchant
 *   analytics       — GA, reports
 *   social          — Instagram, Facebook, TikTok
 *   sites           — site sync
 *   other           — everything else
 */

return array(

    // ── Communications ───────────────────────────────────────────────────────
    'telegram' => array(
        'name'        => 'Telegram Bot',
        'category'    => 'communications',
        'icon'        => 'telegram.svg',
        'description' => 'Сповіщення та повідомлення через Telegram бота',
        'settings'    => array(
            array('key' => 'bot_token',  'label' => 'Bot Token',  'type' => 'text', 'secret' => true),
        ),
        'source_class' => 'TelegramBotService',
    ),

    'alphasms' => array(
        'name'        => 'AlphaSMS',
        'category'    => 'communications',
        'icon'        => 'alphasms.svg',
        'description' => 'SMS та Viber розсилки через AlphaSMS',
        'settings'    => array(
            array('key' => 'api_key',    'label' => 'API Key',    'type' => 'text', 'secret' => true),
            array('key' => 'alpha_name', 'label' => 'Alpha Name', 'type' => 'text', 'secret' => false),
            array('key' => 'api_url',    'label' => 'API URL',    'type' => 'text', 'secret' => false),
        ),
        'source_class' => 'AlphaSmsService',
    ),

    'gmail_smtp' => array(
        'name'        => 'Gmail SMTP',
        'category'    => 'communications',
        'icon'        => 'gmail.svg',
        'description' => 'Відправка email через Gmail SMTP',
        'settings'    => array(
            array('key' => 'smtp_host',  'label' => 'SMTP Host',     'type' => 'text',  'secret' => false),
            array('key' => 'smtp_port',  'label' => 'SMTP Port',     'type' => 'text',  'secret' => false),
            array('key' => 'from_email', 'label' => 'Email',         'type' => 'text',  'secret' => false),
            array('key' => 'from_name',  'label' => 'Ім\'я відправника', 'type' => 'text', 'secret' => false),
            array('key' => 'app_pass',   'label' => 'App Password',  'type' => 'text',  'secret' => true),
        ),
        'source_class' => 'GmailSmtpService',
    ),

    'client_portal' => array(
        'name'        => 'Клієнтський портал',
        'category'    => 'communications',
        'icon'        => 'portal.svg',
        'description' => 'Публічна сторінка замовлення для клієнта (перехід за коротким посиланням)',
        'settings'    => array(
            array('key' => 'portal_base_url',      'label' => 'Базовий URL',         'type' => 'text', 'secret' => false),
            array('key' => 'telegram_contact_url', 'label' => 'Telegram для зв\'язку', 'type' => 'text', 'secret' => false),
        ),
        'source_class' => 'ClientPortalService',
    ),

    'whatsapp' => array(
        'name'        => 'WhatsApp',
        'category'    => 'communications',
        'icon'        => 'whatsapp.svg',
        'description' => 'Повідомлення через WhatsApp Business API',
        'settings'    => array(
            array('key' => 'api_token',  'label' => 'API Token',    'type' => 'text', 'secret' => true),
            array('key' => 'phone_id',   'label' => 'Phone Number ID', 'type' => 'text', 'secret' => false),
        ),
        'enabled'     => false,
    ),

    // ── Delivery ─────────────────────────────────────────────────────────────
    'novaposhta' => array(
        'name'        => 'Нова Пошта',
        'category'    => 'delivery',
        'icon'        => 'novaposhta.png',
        'description' => 'ТТН, реєстри, відстеження посилок',
        'custom_view' => '/modules/integrations/views/novaposhta_settings.php',
        'has_connections' => true,
        'settings'    => array(
            array('key' => 'default_service_type',   'label' => 'Тип доставки',    'type' => 'select', 'secret' => false),
            array('key' => 'default_payer_type',     'label' => 'Платник',          'type' => 'select', 'secret' => false),
            array('key' => 'default_payment_method', 'label' => 'Форма оплати',    'type' => 'select', 'secret' => false),
            array('key' => 'default_cargo_type',     'label' => 'Тип вантажу',     'type' => 'select', 'secret' => false),
            array('key' => 'default_weight',         'label' => 'Вага (кг)',        'type' => 'number', 'secret' => false),
            array('key' => 'default_seats_amount',   'label' => 'К-сть місць',     'type' => 'number', 'secret' => false),
            array('key' => 'default_description',    'label' => 'Опис вантажу',    'type' => 'text',   'secret' => false),
        ),
    ),

    'ukrposhta' => array(
        'name'        => 'Укрпошта',
        'category'    => 'delivery',
        'icon'        => 'ukrposhta.svg',
        'description' => 'ТТН, реєстри, відстеження посилок Укрпошти',
        'custom_view' => '/modules/integrations/views/ukrposhta_settings.php',
        'has_connections' => false, // single account — tokens stored in integration_settings
        'settings'    => array(
            // API tokens (secrets)
            array('key' => 'ecom_token',                'label' => 'eCom Bearer',         'type' => 'text',   'secret' => true),
            array('key' => 'user_token',                'label' => 'User Token',          'type' => 'text',   'secret' => true),
            array('key' => 'tracking_token',            'label' => 'Tracking Bearer',     'type' => 'text',   'secret' => true),
            // Default sender (Hlonder FOP — one account)
            array('key' => 'default_sender_uuid',       'label' => 'Sender UUID',         'type' => 'select', 'secret' => false),
            array('key' => 'default_client_uuid',       'label' => 'Client UUID',         'type' => 'text',   'secret' => false),
            array('key' => 'default_sender_address_id', 'label' => 'Адреса відправки',    'type' => 'text',   'secret' => false),
            array('key' => 'default_return_address_id', 'label' => 'Адреса повернень',    'type' => 'text',   'secret' => false),
            // Shipment defaults
            array('key' => 'default_shipment_type',     'label' => 'Тип (EXPRESS/STANDARD)', 'type' => 'select', 'secret' => false),
            array('key' => 'default_delivery_type',     'label' => 'Тип доставки',        'type' => 'select', 'secret' => false),
            array('key' => 'default_payer',             'label' => 'Платник',             'type' => 'select', 'secret' => false),
            array('key' => 'default_weight',            'label' => 'Вага (кг)',           'type' => 'number', 'secret' => false),
            array('key' => 'default_length',            'label' => 'Довжина (см)',        'type' => 'number', 'secret' => false),
            array('key' => 'default_width',             'label' => 'Ширина (см)',         'type' => 'number', 'secret' => false),
            array('key' => 'default_height',            'label' => 'Висота (см)',         'type' => 'number', 'secret' => false),
            array('key' => 'default_description',       'label' => 'Опис вантажу',        'type' => 'text',   'secret' => false),
            array('key' => 'on_fail_receive_type',      'label' => 'При неотриманні',     'type' => 'select', 'secret' => false),
            array('key' => 'return_after_storage_days', 'label' => 'Днів зберігання',     'type' => 'number', 'secret' => false),
        ),
    ),

    // ── Finance ──────────────────────────────────────────────────────────────
    'moysklad' => array(
        'name'        => 'МойСклад',
        'category'    => 'finance',
        'icon'        => 'moysklad.png',
        'description' => 'Синхронізація з МойСклад ERP',
        'custom_view' => '/modules/integrations/views/moysklad_settings.php',
        'settings'    => array(
            array('key' => 'auth', 'label' => 'Авторизація', 'type' => 'text', 'secret' => true),
            // CRUD-level settings: ms_{doc}_{C|U|D}_{from|to}
            array('key' => 'ms_order_C_from',   'label' => 'Order Create ←', 'type' => 'toggle', 'secret' => false),
            array('key' => 'ms_order_C_to',     'label' => 'Order Create →', 'type' => 'toggle', 'secret' => false),
            array('key' => 'ms_order_U_from',   'label' => 'Order Update ←', 'type' => 'toggle', 'secret' => false),
            array('key' => 'ms_order_U_to',     'label' => 'Order Update →', 'type' => 'toggle', 'secret' => false),
            array('key' => 'ms_order_D_from',   'label' => 'Order Delete ←', 'type' => 'toggle', 'secret' => false),
            array('key' => 'ms_order_D_to',     'label' => 'Order Delete →', 'type' => 'toggle', 'secret' => false),
            array('key' => 'ms_demand_C_from',  'label' => 'Demand Create ←','type' => 'toggle', 'secret' => false),
            array('key' => 'ms_demand_C_to',    'label' => 'Demand Create →','type' => 'toggle', 'secret' => false),
            array('key' => 'ms_demand_U_from',  'label' => 'Demand Update ←','type' => 'toggle', 'secret' => false),
            array('key' => 'ms_demand_U_to',    'label' => 'Demand Update →','type' => 'toggle', 'secret' => false),
            array('key' => 'ms_demand_D_from',  'label' => 'Demand Delete ←','type' => 'toggle', 'secret' => false),
            array('key' => 'ms_demand_D_to',    'label' => 'Demand Delete →','type' => 'toggle', 'secret' => false),
            array('key' => 'ms_finance_C_from', 'label' => 'Finance Create ←','type' => 'toggle','secret' => false),
            array('key' => 'ms_finance_C_to',   'label' => 'Finance Create →','type' => 'toggle','secret' => false),
            array('key' => 'ms_finance_U_from', 'label' => 'Finance Update ←','type' => 'toggle','secret' => false),
            array('key' => 'ms_finance_U_to',   'label' => 'Finance Update →','type' => 'toggle','secret' => false),
            array('key' => 'ms_finance_D_from', 'label' => 'Finance Delete ←','type' => 'toggle','secret' => false),
            array('key' => 'ms_finance_D_to',   'label' => 'Finance Delete →','type' => 'toggle','secret' => false),
        ),
    ),

    'liqpay' => array(
        'name'        => 'LiqPay',
        'category'    => 'finance',
        'icon'        => 'liqpay.svg',
        'description' => 'Онлайн-оплати через LiqPay: webhook, reports backfill, автоматичне створення cashin',
        'has_connections' => true,
        'settings'    => array(
            array('key' => 'sandbox', 'label' => 'Sandbox-режим', 'type' => 'toggle', 'secret' => false),
        ),
        'note' => 'API-ключі (public + private) зберігаються в "Підключеннях" — по одному запису на merchant/сайт. Webhook URL: /liqpay/webhook/callback',
    ),

    'privatbank' => array(
        'name'        => 'ПриватБанк',
        'category'    => 'finance',
        'icon'        => 'privatbank.svg',
        'description' => 'Імпорт банківських виписок',
        'settings'    => array(),
        'settings_url' => '/finance/bank',
        'note'        => 'Налаштовується в модулі Фінанси → Банк',
    ),

    'monobank' => array(
        'name'        => 'MonoBank',
        'category'    => 'finance',
        'icon'        => 'monobank.svg',
        'description' => 'Імпорт виписок MonoBank',
        'settings'    => array(),
        'settings_url' => '/finance/bank',
        'note'        => 'Налаштовується в модулі Фінанси → Банк',
    ),

    'ukrsibbank' => array(
        'name'        => 'УкрСібБанк',
        'category'    => 'finance',
        'icon'        => 'ukrsibbank.svg',
        'description' => 'OAuth токени та статус підключення',
        'settings'    => array(),
        'settings_url' => '/ukrsib_token_status',
        'note'        => 'Окрема сторінка налаштувань OAuth',
    ),

    // ── Advertising ──────────────────────────────────────────────────────────
    'google_merchant' => array(
        'name'        => 'Google Merchant',
        'category'    => 'advertising',
        'icon'        => 'google_merchant.svg',
        'description' => 'Фід товарів для Google Shopping',
        'settings'    => array(),
        'settings_url' => '/integr/merchant',
        'note'        => 'Налаштовується на сторінці Merchant',
    ),

    // ── Analytics ────────────────────────────────────────────────────────────
    'google_analytics' => array(
        'name'        => 'Google Analytics',
        'category'    => 'analytics',
        'icon'        => 'google_analytics.svg',
        'description' => 'Аналітика відвідуваності сайтів',
        'settings'    => array(
            array('key' => 'property_off', 'label' => 'Property ID (off)', 'type' => 'text', 'secret' => false),
            array('key' => 'property_mff', 'label' => 'Property ID (mff)', 'type' => 'text', 'secret' => false),
            array('key' => 'credentials_path', 'label' => 'Шлях до credentials.json', 'type' => 'text', 'secret' => false),
        ),
        'source_class' => 'GoogleAnalyticsService',
    ),

    'openai' => array(
        'name'        => 'OpenAI',
        'category'    => 'analytics',
        'icon'        => 'openai.svg',
        'description' => 'AI генерація контенту для товарів',
        'settings'    => array(
            array('key' => 'api_key', 'label' => 'API Key', 'type' => 'text', 'secret' => true),
        ),
        'source_file' => '/modules/openai/storage/openai_auth.php',
        'settings_url' => '/ai',
    ),

    // ── Social ───────────────────────────────────────────────────────────────
    'instagram' => array(
        'name'        => 'Instagram',
        'category'    => 'social',
        'icon'        => 'instagram.svg',
        'description' => 'Інтеграція з Instagram Business',
        'settings'    => array(
            array('key' => 'access_token', 'label' => 'Access Token', 'type' => 'text', 'secret' => true),
            array('key' => 'account_id',   'label' => 'Account ID',   'type' => 'text', 'secret' => false),
        ),
        'enabled' => false,
    ),

    'facebook' => array(
        'name'        => 'Facebook',
        'category'    => 'social',
        'icon'        => 'facebook.svg',
        'description' => 'Facebook Business інтеграція',
        'settings'    => array(
            array('key' => 'access_token', 'label' => 'Access Token', 'type' => 'text', 'secret' => true),
            array('key' => 'page_id',      'label' => 'Page ID',      'type' => 'text', 'secret' => false),
        ),
        'enabled' => false,
    ),

    'tiktok' => array(
        'name'        => 'TikTok',
        'category'    => 'social',
        'icon'        => 'tiktok.svg',
        'description' => 'TikTok Shop та реклама',
        'settings'    => array(
            array('key' => 'access_token', 'label' => 'Access Token', 'type' => 'text', 'secret' => true),
        ),
        'enabled' => false,
    ),

    // ── Sites ────────────────────────────────────────────────────────────────
    'prom' => array(
        'name'        => 'Prom.ua',
        'category'    => 'sites',
        'icon'        => 'prom.svg',
        'description' => 'Маркетплейс Prom.ua: замовлення, товари, клієнти, доставка',
        'custom_view' => '/modules/integrations/views/prom_settings.php',
        'settings'    => array(
            array('key' => 'auth_token',                    'label' => 'API Token',                         'type' => 'text',   'secret' => true),
            array('key' => 'default_order_status_on_accept','label' => 'Статус при прийнятті',              'type' => 'select', 'secret' => false),
            array('key' => 'default_delivery_type',         'label' => 'Тип доставки для ТТН',             'type' => 'select', 'secret' => false),
            array('key' => 'sync_interval_minutes',         'label' => 'Інтервал синхронізації (хв)',      'type' => 'number', 'secret' => false),
            array('key' => 'auto_set_ttn',                  'label' => 'Авто-відправка ТТН',               'type' => 'toggle', 'secret' => false),
        ),
    ),

    'order_import' => array(
        'name'        => 'Імпорт замовлень',
        'category'    => 'finance',
        'icon'        => 'order_import.svg',
        'description' => 'Дефолтні організації для імпорту замовлень: платник ПДВ / неплатник',
        'custom_view' => '/modules/integrations/views/order_import_settings.php',
        'settings'    => array(
            array('key' => 'default_org_vat',   'label' => 'Організація — платник ПДВ',   'type' => 'select', 'secret' => false),
            array('key' => 'default_org_novat', 'label' => 'Організація — неплатник ПДВ', 'type' => 'select', 'secret' => false),
        ),
    ),

    'site_off' => array(
        'name'        => 'OfficeTorg',
        'category'    => 'sites',
        'icon'        => 'opencart.svg',
        'description' => 'Синхронізація з officetorg.com.ua (OpenCart)',
        'settings'    => array(),
        'settings_url' => '/system/sites',
        'note'        => 'Налаштовується в Система → Сайти',
    ),

    'site_mff' => array(
        'name'        => 'MenuFolder',
        'category'    => 'sites',
        'icon'        => 'opencart.svg',
        'description' => 'Синхронізація з menufolder.com.ua (OpenCart)',
        'settings'    => array(),
        'settings_url' => '/system/sites',
        'note'        => 'Налаштовується в Система → Сайти',
    ),
);
