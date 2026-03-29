<?php

class CounterpartyRepository
{
    // ── Labels ───────────────────────────────────────────────────────────────

    public static function typeLabel($type)
    {
        $map = array(
            'company'    => 'Юрлицо',
            'fop'        => 'ФОП',
            'person'     => 'Фізособа',
            'department' => 'Підрозділ',
            'other'      => 'Інше',
        );
        return isset($map[$type]) ? $map[$type] : $type;
    }

    public static function typeBadgeClass($type)
    {
        $map = array(
            'company'    => 'badge-blue',
            'fop'        => 'badge-orange',
            'person'     => 'badge-gray',
            'department' => 'badge-gray',
            'other'      => 'badge-gray',
        );
        return isset($map[$type]) ? $map[$type] : 'badge-gray';
    }

    public static function relationTypeLabel($type)
    {
        $map = array(
            'contact_person'    => 'Контактна особа',
            'employee'          => 'Співробітник',
            'accountant'        => 'Бухгалтер',
            'director'          => 'Директор',
            'buyer'             => 'Покупець',
            'receiver'          => 'Отримувач',
            'payer'             => 'Платник',
            'department_contact'=> 'Контакт підрозділу',
            'manager'           => 'Менеджер',
            'signer'            => 'Підписант',
            'other'             => 'Інше',
        );
        return isset($map[$type]) ? $map[$type] : $type;
    }

    // ── Registry list ────────────────────────────────────────────────────────

    public function getList($params)
    {
        $search  = isset($params['search'])  ? trim((string)$params['search'])  : '';
        $type    = isset($params['type'])    ? trim((string)$params['type'])    : '';
        $groupId = isset($params['group_id'])? (int)$params['group_id']         : 0;
        $limit   = isset($params['limit'])   ? (int)$params['limit']            : 50;
        $offset  = isset($params['offset'])  ? (int)$params['offset']           : 0;

        $where = $this->buildListWhere($search, $type, $groupId);

        $sql = "SELECT
                    c.id, c.type, c.status, c.name, c.group_id, c.group_is_head, c.created_at,
                    cg.name AS group_name,
                    COALESCE(cc.phone, cp.phone) AS phone,
                    COALESCE(cc.email, cp.email) AS email,
                    cc.okpo,
                    COALESCE(ord_stats.order_count, 0) AS order_count,
                    COALESCE(ord_stats.ltv, 0)         AS ltv,
                    ord_stats.last_order_at
                FROM counterparty c
                LEFT JOIN counterparty_group cg   ON cg.id = c.group_id
                LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
                LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
                LEFT JOIN (
                    SELECT counterparty_id,
                           COUNT(*) AS order_count,
                           SUM(sum_total) AS ltv,
                           MAX(moment) AS last_order_at
                    FROM customerorder
                    WHERE deleted_at IS NULL
                    GROUP BY counterparty_id
                ) ord_stats ON ord_stats.counterparty_id = c.id
                WHERE {$where}
                ORDER BY c.name ASC
                LIMIT {$limit} OFFSET {$offset}";

        $r = Database::fetchAll('Papir', $sql);
        return $r['ok'] ? $r['rows'] : array();
    }

    public function getCount($params)
    {
        $search  = isset($params['search'])  ? trim((string)$params['search'])  : '';
        $type    = isset($params['type'])    ? trim((string)$params['type'])    : '';
        $groupId = isset($params['group_id'])? (int)$params['group_id']         : 0;

        $where = $this->buildListWhere($search, $type, $groupId);

        $sql = "SELECT COUNT(*) AS cnt
                FROM counterparty c
                LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
                LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
                WHERE {$where}";

        $r = Database::fetchRow('Papir', $sql);
        return ($r['ok'] && $r['row']) ? (int)$r['row']['cnt'] : 0;
    }

