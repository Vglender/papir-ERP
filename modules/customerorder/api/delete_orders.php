<?php
/**
 * POST /customerorder/api/delete_orders
 * Soft-deletes one or more customer orders + cascade delete in МС.
 *
 * Params (JSON body):
 *   ids — array of customerorder.id to delete
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

require_once __DIR__ . '/../customerorder_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : array();

if (empty($ids)) {
    echo json_encode(array('ok' => false, 'error' => 'ids required'));
    exit;
}

$repo = new CustomerOrderRepository();
$service = new CustomerOrderService($repo);

$employeeId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : null;

$deleted = array();
$errors = array();

foreach ($ids as $id) {
    $id = (int)$id;
    if ($id <= 0) continue;

    $result = $service->deleteOrder($id, $employeeId);
    if ($result['ok']) {
        $deleted[] = $id;
    } else {
        $errors[] = array('id' => $id, 'error' => $result['error']);
    }
}

echo json_encode(array(
    'ok' => empty($errors),
    'deleted' => $deleted,
    'errors' => $errors,
));