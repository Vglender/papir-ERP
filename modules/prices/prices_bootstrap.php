<?php

require_once __DIR__ . '/../database/database.php';

// Domain
require_once __DIR__ . '/domain/PurchasePriceResolver.php';
require_once __DIR__ . '/domain/BasePriceCalculator.php';
require_once __DIR__ . '/domain/RrpPriceAdjuster.php';
require_once __DIR__ . '/domain/DiscountStrategyResolver.php';
require_once __DIR__ . '/domain/QuantityThresholdResolver.php';
require_once __DIR__ . '/domain/DiscountPriceCalculator.php';
require_once __DIR__ . '/domain/PriceConsistencyValidator.php';
require_once __DIR__ . '/domain/PriceEngine.php';

// Repositories
require_once __DIR__ . '/repositories/GlobalSettingsRepository.php';
require_once __DIR__ . '/repositories/ProductPriceRepository.php';
require_once __DIR__ . '/repositories/DiscountStrategyRepository.php';
require_once __DIR__ . '/repositories/QuantityStrategyRepository.php';
require_once __DIR__ . '/repositories/ProductPackageRepository.php';
require_once __DIR__ . '/repositories/ProductDiscountProfileRepository.php';
require_once __DIR__ . '/repositories/SupplierRepository.php';
require_once __DIR__ . '/repositories/PricelistRepository.php';
require_once __DIR__ . '/repositories/PricelistItemRepository.php';

// Services
require_once __DIR__ . '/services/PriceRecalculationService.php';
require_once __DIR__ . '/services/PriceSyncService.php';
require_once __DIR__ . '/services/DiscountProfileBuilder.php';
require_once __DIR__ . '/services/PriceStrategyAutoSelector.php';
require_once __DIR__ . '/services/MoySkladPriceSync.php';
require_once __DIR__ . '/services/GoogleSheetsPriceSync.php';
require_once __DIR__ . '/services/OpenCartPriceExport.php';
require_once __DIR__ . '/services/MoySkladPriceExport.php';
