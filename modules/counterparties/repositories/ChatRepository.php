<?php

class ChatRepository
{
    // ── Messages ─────────────────────────────────────────────────────────────

    public function getMessages($counterpartyId, $channel = null, $limit = 60)
    {
        $cid   = (int)$counterpartyId;
        $limit = (int)$limit;
        $where = "counterparty_id = {$cid}"
               . " AND (scheduled_at IS NULL OR scheduled_at <= NOW())";
        if ($channel) {
            $ch    = Database::escape('Papir', $channel);
            $where .= " AND channel = '{$ch}'";
        }
        $r = Database::fetchAll('Papir',
            "SELECT * FROM cp_messages WHERE {$where} ORDER BY created_at ASC LIMIT {$limit}");
        return $r['ok'] ? $r['rows'] : array();
    }

    public function saveMessage($data)
    {
        $row = array(
            'counterparty_id' => (int)$data['counterparty_id'],
            'channel'         => $data['channel'],
            'direction'       => isset($data['direction'])    ? $data['direction']    : 'out',
            'status'          => isset($data['status'])       ? $data['status']       : 'sent',
            'phone'           => isset($data['phone'])        ? $data['phone']        : null,
            'email_addr'      => isset($data['email_addr'])   ? $data['email_addr']   : null,
            'subject'         => isset($data['subject'])      ? $data['subject']      : null,
            'body'            => $data['body'],
            'media_url'       => isset($data['media_url'])    ? $data['media_url']    : null,
            'external_id'     => isset($data['external_id'])  ? (string)$data['external_id'] : null,
            'order_id'        => isset($data['order_id'])     ? (int)$data['order_id'] : null,
        );
        $r = Database::insert('Papir', 'cp_messages', $row);
        return ($r['ok']) ? (int)$r['insert_id'] : 0;
    }

    public function getUnreadCount($counterpartyId)
    {
        $cid = (int)$counterpartyId;
        $r   = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM cp_messages
             WHERE counterparty_id = {$cid} AND direction = 'in' AND read_at IS NULL
               AND (scheduled_at IS NULL OR scheduled_at <= NOW())");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;
    }

    public function markRead($counterpartyId, $channel = null)
    {
        $cid   = (int)$counterpartyId;
        $where = "counterparty_id = {$cid} AND direction = 'in' AND read_at IS NULL"
               . " AND (scheduled_at IS NULL OR scheduled_at <= NOW())";
        if ($channel) {
            $ch    = Database::escape('Papir', $channel);
            $where .= " AND channel = '{$ch}'";
        }
        Database::query('Papir', "UPDATE cp_messages SET read_at = NOW() WHERE {$where}");
    }

    public function saveReminder($counterpartyId, $body, $scheduledAt, $assignedTo = null)
    {
        $row = array(
            'counterparty_id' => (int)$counterpartyId,
            'channel'         => 'note',
            'direction'       => 'in',
            'status'          => 'sent',
            'body'            => $body,
            'scheduled_at'    => $scheduledAt,
            'assigned_to'     => $assignedTo ? (int)$assignedTo : null,
            'read_at'         => null,
        );
        $r = Database::insert('Papir', 'cp_messages', $row);
        return $r['ok'] ? (int)$r['insert_id'] : 0;
    }

    // Find counterparty by Telegram chat_id (stored in phone field for telegram channel)
    public function findCounterpartyByTelegramChatId($chatId)
    {
        $esc = Database::escape('Papir', (string)$chatId);
        $r   = Database::fetchRow('Papir',
            "SELECT counterparty_id FROM cp_messages
             WHERE channel = 'telegram' AND phone = '{$esc}' AND counterparty_id > 0
             ORDER BY id DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['counterparty_id'] : 0;
    }

    // Find lead_id by Telegram chat_id
    public function findLeadByTelegramChatId($chatId)
    {
        $esc = Database::escape('Papir', (string)$chatId);
        $r   = Database::fetchRow('Papir',
            "SELECT lead_id FROM cp_messages
             WHERE channel = 'telegram' AND phone = '{$esc}' AND lead_id IS NOT NULL
             ORDER BY id DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['lead_id'] : 0;
    }

    // Get Telegram chat_id for outgoing messages (from last incoming message)
    public function getTelegramChatId($counterpartyId)
    {
        $cid = (int)$counterpartyId;
        $r   = Database::fetchRow('Papir',
            "SELECT phone FROM cp_messages
             WHERE counterparty_id = {$cid} AND channel = 'telegram'
               AND direction = 'in' AND phone IS NOT NULL
             ORDER BY id DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row']['phone'] : null;
    }

    // Find counterparty by phone (last 9 digits match)
    public function findCounterpartyByPhone($phone)
    {
        $last9 = Database::escape('Papir', AlphaSmsService::phoneLast9($phone));
        $r = Database::fetchRow('Papir',
            "SELECT c.id FROM counterparty c
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             WHERE c.status = 1
               AND (
                   REPLACE(REPLACE(REPLACE(REPLACE(cc.phone,'+',''),' ',''),'-',''),'(','') LIKE '%{$last9}'
                OR REPLACE(REPLACE(REPLACE(REPLACE(cp.phone,'+',''),' ',''),'-',''),'(','') LIKE '%{$last9}'
               )
             LIMIT 1");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['id'] : 0;
    }

    // ── Templates ────────────────────────────────────────────────────────────

    public function getTemplates($channel = null)
    {
        $where = 'status = 1';
        if ($channel) {
            $ch    = Database::escape('Papir', $channel);
            $where .= " AND FIND_IN_SET('{$ch}', channels) > 0";
        }
        $r = Database::fetchAll('Papir',
            "SELECT * FROM cp_message_templates WHERE {$where} ORDER BY sort_order ASC, id ASC");
        return $r['ok'] ? $r['rows'] : array();
    }

    public function getAllTemplates()
    {
        $r = Database::fetchAll('Papir',
            "SELECT * FROM cp_message_templates ORDER BY sort_order ASC, id ASC");
        return $r['ok'] ? $r['rows'] : array();
    }

    public function saveTemplate($data)
    {
        $id     = isset($data['id']) ? (int)$data['id'] : 0;
        $fields = array(
            'title'      => trim((string)$data['title']),
            'body'       => trim((string)$data['body']),
            'channels'   => isset($data['channels'])   ? trim($data['channels'])   : 'viber,sms',
            'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order']  : 0,
            'status'     => isset($data['status'])     ? (int)(bool)$data['status'] : 1,
        );
        if ($id > 0) {
            $r = Database::update('Papir', 'cp_message_templates', $fields, array('id' => $id));
            return $r['ok'] ? $id : 0;
        }
        $r = Database::insert('Papir', 'cp_message_templates', $fields);
        return $r['ok'] ? (int)$r['insert_id'] : 0;
    }

    public function deleteTemplate($id)
    {
        $r = Database::query('Papir', "DELETE FROM cp_message_templates WHERE id = " . (int)$id);
        return $r['ok'];
    }
}
