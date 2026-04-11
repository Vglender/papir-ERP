<?php
/**
 * Bootstrap for Ukrposhta module.
 * Include at top of every controller / API endpoint.
 */
require_once __DIR__ . '/../database/database.php';
require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';
require_once __DIR__ . '/../integrations/AppRegistry.php';

require_once __DIR__ . '/UkrposhtaApi.php';
require_once __DIR__ . '/UpDefaults.php';
require_once __DIR__ . '/repositories/UpTtnRepository.php';
require_once __DIR__ . '/repositories/UpGroupRepository.php';
require_once __DIR__ . '/repositories/UpGroupLinkRepository.php';
require_once __DIR__ . '/repositories/UpSenderRepository.php';
require_once __DIR__ . '/repositories/UpClassifierRepository.php';
require_once __DIR__ . '/services/ClassifierService.php';
require_once __DIR__ . '/services/TtnService.php';
require_once __DIR__ . '/services/TrackingService.php';
require_once __DIR__ . '/services/GroupService.php';