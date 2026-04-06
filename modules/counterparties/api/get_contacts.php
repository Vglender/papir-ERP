<?php
/**
 * GET /counterparties/api/get_contacts?counterparty_id=X
 * Returns linked contact persons for a given counterparty.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$counterpartyId = isset($_GET['counterparty_id']) ? (int)$_GET['counterparty_id'] : 0;

if ($counterpartyId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'counterparty_id required'));
    exit;
}

$repo = new CounterpartyRepository();
$contacts = $repo->getContacts($counterpartyId);

$result = array();
foreach ($contacts as $row) {
    $result[] = array(
        'id'   => (int)$row['id'],
        'name' => $row['name'],
        'phone' => isset($row['phone']) ? $row['phone'] : '',
        'position' => isset($row['position_name']) ? $row['position_name'] : (isset($row['job_title']) ? $row['job_title'] : ''),
    );
}

echo json_encode(array('ok' => true, 'items' => $result));