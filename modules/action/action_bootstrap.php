<?php

// Database module
require_once __DIR__ . '/../../modules/database/database.php';

// Source helpers (Paginator, Request, ViewHelper, TableHelper)
require_once __DIR__ . '/../../src/Paginator.php';
require_once __DIR__ . '/../../src/Request.php';
require_once __DIR__ . '/../../src/ViewHelper.php';
require_once __DIR__ . '/../../src/TableHelper.php';

// Repositories
require_once __DIR__ . '/repositories/ActionRepository.php';
require_once __DIR__ . '/repositories/ActionPriceRepository.php';
require_once __DIR__ . '/repositories/ActionDashboardRepository.php';

// Services
require_once __DIR__ . '/services/ActionPriceCalculator.php';
require_once __DIR__ . '/services/ActionPublisher.php';
require_once __DIR__ . '/services/StockUpdater.php';

// Internal merchant module (Google Merchant, no external deps)
require_once __DIR__ . '/../../modules/merchant/MerchantService.php';
