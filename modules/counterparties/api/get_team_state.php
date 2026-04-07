<?php
// Returns employee list + unread counts for team chat badge/tabs
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$currentUser = \Papir\Crm\AuthService::getCurrentUser();
if (!$currentUser) {
    echo json_encode(array('ok' => false, 'error' => 'unauthorized'));
    exit;
}
$myEmpId = (int)$currentUser['employee_id'];

// Team chat participants: active employees who have an active auth account (excluding self and bots)
$rEmps = Database::fetchAll('Papir',
    "SELECT e.id, e.full_name
     FROM employee e
     JOIN auth_users au ON au.employee_id = e.id AND au.status = 'active'
     WHERE e.status = 1
     ORDER BY e.full_name");
$employees = array();
foreach ($rEmps['ok'] ? $rEmps['rows'] : array() as $e) {
    if ((int)$e['id'] !== $myEmpId) {
        $employees[] = array('id' => (int)$e['id'], 'name' => $e['full_name']);
    }
}

// Unread in global general chat (no cp context)
$rGeneral = Database::fetchRow('Papir',
    "SELECT COUNT(*) AS cnt
     FROM team_messages tm
     LEFT JOIN team_message_reads tmr ON tmr.message_id = tm.id AND tmr.employee_id = {$myEmpId}
     WHERE tm.to_employee_id IS NULL AND tm.counterparty_id IS NULL
       AND tm.from_employee_id != {$myEmpId} AND tmr.message_id IS NULL");
$generalUnread = ($rGeneral['ok'] && $rGeneral['row']) ? (int)$rGeneral['row']['cnt'] : 0;

// Unread in cp-context messages (for ws badge)
$cpId = isset($_GET['cp_id']) ? (int)$_GET['cp_id'] : 0;
$cpUnread = 0;
if ($cpId > 0) {
    $rCp = Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt
         FROM team_messages tm
         LEFT JOIN team_message_reads tmr ON tmr.message_id = tm.id AND tmr.employee_id = {$myEmpId}
         WHERE tm.to_employee_id IS NULL AND tm.counterparty_id = {$cpId}
           AND tm.from_employee_id != {$myEmpId} AND tmr.message_id IS NULL");
    $cpUnread = ($rCp['ok'] && $rCp['row']) ? (int)$rCp['row']['cnt'] : 0;
}

// Unread DMs (including those with cp context — so shared orders are counted)
$rDm = Database::fetchAll('Papir',
    "SELECT tm.from_employee_id, COUNT(*) AS cnt
     FROM team_messages tm
     LEFT JOIN team_message_reads tmr ON tmr.message_id = tm.id AND tmr.employee_id = {$myEmpId}
     WHERE tm.to_employee_id = {$myEmpId} AND tmr.message_id IS NULL
     GROUP BY tm.from_employee_id");
$dmUnread = array();
foreach ($rDm['ok'] ? $rDm['rows'] : array() as $row) {
    $dmUnread[(int)$row['from_employee_id']] = (int)$row['cnt'];
}

echo json_encode(array(
    'ok'             => true,
    'my_emp_id'      => $myEmpId,
    'employees'      => $employees,
    'general_unread' => $generalUnread,
    'cp_unread'      => $cpUnread,
    'dm_unread'      => $dmUnread,
));