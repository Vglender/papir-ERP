<?php
// /var/www/papir/modules/customerorder/ajax_get_bank_accounts.php

// Включаем отображение всех ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/customerorder_bootstrap.php';
require_once __DIR__ . '/customerorder_helpers.php';

// Очищаем буфер вывода, чтобы избежать лишнего HTML
ob_clean();

header('Content-Type: application/json');

try {
    $organizationId = isset($_GET['organization_id']) ? (int)$_GET['organization_id'] : 0;
    
    if ($organizationId <= 0) {
        throw new Exception('Invalid organization ID');
    }
    
    $accounts = getOrganizationBankAccounts($organizationId);
    
    echo json_encode(array(
        'ok' => true,
        'accounts' => $accounts
    ));
    
} catch (Exception $e) {
    echo json_encode(array(
        'ok' => false,
        'error' => $e->getMessage(),
        'accounts' => array()
    ));
}
exit;