    private function buildListWhere($search, $type, $groupId)
    {
        $parts = array();

        // Type filter
        if ($type === 'company') {
            $parts[] = "c.type = 'company'";
        } elseif ($type === 'fop') {
            $parts[] = "c.type = 'fop'";
        } elseif ($type === 'person') {
            $parts[] = "c.type = 'person'";
        } elseif ($type === 'business') {
            $parts[] = "c.type IN ('company','fop')";
        } else {
            // Default: show company/fop + autonomous persons (not linked as child contact)
            $parts[] = "(c.type IN ('company','fop','department','other')
                        OR (c.type = 'person' AND NOT EXISTS (
                            SELECT 1 FROM counterparty_relation cr2
                            WHERE cr2.child_counterparty_id = c.id
                              AND cr2.relation_type IN ('contact_person','employee','accountant','director','manager')
                        )))";
        }

        // Group filter
        if ($groupId > 0) {
            $parts[] = "c.group_id = {$groupId}";
        }

        // Search
        if ($search !== '') {
            $chipSep = (strpos($search, '|||') !== false) ? '/\s*\|\|\|\s*/u' : '/\s*,\s*/u';
            $rawChips = preg_split($chipSep, $search);
            $chipConds = array();

            foreach ($rawChips as $chip) {
                $chip = trim($chip);
                if ($chip === '') continue;

                // Short numeric → exact ID; long numeric (phone/okpo) → text search
                if (preg_match('/^\d+$/', $chip) && strlen($chip) <= 7) {
                    $chipConds[] = "c.id = " . (int)$chip;
                    continue;
                }

                $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                $tokens = array_filter($tokens, function($t) { return $t !== ''; });
                $tokenParts = array();
                foreach ($tokens as $token) {
                    $t = Database::escape('Papir', $token);
                    $tokenParts[] = "(LOWER(c.name) LIKE '%{$t}%'
                        OR LOWER(COALESCE(cc.okpo,''))  LIKE '%{$t}%'
                        OR LOWER(COALESCE(cc.inn,''))   LIKE '%{$t}%'
                        OR LOWER(COALESCE(cc.phone,'')) LIKE '%{$t}%'
                        OR LOWER(COALESCE(cc.email,'')) LIKE '%{$t}%'
                        OR LOWER(COALESCE(cp.phone,'')) LIKE '%{$t}%'
                        OR LOWER(COALESCE(cp.email,'')) LIKE '%{$t}%'
                        OR LOWER(COALESCE(cp.full_name,'')) LIKE '%{$t}%')";
                }
                if (!empty($tokenParts)) {
                    $chipConds[] = '(' . implode(' AND ', $tokenParts) . ')';
                }
            }

