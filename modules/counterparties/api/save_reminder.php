<?php
/**
 * POST /counterparties/api/save_reminder
 * Create a scheduled reminder note for a counterparty or lead.
 *
 * Params:
 *   id           — counterparty_id (or lead_id below)
 *   lead_id      — lead_id (if lead)
 *   body         — reminder text
 *   scheduled_at — datetime: "2026-04-02 09:00" (server timezone)
 *   assigned_to  — employee.id (optional, defaults to null = everyone)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$cpId        = isset($_POST['id'])           ? (int)$_POST['id']              : 0;
$leadId      = isset($_POST['lead_id'])      ? (int)$_POST['lead_id']         : 0;
$body        = isset($_POST['body'])         ? trim($_POST['body'])            : '';
$scheduledAt = isset($_POST['scheduled_at']) ? trim($_POST['scheduled_at'])    : '';
$assignedTo  = isset($_POST['assigned_to'])  ? (int)$_POST['assigned_to']     : 0;

if ($cpId <= 0 && $leadId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id або lead_id обовʼязковий'));
    exit;
}
if ($body === '') {
    echo json_encode(array('ok' => false, 'error' => 'body обовʼязковий'));
    exit;
}
if (!$scheduledAt || !strtotime($scheduledAt)) {
    echo json_encode(array('ok' => false, 'error' => 'scheduled_at: невірний формат дати'));
    exit;
}

// Normalize to MySQL datetime
$scheduledAt = date('Y-m-d H:i:s', strtotime($scheduledAt));

// Must be in the future
if ($scheduledAt <= date('Y-m-d H:i:s')) {
    echo json_encode(array('ok' => false, 'error' => 'scheduled_at має бути в майбутньому'));
    exit;
}

$assignedTo = $assignedTo > 0 ? $assignedTo : null;

if ($leadId > 0) {
    $leadRepo = new LeadRepository();
    $msgId = $leadRepo->saveReminder($leadId, $body, $scheduledAt, $assignedTo);
} else {
    $chatRepo = new ChatRepository();
    $msgId = $chatRepo->saveReminder($cpId, $body, $scheduledAt, $assignedTo);
}

if (!$msgId) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
    exit;
}

echo json_encode(array(
    'ok'           => true,
    'id'           => $msgId,
    'scheduled_at' => $scheduledAt,
    'assigned_to'  => $assignedTo,
));
