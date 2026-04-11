<?php
/**
 * Client Portal — App Manifest.
 *
 * Публічна сторінка замовлення для клієнта.
 * Доступ за коротким токеном у URL: https://officetorg.com.ua/p/{short_code}
 *
 * Маршрут /p/{short_code} обробляється динамічно в Router::resolveRoute().
 * Ця точка входу (short.php) все одно зареєстрована тут, щоб AppRegistry
 * міг вимкнути/ввімкнути портал через integration_settings.is_active.
 */

return array(
    'key'         => 'client_portal',
    'name'        => 'Клієнтський портал',
    'description' => 'Публічна сторінка замовлення для клієнта (доступ за токеном)',

    'routes' => array(
        '/client_portal/view'          => '/modules/client_portal/short.php',
        '/client_portal/lookup'        => '/modules/client_portal/lookup.php',
        '/client_portal/requisites'    => '/modules/client_portal/requisites.php',
        '/client_portal/invoice'       => '/modules/client_portal/invoice.php',
        '/client_portal/delivery_note' => '/modules/client_portal/delivery_note.php',
        '/client_portal/api/get_link'  => '/modules/client_portal/api/get_link.php',
        '/client_portal/api/photos'    => '/modules/client_portal/api/photos.php',
        '/client_portal/api/lookup'    => '/modules/client_portal/api/lookup.php',
    ),

    'tables' => array(
        array('db' => 'Papir', 'table' => 'client_portal_tokens',
              'description' => 'Токени доступу клієнта до публічної сторінки замовлення'),
    ),

    'services' => array(
        '/modules/client_portal/ClientPortalService.php' => 'ClientPortalService',
    ),
);