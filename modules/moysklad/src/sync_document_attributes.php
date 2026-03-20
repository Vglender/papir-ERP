<?php

/**
 * CLI утилита синхронизации атрибутов документов и customentity из МойСклад в БД Papir.
 *
 * Пример запуска:
 * php /var/www/papir/modules/moysklad/tools/sync_document_attributes.php
 *
 * Пример запуска только для отдельных документов:
 * php /var/www/papir/modules/moysklad/tools/sync_document_attributes.php customerorder paymentin demand
 */

// --------------------------------------------------
// BOOTSTRAP
// --------------------------------------------------

// Подстрой пути под фактическую структуру проекта.
require_once __DIR__ . '/../moysklad_api.php';
require_once __DIR__ . '/../../shared/Database.php';
require_once __DIR__ . '/../src/MoySkladAttributesSync.php';

// DEBUG_START: вывод старта скрипта
/* echo PHP_EOL;
echo '===== sync_document_attributes.php START =====' . PHP_EOL;
echo 'Time: ' . date('Y-m-d H:i:s') . PHP_EOL; */
// DEBUG_END

// --------------------------------------------------
// INIT DB CONFIG
// --------------------------------------------------

/**
 * Ниже два варианта:
 *
 * 1) Если Database::init() уже вызывается где-то в bootstrap проекта,
 *    этот блок можно удалить.
 *
 * 2) Если нет — подключи здесь свой файл конфигов БД.
 */

// Пример:
 require_once '/var/www/papir/config/database.php';
 Database::init($dbConfigs);

// Временный защитный вариант — если не инициализировано, попробуем загрузить локальный конфиг.
// Подправь путь под свой проект.
if (!class_exists('Database')) {
    throw new Exception('Database class not loaded');
}

// Если у тебя Database::init() уже вызван выше в общем bootstrap — оставь как есть.
// Иначе раскомментируй и поправь этот блок:
/*
$dbConfigs = require '/var/www/papir/config/db_configs.php';
Database::init($dbConfigs);
*/

// --------------------------------------------------
// CONFIG
// --------------------------------------------------

$dbName = 'Papir';
$debug = true;

// Список документов по умолчанию.
// Убрал дубликат paymentout.
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

// Документы можно передать аргументами CLI
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

    echo PHP_EOL;
    echo '===== FINAL RESULT =====' . PHP_EOL;
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

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