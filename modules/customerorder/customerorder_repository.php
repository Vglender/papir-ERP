<?php

class CustomerOrderRepository
{
    protected $dbName = 'Papir';

/**
 * Build WHERE clauses + flags from shared filter array.
 * Returns ['where' => array, 'needsCpJoin' => bool, 'needsMsgJoin' => bool]
 */
private function buildWhere($filters)
{
    $where = array();
    $where[] = 'co.`deleted_at` IS NULL';
    $needsCpJoin = false;
    $needsPhoneJoin = false;
    $needsMsgJoin = false;

    if (!empty($filters['search'])) {
        $needsCpJoin = true;
        $rawChips = preg_split('/\s*,\s*/', trim($filters['search']));
        $chipConds = array();
        foreach ($rawChips as $chip) {
            $chip = trim($chip);
            if ($chip === '') continue;
            // Phone-like: starts with +, 0, or 10+ digits
            $digits = preg_replace('/[\s\-\(\)]+/', '', $chip);
            $isPhone = preg_match('/^[\+0]\d{6,}$/', $digits) || preg_match('/^\d{10,}$/', $digits);
            if ($isPhone) {
                $needsPhoneJoin = true;
                $t = Database::escape($this->dbName, $digits);
                $chipConds[] = "(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cp_p.`phone`,''),' ',''),'-',''),'(',''),')','') LIKE '%{$t}%'"
                    . " OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cp_p.`phone_alt`,''),' ',''),'-',''),'(',''),')','') LIKE '%{$t}%'"
                    . " OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cp_c.`phone`,''),' ',''),'-',''),'(',''),')','') LIKE '%{$t}%'"
                    . " OR co.`id` = " . (int)$digits . " OR LOWER(co.`number`) LIKE '%{$t}%')";
            } elseif (preg_match('/^\d+$/', $chip)) {
                $t = Database::escape($this->dbName, $chip);
                $chipConds[] = "(co.`id` = " . (int)$chip . " OR LOWER(co.`number`) LIKE '%{$t}%')";
            } else {
                $tokens = preg_split('/\s+/u', mb_strtolower($chip, 'UTF-8'));
                $tokenParts = array();
                foreach ($tokens as $token) {
                    if ($token === '') continue;
                    $t = Database::escape($this->dbName, $token);
                    $tokenParts[] = "(LOWER(co.`number`) LIKE '%{$t}%' OR LOWER(COALESCE(c.`name`,'')) LIKE '%{$t}%' OR LOWER(COALESCE(cp_link.`name`,'')) LIKE '%{$t}%'"
                        . " OR (c.`group_id` IS NOT NULL AND EXISTS (SELECT 1 FROM `counterparty` cg_s WHERE cg_s.`group_id` = c.`group_id` AND LOWER(cg_s.`name`) LIKE '%{$t}%')))";
                }
                if (!empty($tokenParts)) {
                    $chipConds[] = '(' . implode(' AND ', $tokenParts) . ')';
                }
            }
        }
        if (!empty($chipConds)) {
            $where[] = '(' . implode(' OR ', $chipConds) . ')';
        }
    }

    if (!empty($filters['id'])) {
        $where[] = 'co.`id` = ' . (int)$filters['id'];
    }
    if (!empty($filters['number'])) {
        $number = Database::escape($this->dbName, $filters['number']);
        $where[] = "co.`number` LIKE '%{$number}%'";
    }
    if (!empty($filters['status'])) {
        if (is_array($filters['status'])) {
            $sts = array();
            foreach ($filters['status'] as $sv) {
                $sts[] = "'" . Database::escape($this->dbName, $sv) . "'";
            }
            if (!empty($sts)) {
                $where[] = 'co.`status` IN (' . implode(',', $sts) . ')';
            }
        } else {
            $status = Database::escape($this->dbName, $filters['status']);
            $where[] = "co.`status` = '{$status}'";
        }
    }
    if (!empty($filters['payment_status'])) {
        if (is_array($filters['payment_status'])) {
            $vals = array();
            foreach ($filters['payment_status'] as $v) {
                $vals[] = "'" . Database::escape($this->dbName, $v) . "'";
            }
            $where[] = 'co.`payment_status` IN (' . implode(',', $vals) . ')';
        } else {
            $paymentStatus = Database::escape($this->dbName, $filters['payment_status']);
            $where[] = "co.`payment_status` = '{$paymentStatus}'";
        }
    }
    if (!empty($filters['shipment_status'])) {
        if (is_array($filters['shipment_status'])) {
            $vals = array();
            foreach ($filters['shipment_status'] as $v) {
                $vals[] = "'" . Database::escape($this->dbName, $v) . "'";
            }
            $where[] = 'co.`shipment_status` IN (' . implode(',', $vals) . ')';
        } else {
            $shipmentStatus = Database::escape($this->dbName, $filters['shipment_status']);
            $where[] = "co.`shipment_status` = '{$shipmentStatus}'";
        }
    }
    if (!empty($filters['manager_employee_id'])) {
        $where[] = 'co.`manager_employee_id` = ' . (int)$filters['manager_employee_id'];
    }
    if (!empty($filters['organization_id'])) {
        $where[] = 'co.`organization_id` = ' . (int)$filters['organization_id'];
    }
    if (!empty($filters['counterparty_id'])) {
        $where[] = 'co.`counterparty_id` = ' . (int)$filters['counterparty_id'];
    }
    if (!empty($filters['date_from'])) {
        $dateFrom = Database::escape($this->dbName, $filters['date_from']);
        $where[] = "co.`moment` >= '{$dateFrom} 00:00:00'";
    }
    if (!empty($filters['date_to'])) {
        $dateTo = Database::escape($this->dbName, $filters['date_to']);
        $where[] = "co.`moment` <= '{$dateTo} 23:59:59'";
    }
    if (!empty($filters['hide_cancelled']) && $filters['hide_cancelled'] === '1') {
        $where[] = "co.`status` != 'cancelled'";
    }
    if (isset($filters['sum_from']) && $filters['sum_from'] !== '' && $filters['sum_from'] !== null) {
        $where[] = 'co.`sum_total` >= ' . (float)$filters['sum_from'];
    }
    if (isset($filters['sum_to']) && $filters['sum_to'] !== '' && $filters['sum_to'] !== null) {
        $where[] = 'co.`sum_total` <= ' . (float)$filters['sum_to'];
    }
    if (!empty($filters['next_action'])) {
        if ($filters['next_action'] === '__none__') {
            $where[] = "(co.`next_action` IS NULL OR co.`next_action` = '')";
        } else {
            $na = Database::escape($this->dbName, $filters['next_action']);
            $where[] = "co.`next_action` = '{$na}'";
        }
    }
    if (!empty($filters['has_unread'])) {
        $needsMsgJoin = true;
        if ($filters['has_unread'] === 'yes') {
            $where[] = 'msg_cnt.cnt > 0';
        } else {
            $where[] = '(msg_cnt.cnt IS NULL OR msg_cnt.cnt = 0)';
        }
    }

    if (!empty($filters['payment_method_id'])) {
        $where[] = 'co.`payment_method_id` = ' . (int)$filters['payment_method_id'];
    }
    if (!empty($filters['delivery_method_id'])) {
        $where[] = 'co.`delivery_method_id` = ' . (int)$filters['delivery_method_id'];
    }

    $needsTtnJoin = false;
    if (!empty($filters['has_ttn'])) {
        $needsTtnJoin = true;
        if ($filters['has_ttn'] === 'yes') {
            $where[] = '(COALESCE(ttn_np_cnt.cnt,0) + COALESCE(ttn_up_cnt.cnt,0)) > 0';
        } else {
            $where[] = '(COALESCE(ttn_np_cnt.cnt,0) + COALESCE(ttn_up_cnt.cnt,0)) = 0';
        }
    }

    return array('where' => $where, 'needsCpJoin' => $needsCpJoin, 'needsPhoneJoin' => $needsPhoneJoin, 'needsMsgJoin' => $needsMsgJoin, 'needsTtnJoin' => $needsTtnJoin);
}

public function getList($filters = array(), $sort = array(), $page = 1, $limit = 50)
{
    $page = max(1, (int)$page);
    $limit = max(1, (int)$limit);
    $offset = ($page - 1) * $limit;

    $built = $this->buildWhere($filters);
    $where = $built['where'];
    $whereSql = implode(' AND ', $where);

    $allowedSort = array(
        'id' => 'co.`id`',
        'moment' => 'co.`moment`',
        'number' => 'co.`number`',
        'status' => 'co.`status`',
        'sum_total' => 'co.`sum_total`',
        'updated_at' => 'co.`updated_at`',
    );

    $sortField = 'co.`id`';
    $sortDir = 'DESC';

    if (!empty($sort['field']) && isset($allowedSort[$sort['field']])) {
        $sortField = $allowedSort[$sort['field']];
    }

    if (!empty($sort['dir']) && in_array(strtoupper($sort['dir']), array('ASC', 'DESC'))) {
        $sortDir = strtoupper($sort['dir']);
    }

    // When sorting by id (default) with no complex filters, FORCE INDEX(PRIMARY)
    // avoids filesort on 119K+ rows — backward index scan, ~50 rows examined
    $onlyDeletedFilter = (count($where) === 1);
    $sortById = ($sortField === 'co.`id`');
    $forceIdx = ($onlyDeletedFilter && $sortById) ? ' FORCE INDEX (PRIMARY)' : '';

    $sql = "
        SELECT
            co.`id`, co.`number`, co.`moment`, co.`status`,
            co.`payment_status`,
            CASE
                WHEN co.`shipment_status` != 'not_shipped' THEN co.`shipment_status`
                WHEN dem_agg.demand_count > 0 THEN
                    CASE
                        WHEN dem_agg.shipped_count = dem_agg.demand_count THEN 'shipped'
                        WHEN dem_agg.shipped_count > 0 THEN 'partially_shipped'
                        ELSE 'reserved'
                    END
                ELSE co.`shipment_status`
            END AS shipment_status,
            co.`sum_total`, co.`counterparty_id`,
            co.`organization_id`, co.`manager_employee_id`,
            co.`next_action`, co.`next_action_label`,
            COALESCE(NULLIF(org.`short_name`,''), org.`name`) AS organization_short,
            org.`name` AS organization_name,
            COALESCE(NULLIF(emp.`full_name`,''), au.`display_name`) AS manager_display,
            c.`name` AS counterparty_name,
            co.`delivery_method_id`,
            dm.`code` AS delivery_method_code,
            co.`payment_method_id`,
            pm.`code` AS payment_method_code
        FROM `customerorder` co{$forceIdx}
        LEFT JOIN `organization` org ON org.`id` = co.`organization_id`
        LEFT JOIN `employee` emp ON emp.`id` = co.`manager_employee_id`
        LEFT JOIN `auth_users` au ON au.`employee_id` = emp.`id`
        LEFT JOIN `counterparty` c ON c.`id` = co.`counterparty_id`
        " . ($built['needsCpJoin']
            ? "LEFT JOIN `counterparty` cp_link ON cp_link.`id` = co.`contact_person_id`"
            : '') . "
        " . ($built['needsPhoneJoin']
            ? "LEFT JOIN `counterparty_person` cp_p ON cp_p.`counterparty_id` = co.`counterparty_id`
               LEFT JOIN `counterparty_company` cp_c ON cp_c.`counterparty_id` = co.`counterparty_id`"
            : '') . "
        LEFT JOIN `delivery_method` dm ON dm.`id` = co.`delivery_method_id`
        LEFT JOIN `payment_method` pm ON pm.`id` = co.`payment_method_id`
        LEFT JOIN (
            SELECT customerorder_id,
                   COUNT(*) AS demand_count,
                   SUM(CASE WHEN status IN ('shipped','arrived') THEN 1 ELSE 0 END) AS shipped_count
            FROM demand
            WHERE deleted_at IS NULL
            GROUP BY customerorder_id
        ) dem_agg ON dem_agg.customerorder_id = co.id
        " . ($built['needsMsgJoin']
            ? "LEFT JOIN (SELECT counterparty_id, COUNT(*) AS cnt FROM cp_messages WHERE direction='in' AND read_at IS NULL AND channel!='note' AND counterparty_id IS NOT NULL GROUP BY counterparty_id) msg_cnt ON msg_cnt.counterparty_id = co.counterparty_id"
            : '') . "
        " . ($built['needsTtnJoin']
            ? "LEFT JOIN (SELECT dl.to_id AS oid, COUNT(*) AS cnt FROM document_link dl JOIN ttn_novaposhta tn ON tn.id=dl.from_id WHERE dl.from_type='ttn_np' AND dl.to_type='customerorder' AND (tn.deletion_mark IS NULL OR tn.deletion_mark=0) AND tn.state_define NOT IN (102,105) AND LOWER(tn.state_name) NOT LIKE '%відмов%' AND LOWER(tn.state_name) NOT LIKE '%отказ%' GROUP BY dl.to_id) ttn_np_cnt ON ttn_np_cnt.oid=co.id
             LEFT JOIN (SELECT dl.to_id AS oid, COUNT(*) AS cnt FROM document_link dl JOIN ttn_ukrposhta tu ON tu.id=dl.from_id WHERE dl.from_type='ttn_up' AND dl.to_type='customerorder' AND tu.lifecycle_status NOT IN ('RETURNED','RETURNING','CANCELLED','DELETED') GROUP BY dl.to_id) ttn_up_cnt ON ttn_up_cnt.oid=co.id"
            : '') . "
        WHERE {$whereSql}
        ORDER BY {$sortField} {$sortDir}
        LIMIT {$offset}, {$limit}
    ";

    return Database::fetchAll($this->dbName, $sql);
}
	
	// В CustomerOrderRepository.php добавьте:
		public function getItemById($itemId)
		{
			$itemId = (int)$itemId;
			
			$sql = "SELECT * FROM `customerorder_item` WHERE `id` = {$itemId} LIMIT 1";
			
			return Database::fetchRow('Papir', $sql);
		}
	
	public function getProductById($product_id)
	{
		$product_id = (int)$product_id;

		$sql = "
			SELECT
				pp.`product_id`,
				pp.`product_article`,
				pp.`price`,
				pp.`unit`,
				pp.`quantity`,
				pp.`weight`,
				pp.`vat`,
				pd.`name`
			FROM `product_papir` pp
			LEFT JOIN `product_description` pd
				ON pd.`product_id` = pp.`product_id`
			   AND pd.`language_id` = 1
			WHERE pp.`product_id` = {$product_id}
			LIMIT 1
		";

		return Database::fetchRow($this->dbName, $sql);
	}
	
