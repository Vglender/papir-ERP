<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth_bootstrap.php';



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

\Papir\Crm\AuthService::log('logout');
\Papir\Crm\AuthService::logout();

echo json_encode(array('ok' => true, 'redirect' => '/login'));
