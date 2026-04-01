<?php
/**
 * Bootstrap for novaposhta module.
 * Include this file at the top of every controller/api in this module.
 */
require_once __DIR__ . '/../../modules/database/database.php';
require_once __DIR__ . '/../../src/ViewHelper.php';
require_once __DIR__ . '/NovaPoshta.php';
require_once __DIR__ . '/repositories/TtnRepository.php';
require_once __DIR__ . '/repositories/SenderRepository.php';
require_once __DIR__ . '/repositories/NpReferenceRepository.php';
require_once __DIR__ . '/repositories/ScanSheetRepository.php';
require_once __DIR__ . '/repositories/NpCounterpartyRepository.php';
require_once __DIR__ . '/services/TtnService.php';
require_once __DIR__ . '/services/TrackingService.php';
require_once __DIR__ . '/services/ScanSheetService.php';
require_once __DIR__ . '/services/ReturnService.php';
require_once __DIR__ . '/NpDocumentMapper.php';
