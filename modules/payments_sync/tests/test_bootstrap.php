<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('PAYMENTS_SYNC_ROOT', realpath(__DIR__ . '/..'));
define('MODULES_ROOT', realpath(PAYMENTS_SYNC_ROOT . '/..'));

$files = [
    'Database.php' => MODULES_ROOT . '/database/src/Database.php',
    'moysklad_api.php' => MODULES_ROOT . '/moysklad/moysklad_api.php',
    'privat_api.php' => MODULES_ROOT . '/bank_privat/privat_api.php',
    'monobank_api.php' => MODULES_ROOT . '/bank_monobank/monobank_api.php',
    'ukrsib_api.php' => MODULES_ROOT . '/bank_ukrsib/ukrsib_api.php',
    'BankPaymentCollector.php' => PAYMENTS_SYNC_ROOT . '/services/BankPaymentCollector.php',
    'PaymentDuplicateChecker.php' => PAYMENTS_SYNC_ROOT . '/services/PaymentDuplicateChecker.php',
    'PaymentMatcher.php' => PAYMENTS_SYNC_ROOT . '/services/PaymentMatcher.php',
    'PaymentMsMapper.php' => PAYMENTS_SYNC_ROOT . '/services/PaymentMsMapper.php',
    'PaymentsSyncService.php' => PAYMENTS_SYNC_ROOT . '/services/PaymentsSyncService.php',
    'payments_sync_config.php' => PAYMENTS_SYNC_ROOT . '/config/payments_sync_config.php',
    'accounts_map.php' => PAYMENTS_SYNC_ROOT . '/config/accounts_map.php',
];

foreach ($files as $label => $path) {
    if (file_exists($path)) {
        echo "[OK] {$label} => {$path}" . PHP_EOL;
    } else {
        echo "[FAIL] {$label} => {$path}" . PHP_EOL;
    }
}