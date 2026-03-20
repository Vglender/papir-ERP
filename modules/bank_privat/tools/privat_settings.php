<?php

require_once __DIR__ . '/../privat_api.php';

$api = new PrivatApi(array(
    'default_user_agent' => 'Papir',
));

$api->loadAccountsFromFile(__DIR__ . '/../storage/privat_accounts.php');

try {
    $settings = $api->getSettings();

    header('Content-Type: text/plain; charset=utf-8');
    print_r($settings);
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}