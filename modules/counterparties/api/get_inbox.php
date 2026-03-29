<?php
/**
 * GET /counterparties/api/get_inbox?mode=chat|orders
 * Returns tiered list: leads (urgent) + counterparties (attention/active/processed)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

$mode = isset($_GET['mode']) ? trim($_GET['mode']) : 'chat';
if (!in_array($mode, array('chat', 'orders'))) $mode = 'chat';

$leadRepo = new LeadRepository();
$cpRepo   = new CounterpartyRepository();

// ── 1. Leads (always urgent tier) ────────────────────────────────────────────

$leadsRaw = $leadRepo->getActiveForInbox();
$leads = array();
foreach ($leadsRaw as $l) {
    $leads[] = array(
        'kind'          => 'lead',
        'id'            => (int)$l['id'],
        'tier'          => 'urgent',
        'source'        => $l['source'],
        'source_label'  => LeadRepository::sourceLabel($l['source']),
        'display_name'  => $l['display_name'] ? $l['display_name'] : LeadRepository::sourceLabel($l['source']),
        'phone'         => $l['phone'],
        'email'         => $l['email'],
        'status'        => $l['status'],
        'last_msg_body'    => $l['last_msg_body'],
        'last_msg_at'      => $l['last_msg_at'],
        'last_msg_dir'     => $l['last_msg_dir'],
        'last_msg_channel' => $l['last_msg_channel'],
        'unread_count'     => (int)$l['unread_count'],
        'created_at'       => $l['created_at'],
    );
}

// ── 2. Counterparties ────────────────────────────────────────────────────────

$activeStatuses = array('new','confirmed','in_progress','waiting_payment','paid','shipped');
$activeStatusesSql = "'" . implode("','", $activeStatuses) . "'";

$sql = "SELECT
            c.id, c.type, c.name, c.status AS cp_status,
            COALESCE(cc.phone, cp.phone) AS phone,
            COALESCE(cc.email, cp.email) AS email,
            msg.body        AS last_msg_body,
            msg.created_at  AS last_msg_at,
            msg.direction   AS last_msg_dir,
            msg.channel     AS last_msg_channel,
            COALESCE(unr.cnt, 0) AS unread_count,
            ord.id          AS last_order_id,
            ord.number      AS last_order_number,
            ord.status      AS last_order_status,
            ord.sum_total   AS last_order_sum,
            ord.moment      AS last_order_at
        FROM counterparty c
        LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
        LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
        LEFT JOIN (
            SELECT m1.counterparty_id, m1.body, m1.created_at, m1.direction, m1.channel
            FROM cp_messages m1
            INNER JOIN (
                SELECT counterparty_id, MAX(id) AS max_id
                FROM cp_messages
                WHERE counterparty_id IS NOT NULL
                GROUP BY counterparty_id
            ) latest ON latest.counterparty_id = m1.counterparty_id AND latest.max_id = m1.id
        ) msg ON msg.counterparty_id = c.id
        LEFT JOIN (
            SELECT counterparty_id, COUNT(*) AS cnt
            FROM cp_messages
            WHERE counterparty_id IS NOT NULL AND direction = 'in' AND read_at IS NULL
              AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            GROUP BY counterparty_id
        ) unr ON unr.counterparty_id = c.id
        LEFT JOIN (
            SELECT o1.*
            FROM customerorder o1
            INNER JOIN (
                SELECT counterparty_id, MAX(id) AS max_id
                FROM customerorder
                WHERE deleted_at IS NULL
                GROUP BY counterparty_id
            ) lo ON lo.counterparty_id = o1.counterparty_id AND lo.max_id = o1.id
        ) ord ON ord.counterparty_id = c.id
        WHERE c.status = 1
          AND (
              c.type IN ('company','fop','department','other')
              OR (c.type = 'person' AND NOT EXISTS (
                  SELECT 1 FROM counterparty_relation cr2
                  WHERE cr2.child_counterparty_id = c.id
                    AND cr2.relation_type IN ('contact_person','employee','accountant','director','manager')
              ))
          )
        ORDER BY COALESCE(msg.created_at, ord.moment, c.created_at) DESC
        LIMIT 300";

$r = Database::fetchAll('Papir', $sql);
$counterparties = array();
if ($r['ok']) {
    foreach ($r['rows'] as $row) {
        // Determine tier
        if ($row['unread_count'] > 0) {
            $tier = 'attention';
        } elseif ($row['last_order_status'] !== null && in_array($row['last_order_status'], $activeStatuses)) {
            $tier = 'active';
        } elseif ($row['last_msg_body'] !== null || $row['last_order_id'] !== null) {
            $tier = 'processed';
        } else {
            $tier = 'processed';
        }

        $counterparties[] = array(
            'kind'               => 'counterparty',
            'id'                 => (int)$row['id'],
            'tier'               => $tier,
            'type'               => $row['type'],
            'name'               => $row['name'],
            'phone'              => $row['phone'],
            'email'              => $row['email'],
            'last_msg_body'      => $row['last_msg_body'],
            'last_msg_at'        => $row['last_msg_at'],
            'last_msg_dir'       => $row['last_msg_dir'],
            'last_msg_channel'   => $row['last_msg_channel'],
            'unread_count'       => (int)$row['unread_count'],
            'last_order_number'  => $row['last_order_number'],
            'last_order_status'  => $row['last_order_status'],
            'last_order_sum'     => $row['last_order_sum'] !== null ? (float)$row['last_order_sum'] : null,
        );
    }
}

echo json_encode(array(
    'ok'    => true,
    'mode'  => $mode,
    'leads' => $leads,
    'counterparties' => $counterparties,
));
