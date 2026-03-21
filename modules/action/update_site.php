<?php

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', 1);

while (ob_get_level() > 0) {
    ob_end_flush();
}

ob_implicit_flush(true);
set_time_limit(0);

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Обновление сайта</title>
    <style>
        body {
            font-family: Consolas, monospace;
            background: #f4f6fa;
            padding: 20px;
            margin: 0;
        }
        h2 {
            font-family: Arial, sans-serif;
            margin: 0 0 16px 0;
        }
        h3 {
            font-family: Arial, sans-serif;
            margin: 20px 0 10px 0;
            font-size: 16px;
        }
        .log {
            background: #ffffff;
            border: 1px solid #dfe3ea;
            border-radius: 8px;
            padding: 16px;
            max-width: 1100px;
        }
        .log-line {
            padding: 4px 0;
        }
        .log-success {
            color: #1a7f37;
            font-weight: bold;
        }
        .log-progress {
            color: #9a6700;
        }
        .log-error {
            color: #d1242f;
            font-weight: bold;
        }
        .log-info {
            color: #444;
        }
        .phase-header {
            font-family: Arial, sans-serif;
            font-size: 15px;
            font-weight: bold;
            margin: 16px 0 8px 0;
            padding: 8px 12px;
            background: #eef4ff;
            border-left: 3px solid #1f6feb;
            border-radius: 4px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-decoration: none;
            font-family: Arial, sans-serif;
            background: #fff;
            color: #222;
        }
    </style>
</head>
<body>
<h2>Обновление сайта</h2>
<div class="log">';

// Give browser initial chunk to start rendering immediately
echo str_repeat(' ', 4096) . PHP_EOL;
flush();

require_once __DIR__ . '/action_bootstrap.php';

// Logging helper
if (!function_exists('logLine')) {
    function logLine($text, $type = 'info')
    {
        echo '<div class="log-line log-' . $type . '">'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '</div>';

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}

$logCallback = function ($message, $type) {
    logLine($message, $type);
};

// -------------------------------------------------------
// PHASE 1: Calculate prices
// -------------------------------------------------------
echo '<div class="phase-header">Фаза 1: Расчёт цен</div>';
flush();

$actionRepo = new ActionRepository();
$priceRepo  = new ActionPriceRepository();
$calculator = new ActionPriceCalculator($actionRepo, $priceRepo);

logLine('=== START CALCULATE ===', 'info');

$calcResult = $calculator->calculate();

if ($calcResult['ok']) {
    $calculated = isset($calcResult['calculated']) ? (int)$calcResult['calculated'] : 0;
    logLine('Рассчитано цен: ' . $calculated, 'success');

    if (isset($calcResult['message'])) {
        logLine($calcResult['message'], 'info');
    }
} else {
    $errMsg = isset($calcResult['error']) ? $calcResult['error'] : 'Unknown error';
    logLine('Ошибка расчёта: ' . $errMsg, 'error');
}

logLine('=== END CALCULATE ===', 'info');
flush();

// -------------------------------------------------------
// PHASE 2: Publish prices
// -------------------------------------------------------
echo '<div class="phase-header">Фаза 2: Публикация на сайт</div>';
flush();

$publisher = new ActionPublisher($priceRepo);

logLine('=== START PUBLISH ===', 'info');

$publishResult = $publisher->publish($logCallback);

if ($publishResult['ok']) {
    $published = isset($publishResult['published']) ? (int)$publishResult['published'] : 0;
    logLine('Опубликовано товаров: ' . $published, 'success');
} else {
    $errMsg = isset($publishResult['error']) ? $publishResult['error'] : 'Unknown error';
    logLine('Ошибка публикации: ' . $errMsg, 'error');

    if (isset($publishResult['auth_url'])) {
        echo '<div style="padding:16px;background:#fff3cd;border:1px solid #ffe69c;border-radius:8px;margin:16px 0;">';
        echo '<strong>Нужна повторная авторизация Google Merchant.</strong><br><br>';
        echo '<a href="' . htmlspecialchars($publishResult['auth_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank">Открыть авторизацию Google</a>';
        echo '<br><br>После авторизации вернитесь и снова запустите обновление сайта.';
        echo '</div>';
        flush();
    }
}

logLine('=== END PUBLISH ===', 'info');

echo '</div>
<a href="/action" class="back-link">Вернуться в dashboard</a>
</body>
</html>';

flush();
