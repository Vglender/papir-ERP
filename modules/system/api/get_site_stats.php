<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../monitor_bootstrap.php';

// ── Helper to collect stats for one OpenCart DB ───────────────────────────────
function collectSiteStats($db)
{
    $stats = array();

    // Orders today
    $r = Database::fetchRow($db, "SELECT COUNT(*) as cnt FROM oc_order WHERE order_status_id > 0 AND DATE(date_added) = CURDATE()");
    $stats['orders_today'] = ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;

    // Orders this week
    $r = Database::fetchRow($db, "SELECT COUNT(*) as cnt FROM oc_order WHERE order_status_id > 0 AND date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['orders_week'] = ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;

    // Orders this month
    $r = Database::fetchRow($db, "SELECT COUNT(*) as cnt FROM oc_order WHERE order_status_id > 0 AND MONTH(date_added)=MONTH(NOW()) AND YEAR(date_added)=YEAR(NOW())");
    $stats['orders_month'] = ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;

    // Revenue this week
    $r = Database::fetchRow($db, "SELECT COALESCE(SUM(total),0) as rev FROM oc_order WHERE order_status_id > 0 AND date_added >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['revenue_week'] = ($r['ok'] && $r['row']) ? (float)$r['row']['rev'] : 0;

    // Revenue this month
    $r = Database::fetchRow($db, "SELECT COALESCE(SUM(total),0) as rev FROM oc_order WHERE order_status_id > 0 AND MONTH(date_added)=MONTH(NOW()) AND YEAR(date_added)=YEAR(NOW())");
    $stats['revenue_month'] = ($r['ok'] && $r['row']) ? (float)$r['row']['rev'] : 0;

    // Active products
    $r = Database::fetchRow($db, "SELECT COUNT(*) as cnt FROM oc_product WHERE status = 1");
    $stats['products_active'] = ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;

    // Products with stock
    $r = Database::fetchRow($db, "SELECT COUNT(*) as cnt FROM oc_product WHERE status = 1 AND quantity > 0");
    $stats['products_with_stock'] = ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;

    // Recent 5 orders
    $r = Database::fetchAll($db, "SELECT order_id, firstname, lastname, total, date_added, order_status_id FROM oc_order WHERE order_status_id > 0 ORDER BY date_added DESC LIMIT 5");
    $stats['recent_orders'] = ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();

    return $stats;
}

// ── Collect off stats ─────────────────────────────────────────────────────────
$offStats = collectSiteStats('off');

// Log files for off site
$logDir  = '/var/www/menufold/data/www/officetorg.com.ua/system/storage/logs/';
$logKeys = array('error.log', 'debug.log', 'ocmod.log');
$logs    = array();
foreach ($logKeys as $filename) {
    $path = $logDir . $filename;
    if (file_exists($path)) {
        $logs[] = array(
            'name'  => $filename,
            'path'  => $path,
            'size'  => filesize($path),
            'mtime' => filemtime($path),
        );
    }
}
$offStats['logs'] = $logs;

// ── Collect mff stats ─────────────────────────────────────────────────────────
$mffStats = collectSiteStats('mff');

echo json_encode(array(
    'ok'  => true,
    'off' => $offStats,
    'mff' => $mffStats,
));
