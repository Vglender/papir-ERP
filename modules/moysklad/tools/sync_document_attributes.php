<?php

/**
 * CLI утилита синхронизации атрибутов документов и customentity из МойСклад в БД Papir.
 *
 * Примеры запуска:
 * php /var/www/papir/modules/moysklad/tools/sync_document_attributes.php
 * php /var/www/papir/modules/moysklad/tools/sync_document_attributes.php customerorder paymentin demand
 */

// --------------------------------------------------
// BOOTSTRAP
// --------------------------------------------------

$projectRoot = dirname(dirname(dirname(__DIR__))); // /var/www/papir

require_once $projectRoot . '/modules/database/src/Database.php';
require_once $projectRoot . '/modules/moysklad/moysklad_api.php';
require_once $projectRoot . '/modules/moysklad/src/MoySkladAttributesSync.php';

// DEBUG_START: вывод старта скрипта
/* echo PHP_EOL;
echo '===== sync_document_attributes.php START =====' . PHP_EOL;
echo 'Time: ' . date('Y-m-d H:i:s') . PHP_EOL;
echo 'Project root: ' . $projectRoot . PHP_EOL;
echo 'Database class: ' . $projectRoot . '/modules/database/src/Database.php' . PHP_EOL;
echo 'MoySklad api: ' . $projectRoot . '/modules/moysklad/moysklad_api.php' . PHP_EOL;
echo 'Sync class: ' . $projectRoot . '/modules/moysklad/src/MoySkladAttributesSync.php' . PHP_EOL; */
// DEBUG_END

// --------------------------------------------------
// INIT DB CONFIG
// --------------------------------------------------

$dbConfigFile = $projectRoot . '/modules/database/config/databases.php';

if (!file_exists($dbConfigFile)) {
    throw new Exception('DB config file not found: ' . $dbConfigFile);
}

$dbConfigs = require $dbConfigFile;

if (!is_array($dbConfigs)) {
    throw new Exception('DB config file must return array: ' . $dbConfigFile);
}

Database::init($dbConfigs);

// DEBUG_START: вывод инициализации БД
//echo 'DB config loaded: ' . $dbConfigFile . PHP_EOL;
// DEBUG_END

// --------------------------------------------------
// CONFIG
// --------------------------------------------------

$dbName = 'Papir';
$debug = true;

$defaultDocuments = [
    'salesreturn',
    'purchasereturn',
    'paymentin',
    'customerorder',
    'purchaseorder',
    'paymentout',
    'demand',
    'move',
    'supply',
    'cashin',
    'cashout',
    'loss',
    'processingplan',
    'processing',
];

$documentsFromCli = array_slice($argv, 1);
$documents = !empty($documentsFromCli) ? $documentsFromCli : $defaultDocuments;

// DEBUG_START: входные параметры
/* echo 'DB Name: ' . $dbName . PHP_EOL;
echo 'Debug: ' . ($debug ? 'ON' : 'OFF') . PHP_EOL;
echo 'Documents: ' . implode(', ', $documents) . PHP_EOL; */
// DEBUG_END

try {
    $ms = new MoySkladApi();
    $sync = new MoySkladAttributesSync($ms, $dbName, $debug);

    $result = $sync->syncAll($documents);

/*     echo PHP_EOL;
    echo '===== FINAL RESULT =====' . PHP_EOL;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL; */

    if (!$result['ok']) {
        echo PHP_EOL;
        echo 'SYNC FINISHED WITH ERRORS' . PHP_EOL;
        exit(1);
    }

    echo PHP_EOL;
    echo 'SYNC FINISHED SUCCESSFULLY' . PHP_EOL;
    exit(0);

} catch (Exception $e) {
    echo PHP_EOL;
    echo 'FATAL ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
} finally {
    if (class_exists('Database')) {
        Database::closeAll();
    }

    // DEBUG_START: вывод завершения скрипта
/*     echo PHP_EOL;
    echo '===== sync_document_attributes.php END =====' . PHP_EOL; */
    // DEBUG_END
}