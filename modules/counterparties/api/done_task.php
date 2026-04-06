<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$taskId = Request::postInt('task_id', 0);
if (!$taskId) {
    echo json_encode(array('ok' => false, 'error' => 'task_id required'));
    exit;
}

// Fetch task context before marking done (for TriggerEngine)
$_taskRow = null;
$_rTask = Database::fetchRow('Papir', "SELECT * FROM cp_tasks WHERE id={$taskId}");
if ($_rTask['ok'] && $_rTask['row']) { $_taskRow = $_rTask['row']; }

$ok = TaskRepository::markDone($taskId);

// Fire trigger after successful completion
if ($ok && $_taskRow) {
    $cpId = (int)$_taskRow['counterparty_id'];
    TriggerEngine::fire('task_done', array(
        'task'           => $_taskRow,
        'task_id'        => $taskId,
        'counterparty_id'=> $cpId,
        'order_id'       => 0,
    ));
}

// Return fresh task list for the cp/lead
$cpId   = Request::postInt('id', 0);
$leadId = Request::postInt('lead_id', 0);
$tasks  = array();
if ($cpId)   $tasks = TaskRepository::getForCounterparty($cpId);
if ($leadId) $tasks = TaskRepository::getForLead($leadId);

echo json_encode(array('ok' => $ok, 'tasks' => $tasks));