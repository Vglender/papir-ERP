<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$cpId   = Request::postInt('id', 0);
$leadId = Request::postInt('lead_id', 0);
$title  = trim(Request::postString('title', ''));

if (!$title) {
    echo json_encode(array('ok' => false, 'error' => 'title required'));
    exit;
}
if (!$cpId && !$leadId) {
    echo json_encode(array('ok' => false, 'error' => 'id or lead_id required'));
    exit;
}

$data = array(
    'counterparty_id' => $cpId,
    'lead_id'         => $leadId ?: null,
    'title'           => $title,
    'task_type'       => Request::postString('task_type', 'other'),
    'priority'        => Request::postInt('priority', 3),
    'due_at'          => Request::postString('due_at', ''),
);

$newId = TaskRepository::create($data);

if (!$newId) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалося створити задачу'));
    exit;
}

// Return fresh list
if ($cpId) {
    $tasks = TaskRepository::getForCounterparty($cpId);
} else {
    $tasks = TaskRepository::getForLead($leadId);
}

echo json_encode(array('ok' => true, 'task_id' => $newId, 'tasks' => $tasks));
