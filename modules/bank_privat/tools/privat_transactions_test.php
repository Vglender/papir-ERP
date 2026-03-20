<?php

require_once __DIR__ . '/../privat_api.php';

$api = new PrivatApi(array(
    'default_user_agent' => 'Papir',
    'default_limit' => 100,
));

$api->loadAccountsFromFile(__DIR__ . '/../storage/privat_accounts.php');

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d', strtotime('-1 day'));

try {
    $transactions = $api->getTransactionsByDate($date);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'date' => $date,
        'count' => count($transactions),
        'transactions' => $transactions,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'status' => 'ERROR',
        'message' => $e->getMessage(),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}