            if (!empty($chipConds)) {
                $parts[] = count($chipConds) === 1
                    ? $chipConds[0]
                    : '(' . implode(' OR ', $chipConds) . ')';
            }
        }

        return empty($parts) ? '1' : implode(' AND ', $parts);
    }

    // ── Single counterparty ──────────────────────────────────────────────────

    public function getById($id)
    {
        $id = (int)$id;
        $r = Database::fetchRow('Papir',
            "SELECT c.*,
                    cg.name AS group_name,
                    cc.short_name, cc.full_name AS full_legal_name, cc.company_type,
                    cc.okpo, cc.inn, cc.vat_number, cc.iban, cc.bank_name, cc.mfo,
                    cc.legal_address, cc.actual_address,
                    cc.phone AS company_phone, cc.email AS company_email,
                    cc.website, cc.notes AS company_notes,
                    cp.last_name, cp.first_name, cp.middle_name, cp.full_name AS person_full_name,
                    cp.phone AS person_phone, cp.phone_alt, cp.email AS person_email,
                    cp.birth_date, cp.position_name, cp.telegram, cp.viber,
                    cp.notes AS person_notes
             FROM counterparty c
             LEFT JOIN counterparty_group cg   ON cg.id = c.group_id
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             WHERE c.id = {$id} LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    // ── Order stats ──────────────────────────────────────────────────────────

    public function getOrderStats($counterpartyId)
    {
        $id = (int)$counterpartyId;
        $r = Database::fetchRow('Papir',
            "SELECT
                COUNT(*) AS order_count,
                COALESCE(SUM(sum_total), 0) AS ltv,
                COALESCE(AVG(sum_total), 0) AS avg_check,
                MAX(moment) AS last_order_at
             FROM customerorder
             WHERE counterparty_id = {$id} AND deleted_at IS NULL");
        return ($r['ok'] && $r['row']) ? $r['row'] : array(
            'order_count' => 0, 'ltv' => 0, 'avg_check' => 0, 'last_order_at' => null
        );
    }

    public function getRecentOrders($counterpartyId, $limit = 5)
    {
        $id  = (int)$counterpartyId;
        $lim = (int)$limit;
        $r = Database::fetchAll('Papir',
            "SELECT id, number, moment, sum_total, status, payment_status, shipment_status
             FROM customerorder
             WHERE counterparty_id = {$id} AND deleted_at IS NULL
             ORDER BY moment DESC
             LIMIT {$lim}");
        return ($r['ok']) ? $r['rows'] : array();
    }

    // ── Contacts (persons linked to this company) ────────────────────────────

    public function getContacts($counterpartyId)
    {
        $id = (int)$counterpartyId;
        $r = Database::fetchAll('Papir',
            "SELECT
                cr.id AS relation_id,
                cr.relation_type,
                cr.job_title,
                cr.is_primary,
                cr.comment AS relation_comment,
                c.id, c.name, c.status,
                cp.phone, cp.phone_alt, cp.email, cp.position_name, cp.telegram, cp.viber
             FROM counterparty_relation cr
             JOIN counterparty c ON c.id = cr.child_counterparty_id
             LEFT JOIN counterparty_person cp ON cp.counterparty_id = c.id
             WHERE cr.parent_counterparty_id = {$id}
               AND c.type = 'person'
             ORDER BY cr.is_primary DESC, c.name ASC");
        return $r['ok'] ? $r['rows'] : array();
    }

    // ── Relations (other companies) ──────────────────────────────────────────

    public function getRelations($counterpartyId)
    {
        $id = (int)$counterpartyId;
        $r = Database::fetchAll('Papir',
            "SELECT
                cr.id AS relation_id,
                cr.relation_type,
                cr.job_title,
                cr.is_primary,
                cr.comment AS relation_comment,
                CASE WHEN cr.parent_counterparty_id = {$id} THEN 'outgoing' ELSE 'incoming' END AS direction,
                c.id, c.name, c.type, c.status
             FROM counterparty_relation cr
             JOIN counterparty c ON c.id = CASE
                WHEN cr.parent_counterparty_id = {$id} THEN cr.child_counterparty_id
                ELSE cr.parent_counterparty_id
             END
             WHERE (cr.parent_counterparty_id = {$id} OR cr.child_counterparty_id = {$id})
               AND c.type != 'person'
             ORDER BY c.name ASC");
        return $r['ok'] ? $r['rows'] : array();
    }

    // ── Group ────────────────────────────────────────────────────────────────

    public function getGroups($onlyActive = true)
    {
        $where = $onlyActive ? "WHERE status = 1" : '';
        $r = Database::fetchAll('Papir', "SELECT id, name, description, status FROM counterparty_group {$where} ORDER BY name ASC");
        return $r['ok'] ? $r['rows'] : array();
    }

    public function getGroupById($id)
    {
        $id = (int)$id;
        $r = Database::fetchRow('Papir', "SELECT * FROM counterparty_group WHERE id = {$id} LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public function getGroupMembers($groupId)
    {
        $id = (int)$groupId;
        $r = Database::fetchAll('Papir',
            "SELECT c.id, c.name, c.type, c.status, c.group_is_head,
                    COALESCE(cc.phone, cp.phone) AS phone
             FROM counterparty c
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             WHERE c.group_id = {$id}
             ORDER BY c.group_is_head DESC, c.name ASC");
        return $r['ok'] ? $r['rows'] : array();
    }

    public function createGroup($data)
    {
        $r = Database::insert('Papir', 'counterparty_group', array(
            'name'        => isset($data['name'])        ? trim($data['name'])        : '',
            'description' => isset($data['description']) ? trim($data['description']) : '',
            'status'      => 1,
        ));
        return ($r['ok']) ? (int)$r['insert_id'] : 0;
    }

    public function updateGroup($id, $data)
    {
        $fields = array();
        if (isset($data['name']))        $fields['name']        = trim($data['name']);
        if (isset($data['description'])) $fields['description'] = trim($data['description']);
        if (isset($data['status']))      $fields['status']      = (int)$data['status'];
        if (empty($fields)) return false;
        $r = Database::update('Papir', 'counterparty_group', $fields, array('id' => (int)$id));
        return $r['ok'];
    }

    // ── CRUD counterparty ────────────────────────────────────────────────────

    public function create($data)
    {
        $type = isset($data['type']) ? $data['type'] : 'company';
        $name = isset($data['name']) ? trim($data['name']) : '';

        $uuid = $this->generateUuid();

        $r = Database::insert('Papir', 'counterparty', array(
            'uuid'         => $uuid,
            'type'         => $type,
            'status'       => 1,
            'name'         => $name,
            'description'  => isset($data['description']) ? trim($data['description']) : '',
            'group_id'     => (isset($data['group_id']) && $data['group_id'] > 0) ? (int)$data['group_id'] : null,
            'group_is_head'=> isset($data['group_is_head']) ? (int)(bool)$data['group_is_head'] : 0,
        ));
        if (!$r['ok']) return 0;
        $id = (int)$r['insert_id'];

        $this->upsertTypeDetails($id, $type, $data);

        return $id;
    }

    public function update($id, $data)
    {
        $id = (int)$id;
        $cp = $this->getById($id);
        if (!$cp) return false;

        $fields = array();
        if (isset($data['name']))        $fields['name']         = trim($data['name']);
        if (isset($data['description'])) $fields['description']  = trim($data['description']);
        if (isset($data['status']))      $fields['status']       = (int)$data['status'];
        if (array_key_exists('group_id', $data)) {
            $fields['group_id'] = ($data['group_id'] > 0) ? (int)$data['group_id'] : null;
        }
        if (isset($data['group_is_head'])) $fields['group_is_head'] = (int)(bool)$data['group_is_head'];

        if (!empty($fields)) {
            Database::update('Papir', 'counterparty', $fields, array('id' => $id));
        }

        $this->upsertTypeDetails($id, $cp['type'], $data);

        return true;
    }

    public function setStatus($id, $status)
    {
        $r = Database::update('Papir', 'counterparty',
            array('status' => (int)(bool)$status),
            array('id' => (int)$id)
        );
        return $r['ok'];
    }

    private function upsertTypeDetails($id, $type, $data)
    {
        if (in_array($type, array('company', 'fop', 'department', 'other'))) {
            $companyFields = array(
                'counterparty_id' => $id,
                'short_name'      => isset($data['short_name'])   ? trim($data['short_name'])   : '',
                'full_name'       => isset($data['full_name'])     ? trim($data['full_name'])     : '',
                'company_type'    => isset($data['company_type'])  ? $data['company_type']        : ($type === 'fop' ? 'fop' : 'company'),
                'okpo'            => isset($data['okpo'])          ? trim($data['okpo'])          : '',
                'inn'             => isset($data['inn'])           ? trim($data['inn'])           : '',
                'vat_number'      => isset($data['vat_number'])    ? trim($data['vat_number'])    : '',
                'iban'            => isset($data['iban'])          ? trim($data['iban'])          : '',
                'bank_name'       => isset($data['bank_name'])     ? trim($data['bank_name'])     : '',
                'mfo'             => isset($data['mfo'])           ? trim($data['mfo'])           : '',
                'legal_address'   => isset($data['legal_address']) ? trim($data['legal_address']) : '',
                'actual_address'  => isset($data['actual_address'])? trim($data['actual_address']): '',
                'phone'           => isset($data['phone'])         ? trim($data['phone'])         : '',
                'email'           => isset($data['email'])         ? trim($data['email'])         : '',
                'website'         => isset($data['website'])       ? trim($data['website'])       : '',
                'notes'           => isset($data['notes'])         ? trim($data['notes'])         : '',
            );
            Database::upsertOne('Papir', 'counterparty_company', $companyFields, 'counterparty_id');

        } elseif ($type === 'person') {
            $lastName  = isset($data['last_name'])  ? trim($data['last_name'])  : '';
            $firstName = isset($data['first_name']) ? trim($data['first_name']) : '';
            $midName   = isset($data['middle_name'])? trim($data['middle_name']): '';
            $fullName  = trim($lastName . ' ' . $firstName . ' ' . $midName);

            $personFields = array(
                'counterparty_id' => $id,
                'last_name'       => $lastName,
                'first_name'      => $firstName,
                'middle_name'     => $midName,
                'full_name'       => $fullName,
                'phone'           => isset($data['phone'])         ? trim($data['phone'])         : '',
                'phone_alt'       => isset($data['phone_alt'])     ? trim($data['phone_alt'])     : '',
                'email'           => isset($data['email'])         ? trim($data['email'])         : '',
                'birth_date'      => (isset($data['birth_date']) && $data['birth_date'] !== '') ? $data['birth_date'] : null,
                'position_name'   => isset($data['position_name'])? trim($data['position_name']): '',
                'telegram'        => isset($data['telegram'])      ? trim($data['telegram'])      : '',
                'viber'           => isset($data['viber'])         ? trim($data['viber'])         : '',
                'notes'           => isset($data['notes'])         ? trim($data['notes'])         : '',
            );
            Database::upsertOne('Papir', 'counterparty_person', $personFields, 'counterparty_id');
        }
    }

    // ── Relations CRUD ───────────────────────────────────────────────────────

    public function addRelation($data)
    {
        $r = Database::insert('Papir', 'counterparty_relation', array(
            'parent_counterparty_id' => (int)$data['parent_id'],
            'child_counterparty_id'  => (int)$data['child_id'],
            'relation_type'          => isset($data['relation_type']) ? $data['relation_type'] : 'other',
            'department_name'        => isset($data['department_name']) ? trim($data['department_name']) : '',
            'job_title'              => isset($data['job_title'])       ? trim($data['job_title'])       : '',
            'is_primary'             => isset($data['is_primary'])      ? (int)(bool)$data['is_primary'] : 0,
            'comment'                => isset($data['comment'])         ? trim($data['comment'])         : '',
        ));
        return $r['ok'] ? (int)$r['insert_id'] : 0;
    }

    public function deleteRelation($id)
    {
        $r = Database::query('Papir', "DELETE FROM counterparty_relation WHERE id = " . (int)$id);
        return $r['ok'];
    }

    // ── Search (for pickers) ─────────────────────────────────────────────────

    public function search($query, $types = null, $limit = 20)
    {
        $query = trim((string)$query);
        $limit = (int)$limit;
        $where = array('c.status = 1');

        if (!empty($types)) {
            $safeTypes = array();
            foreach ((array)$types as $t) {
                $safeTypes[] = "'" . Database::escape('Papir', $t) . "'";
            }
            $where[] = 'c.type IN (' . implode(',', $safeTypes) . ')';
        }

        if ($query !== '') {
            $tokens = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'));
            $tokens = array_filter($tokens, function($t) { return $t !== ''; });
            $tParts = array();
            foreach ($tokens as $token) {
                $t = Database::escape('Papir', $token);
                $tParts[] = "(c.id = " . (int)$token . "
                    OR LOWER(c.name) LIKE '%{$t}%'
                    OR LOWER(COALESCE(cc.okpo,''))   LIKE '%{$t}%'
                    OR LOWER(COALESCE(cc.phone,''))  LIKE '%{$t}%'
                    OR LOWER(COALESCE(cp.phone,''))  LIKE '%{$t}%'
                    OR LOWER(COALESCE(cp.phone_alt,'')) LIKE '%{$t}%')";
            }
            if (!empty($tParts)) $where[] = '(' . implode(' AND ', $tParts) . ')';
        }

        $r = Database::fetchAll('Papir',
            "SELECT c.id, c.name, c.type,
                    COALESCE(cc.phone, cp.phone) AS phone,
                    COALESCE(cc.email, cp.email) AS email,
                    cc.okpo
             FROM counterparty c
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY c.name ASC
             LIMIT {$limit}");
        return $r['ok'] ? $r['rows'] : array();
    }

    // ── Activities ───────────────────────────────────────────────────────────

    public function getActivities($counterpartyId, $limit = 100)
    {
        $id = (int)$counterpartyId;
        $r = Database::fetchAll('Papir',
            "SELECT id, type, content, created_at
             FROM counterparty_activity
             WHERE counterparty_id = {$id}
             ORDER BY created_at DESC
             LIMIT {$limit}");
        return ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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
