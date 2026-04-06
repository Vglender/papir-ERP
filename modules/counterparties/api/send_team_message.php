<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$currentUser = \Papir\Crm\AuthService::getCurrentUser();
if (!$currentUser) {
    echo json_encode(array('ok' => false, 'error' => 'unauthorized'));
    exit;
}
$myEmpId = (int)$currentUser['employee_id'];
if ($myEmpId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'no employee linked'));
    exit;
}

$body          = isset($_POST['body'])           ? trim($_POST['body'])           : '';
$toEmployeeId  = isset($_POST['to_employee_id']) ? (int)$_POST['to_employee_id'] : 0;
$counterpartyId= isset($_POST['counterparty_id'])? (int)$_POST['counterparty_id']: 0;
$fwdMsgId      = isset($_POST['fwd_msg_id'])     ? (int)$_POST['fwd_msg_id']     : 0;
$fwdAuthor     = isset($_POST['fwd_author'])     ? trim($_POST['fwd_author'])     : '';

if (!$body && !$fwdMsgId) {
    echo json_encode(array('ok' => false, 'error' => 'body required'));
    exit;
}

// If forwarding without body — use fwd content as body
if (!$body && $fwdMsgId) {
    $rOrig = Database::fetchRow('Papir',
        "SELECT body FROM cp_messages WHERE id = {$fwdMsgId} LIMIT 1");
    $body = ($rOrig['ok'] && !empty($rOrig['row'])) ? $rOrig['row']['body'] : '';
}

$row = array(
    'from_employee_id' => $myEmpId,
    'to_employee_id'   => $toEmployeeId > 0 ? $toEmployeeId : null,
    'counterparty_id'  => $counterpartyId > 0 ? $counterpartyId : null,
    'fwd_msg_id'       => $fwdMsgId > 0 ? $fwdMsgId : null,
    'fwd_author'       => $fwdAuthor ? $fwdAuthor : null,
    'body'             => $body,
);

$r = Database::insert('Papir', 'team_messages', $row);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'db error'));
    exit;
}

// Auto-mark as read for sender
$newId = $r['insert_id'];
Database::query('Papir',
    "INSERT IGNORE INTO team_message_reads (message_id, employee_id) VALUES ({$newId}, {$myEmpId})");

echo json_encode(array('ok' => true, 'id' => $newId));
