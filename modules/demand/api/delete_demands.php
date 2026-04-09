<?php
/**
 * POST /demand/api/delete_demands
 * Soft-deletes one or more demands.
 *
 * Params (JSON body):
 *   ids — array of demand.id to delete
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

require_once __DIR__ . '/../demand_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : array();

if (empty($ids)) {
    echo json_encode(array('ok' => false, 'error' => 'ids required'));
    exit;
}

$repo = new DemandRepository();

$deleted = array();
$errors = array();

foreach ($ids as $id) {
    $id = (int)$id;
    if ($id <= 0) continue;

    $result = $repo->softDelete($id);
    if ($result['ok']) {
        // Clean up document_link records for this demand
        Database::query('Papir',
            "DELETE FROM document_link WHERE (from_type='demand' AND from_id={$id}) OR (to_type='demand' AND to_id={$id})");
        $deleted[] = $id;
    } else {
        $errors[] = array('id' => $id, 'error' => isset($result['error']) ? $result['error'] : 'Unknown error');
    }
}

echo json_encode(array(
    'ok' => empty($errors),
    'deleted' => $deleted,
    'errors' => $errors,
));