public function searchProducts($query, $limit = 15)
{
    $query = trim($query);
    $limit = (int)$limit;

    if ($limit <= 0) {
        $limit = 15;
    }

    if ($query === '') {
        return array(
            'ok' => true,
            'rows' => array(),
        );
    }

    $tokens = preg_split('/\s+/u', mb_strtolower($query, 'UTF-8'));
    $tokens = array_filter($tokens, function ($token) {
        return mb_strlen($token, 'UTF-8') >= 2;
    });

    if (!$tokens) {
        return array(
            'ok' => true,
            'rows' => array(),
        );
    }

    $whereParts = array();

    foreach ($tokens as $token) {
        $escaped = Database::escape($this->dbName, $token);

        $whereParts[] = "
            LOWER(CONCAT(
                COALESCE(pd.`name`, ''), ' ',
                COALESCE(pp.`product_article`, ''), ' ',
                COALESCE(pp.`ean`, '')
            )) LIKE '%{$escaped}%'
        ";
    }

    $whereSql = implode(' AND ', $whereParts);

    $sql = "
        SELECT
            pp.`product_id`,
            pp.`product_article`,
            pp.`price_sale` AS `price`,
            pp.`vat`,
            pp.`unit`,
            pp.`quantity`,
            pp.`weight`,
            pd.`name`
        FROM `product_papir` pp
        LEFT JOIN `product_description` pd
            ON pd.`product_id` = pp.`product_id`
           AND pd.`language_id` = 1
        WHERE {$whereSql}
        ORDER BY
            CASE
                WHEN LOWER(pd.`name`) LIKE '" . Database::escape($this->dbName, mb_strtolower($query, 'UTF-8')) . "%' THEN 0
                ELSE 1
            END,
            pd.`name` ASC
        LIMIT {$limit}
    ";

    return Database::fetchAll($this->dbName, $sql);
}

	public function countList($filters = array())
	{
		$built = $this->buildWhere($filters);
		$whereSql = implode(' AND ', $built['where']);

		$joins = '';
		if ($built['needsCpJoin']) {
			$joins .= ' LEFT JOIN `counterparty` c ON c.`id` = co.`counterparty_id`';
			$joins .= ' LEFT JOIN `counterparty` cp_link ON cp_link.`id` = co.`contact_person_id`';
		}
		if ($built['needsPhoneJoin']) {
			$joins .= ' LEFT JOIN `counterparty_person` cp_p ON cp_p.`counterparty_id` = co.`counterparty_id`';
			$joins .= ' LEFT JOIN `counterparty_company` cp_c ON cp_c.`counterparty_id` = co.`counterparty_id`';
		}
		if ($built['needsMsgJoin']) {
			$joins .= " LEFT JOIN (SELECT counterparty_id, COUNT(*) AS cnt FROM cp_messages WHERE direction='in' AND read_at IS NULL AND channel!='note' AND counterparty_id IS NOT NULL GROUP BY counterparty_id) msg_cnt ON msg_cnt.counterparty_id = co.counterparty_id";
		}
		if ($built['needsTtnJoin']) {
			$joins .= " LEFT JOIN (SELECT dl.to_id AS oid, COUNT(*) AS cnt FROM document_link dl JOIN ttn_novaposhta tn ON tn.id=dl.from_id WHERE dl.from_type='ttn_np' AND dl.to_type='customerorder' AND (tn.deletion_mark IS NULL OR tn.deletion_mark=0) AND tn.state_define NOT IN (102,105) AND LOWER(tn.state_name) NOT LIKE '%відмов%' AND LOWER(tn.state_name) NOT LIKE '%отказ%' GROUP BY dl.to_id) ttn_np_cnt ON ttn_np_cnt.oid=co.id";
			$joins .= " LEFT JOIN (SELECT dl.to_id AS oid, COUNT(*) AS cnt FROM document_link dl JOIN ttn_ukrposhta tu ON tu.id=dl.from_id WHERE dl.from_type='ttn_up' AND dl.to_type='customerorder' AND tu.lifecycle_status NOT IN ('RETURNED','RETURNING','CANCELLED','DELETED') GROUP BY dl.to_id) ttn_up_cnt ON ttn_up_cnt.oid=co.id";
		}

		$sql = "SELECT COUNT(*) AS total FROM `customerorder` co{$joins} WHERE {$whereSql}";
		$result = Database::fetchValue($this->dbName, $sql, 'total');

		if ($result['ok']) {
			return array('ok' => true, 'value' => (int)$result['value']);
		}
		return array('ok' => false, 'error' => $result['error'] ? $result['error']: 'Unknown error');
	}

	public function getById($id)
	{
		$id = (int)$id;

		$sql = "
			SELECT
				co.*,
				org.`name` AS organization_name,
				st.`name` AS store_name,
				emp.`full_name` AS manager_name,
				upd.`full_name` AS updated_by_name,
				c.`name` AS counterparty_name,
				cp.`name` AS contact_person_name,
				ctr.`number` AS contract_number,
				ctr.`title` AS contract_title,
				pr.`name` AS project_name,
				oba.`iban` AS organization_bank_account_iban,
				oba.`account_name` AS organization_bank_account_name
			FROM `customerorder` co
			LEFT JOIN `organization` org ON org.`id` = co.`organization_id`
			LEFT JOIN `store` st ON st.`id` = co.`store_id`
			LEFT JOIN `employee` emp ON emp.`id` = co.`manager_employee_id`
			LEFT JOIN `employee` upd ON upd.`id` = co.`updated_by_employee_id`
			LEFT JOIN `counterparty` c ON c.`id` = co.`counterparty_id`
			LEFT JOIN `counterparty` cp ON cp.`id` = co.`contact_person_id`
			LEFT JOIN `contract` ctr ON ctr.`id` = co.`contract_id`
			LEFT JOIN `project` pr ON pr.`id` = co.`project_id`
			LEFT JOIN `organization_bank_account` oba ON oba.`id` = co.`organization_bank_account_id`
			WHERE co.`id` = {$id}
			  AND co.`deleted_at` IS NULL
			LIMIT 1
		";

		return Database::fetchRow($this->dbName, $sql);
	}

    public function create($data)
    {
        return Database::insert($this->dbName, 'customerorder', $data);
    }

    public function update($id, $data)
    {
        return Database::update($this->dbName, 'customerorder', $data, array('id' => (int)$id));
    }

    public function softDelete($id)
    {
        return Database::update(
            $this->dbName,
            'customerorder',
            array('deleted_at' => date('Y-m-d H:i:s')),
            array('id' => (int)$id)
        );
    }

    public function getItems($orderId)
    {
        $orderId = (int)$orderId;

        $sql = "
            SELECT ci.*,
                COALESCE(
                    NULLIF(ci.`product_name`, ''),
                    pd2.`name`,
                    pd1.`name`
                ) AS product_name,
                COALESCE(pp.`quantity`, 0) AS stock_quantity
            FROM `customerorder_item` ci
            LEFT JOIN `product_description` pd2
                ON pd2.`product_id` = ci.`product_id` AND pd2.`language_id` = 2
            LEFT JOIN `product_description` pd1
                ON pd1.`product_id` = ci.`product_id` AND pd1.`language_id` = 1
            LEFT JOIN `product_papir` pp
                ON pp.`product_id` = ci.`product_id`
            WHERE ci.`customerorder_id` = {$orderId}
            ORDER BY ci.`line_no` ASC, ci.`id` ASC
        ";

        return Database::fetchAll($this->dbName, $sql);
    }

    public function insertItem($orderId, $item)
    {
        $item['customerorder_id'] = (int)$orderId;

        if (empty($item['line_no'])) {
            $item['line_no'] = $this->getNextLineNo($orderId);
        }

        return Database::insert($this->dbName, 'customerorder_item', $item);
    }

    public function updateItem($itemId, $item)
    {
        return Database::update($this->dbName, 'customerorder_item', $item, array('id' => (int)$itemId));
    }

    public function deleteItem($itemId)
    {
        return Database::delete($this->dbName, 'customerorder_item', array('id' => (int)$itemId));
    }

    public function getAttributes($orderId)
    {
        $orderId = (int)$orderId;

        $sql = "
            SELECT cav.*
            FROM `customerorder_attr_value` cav
            WHERE cav.`customerorder_id` = {$orderId}
            ORDER BY cav.`name_main` ASC, cav.`id` ASC
        ";

        return Database::fetchAll($this->dbName, $sql);
    }

    public function saveAttribute($orderId, $attr)
    {
        $orderId = (int)$orderId;

        if (!empty($attr['id'])) {
            $attrId = (int)$attr['id'];
            unset($attr['id']);
            return Database::update($this->dbName, 'customerorder_attr_value', $attr, array('id' => $attrId));
        }

        $attr['customerorder_id'] = $orderId;
        return Database::insert($this->dbName, 'customerorder_attr_value', $attr);
    }

    public function deleteAttribute($attrId)
    {
        return Database::delete($this->dbName, 'customerorder_attr_value', array('id' => (int)$attrId));
    }

    /**
     * Записать событие в историю документа.
     *
     * @param int    $orderId
     * @param string $eventType   'create'|'update'|'status_change'|'add_item'|...
     * @param string $fieldName   имя поля (или null)
     * @param mixed  $oldValue
     * @param mixed  $newValue
     * @param int    $employeeId  id из таблицы employee (или null для авто)
     * @param string $comment
     * @param array  $actorOverride  array(actor_type, actor_id, actor_label) — для webhook/cron/ai
     */
    public function addHistory($orderId, $eventType, $fieldName, $oldValue, $newValue, $employeeId, $comment, $actorOverride = array())
    {
        if (!empty($actorOverride)) {
            $params = array_merge(array(
                'field_name' => $fieldName,
                'old_value'  => $oldValue !== null ? (string)$oldValue : null,
                'new_value'  => $newValue !== null ? (string)$newValue : null,
                'comment'    => $comment,
            ), $actorOverride);
        } elseif ($employeeId) {
            // Ищем display_name из auth_users по employee_id
            $r = Database::fetchRow('Papir',
                "SELECT u.display_name FROM auth_users u
                 WHERE u.employee_id = " . (int)$employeeId . " LIMIT 1");
            $label = ($r['ok'] && $r['row']) ? $r['row']['display_name'] : '';
            if (!$label) {
                $re = Database::fetchRow('Papir',
                    "SELECT full_name FROM employee WHERE id = " . (int)$employeeId . " LIMIT 1");
                $label = ($re['ok'] && $re['row']) ? $re['row']['full_name'] : 'Співробітник';
            }
            $params = array(
                'field_name'  => $fieldName,
                'old_value'   => $oldValue !== null ? (string)$oldValue : null,
                'new_value'   => $newValue !== null ? (string)$newValue : null,
                'actor_type'  => 'user',
                'actor_id'    => (int)$employeeId,
                'actor_label' => $label,
                'comment'     => $comment,
            );
        } else {
            $params = array(
                'field_name'  => $fieldName,
                'old_value'   => $oldValue !== null ? (string)$oldValue : null,
                'new_value'   => $newValue !== null ? (string)$newValue : null,
                'actor_type'  => 'system',
                'actor_id'    => null,
                'actor_label' => 'Система',
                'comment'     => $comment,
            );
        }

        return DocumentHistory::log('customerorder', $orderId, $eventType, $params);
    }

// В CustomerOrderRepository.php
	public function recalculateTotals($orderId)
	{
		$orderId = (int)$orderId;
		
		// Пересчитываем итоги по строкам
		$sql = "
			SELECT 
				COUNT(*) as items_count,
				SUM(quantity) as total_quantity,
				SUM(price * quantity) as total_gross,
				SUM(discount_amount) as total_discount,
				SUM(vat_amount) as total_vat,
				SUM(sum_row) as total_sum,
				SUM(weight * quantity) as total_weight
			FROM customerorder_item 
			WHERE customerorder_id = {$orderId}
		";
		
		$result = Database::fetchRow('Papir', $sql);
		if (!$result['ok']) {
			return $result;
		}
		
		$totals = $result['row'];
		
		// Обновляем шапку документа
		$updateData = [
			'sum_items' => (float)$totals['total_gross'],
			'sum_discount' => (float)$totals['total_discount'],
			'sum_vat' => (float)$totals['total_vat'],
			'sum_total' => (float)$totals['total_sum'],
			'updated_at' => date('Y-m-d H:i:s')
		];
		
		return Database::update('Papir', 'customerorder', $updateData, ['id' => $orderId]);
	}

    protected function getNextLineNo($orderId)
    {
        $orderId = (int)$orderId;

        $sql = "
            SELECT MAX(`line_no`) AS max_line_no
            FROM `customerorder_item`
            WHERE `customerorder_id` = {$orderId}
        ";

        $row = Database::fetchValue($this->dbName, $sql, 'max_line_no');

        if (!$row['ok']) {
            return 1;
        }

        return (int)$row['value'] + 1;
    }
}