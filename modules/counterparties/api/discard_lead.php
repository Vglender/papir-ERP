<?php
/**
 * POST /counterparties/api/discard_lead
 * Mark lead as lost and block the sender in spam_senders
 *
 * Params:
 *   lead_id  — required
 *   block    — 1 (default) / 0: чи блокувати відправника
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$leadId     = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
$blockSender = !isset($_POST['block']) || $_POST['block'] !== '0'; // за замовчуванням — блокуємо

if ($leadId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'lead_id required'));
    exit;
}

$leadRepo = new LeadRepository();
$ok       = $leadRepo->discard($leadId);

if ($ok && $blockSender) {
    // Блокуємо відправника — додаємо в spam_senders
    $lead = $leadRepo->getById($leadId);
    if ($lead) {
        $channel    = $lead['source'];
        $identifier = null;

        if ($channel === 'email' && !empty($lead['email'])) {
            $identifier = trim($lead['email']);
        } elseif ($channel === 'telegram' && !empty($lead['telegram_chat_id'])) {
            $identifier = trim($lead['telegram_chat_id']);
        } elseif (in_array($channel, array('viber', 'sms')) && !empty($lead['phone'])) {
            $identifier = trim($lead['phone']);
        } elseif ($channel === 'website' && !empty($lead['email'])) {
            $identifier = trim($lead['email']);
        } elseif ($channel === 'website' && !empty($lead['phone'])) {
            $identifier = trim($lead['phone']);
        }

        if ($channel && $identifier) {
            $chEsc  = Database::escape('Papir', $channel);
            $idEsc  = Database::escape('Papir', $identifier);
            $exists = Database::fetchRow('Papir',
                "SELECT id FROM spam_senders
                 WHERE channel='{$chEsc}' AND identifier='{$idEsc}' LIMIT 1");
            if (!($exists['ok'] && !empty($exists['row']))) {
                Database::insert('Papir', 'spam_senders', array(
                    'channel'      => $channel,
                    'identifier'   => $identifier,
                    'lead_id'      => $leadId,
                    'display_name' => $lead['display_name'] ? $lead['display_name'] : $identifier,
                    'blocked_at'   => date('Y-m-d H:i:s'),
                ));
            }
        }
    }
}

echo json_encode(array('ok' => $ok));