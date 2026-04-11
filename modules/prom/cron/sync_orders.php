<?php
/**
 * Cron: import new orders from Prom.ua
 * Schedule: every 15 minutes
 */

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
require_once __DIR__ . '/../services/PromOrderImporter.php';

// TriggerEngine + ScenarioRepository — для fire('order_created') після INSERT
require_once __DIR__ . '/../../counterparties/services/TriggerEngine.php';
require_once __DIR__ . '/../../counterparties/repositories/ScenarioRepository.php';

AppRegistry::guard('prom');

$importer = new PromOrderImporter(function($msg) {
    error_log('[prom_sync] ' . $msg);
});

$result = $importer->importAll(7);

error_log(sprintf(
    '[prom_sync] done: imported=%d, skipped=%d, errors=%d',
    $result['imported'], $result['skipped'], count($result['errors'])
));
foreach ($result['errors'] as $e) {
    error_log('[prom_sync] error: ' . $e);
}