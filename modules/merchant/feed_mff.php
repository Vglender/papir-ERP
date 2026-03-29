<?php
require_once __DIR__ . '/FeedGenerator.php';

$filters = array(
    'only_stock'  => !empty($_GET['only_stock']),
    'category_id' => isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0,
    'limit'       => isset($_GET['limit'])        ? (int)$_GET['limit']       : 0,
);

while (ob_get_level()) { ob_end_clean(); }

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');

MerchantFeedGenerator::stream($filters, 'mff');