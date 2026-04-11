<?php
/**
 * Ukrposhta — App Manifest.
 *
 * Declares routes, navigation, cron jobs and tables used by the module.
 * AppRegistry enables/disables everything based on integration_settings.is_active.
 */

return array(
    'key'         => 'ukrposhta',
    'name'        => 'Укрпошта',
    'description' => 'ТТН, реєстри, відстеження посилок Укрпошти',

    // ── Routes ───────────────────────────────────────────────────────────────
    'routes' => array(
        // Pages
        '/ukrposhta/ttns'                 => '/modules/ukrposhta/index.php',
        '/ukrposhta/groups'               => '/modules/ukrposhta/groups.php',

        // Settings test
        '/ukrposhta/api/test_connection'  => '/modules/ukrposhta/api/test_connection.php',

        // Classifier (address search)
        '/ukrposhta/api/search_city'      => '/modules/ukrposhta/api/search_city.php',
        '/ukrposhta/api/get_postoffices'  => '/modules/ukrposhta/api/get_postoffices.php',
        '/ukrposhta/api/search_street'    => '/modules/ukrposhta/api/search_street.php',

        // TTN CRUD
        '/ukrposhta/api/get_ttn_form'     => '/modules/ukrposhta/api/get_ttn_form.php',
        '/ukrposhta/api/create_ttn'       => '/modules/ukrposhta/api/create_ttn.php',
        '/ukrposhta/api/update_ttn'       => '/modules/ukrposhta/api/update_ttn.php',
        '/ukrposhta/api/delete_ttn'       => '/modules/ukrposhta/api/delete_ttn.php',
        '/ukrposhta/api/refresh_ttn'      => '/modules/ukrposhta/api/refresh_ttn.php',
        '/ukrposhta/api/track_ttn'        => '/modules/ukrposhta/api/track_ttn.php',
        '/ukrposhta/api/get_ttn_list'     => '/modules/ukrposhta/api/get_ttn_list.php',
        '/ukrposhta/api/print_sticker'    => '/modules/ukrposhta/api/print_sticker.php',

        // Shipment-groups (реєстри)
        '/ukrposhta/api/create_group'         => '/modules/ukrposhta/api/create_group.php',
        '/ukrposhta/api/add_to_group'         => '/modules/ukrposhta/api/add_to_group.php',
        '/ukrposhta/api/remove_from_group'    => '/modules/ukrposhta/api/remove_from_group.php',
        '/ukrposhta/api/close_group'          => '/modules/ukrposhta/api/close_group.php',
        '/ukrposhta/api/reopen_group'         => '/modules/ukrposhta/api/reopen_group.php',
        '/ukrposhta/api/delete_group'         => '/modules/ukrposhta/api/delete_group.php',
        '/ukrposhta/api/get_group_shipments'  => '/modules/ukrposhta/api/get_group_shipments.php',
        '/ukrposhta/api/sync_groups'          => '/modules/ukrposhta/api/sync_groups.php',
        '/ukrposhta/api/print_registry'       => '/modules/ukrposhta/api/print_registry.php',
    ),

    // ── Navigation ───────────────────────────────────────────────────────────
    'nav' => array(
        'group' => 'logistics',
        'items' => array(
            array('key' => 'up-ttns',   'label' => 'УП · ТТН',     'url' => '/ukrposhta/ttns'),
            array('key' => 'up-groups', 'label' => 'УП · Реєстри', 'url' => '/ukrposhta/groups'),
        ),
    ),

    // ── Cron jobs ────────────────────────────────────────────────────────────
    'cron' => array(
        array(
            'script'   => '/modules/ukrposhta/cron/refresh_tracking.php',
            'schedule' => '*/30 * * * *',
            'label'    => 'Укрпошта: оновлення статусів ТТН',
        ),
    ),

    // ── Tables owned by this app (documentation only) ────────────────────────
    'tables' => array(
        array('db' => 'Papir', 'table' => 'ttn_ukrposhta',        'description' => 'ТТН Укрпошти (legacy camelCase schema)'),
        array('db' => 'Papir', 'table' => 'shipment_groups',      'description' => 'Реєстри (shipment-groups)'),
        array('db' => 'Papir', 'table' => 'shipment_group_links', 'description' => 'Зв\'язки ТТН ↔ реєстр'),
        array('db' => 'Papir', 'table' => 'sender_ukr',           'description' => 'Довідник відправників Укрпошти'),
        array('db' => 'Papir', 'table' => 'ukrposhta_cities',       'description' => 'Класифікатор міст (lazy cache)'),
        array('db' => 'Papir', 'table' => 'ukrposhta_postoffices',  'description' => 'Класифікатор відділень (lazy cache)'),
        array('db' => 'Papir', 'table' => 'ukrposhta_streets',      'description' => 'Класифікатор вулиць (lazy cache)'),
    ),

    // ── Service classes (documentation) ──────────────────────────────────────
    'services' => array(
        '/modules/ukrposhta/UkrposhtaApi.php' => 'Papir\\Crm\\UkrposhtaApi',
        '/modules/ukrposhta/services/TtnService.php'      => 'Papir\\Crm\\TtnService',
        '/modules/ukrposhta/services/TrackingService.php' => 'Papir\\Crm\\TrackingService',
        '/modules/ukrposhta/services/GroupService.php'    => 'Papir\\Crm\\GroupService',
    ),
);