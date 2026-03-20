<?php

require_once '/var/www/papir/modules/bank_privat/privat_api.php';

function request_pb_ur($dateFrom)
{
    $api = new PrivatApi(array(
        'default_user_agent' => 'Papir',
        'default_limit' => 100,
    ));

    $api->loadAccountsFromFile('/var/www/papir/modules/bank_privat/storage/privat_accounts.php');

    return $api->getTransactionsByDate($dateFrom);
}

$date_from = date('Y-m-d',strtotime("-1 days"));

$data = request_pb_ur($date_from);

print_R($data);


?>