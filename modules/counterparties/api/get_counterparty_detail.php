<?php
/**
 * GET /counterparties/api/get_counterparty_detail?id=X
 * Returns counterparty data for workspace right context panel + hub header
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

$repo = new CounterpartyRepository();
$cp   = $repo->getById($id);
if (!$cp) {
    echo json_encode(array('ok' => false, 'error' => 'not found'));
    exit;
}

$stats        = $repo->getOrderStats($id);
$recentOrders = $repo->getRecentOrders($id, 5);
$allContacts  = $repo->getContacts($id);

function cpAvailableChannels($phone, $email, $telegramChatId) {
    $ch = array('note');
    if ($phone)          { $ch[] = 'viber'; $ch[] = 'sms'; }
    if ($email)          { $ch[] = 'email'; }
    if ($telegramChatId) { $ch[] = 'telegram'; }
    return $ch;
}

$phone = $cp['company_phone'] ? $cp['company_phone'] : $cp['person_phone'];
$email = $cp['company_email'] ? $cp['company_email'] : $cp['person_email'];

// Build initials
$words = preg_split('/\s+/', $cp['name']);
if (count($words) >= 2) {
    $initials = mb_strtoupper(
        mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8'),
        'UTF-8'
    );
} else {
    $initials = mb_strtoupper(mb_substr($cp['name'], 0, 2, 'UTF-8'), 'UTF-8');
}

// Last active order
$activeStatuses = array('new','confirmed','in_progress','waiting_payment','paid','shipped');
$activeOrder = null;
foreach ($recentOrders as $o) {
    if (in_array($o['status'], $activeStatuses)) {
        $activeOrder = $o;
        break;
    }
}
if (!$activeOrder && !empty($recentOrders)) {
    $activeOrder = $recentOrders[0];
}

// Unread counts per channel
$unreadRaw = Database::fetchAll('Papir',
    "SELECT channel, COUNT(*) AS cnt FROM cp_messages
     WHERE counterparty_id = {$id} AND direction = 'in' AND read_at IS NULL
     GROUP BY channel");
$unreadByChannel = array('viber' => 0, 'sms' => 0, 'email' => 0, 'telegram' => 0, 'note' => 0);
if ($unreadRaw['ok']) {
    foreach ($unreadRaw['rows'] as $row) {
        if (isset($unreadByChannel[$row['channel']])) {
            $unreadByChannel[$row['channel']] = (int)$row['cnt'];
        }
    }
}

$orderStatusLabels = array(
    'draft'           => 'Чернетка',
    'new'             => 'Новий',
    'confirmed'       => 'Підтверджено',
    'in_progress'     => 'В роботі',
    'waiting_payment' => 'Очікує оплату',
    'paid'            => 'Оплачено',
    'shipped'         => 'Відвантажено',
    'completed'       => 'Виконано',
    'cancelled'       => 'Скасовано',
);

echo json_encode(array(
    'ok' => true,
    'cp' => array(
        'id'       => (int)$cp['id'],
        'type'     => $cp['type'],
        'name'     => $cp['name'],
        'initials' => $initials,
        'phone'    => $phone,
        'email'    => $email,
        'okpo'     => $cp['okpo'],
        'website'  => $cp['website'],
        'id_ms'    => $cp['id_ms'],
        'group_name'   => $cp['group_name'],
        'group_is_head'=> (bool)$cp['group_is_head'],
        'status'   => (int)$cp['status'],
        'telegram_chat_id'   => $cp['telegram_chat_id'],
        'available_channels' => cpAvailableChannels($phone, $email, $cp['telegram_chat_id']),
    ),
    'stats' => array(
        'order_count' => (int)$stats['order_count'],
        'ltv'         => (float)$stats['ltv'],
        'avg_check'   => (float)$stats['avg_check'],
        'last_order_at' => $stats['last_order_at'],
    ),
    'active_order' => $activeOrder ? array(
        'id'             => (int)$activeOrder['id'],
        'number'         => $activeOrder['number'],
        'status'         => $activeOrder['status'],
        'status_label'   => isset($orderStatusLabels[$activeOrder['status']]) ? $orderStatusLabels[$activeOrder['status']] : $activeOrder['status'],
        'sum_total'      => (float)$activeOrder['sum_total'],
        'moment'         => $activeOrder['moment'],
    ) : null,
    'order_status_labels' => $orderStatusLabels,
    'unread_by_channel'   => $unreadByChannel,
    'contacts'            => array_values(array_filter(array_map(function($ct) {
        if (!$ct['phone'] && !$ct['viber'] && !$ct['telegram'] && !$ct['email']) return null;
        return array(
            'id'                 => (int)$ct['id'],
            'name'               => $ct['name'],
            'available_channels' => cpAvailableChannels($ct['phone'], $ct['email'], null),
        );
    }, $allContacts))),
));
