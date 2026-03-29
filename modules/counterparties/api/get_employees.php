<?php
/**
 * GET /counterparties/api/get_employees
 * Returns active CRM users for reminder assignment picker.
 * If user has linked employee → shows real name from employee table.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$r = Database::fetchAll('Papir',
    "SELECT u.user_id AS id,
            COALESCE(NULLIF(e.full_name,''), u.display_name) AS full_name
     FROM auth_users u
     LEFT JOIN employee e ON e.id = u.employee_id
     WHERE u.status = 'active'
     ORDER BY full_name ASC");

$employees = array();
if ($r['ok']) {
    foreach ($r['rows'] as $row) {
        $employees[] = array(
            'id'        => (int)$row['id'],
            'full_name' => $row['full_name'],
        );
    }
}

echo json_encode(array('ok' => true, 'employees' => $employees));