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

require_once '/var/sqript/products/lib_stock_update.php';

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Обновление stock_</title>
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

        .log {
            background: #ffffff;
            border: 1px solid #dfe3ea;
            border-radius: 8px;
            padding: 16px;
            max-width: 1000px;
        }

        .log-line {
            padding: 3px 0;
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

<h2>Обновление остатков</h2>

<div class="log">';
 
// даём браузеру стартовый кусок, чтобы начал показывать вывод сразу
echo str_repeat(' ', 4096) . PHP_EOL;
flush();

updateStockFromMs(true);

echo '</div>

<a href="/action" class="back-link">Вернуться в dashboard</a>


</body>
</html>';

flush();
?>