<?php
/**
 * Генерує XML-фід для Google Merchant Center (менюфолдер) і зберігає у публічний файл.
 * Запуск: 30 5 * * * php /var/www/papir/cron/generate_merchant_feed_mff.php
 */

require_once __DIR__ . '/../modules/merchant/FeedGenerator.php';

$outFile = '/var/www/menufold/data/www/officetorg.com.ua/merchant_feed_mff.xml';

echo date('Y-m-d H:i:s') . ' Генерація фіду menufolder...' . PHP_EOL;

$result = MerchantFeedGenerator::toFile($outFile, array('only_stock' => true), 'mff');

if (!$result['ok']) {
    echo 'ПОМИЛКА: ' . $result['error'] . PHP_EOL;
    exit(1);
}

echo "Готово: {$result['items']} товарів, {$result['size_kb']} KB, {$result['elapsed']}с" . PHP_EOL;
echo "Файл: {$outFile}" . PHP_EOL;
echo "URL:  https://officetorg.com.ua/merchant_feed_mff.xml" . PHP_EOL;