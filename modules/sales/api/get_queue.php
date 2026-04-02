<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';

$r = Database::fetchAll('Papir',
    "SELECT q.id, q.status, q.action_type, q.executor, q.fire_at, q.done_at, q.result_note,
            c.name AS counterparty_name
     FROM cp_task_queue q
     LEFT JOIN counterparty c ON c.id = q.counterparty_id
     ORDER BY q.id DESC LIMIT 20"
);

echo json_encode(array('ok' => true, 'items' => $r['ok'] ? $r['rows'] : array()));