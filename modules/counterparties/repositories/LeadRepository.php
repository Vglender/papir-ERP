<?php

class LeadRepository
{
    // ── Source labels ─────────────────────────────────────────────────────────

    public static function sourceLabel($source)
    {
        $map = array(
            'telegram' => 'Telegram',
            'email'    => 'Email',
            'website'  => 'Сайт',
            'viber'    => 'Viber',
            'sms'      => 'SMS',
            'manual'   => 'Вручну',
        );
        return isset($map[$source]) ? $map[$source] : $source;
    }

    public static function sourceIcon($source)
    {
        $map = array(
            'telegram' => '✈',
            'email'    => '✉',
            'website'  => '🌐',
            'viber'    => '📱',
            'sms'      => '💬',
            'manual'   => '✎',
        );
        return isset($map[$source]) ? $map[$source] : '?';
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function create($data)
    {
        $uuid = $this->generateUuid();
        $row = array(
            'uuid'             => $uuid,
            'source'           => isset($data['source'])           ? $data['source']           : 'manual',
            'source_ref'       => isset($data['source_ref'])       ? $data['source_ref']       : null,
            'display_name'     => isset($data['display_name'])     ? trim($data['display_name']): null,
            'phone'            => isset($data['phone'])            ? trim($data['phone'])       : null,
            'email'            => isset($data['email'])            ? trim($data['email'])       : null,
            'telegram_chat_id' => isset($data['telegram_chat_id']) ? $data['telegram_chat_id'] : null,
            'status'           => 'new',
        );
        $r = Database::insert('Papir', 'leads', $row);
        return $r['ok'] ? (int)$r['insert_id'] : 0;
    }

    public function getById($id)
    {
        $id = (int)$id;
        $r = Database::fetchRow('Papir', "SELECT * FROM leads WHERE id = {$id} LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public function update($id, $data)
    {
        $id = (int)$id;
        $fields = array();
        $allowed = array('display_name','phone','email','telegram_chat_id','status','source_ref');
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $fields[$f] = $data[$f];
            }
        }
        if (empty($fields)) return false;
        $r = Database::update('Papir', 'leads', $fields, array('id' => $id));
        return $r['ok'];
    }

    public function setWorking($id)
    {
        return $this->update($id, array('status' => 'working'));
    }

    public function discard($id)
    {
        return $this->update($id, array('status' => 'lost'));
    }

    // ── Merge lead → counterparty ─────────────────────────────────────────────

    /**
     * Merge lead into counterparty.
     *
     * $resolutions — map of field => 'existing'|'lead' for conflict resolution.
     * $supplements — list of field names where lead has data and counterparty is empty (auto-apply).
     *
     * Fields that can be updated on counterparty: phone, email, telegram_chat_id.
     * Phone/email are written to the primary person or company sub-table, telegram_chat_id to counterparty.
     */
    public function merge($leadId, $counterpartyId, $resolutions = array(), $supplements = array())
    {
        $leadId         = (int)$leadId;
        $counterpartyId = (int)$counterpartyId;

        $lead = $this->getById($leadId);
        if (!$lead) return false;

        // ── Move all messages from lead → counterparty ─────────────────────────
        Database::query('Papir',
            "UPDATE cp_messages
             SET counterparty_id = {$counterpartyId}, lead_id = NULL
             WHERE lead_id = {$leadId}");

        // ── Determine which field values to apply to counterparty ──────────────
        // A field is applied if: resolution says 'lead', OR it's in supplements (auto-fill).
        $applyFields = array();

        // Conflicts: user explicitly chose 'lead' value
        foreach ($resolutions as $field => $choice) {
            if ($choice === 'lead') {
                $applyFields[] = $field;
            }
        }

        // Supplements: lead has data, counterparty was empty — auto-apply
        foreach ($supplements as $field) {
            if (!in_array($field, $applyFields)) {
                $applyFields[] = $field;
            }
        }

        // ── Apply fields to counterparty ───────────────────────────────────────
        if (!empty($applyFields)) {
            // Determine counterparty type to know which sub-table to update
            $cpR = Database::fetchRow('Papir',
                "SELECT c.type,
                        cp.id AS person_id, cc.id AS company_id
                 FROM counterparty c
                 LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
                 LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
                 WHERE c.id = {$counterpartyId} LIMIT 1");

            $cpType     = ($cpR['ok'] && $cpR['row']) ? $cpR['row']['type']       : '';
            $personId   = ($cpR['ok'] && $cpR['row']) ? (int)$cpR['row']['person_id']  : 0;
            $companyId  = ($cpR['ok'] && $cpR['row']) ? (int)$cpR['row']['company_id'] : 0;

            foreach ($applyFields as $field) {
                $val = isset($lead[$field]) ? $lead[$field] : null;
                if ($val === null || $val === '') continue;

                if ($field === 'telegram_chat_id') {
                    Database::update('Papir', 'counterparty',
                        array('telegram_chat_id' => $val),
                        array('id' => $counterpartyId));

                } elseif ($field === 'phone' || $field === 'email') {
                    // Write to person sub-table if exists, else company sub-table
                    if ($personId > 0) {
                        Database::update('Papir', 'counterparty_person',
                            array($field => $val),
                            array('id' => $personId));
                    } elseif ($companyId > 0) {
                        $col = ($field === 'phone') ? 'phone' : 'email';
                        Database::update('Papir', 'counterparty_company',
                            array($col => $val),
                            array('id' => $companyId));
                    }
                }
            }
        }

        // ── Mark lead as merged ────────────────────────────────────────────────
        Database::update('Papir', 'leads',
            array('status' => 'merged', 'counterparty_id' => $counterpartyId),
            array('id' => $leadId));

        return true;
    }

    // ── Inbox list (active leads: new + working) ──────────────────────────────

    public function getActiveForInbox()
    {
        $r = Database::fetchAll('Papir',
            "SELECT l.*,
                    m.body    AS last_msg_body,
                    m.created_at AS last_msg_at,
                    m.direction  AS last_msg_dir,
                    m.channel    AS last_msg_channel,
                    COALESCE(unr.cnt, 0) AS unread_count
             FROM leads l
             LEFT JOIN (
                 SELECT lead_id, body, created_at, direction, channel
                 FROM cp_messages m1
                 WHERE id = (
                     SELECT MAX(m2.id) FROM cp_messages m2
                     WHERE m2.lead_id = m1.lead_id
                 ) AND lead_id IS NOT NULL
             ) m ON m.lead_id = l.id
             LEFT JOIN (
                 SELECT lead_id, COUNT(*) AS cnt
                 FROM cp_messages
                 WHERE lead_id IS NOT NULL AND direction = 'in' AND read_at IS NULL
                 GROUP BY lead_id
             ) unr ON unr.lead_id = l.id
             WHERE l.status IN ('new','working')
             ORDER BY COALESCE(m.created_at, l.created_at) DESC");
        return $r['ok'] ? $r['rows'] : array();
    }

    // ── Find existing counterparty by lead contacts ───────────────────────────

    public function findMatches($lead)
    {
        $matches = array();
        $seen    = array();

        // By telegram_chat_id
        if (!empty($lead['telegram_chat_id'])) {
            $esc = Database::escape('Papir', $lead['telegram_chat_id']);
            $r   = Database::fetchAll('Papir',
                "SELECT c.id, c.name, c.type,
                        COALESCE(cc.phone, cp.phone) AS phone,
                        COALESCE(cc.email, cp.email) AS email
                 FROM counterparty c
                 LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
                 LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
                 WHERE c.status = 1 AND c.telegram_chat_id = '{$esc}'
                 LIMIT 5");
            if ($r['ok']) {
                foreach ($r['rows'] as $row) {
                    if (!isset($seen[$row['id']])) {
                        $row['match_by'] = 'telegram';
                        $matches[] = $row;
                        $seen[$row['id']] = true;
                    }
                }
            }
        }

        // By phone (last 9 digits)
        if (!empty($lead['phone'])) {
            $last9 = Database::escape('Papir', $this->phoneLast9($lead['phone']));
            $r = Database::fetchAll('Papir',
                "SELECT c.id, c.name, c.type,
                        COALESCE(cc.phone, cp.phone) AS phone,
                        COALESCE(cc.email, cp.email) AS email
                 FROM counterparty c
                 LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
                 LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
                 WHERE c.status = 1 AND (
                     REPLACE(REPLACE(REPLACE(cc.phone,'+',''),' ',''),'-','') LIKE '%{$last9}'
                  OR REPLACE(REPLACE(REPLACE(cp.phone,'+',''),' ',''),'-','') LIKE '%{$last9}'
                 )
                 LIMIT 5");
            if ($r['ok']) {
                foreach ($r['rows'] as $row) {
                    if (!isset($seen[$row['id']])) {
                        $row['match_by'] = 'phone';
                        $matches[] = $row;
                        $seen[$row['id']] = true;
                    }
                }
            }
        }

        // By email
        if (!empty($lead['email'])) {
            $esc = Database::escape('Papir', strtolower(trim($lead['email'])));
            $r   = Database::fetchAll('Papir',
                "SELECT c.id, c.name, c.type,
                        COALESCE(cc.phone, cp.phone) AS phone,
                        COALESCE(cc.email, cp.email) AS email
                 FROM counterparty c
                 LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
                 LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
                 WHERE c.status = 1 AND (
                     LOWER(cc.email) = '{$esc}'
                  OR LOWER(cp.email) = '{$esc}'
                 )
                 LIMIT 5");
            if ($r['ok']) {
                foreach ($r['rows'] as $row) {
                    if (!isset($seen[$row['id']])) {
                        $row['match_by'] = 'email';
                        $matches[] = $row;
                        $seen[$row['id']] = true;
                    }
                }
            }
        }

        return $matches;
    }

    // ── Messages (lead-specific) ──────────────────────────────────────────────

    public function getMessages($leadId, $channel = null, $limit = 60)
    {
        $lid   = (int)$leadId;
        $limit = (int)$limit;
        $where = "lead_id = {$lid}"
               . " AND (scheduled_at IS NULL OR scheduled_at <= NOW())";
        if ($channel) {
            $ch    = Database::escape('Papir', $channel);
            $where .= " AND channel = '{$ch}'";
        }
        $r = Database::fetchAll('Papir',
            "SELECT * FROM cp_messages WHERE {$where} ORDER BY created_at ASC LIMIT {$limit}");
        return $r['ok'] ? $r['rows'] : array();
    }

    public function saveMessage($leadId, $data)
    {
        $row = array(
            'lead_id'          => (int)$leadId,
            'counterparty_id'  => null,
            'channel'          => $data['channel'],
            'direction'        => isset($data['direction']) ? $data['direction'] : 'out',
            'status'           => isset($data['status'])    ? $data['status']    : 'sent',
            'phone'            => isset($data['phone'])     ? $data['phone']     : null,
            'email_addr'       => isset($data['email_addr'])? $data['email_addr']: null,
            'subject'          => isset($data['subject'])   ? $data['subject']   : null,
            'body'             => $data['body'],
            'external_id'      => isset($data['external_id']) ? (string)$data['external_id'] : null,
        );
        $r = Database::insert('Papir', 'cp_messages', $row);
        return $r['ok'] ? (int)$r['insert_id'] : 0;
    }

    public function markRead($leadId, $channel = null)
    {
        $lid   = (int)$leadId;
        $where = "lead_id = {$lid} AND direction = 'in' AND read_at IS NULL"
               . " AND (scheduled_at IS NULL OR scheduled_at <= NOW())";
        if ($channel) {
            $ch    = Database::escape('Papir', $channel);
            $where .= " AND channel = '{$ch}'";
        }
        Database::query('Papir', "UPDATE cp_messages SET read_at = NOW() WHERE {$where}");
    }

    public function getUnreadCount($leadId)
    {
        $lid = (int)$leadId;
        $r   = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt FROM cp_messages
             WHERE lead_id = {$lid} AND direction = 'in' AND read_at IS NULL
               AND (scheduled_at IS NULL OR scheduled_at <= NOW())");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;
    }

    public function saveReminder($leadId, $body, $scheduledAt, $assignedTo = null)
    {
        $row = array(
            'lead_id'      => (int)$leadId,
            'channel'      => 'note',
            'direction'    => 'in',
            'status'       => 'sent',
            'body'         => $body,
            'scheduled_at' => $scheduledAt,
            'assigned_to'  => $assignedTo ? (int)$assignedTo : null,
            'read_at'      => null,
        );
        $r = Database::insert('Papir', 'cp_messages', $row);
        return $r['ok'] ? (int)$r['insert_id'] : 0;
    }

    // ── Find lead by incoming contact (for webhooks) ──────────────────────────

    public function findByTelegramChatId($chatId)
    {
        $esc = Database::escape('Papir', (string)$chatId);
        $r   = Database::fetchRow('Papir',
            "SELECT id FROM leads
             WHERE telegram_chat_id = '{$esc}' AND status IN ('new','working')
             ORDER BY id DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['id'] : 0;
    }

    public function findByPhone($phone)
    {
        $last9 = Database::escape('Papir', $this->phoneLast9($phone));
        $r = Database::fetchRow('Papir',
            "SELECT id FROM leads
             WHERE REPLACE(REPLACE(phone,'+',''),' ','') LIKE '%{$last9}'
               AND status IN ('new','working')
             ORDER BY id DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['id'] : 0;
    }

    public function findByEmail($email)
    {
        $esc = Database::escape('Papir', strtolower(trim($email)));
        $r   = Database::fetchRow('Papir',
            "SELECT id FROM leads
             WHERE LOWER(email) = '{$esc}' AND status IN ('new','working')
             ORDER BY id DESC LIMIT 1");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['id'] : 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function phoneLast9($phone)
    {
        $digits = preg_replace('/\D/', '', $phone);
        return substr($digits, -9);
    }

    private function generateUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
