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
