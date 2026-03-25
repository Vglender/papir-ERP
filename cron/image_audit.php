<?php
/**
 * Cron: Image Audit — щонеділі о 3:00
 * Аудит + видалення orphans + стиснення oversized + прогрів cache (без очистки)
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

$scripts = __DIR__ . '/../scripts';

function run($label, $cmd) {
    echo "\n=== {$label} === " . date('Y-m-d H:i:s') . "\n";
    passthru($cmd);
    echo "--- done: " . date('H:i:s') . "\n";
}

run('Аудит зображень',        "php {$scripts}/image_audit.php --delete-orphans");
run('Стиснення oversized',    "php {$scripts}/recompress_images.php");
run('Оновлення звіту',        "php {$scripts}/image_audit.php");
run('Прогрів cache',          "php {$scripts}/warm_image_cache.php");

echo "\nCron image_audit завершено: " . date('Y-m-d H:i:s') . "\n";
