<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../analytics_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$site   = isset($_POST['site'])   ? preg_replace('/[^a-z]/', '', $_POST['site']) : 'off';
$period = isset($_POST['period']) ? (int)$_POST['period'] : 30;
$report = isset($_POST['report']) ? $_POST['report'] : 'summary';

if (!in_array($period, array(7, 30, 90, 365))) { $period = 30; }

switch ($report) {
    case 'summary':
        $result = GoogleAnalyticsService::getSummaryWithComparison($site, $period);
        break;
    case 'by_date':
        $result = GoogleAnalyticsService::getByDate($site, $period);
        break;
    case 'top_pages':
        $result = GoogleAnalyticsService::getTopPages($site, $period);
        break;
    case 'channels':
        $result = GoogleAnalyticsService::getChannels($site, $period);
        break;
    case 'geography':
        $result = GoogleAnalyticsService::getGeography($site, $period);
        break;
    case 'ecommerce':
        $result = GoogleAnalyticsService::getEcommerce($site, $period);
        break;
    case 'orders_by_channel':
        $result = GoogleAnalyticsService::getOrdersByChannel($site, $period);
        break;
    default:
        $result = array('ok' => false, 'error' => 'Unknown report: ' . $report);
}

echo json_encode($result);
