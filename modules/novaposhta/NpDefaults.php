<?php
namespace Papir\Crm;

/**
 * Nova Poshta default settings — reads from integration_settings.
 * Cached per-request.
 */
class NpDefaults
{
    private static $cache = null;

    private static function load()
    {
        if (self::$cache !== null) return;
        require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';
        self::$cache = array(
            'service_type'   => \IntegrationSettingsService::get('novaposhta', 'default_service_type',   'WarehouseWarehouse'),
            'payer_type'     => \IntegrationSettingsService::get('novaposhta', 'default_payer_type',     'Recipient'),
            'payment_method' => \IntegrationSettingsService::get('novaposhta', 'default_payment_method', 'Cash'),
            'cargo_type'     => \IntegrationSettingsService::get('novaposhta', 'default_cargo_type',     'Cargo'),
            'weight'         => (float)\IntegrationSettingsService::get('novaposhta', 'default_weight',  '0.5'),
            'seats_amount'   => (int)\IntegrationSettingsService::get('novaposhta', 'default_seats_amount', '1'),
            'description'    => \IntegrationSettingsService::get('novaposhta', 'default_description',    'Товар'),
        );
    }

    public static function get($key, $fallback = null)
    {
        self::load();
        return isset(self::$cache[$key]) ? self::$cache[$key] : $fallback;
    }

    public static function all()
    {
        self::load();
        return self::$cache;
    }
}