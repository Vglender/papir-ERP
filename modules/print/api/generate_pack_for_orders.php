<?php
/**
 * POST /print/api/generate_pack_for_orders
 * Finds demands linked to given order IDs, generates packs, optionally queues them.
 *
 * Params:
 *   order_ids — comma-separated customerorder IDs
 *   queue     — if "1", sets queued=1 on generated packs
 *   profile_id — optional
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../services/PackGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orderIds  = isset($_POST['order_ids'])  ? trim($_POST['order_ids'])  : '';
$queue     = isset($_POST['queue'])      ? (int)$_POST['queue']      : 0;
$profileId = isset($_POST['profile_id']) ? (int)$_POST['profile_id'] : 0;

if (empty($orderIds)) {
    echo json_encode(array('ok' => false, 'error' => 'order_ids required'));
    exit;
}

$ids = array_filter(array_map('intval', explode(',', $orderIds)));
if (empty($ids) || count($ids) > 50) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid or too many order IDs'));
    exit;
}

// Find demands for these orders
$idList = implode(',', $ids);
$r = Database::fetchAll('Papir',
    "SELECT id FROM demand
     WHERE customerorder_id IN ({$idList}) AND deleted_at IS NULL
     ORDER BY id ASC");

$demandIds = array();
if ($r['ok'] && !empty($r['rows'])) {
    foreach ($r['rows'] as $row) {
        $demandIds[] = (int)$row['id'];
    }
}

if (empty($demandIds)) {
    echo json_encode(array('ok' => true, 'urls' => array(), 'queued' => 0, 'error' => 'Відвантажень не знайдено'));
    exit;
}

$allUrls = array();
$queued  = 0;

foreach ($demandIds as $demandId) {
    $result = PackGenerator::generate($demandId, $profileId);
    if (!$result['ok']) continue;

    $packId = (int)$result['pack_id'];

    if ($queue) {
        Database::update('Papir', 'print_pack_jobs',
            array('queued' => 1),
            array('id' => $packId));
        $queued++;
    } else {
        // Collect URLs for immediate printing
        foreach ($result['items'] as $item) {
            if (isset($item['status']) && $item['status'] === 'ok' && !empty($item['url'])) {
                $allUrls[] = $item['url'];
            }
        }
    }
}

echo json_encode(array(
    'ok'     => true,
    'urls'   => $allUrls,
    'queued' => $queued,
), JSON_UNESCAPED_UNICODE);
