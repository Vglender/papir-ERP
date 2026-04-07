<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$currentUser = \Papir\Crm\AuthService::getCurrentUser();
if (!$currentUser) {
    echo json_encode(array('ok' => false, 'error' => 'unauthorized'));
    exit;
}
$myEmpId = (int)$currentUser['employee_id'];

// mode: 'cp' (counterparty context), 'general' (no context), 'dm' (direct)
$mode       = isset($_GET['mode'])     ? trim($_GET['mode'])     : 'general';
$withEmpId  = isset($_GET['with'])     ? (int)$_GET['with']      : 0;
$cpId       = isset($_GET['cp_id'])    ? (int)$_GET['cp_id']     : 0;
$limit      = isset($_GET['limit'])    ? (int)$_GET['limit']     : 60;
$afterId    = isset($_GET['after_id']) ? (int)$_GET['after_id']  : 0;

if ($mode === 'dm' && $withEmpId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'with required for dm'));
    exit;
}

if ($mode === 'cp') {
    // Broadcast messages + DMs addressed to/from me in this cp context
    $where = $cpId > 0
        ? "tm.counterparty_id = {$cpId} AND (tm.to_employee_id IS NULL OR tm.to_employee_id = {$myEmpId} OR tm.from_employee_id = {$myEmpId})"
        : "1=0";
} elseif ($mode === 'general') {
    // Global team chat — no counterparty filter
    $where = "tm.to_employee_id IS NULL AND tm.counterparty_id IS NULL";
} else {
    // DM between two employees
    $where = "(tm.to_employee_id IS NOT NULL AND (
        (tm.from_employee_id = {$myEmpId} AND tm.to_employee_id = {$withEmpId}) OR
        (tm.from_employee_id = {$withEmpId} AND tm.to_employee_id = {$myEmpId})
    ))";
}

if ($afterId > 0) {
    $where .= " AND tm.id > {$afterId}";
    $sql = "SELECT * FROM (
        SELECT tm.*, e.full_name AS from_name, c.name AS cp_name,
               LEFT(fwd.body, 200) AS fwd_body
        FROM team_messages tm
        LEFT JOIN employee e ON e.id = tm.from_employee_id
        LEFT JOIN counterparty c ON c.id = tm.counterparty_id
        LEFT JOIN cp_messages fwd ON fwd.id = tm.fwd_msg_id
        WHERE {$where}
        ORDER BY tm.created_at ASC LIMIT {$limit}
    ) sub ORDER BY sub.created_at ASC";
} else {
    $sql = "SELECT * FROM (
        SELECT tm.*, e.full_name AS from_name, c.name AS cp_name,
               LEFT(fwd.body, 200) AS fwd_body
        FROM team_messages tm
        LEFT JOIN employee e ON e.id = tm.from_employee_id
        LEFT JOIN counterparty c ON c.id = tm.counterparty_id
        LEFT JOIN cp_messages fwd ON fwd.id = tm.fwd_msg_id
        WHERE {$where}
        ORDER BY tm.created_at DESC LIMIT {$limit}
    ) sub ORDER BY sub.created_at ASC";
}

$r = Database::fetchAll('Papir', $sql);
$msgs = $r['ok'] ? $r['rows'] : array();

// Mark as read
if (!empty($msgs)) {
    $ids = implode(',', array_map(function($m){ return (int)$m['id']; }, $msgs));
    Database::query('Papir',
        "INSERT IGNORE INTO team_message_reads (message_id, employee_id)
         SELECT id, {$myEmpId} FROM team_messages WHERE id IN ({$ids})");
}

// Unread counts for DM threads (for badge display)
$dmUnread = array();
if ($mode === 'general') {
    $rU = Database::fetchAll('Papir',
        "SELECT tm.from_employee_id, COUNT(*) AS cnt
         FROM team_messages tm
         LEFT JOIN team_message_reads tmr ON tmr.message_id = tm.id AND tmr.employee_id = {$myEmpId}
         WHERE tm.to_employee_id = {$myEmpId} AND tmr.message_id IS NULL
         GROUP BY tm.from_employee_id");
    if ($rU['ok']) {
        foreach ($rU['rows'] as $row) {
            $dmUnread[(int)$row['from_employee_id']] = (int)$row['cnt'];
        }
    }
}

$out = array();
foreach ($msgs as $m) {
    $out[] = array(
        'id'              => (int)$m['id'],
        'from_employee_id'=> (int)$m['from_employee_id'],
        'from_name'       => $m['from_name'],
        'to_employee_id'  => $m['to_employee_id'] ? (int)$m['to_employee_id'] : null,
        'counterparty_id' => $m['counterparty_id'] ? (int)$m['counterparty_id'] : null,
        'cp_name'         => $m['cp_name'],
        'fwd_msg_id'      => $m['fwd_msg_id'] ? (int)$m['fwd_msg_id'] : null,
        'fwd_author'      => $m['fwd_author'],
        'fwd_body'        => isset($m['fwd_body']) ? $m['fwd_body'] : null,
        'body'            => $m['body'],
        'created_at'      => $m['created_at'],
        'is_mine'         => ((int)$m['from_employee_id'] === $myEmpId),
    );
}

echo json_encode(array(
    'ok'        => true,
    'messages'  => $out,
    'dm_unread' => $dmUnread,
    'my_emp_id' => $myEmpId,
));
