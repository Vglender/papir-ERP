<?php

require_once __DIR__ . '/../database/src/Database.php';
require_once __DIR__ . '/../moysklad/moysklad_api.php';
require_once __DIR__ . '/customerorder_repository.php';
require_once __DIR__ . '/customerorder_service.php';
require_once __DIR__ . '/customerorder_controller.php';
require_once __DIR__ . '/services/CustomerOrderMsSync.php';
require_once __DIR__ . '/../shared/DocumentHistory.php';

$dbConfigs = require __DIR__ . '/../database/config/databases.php';
Database::init($dbConfigs);