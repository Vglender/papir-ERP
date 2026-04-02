<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$ids = isset($_POST['ids']) ? trim($_POST['ids']) : '';
if (!$ids) {
    echo json_encode(array('ok' => false, 'error' => 'ids required'));
    exit;
}

$idList = array_filter(array_map('intval', explode(',', $ids)));
if (empty($idList)) {
    echo json_encode(array('ok' => false, 'error' => 'No valid ids'));
    exit;
}

$deleted = array();
$errors  = array();

foreach ($idList as $ttnId) {
    $result = \Papir\Crm\TtnService::delete($ttnId);
    if ($result['ok']) {
        $deleted[] = $ttnId;
    } else {
        $errors[] = array('id' => $ttnId, 'error' => $result['error']);
    }
}

echo json_encode(array(
    'ok'      => count($errors) === 0,
    'deleted' => $deleted,
    'errors'  => $errors,
));