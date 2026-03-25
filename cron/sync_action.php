<?php

/**
 * Cron: розрахунок та публікація акційних цін.
 *
 * Ланцюг:
 *   1. ActionPriceCalculator — розраховує price_act → action_prices
 *   2. ActionPublisher       — публікує в off.oc_product_special + Google Merchant
 *
 * Запуск:
 *   php /var/www/papir/cron/sync_action.php
 *
 * Cron (щодня о 00:05 за Києвом, UTC+2/UTC+3):
 *   5 21 * * * php /var/www/papir/cron/sync_action.php >> /var/log/papir/sync_action.log 2>&1
 */

define('CRON_MODE', true);

function logLine($text, $type = 'info')
{
    echo '[' . strtoupper($type) . '] ' . $text . PHP_EOL;
}

require_once __DIR__ . '/../modules/action/action_bootstrap.php';

$logCallback = function ($message, $type) {
    logLine($message, $type);
};

$start = microtime(true);

echo '[' . date('Y-m-d H:i:s') . '] === START ACTION SYNC ===' . PHP_EOL;

// 1. Calculate action prices
$actionRepo = new ActionRepository();
$priceRepo  = new ActionPriceRepository();
$calculator = new ActionPriceCalculator($actionRepo, $priceRepo);

$calcResult = $calculator->calculate();

$calculated = 0;
if ($calcResult['ok']) {
    $calculated = isset($calcResult['calculated']) ? (int)$calcResult['calculated'] : 0;
    logLine('Calculated: ' . $calculated, 'success');
    if (isset($calcResult['message'])) {
        logLine($calcResult['message'], 'info');
    }
} else {
    $errMsg = isset($calcResult['error']) ? $calcResult['error'] : 'Unknown error';
    logLine('Calculate error: ' . $errMsg, 'error');
}

// 2. Publish prices
$publisher     = new ActionPublisher($priceRepo);
$publishResult = $publisher->publish($logCallback);

$published = 0;
if ($publishResult['ok']) {
    $published = isset($publishResult['published']) ? (int)$publishResult['published'] : 0;
    logLine('Published: ' . $published, 'success');
} else {
    $errMsg = isset($publishResult['error']) ? $publishResult['error'] : 'Unknown error';
    logLine('Publish error: ' . $errMsg, 'error');
}

$elapsed = round(microtime(true) - $start, 2);

echo '[' . date('Y-m-d H:i:s') . '] Done.'
    . ' calculated=' . $calculated
    . ' published=' . $published
    . ' time=' . $elapsed . 's'
    . PHP_EOL;
