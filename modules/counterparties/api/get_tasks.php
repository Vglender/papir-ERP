<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$cpId   = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
$leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;

if (!$cpId && !$leadId) {
    echo json_encode(array('ok' => false, 'error' => 'id or lead_id required'));
    exit;
}

// Wake tasks whose snooze expired
if ($cpId) {
    Database::query('Papir',
        "UPDATE cp_tasks SET status='open', snoozed_until=NULL
         WHERE counterparty_id={$cpId} AND status='snoozed' AND snoozed_until <= NOW()"
    );
    $tasks = TaskRepository::getForCounterparty($cpId);
} else {
    Database::query('Papir',
        "UPDATE cp_tasks SET status='open', snoozed_until=NULL
         WHERE lead_id={$leadId} AND status='snoozed' AND snoozed_until <= NOW()"
    );
    $tasks = TaskRepository::getForLead($leadId);
}

echo json_encode(array('ok' => true, 'tasks' => $tasks));
