<?php

require_once dirname(__DIR__) . '/monobank_api.php';

try {
    $from = isset($_GET['from']) ? (int)$_GET['from'] : 0;
    $to   = isset($_GET['to']) ? (int)$_GET['to'] : null;

    if ($from <= 0) {
        throw new Exception('GET parameter "from" is required and must be Unix timestamp');
    }

    $mono = new MonobankApi(dirname(__DIR__) . '/storage');
    $result = $mono->getAllStatements($from, $to);

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