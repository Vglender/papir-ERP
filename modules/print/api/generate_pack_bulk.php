<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../services/PackGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$demandIds = isset($_POST['demand_ids']) ? trim($_POST['demand_ids']) : '';
$profileId = isset($_POST['profile_id']) ? (int)$_POST['profile_id'] : 0;

if (empty($demandIds)) {
    echo json_encode(array('ok' => false, 'error' => 'demand_ids required'));
    exit;
}

$ids = array_filter(array_map('intval', explode(',', $demandIds)));
if (empty($ids)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid demand IDs'));
    exit;
}

// Limit to prevent abuse
if (count($ids) > 50) {
    echo json_encode(array('ok' => false, 'error' => 'Максимум 50 відвантажень за раз'));
    exit;
}

$results = array();
foreach ($ids as $id) {
    $results[] = array(
        'demand_id' => $id,
        'result'    => PackGenerator::generate($id, $profileId),
    );
}

echo json_encode(array('ok' => true, 'results' => $results), JSON_UNESCAPED_UNICODE);
