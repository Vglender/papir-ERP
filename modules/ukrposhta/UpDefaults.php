<?php
namespace Papir\Crm;

/**
 * Default values for Ukrposhta shipments.
 * Reads overrides from integration_settings (app_key='ukrposhta').
 */
class UpDefaults
{
    public static function senderUuid()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_sender_uuid',
            '95f1f441-1e5b-4a5b-8bd4-66826a5042f7');
    }

    public static function clientUuid()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_client_uuid', self::senderUuid());
    }

    public static function senderAddressId()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_sender_address_id', '645116149');
    }

    public static function returnAddressId()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_return_address_id', '645116149');
    }

    public static function shipmentType()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_shipment_type', 'STANDARD');
    }

    public static function deliveryType()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_delivery_type', 'W2W');
    }

    public static function payer()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_payer', 'recipient');
    }

    public static function weight()
    {
        return (float)\IntegrationSettingsService::get('ukrposhta', 'default_weight', '1');
    }

    public static function length()
    {
        return (int)\IntegrationSettingsService::get('ukrposhta', 'default_length', '30');
    }

    public static function width()
    {
        return (int)\IntegrationSettingsService::get('ukrposhta', 'default_width', '20');
    }

    public static function height()
    {
        return (int)\IntegrationSettingsService::get('ukrposhta', 'default_height', '2');
    }

    public static function description()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'default_description', 'Канцелярські приладдя');
    }

    public static function onFailReceiveType()
    {
        return \IntegrationSettingsService::get('ukrposhta', 'on_fail_receive_type', 'RETURN');
    }

    public static function returnAfterStorageDays()
    {
        return (int)\IntegrationSettingsService::get('ukrposhta', 'return_after_storage_days', '10');
    }

    // ── Enumerations (for select lists) ──────────────────────────────────────

    public static function deliveryTypes()
    {
        return array(
            'W2W' => 'Відділення → Відділення',
            'W2D' => 'Відділення → Адреса',
            'D2W' => 'Адреса → Відділення',
            'D2D' => 'Адреса → Адреса',
        );
    }

    public static function payers()
    {
        return array(
            'sender'    => 'Відправник',
            'recipient' => 'Отримувач',
        );
    }

    public static function shipmentTypes()
    {
        return array(
            'STANDARD' => 'Стандарт',
            'EXPRESS'  => 'Експрес',
        );
    }

    public static function lifecycleLabels()
    {
        return array(
            'CREATED'       => 'Чернетка',
            'REGISTERED'    => 'Зареєстрована',
            'DELIVERING'    => 'В дорозі',
            'IN_DEPARTMENT' => 'На відділенні',
            'STORAGE'       => 'На зберіганні',
            'FORWARDING'    => 'Переадресовано',
            'DELIVERED'     => 'Отримана',
            'RETURNING'     => 'Повертається',
            'RETURNED'      => 'Повернена',
            'CANCELLED'     => 'Скасована',
            'DELETED'       => 'Видалена',
            'UNKNOWN'       => 'Невідомий',
        );
    }

    public static function lifecycleBadgeClass($status)
    {
        $map = array(
            'CREATED'       => 'badge-gray',
            'REGISTERED'    => 'badge-gray',
            'DELIVERING'    => 'badge-blue',
            'IN_DEPARTMENT' => 'badge-orange',
            'STORAGE'       => 'badge-orange',
            'FORWARDING'    => 'badge-blue',
            'DELIVERED'     => 'badge-green',
            'RETURNING'     => 'badge-orange',
            'RETURNED'      => 'badge-gray',
            'CANCELLED'     => 'badge-red',
            'DELETED'       => 'badge-red',
            'UNKNOWN'       => 'badge-gray',
        );
        return isset($map[$status]) ? $map[$status] : 'badge-gray';
    }
}