<?php
/**
 * Генерує XML-фід для Google Merchant Center і зберігає у публічний файл.
 * Запуск: 0 5 * * * php /var/www/papir/cron/generate_merchant_feed.php
 */

require_once __DIR__ . '/../modules/merchant/FeedGenerator.php';

$outFile = '/var/www/menufold/data/www/officetorg.com.ua/merchant_feed.xml';

echo date('Y-m-d H:i:s') . ' Генерація фіду...' . PHP_EOL;

$result = MerchantFeedGenerator::toFile($outFile, array('only_stock' => true));

if (!$result['ok']) {
    echo 'ПОМИЛКА: ' . $result['error'] . PHP_EOL;
    exit(1);
}

echo "Готово: {$result['items']} товарів, {$result['size_kb']} KB, {$result['elapsed']}с" . PHP_EOL;
echo "Файл: {$outFile}" . PHP_EOL;
echo "URL:  https://officetorg.com.ua/merchant_feed.xml" . PHP_EOL;
