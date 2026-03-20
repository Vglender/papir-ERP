<?php

require_once dirname(__DIR__) . '/monobank_api.php';

try {
    $mono = new MonobankApi(dirname(__DIR__) . '/storage');
    $result = $mono->getAllClientsInfo();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}