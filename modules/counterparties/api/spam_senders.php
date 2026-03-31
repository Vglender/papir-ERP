<?php
/**
 * GET  /counterparties/api/spam_senders        — список заблокованих
 * POST /counterparties/api/spam_senders (action=unblock, id=X) — розблокувати
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $id     = isset($_POST['id'])     ? (int)$_POST['id']      : 0;

    if ($action === 'unblock' && $id > 0) {
        $r = Database::query('Papir', "DELETE FROM spam_senders WHERE id={$id}");
        echo json_encode(array('ok' => $r['ok']));
    } else {
        echo json_encode(array('ok' => false, 'error' => 'unknown action'));
    }
    exit;
}

// GET — повертаємо список
$r = Database::fetchAll('Papir',
    "SELECT id, channel, identifier, display_name, lead_id, blocked_at
     FROM spam_senders
     ORDER BY blocked_at DESC
     LIMIT 200"
);
echo json_encode(array('ok' => true, 'rows' => $r['ok'] ? $r['rows'] : array()));