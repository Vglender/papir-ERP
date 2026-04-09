<?php
require_once __DIR__ . '/IntegrationSettingsService.php';

$appKey = isset($_GET['key']) ? trim($_GET['key']) : '';
$app    = IntegrationSettingsService::getRegistryEntry($appKey);
$appName = $app ? $app['name'] : 'Додаток';

$title     = $appName;
$activeNav = 'integr';
$subNav    = 'catalog';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/app_settings.php';
require_once __DIR__ . '/../shared/layout_end.php';
