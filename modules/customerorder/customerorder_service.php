<?php

define('CUSTOMERORDER_DEBUG', false);

function order_log($message) {
    if (!CUSTOMERORDER_DEBUG) return;
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    $file = basename($trace['file']);
    $line = $trace['line'];
    $logMessage = "[$file:$line] " . (is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE));
    error_log($logMessage);
}

class CustomerOrderService
{
    protected $repository;

    public function __construct(CustomerOrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Дефолтна ставка ПДВ для нових позицій замовлення.
     * Береться з organization.is_vat_payer: 20% якщо платник, 0% якщо ні.
     * Якщо замовлення/організацію не знайдено — повертає 20 (legacy fallback).
     */
    protected function getOrderVatDefault($orderId)
    {
        $orderId = (int)$orderId;
        if ($orderId <= 0) return 20;
        $r = Database::fetchRow('Papir',
            "SELECT o.is_vat_payer
               FROM customerorder co
          LEFT JOIN organization o ON o.id = co.organization_id
              WHERE co.id = {$orderId}
              LIMIT 1");
        if (!$r['ok'] || empty($r['row']) || $r['row']['is_vat_payer'] === null) {
            return 20;
        }
        return ((int)$r['row']['is_vat_payer'] === 1) ? 20 : 0;
    }

    public function getList($filters = array(), $sort = array(), $page = 1, $limit = 50)
    {
        $rows = $this->repository->getList($filters, $sort, $page, $limit);
        $count = $this->repository->countList($filters);

        if (!$rows['ok']) {
            return $rows;
        }

        if (!$count['ok']) {
            return $count;
        }

        // Batch-load unread message counts for visible orders (via counterparty)
        $orderRows = $rows['rows'];
        if (!empty($orderRows)) {
            $ids = array();
            $cpIds = array();
            foreach ($orderRows as $r) {
                $ids[] = (int)$r['id'];
                if (!empty($r['counterparty_id'])) {
                    $cpIds[] = (int)$r['counterparty_id'];
                }
            }
            $idList = implode(',', $ids);
            $unreadMap = array();
            if (!empty($cpIds)) {
                $cpIdList = implode(',', array_unique($cpIds));
                $rMsg = Database::fetchAll('Papir',
                    "SELECT counterparty_id, COUNT(*) AS cnt
                     FROM cp_messages
                     WHERE counterparty_id IN ({$cpIdList})
                       AND direction = 'in' AND read_at IS NULL AND channel != 'note'
                     GROUP BY counterparty_id");
                $cpUnreadMap = array();
                if ($rMsg['ok'] && !empty($rMsg['rows'])) {
                    foreach ($rMsg['rows'] as $mr) {
                        $cpUnreadMap[(int)$mr['counterparty_id']] = (int)$mr['cnt'];
                    }
                }
                // Map counterparty unread counts back to order ids
                foreach ($orderRows as $r) {
                    $cpId = isset($r['counterparty_id']) ? (int)$r['counterparty_id'] : 0;
                    if ($cpId && isset($cpUnreadMap[$cpId])) {
                        $unreadMap[(int)$r['id']] = $cpUnreadMap[$cpId];
                    }
                }
            }
            // Batch-load active TTN counts (Nova Poshta + Ukrposhta)
            $rNp = Database::fetchAll('Papir',
                "SELECT dl.to_id AS order_id, COUNT(*) AS cnt
                 FROM document_link dl
                 JOIN ttn_novaposhta tn ON tn.id = dl.from_id
                 WHERE dl.from_type = 'ttn_np'
                   AND dl.to_type   = 'customerorder'
                   AND dl.to_id     IN ({$idList})
                   AND (tn.deletion_mark IS NULL OR tn.deletion_mark = 0)
                   AND tn.state_define NOT IN (102, 105)
                   AND LOWER(tn.state_name) NOT LIKE '%відмов%'
                   AND LOWER(tn.state_name) NOT LIKE '%отказ%'
                 GROUP BY dl.to_id");
            $rUp = Database::fetchAll('Papir',
                "SELECT dl.to_id AS order_id, COUNT(*) AS cnt
                 FROM document_link dl
                 JOIN ttn_ukrposhta tu ON tu.id = dl.from_id
                 WHERE dl.from_type = 'ttn_up'
                   AND dl.to_type   = 'customerorder'
                   AND dl.to_id     IN ({$idList})
                   AND tu.lifecycle_status NOT IN ('RETURNED','RETURNING','CANCELLED','DELETED')
                 GROUP BY dl.to_id");
            $ttnMap = array();
            $ttnNpMap = array();
            $ttnUpMap = array();
            if ($rNp['ok'] && !empty($rNp['rows'])) {
                foreach ($rNp['rows'] as $tr) {
                    $oid = (int)$tr['order_id'];
                    $ttnNpMap[$oid] = (int)$tr['cnt'];
                    $ttnMap[$oid] = (isset($ttnMap[$oid]) ? $ttnMap[$oid] : 0) + (int)$tr['cnt'];
                }
            }
            if ($rUp['ok'] && !empty($rUp['rows'])) {
                foreach ($rUp['rows'] as $tr) {
                    $oid = (int)$tr['order_id'];
                    $ttnUpMap[$oid] = (int)$tr['cnt'];
                    $ttnMap[$oid] = (isset($ttnMap[$oid]) ? $ttnMap[$oid] : 0) + (int)$tr['cnt'];
                }
            }

            // Print queue: orders that have demands in print queue
            $pqMap = array();
            $rPq = Database::fetchAll('Papir',
                "SELECT d.customerorder_id AS order_id, COUNT(*) AS cnt
                 FROM print_pack_jobs pj
                 JOIN demand d ON d.id = pj.demand_id
                 WHERE pj.queued = 1 AND pj.status = 'ready'
                   AND d.customerorder_id IN ({$idList})
                 GROUP BY d.customerorder_id");
            if ($rPq['ok'] && !empty($rPq['rows'])) {
                foreach ($rPq['rows'] as $pqr) {
                    $pqMap[(int)$pqr['order_id']] = (int)$pqr['cnt'];
                }
            }

            foreach ($orderRows as &$r) {
                $r['unread_count']  = isset($unreadMap[(int)$r['id']]) ? $unreadMap[(int)$r['id']] : 0;
                $r['ttn_count']     = isset($ttnMap[(int)$r['id']])    ? $ttnMap[(int)$r['id']]    : 0;
                $r['ttn_np_count']  = isset($ttnNpMap[(int)$r['id']])  ? $ttnNpMap[(int)$r['id']]  : 0;
                $r['ttn_up_count']  = isset($ttnUpMap[(int)$r['id']])  ? $ttnUpMap[(int)$r['id']]  : 0;
                $r['print_queue']   = isset($pqMap[(int)$r['id']])     ? $pqMap[(int)$r['id']]     : 0;
            }
            unset($r);
        }

        // Global unread messages count — only for counterparties that have orders
        $gUnread = Database::fetchRow('Papir',
            "SELECT COUNT(*) AS cnt
             FROM cp_messages m
             WHERE m.direction='in' AND m.read_at IS NULL AND m.channel!='note'
               AND m.counterparty_id IN (SELECT DISTINCT counterparty_id FROM customerorder WHERE deleted_at IS NULL AND counterparty_id IS NOT NULL)");
        $globalUnread = ($gUnread['ok'] && !empty($gUnread['row'])) ? (int)$gUnread['row']['cnt'] : 0;

        return array(
            'ok' => true,
            'rows' => $orderRows,
            'count' => (int)$count['value'],
            'page' => (int)$page,
            'limit' => (int)$limit,
            'global_unread' => $globalUnread,
        );
    }

    public function getOrderCard($id)
    {
        $order = $this->repository->getById($id);
        if (!$order['ok']) {
            return $order;
        }

        if (empty($order['row'])) {
            return array(
                'ok' => false,
                'error' => 'Заказ не найден',
            );
        }

        $items = $this->repository->getItems($id);
        if (!$items['ok']) {
            return $items;
        }

        $attributes = $this->repository->getAttributes($id);
        if (!$attributes['ok']) {
            return $attributes;
        }

        return array(
            'ok' => true,
            'order' => $order['row'],
            'items' => $items['rows'],
            'attributes' => $attributes['rows'],
        );
    }

    public function createOrder($data, $employeeId = null)
    {
        $header = $this->prepareHeaderData($data, true);
		$header['updated_by_employee_id'] = $employeeId ? (int)$employeeId : null;
		if (empty($header['number'])) {
			$header['number'] = $this->generateDocumentNumber('customerorder', 'CO');
		}

        Database::begin('Papir');

        try {
            $create = $this->repository->create($header);
            if (!$create['ok']) {
                throw new Exception($create['error']);
            }

            $orderId = (int)$create['insert_id'];

            $history = $this->repository->addHistory(
                $orderId,
                'create',
                null,
                null,
                'created',
                $employeeId,
                'Создание заказа'
            );

            if (!$history['ok']) {
                throw new Exception($history['error']);
            }

            Database::commit('Papir');

            $this->pushToMs($orderId);

            return array(
                'ok' => true,
                'id' => $orderId,
            );
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(
                'ok' => false,
                'error' => $e->getMessage(),
            );
        }
    }
	public function searchProducts($query, $limit = 15)
{
    return $this->repository->searchProducts($query, $limit);
}

    public function updateOrder($id, $data, $employeeId = null)
    {
        $current = $this->repository->getById($id);
        if (!$current['ok']) {
            return $current;
        }

        if (empty($current['row'])) {
            return array(
                'ok' => false,
                'error' => 'Заказ не найден',
            );
        }

        $header = $this->prepareHeaderData($data, false);
        $header['updated_by_employee_id'] = $employeeId ? (int)$employeeId : null;

        Database::begin('Papir');

        try {
            $update = $this->repository->update($id, $header);
            if (!$update['ok']) {
                throw new Exception($update['error']);
            }

            $history = $this->repository->addHistory(
                $id,
                'update',
                null,
                null,
                'updated',
                $employeeId,
                'Обновление шапки заказа'
            );

            if (!$history['ok']) {
                throw new Exception($history['error']);
            }

            Database::commit('Papir');

            $this->pushToMs($id);

            return array(
                'ok' => true,
                'id' => (int)$id,
            );
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(
                'ok' => false,
                'error' => $e->getMessage(),
            );
        }
    }

	public function addItem($orderId, $item, $employeeId = null)
	{
		// Дефолтна ставка ПДВ для замовлення = is_vat_payer організації
		$orgVat = $this->getOrderVatDefault($orderId);

		if (!empty($item['product_id'])) {
			$productResult = $this->repository->getProductById((int)$item['product_id']);

			if (!$productResult['ok']) {
				return $productResult;
			}

			if (!empty($productResult['row'])) {
				$product = $productResult['row'];

				$item['product_id'] = (int)$product['product_id'];
				$item['product_name'] = !empty($item['product_name']) ? $item['product_name'] : (isset($product['name']) ? $product['name'] : null);
				$item['sku'] = !empty($item['sku']) ? $item['sku'] : (isset($product['product_article']) ? $product['product_article'] : null);
				$item['unit'] = !empty($product['unit']) ? $product['unit'] : 'шт';

				$item['quantity'] = isset($item['quantity']) && $item['quantity'] !== '' ? (float)$item['quantity'] : 1;
				$item['price'] = isset($item['price']) && $item['price'] !== '' ? (float)$item['price'] : (isset($product['price']) ? (float)$product['price'] : 0);

				$item['vat_rate'] = isset($item['vat_rate']) && $item['vat_rate'] !== ''
					? (float)$item['vat_rate']
					: ((isset($product['vat']) && $product['vat'] !== null && $product['vat'] !== '') ? (float)$product['vat'] : $orgVat);

				$item['stock_quantity'] = isset($product['quantity']) ? (float)$product['quantity'] : 0;
				$item['reserved_stock_quantity'] = 0;
				$item['expected_quantity'] = 0;
				$item['weight'] = isset($product['weight']) ? (float)$product['weight'] : 0;
			}
		}

		if (!isset($item['quantity']) || $item['quantity'] === '') {
			$item['quantity'] = 1;
		}

		if (!isset($item['vat_rate']) || $item['vat_rate'] === '') {
			$item['vat_rate'] = $orgVat;
		}

		$item = $this->prepareItemData($item);

		Database::begin('Papir');

		try {
			$insert = $this->repository->insertItem($orderId, $item);
			if (!$insert['ok']) {
				throw new Exception($insert['error']);
			}

			$recalc = $this->repository->recalculateTotals($orderId);
			if (!$recalc['ok']) {
				throw new Exception($recalc['error']);
			}

			$history = $this->repository->addHistory(
				$orderId,
				'add_item',
				null,
				null,
				json_encode($item, JSON_UNESCAPED_UNICODE),
				$employeeId,
				'Добавлена строка заказа'
			);

			if (!$history['ok']) {
				throw new Exception($history['error']);
			}

			Database::commit('Papir');

			$this->pushToMs($orderId);

			return array(
				'ok' => true,
				'item_id' => $insert['insert_id'],
			);
		} catch (Exception $e) {
			Database::rollback('Papir');
			return array(
				'ok' => false,
				'error' => $e->getMessage(),
			);
		}
	}

    public function updateItem($itemId, $item, $employeeId = null)
    {
        $item = $this->prepareItemData($item);

        $sql = "SELECT `customerorder_id` FROM `customerorder_item` WHERE `id` = " . (int)$itemId . " LIMIT 1";
        $row = Database::fetchRow('Papir', $sql);

        if (!$row['ok']) {
            return $row;
        }

        if (empty($row['row'])) {
            return array(
                'ok' => false,
                'error' => 'Строка заказа не найдена',
            );
        }

        $orderId = (int)$row['row']['customerorder_id'];

        Database::begin('Papir');

        try {
            $update = $this->repository->updateItem($itemId, $item);
            if (!$update['ok']) {
                throw new Exception($update['error']);
            }

            $recalc = $this->repository->recalculateTotals($orderId);
            if (!$recalc['ok']) {
                throw new Exception($recalc['error']);
            }

            $history = $this->repository->addHistory(
                $orderId,
                'update_item',
                null,
                null,
                json_encode($item, JSON_UNESCAPED_UNICODE),
                $employeeId,
                'Обновлена строка заказа'
            );

            if (!$history['ok']) {
                throw new Exception($history['error']);
            }

            Database::commit('Papir');

            $this->pushToMs($orderId);

            return array(
                'ok' => true,
                'order_id' => $orderId,
            );
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(
                'ok' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    public function removeItem($itemId, $employeeId = null)
    {
        $sql = "SELECT `customerorder_id` FROM `customerorder_item` WHERE `id` = " . (int)$itemId . " LIMIT 1";
        $row = Database::fetchRow('Papir', $sql);

        if (!$row['ok']) {
            return $row;
        }

        if (empty($row['row'])) {
            return array(
                'ok' => false,
                'error' => 'Строка заказа не найдена',
            );
        }

        $orderId = (int)$row['row']['customerorder_id'];

        Database::begin('Papir');

        try {
            $delete = $this->repository->deleteItem($itemId);
            if (!$delete['ok']) {
                throw new Exception($delete['error']);
            }

            $recalc = $this->repository->recalculateTotals($orderId);
            if (!$recalc['ok']) {
                throw new Exception($recalc['error']);
            }

            $history = $this->repository->addHistory(
                $orderId,
                'delete_item',
                null,
                null,
                'deleted',
                $employeeId,
                'Удалена строка заказа'
            );

            if (!$history['ok']) {
                throw new Exception($history['error']);
            }

            Database::commit('Papir');

            $this->pushToMs($orderId);

            return array(
                'ok' => true,
                'order_id' => $orderId,
            );
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(
                'ok' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    public function saveAttributes($orderId, $attributes, $employeeId = null)
    {
        Database::begin('Papir');

        try {
            foreach ($attributes as $attribute) {
                $prepared = $this->prepareAttributeData($attribute);

                $save = $this->repository->saveAttribute($orderId, $prepared);
                if (!$save['ok']) {
                    throw new Exception($save['error']);
                }
            }

            $history = $this->repository->addHistory(
                $orderId,
                'save_attributes',
                null,
                null,
                'saved',
                $employeeId,
                'Сохранены атрибуты заказа'
            );

            if (!$history['ok']) {
                throw new Exception($history['error']);
            }

            Database::commit('Papir');

            return array('ok' => true);
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(
                'ok' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    public function changeStatus($orderId, $status, $employeeId = null)
    {
        $allowed = array(
            'draft',
            'new',
            'confirmed',
            'in_progress',
            'waiting_payment',
            'completed',
            'cancelled'
        );

        if (!in_array($status, $allowed)) {
            return array(
                'ok' => false,
                'error' => 'Недопустимый статус заказа',
            );
        }

        Database::begin('Papir');

        try {
            $update = $this->repository->update($orderId, array(
                'status' => $status,
                'next_action' => null,
                'next_action_label' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ));

            if (!$update['ok']) {
                throw new Exception($update['error']);
            }

            $history = $this->repository->addHistory(
                $orderId,
                'change_status',
                'status',
                null,
                $status,
                $employeeId,
                'Изменение статуса заказа'
            );

            if (!$history['ok']) {
                throw new Exception($history['error']);
            }

            Database::commit('Papir');

            $this->pushToMs($orderId);

            return array('ok' => true);
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(
                'ok' => false,
                'error' => $e->getMessage(),
            );
        }
    }

	protected function generateDocumentNumber($documentType, $prefix)
{
    $dbName = 'Papir';
    $periodKey = date('Y');
    $documentTypeEscaped = Database::escape($dbName, $documentType);
    $prefixEscaped = Database::escape($dbName, $prefix);
    $periodKeyEscaped = Database::escape($dbName, $periodKey);

    $sql = "
        SELECT *
        FROM `document_number_counter`
        WHERE `document_type` = '{$documentTypeEscaped}'
          AND `prefix` = '{$prefixEscaped}'
          AND `period_key` = '{$periodKeyEscaped}'
        LIMIT 1
    ";

    $row = Database::fetchRow($dbName, $sql);
    if (!$row['ok']) {
        throw new Exception($row['error']);
    }

    if (empty($row['row'])) {
        $insert = Database::insert($dbName, 'document_number_counter', array(
            'document_type' => $documentType,
            'prefix' => $prefix,
            'period_key' => $periodKey,
            'last_number' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        if (!$insert['ok']) {
            throw new Exception($insert['error']);
        }

        $number = 1;
    } else {
        $counterRow = $row['row'];
        $number = (int)$counterRow['last_number'] + 1;

        $update = Database::update($dbName, 'document_number_counter', array(
            'last_number' => $number,
            'updated_at' => date('Y-m-d H:i:s'),
        ), array(
            'id' => (int)$counterRow['id']
        ));

        if (!$update['ok']) {
            throw new Exception($update['error']);
        }
    }

    return $prefix . '-' . date('Y') . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
}

protected function prepareHeaderData($data, $isCreate)
{
    $result = array(
        'number' => isset($data['number']) ? trim($data['number']) : null,
        'moment' => !empty($data['moment']) ? date('Y-m-d H:i:s', strtotime($data['moment'])) : date('Y-m-d H:i:s'),
        'applicable' => !empty($data['applicable']) ? 1 : 0,
        'wait_call' => !empty($data['wait_call']) ? 1 : 0,
        'organization_id' => !empty($data['organization_id']) ? (int)$data['organization_id'] : null,
        'store_id' => !empty($data['store_id']) ? (int)$data['store_id'] : null,
        'manager_employee_id' => !empty($data['manager_employee_id']) ? (int)$data['manager_employee_id'] : null,
        'counterparty_id' => !empty($data['counterparty_id']) ? (int)$data['counterparty_id'] : null,
		'contact_person_id' => !empty($data['contact_person_id']) ? (int)$data['contact_person_id'] : null,
        'contract_id' => !empty($data['contract_id']) ? (int)$data['contract_id'] : null,
		'organization_bank_account_id' => !empty($data['organization_bank_account_id']) ? (int)$data['organization_bank_account_id'] : null,
		'project_id' => !empty($data['project_id']) ? (int)$data['project_id'] : null,	
		'planned_shipment_at' => !empty($data['planned_shipment_at']) ? date('Y-m-d H:i:s', strtotime($data['planned_shipment_at'])) : null,
		'updated_by_employee_id' => !empty($data['updated_by_employee_id']) ? (int)$data['updated_by_employee_id'] : null,
        'status' => !empty($data['status']) ? $data['status'] : 'draft',
        'currency_code' => !empty($data['currency_code']) ? $data['currency_code'] : 'UAH',
        'currency_rate' => isset($data['currency_rate']) && $data['currency_rate'] !== '' ? (float)$data['currency_rate'] : 1,
        'delivery_method_id' => !empty($data['delivery_method_id']) ? (int)$data['delivery_method_id'] : null,
        'payment_method_id' => !empty($data['payment_method_id']) ? (int)$data['payment_method_id'] : null,
        'sales_channel' => isset($data['sales_channel']) ? $data['sales_channel'] : null,
        'description' => isset($data['description']) ? trim($data['description']) : null,
        'updated_at' => date('Y-m-d H:i:s'),
    );

    if ($isCreate) {
        $result['uuid'] = $this->generateUuid();
        $result['source'] = !empty($data['source']) ? $data['source'] : 'manual';
        $result['sync_state'] = !empty($data['sync_state']) ? $data['sync_state'] : 'new';
        $result['created_at'] = date('Y-m-d H:i:s');

        $result['sum_items'] = 0;
        $result['sum_discount'] = 0;
        $result['sum_vat'] = 0;
        $result['sum_total'] = 0;
        $result['sum_paid'] = 0;
        $result['sum_shipped'] = 0;
        $result['sum_reserved'] = 0;

        // Эти статусы пока можно задавать стартовыми значениями
        $result['payment_status'] = 'not_paid';
        $result['shipment_status'] = 'not_shipped';
    }

    return $result;
}

	protected function prepareItemData($item)
	{
		order_log($item);
		
		$quantity = isset($item['quantity']) ? (float)$item['quantity'] : 0;
		if ($quantity <= 0) {
			$quantity = 1;
		}

		$price = isset($item['price']) ? (float)$item['price'] : 0;
		$vatRate = isset($item['vat_rate']) ? (float)$item['vat_rate'] : 20;
		$discountPercent = isset($item['discount_percent']) ? (float)$item['discount_percent'] : 0;
		
		// КЛЮЧЕВОЙ МОМЕНТ: правильно определяем флаг
		$sumRowChanged = isset($item['sum_row_changed']) && (int)$item['sum_row_changed'] === 1;

		// sum_row приходит с фронта
		$sumRow = isset($item['sum_row']) ? (float)$item['sum_row'] : 0;

		// ЛОГИКА РАСЧЕТА:
		if ($sumRowChanged) {
			// Случай 1: пользователь менял сумму вручную
			$price = $quantity > 0 ? round($sumRow / $quantity, 2) : 0;
			$gross = round($quantity * $price, 2);
			$discountAmount = round($gross * ($discountPercent / 100), 2);
			
			$calculatedSum = round($gross - $discountAmount, 2);
			
			if (abs($calculatedSum - $sumRow) > 0.01) {
				$price = round(($sumRow + $discountAmount) / $quantity, 2);
				$gross = round($quantity * $price, 2);
				$discountAmount = round($gross * ($discountPercent / 100), 2);
				$sumRow = round($gross - $discountAmount, 2);
			}
		} else {
			$gross = round($quantity * $price, 2);
			$discountAmount = round($gross * ($discountPercent / 100), 2);
			$sumRow = round($gross - $discountAmount, 2);
		}

		// Расчет НДС
		if ($vatRate > 0) {
			$sumWithoutDiscount = round($sumRow / (1 + $vatRate / 100), 2);
			$vatAmount = round($sumRow - $sumWithoutDiscount, 2);
		} else {
			$sumWithoutDiscount = round($sumRow, 2);
			$vatAmount = 0;
		}

		// ВАЖНО: Сохраняем line_no если он был передан
		$lineNo = null;
		if (isset($item['line_no'])) {
			$lineNo = (int)$item['line_no'];
		} elseif (isset($item['id'])) {
			// Если это обновление существующей строки, нужно получить текущий line_no
			// Это будет сделано в updateItems()
			$lineNo = null; // Пометим что нужно получить из БД
		}

		return array(
			'line_no' => $lineNo, // Может быть null, но это обработается в updateItems
			'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
			'stock_quantity' => isset($item['stock_quantity']) ? (float)$item['stock_quantity'] : 0,
			'reserved_stock_quantity' => isset($item['reserved_stock_quantity']) ? (float)$item['reserved_stock_quantity'] : 0,
			'expected_quantity' => isset($item['expected_quantity']) ? (float)$item['expected_quantity'] : 0,
			'product_ms_id' => !empty($item['product_ms_id']) ? $item['product_ms_id'] : null,
			'product_name' => !empty($item['product_name']) ? $item['product_name'] : null,
			'sku' => !empty($item['sku']) ? $item['sku'] : null,
			'unit' => !empty($item['unit']) ? $item['unit'] : null,
			'quantity' => $quantity,
			'price' => round($price, 2),
			'discount_percent' => $discountPercent,
			'discount_amount' => $discountAmount,
			'vat_rate' => $vatRate,
			'vat_amount' => $vatAmount,
			'sum_without_discount' => $sumWithoutDiscount,
			'sum_row' => round($sumRow, 2),
			'weight' => isset($item['weight']) ? (float)$item['weight'] : 0,
			'reserved_quantity' => isset($item['reserved_quantity']) ? (float)$item['reserved_quantity'] : 0,
			'shipped_quantity' => isset($item['shipped_quantity']) ? (float)$item['shipped_quantity'] : 0,
			'comment' => isset($item['comment']) ? $item['comment'] : null,
			'updated_at' => date('Y-m-d H:i:s'),
		);
	}

    protected function prepareAttributeData($attribute)
    {
        return array(
            'id' => !empty($attribute['id']) ? (int)$attribute['id'] : null,
            'attr_id' => !empty($attribute['attr_id']) ? (int)$attribute['attr_id'] : null,
            'name_main' => !empty($attribute['name_main']) ? $attribute['name_main'] : 'unknown_attr',
            'value_string' => isset($attribute['value_string']) ? $attribute['value_string'] : null,
            'value_text' => isset($attribute['value_text']) ? $attribute['value_text'] : null,
            'value_number' => isset($attribute['value_number']) ? $attribute['value_number'] : null,
            'value_date' => isset($attribute['value_date']) ? $attribute['value_date'] : null,
            'value_bool' => isset($attribute['value_bool']) ? (int)$attribute['value_bool'] : null,
            'value_ref' => isset($attribute['value_ref']) ? $attribute['value_ref'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        );
    }

    protected function generateUuid()
    {
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
	
	public function updateItems($orderId, $items, $employeeId = null)
	{
		if (empty($items)) {
			return array('ok' => true);
		}

		order_log([
			'method' => 'updateItems',
			'orderId' => $orderId,
			'items_count' => count($items)
		]);

		Database::begin('Papir');

		try {
			foreach ($items as $item) {
				if (!isset($item['id'])) {
					order_log("Skipping item without id");
					continue;
				}

				$itemId = (int)$item['id'];
				
				// Получаем текущую строку из БД
				$currentItem = $this->repository->getItemById($itemId);
				if (!$currentItem['ok'] || empty($currentItem['row'])) {
					throw new Exception("Строка заказа #{$itemId} не найдена");
				}

				// Подготавливаем данные
				$preparedItem = [
					'id' => $itemId,
					'line_no' => $currentItem['row']['line_no'], // Берем line_no из БД!
					'quantity' => isset($item['quantity']) ? (float)$item['quantity'] : $currentItem['row']['quantity'],
					'price' => isset($item['price']) ? (float)$item['price'] : $currentItem['row']['price'],
					'vat_rate' => isset($item['vat_rate']) ? (float)$item['vat_rate'] : $currentItem['row']['vat_rate'],
					'discount_percent' => isset($item['discount_percent']) ? (float)$item['discount_percent'] : $currentItem['row']['discount_percent'],
					'sum_row' => isset($item['sum_row']) ? (float)$item['sum_row'] : $currentItem['row']['sum_row'],
					'sum_row_changed' => isset($item['sum_row_changed']) ? (int)$item['sum_row_changed'] : 0,
					'product_id' => $currentItem['row']['product_id'],
					'product_name' => $currentItem['row']['product_name'],
					'sku' => $currentItem['row']['sku'],
					'unit' => $currentItem['row']['unit']
				];

				order_log("Processing item #{$itemId}: " . json_encode($preparedItem));

				// Подготавливаем данные с правильными расчетами
				$preparedData = $this->prepareItemData($preparedItem);
				
				// Убеждаемся что line_no не null
				if (!isset($preparedData['line_no']) || $preparedData['line_no'] === null) {
					$preparedData['line_no'] = $currentItem['row']['line_no'];
				}

				order_log("Prepared data for item #{$itemId}: " . json_encode($preparedData));

				// Обновляем через репозиторий
				$update = $this->repository->updateItem($itemId, $preparedData);
				if (!$update['ok']) {
					throw new Exception("Ошибка обновления строки #{$itemId}: {$update['error']}");
				}
			}

			// Пересчитываем итоги
			$recalc = $this->repository->recalculateTotals($orderId);
			if (!$recalc['ok']) {
				throw new Exception("Ошибка пересчета итогов: {$recalc['error']}");
			}

			// Добавляем запись в историю
			$history = $this->repository->addHistory(
				$orderId,
				'update_items',
				null,
				null,
				'bulk_update',
				$employeeId,
				'Массовое обновление строк заказа'
			);

			if (!$history['ok']) {
				throw new Exception($history['error']);
			}

			Database::commit('Papir');

			order_log("Items updated successfully for order #{$orderId}");

			$this->pushToMs($orderId);

			return array(
				'ok' => true,
				'message' => 'Строки успешно обновлены'
			);

		} catch (Exception $e) {
			Database::rollback('Papir');
			order_log("Error updating items: " . $e->getMessage());
			return array(
				'ok' => false,
				'error' => $e->getMessage()
			);
		}
	}

    /**
     * Soft-delete заказа в Papir + каскадное удаление в МС (если id_ms есть).
     */
    public function deleteOrder($orderId, $employeeId = null)
    {
        $orderId = (int)$orderId;

        // Загрузить заказ для проверки и получения id_ms
        $r = Database::fetchRow('Papir',
            "SELECT id, id_ms, number FROM customerorder WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");
        if (!$r['ok'] || empty($r['row'])) {
            return array('ok' => false, 'error' => 'Замовлення не знайдено');
        }
        $order = $r['row'];

        Database::begin('Papir');

        try {
            $del = $this->repository->softDelete($orderId);
            if (!$del['ok']) {
                throw new Exception($del['error']);
            }

            $this->repository->addHistory(
                $orderId,
                'delete',
                null,
                null,
                null,
                $employeeId,
                'Видалення замовлення'
            );

            Database::commit('Papir');

            // Каскад: удалить в МС
            if (!empty($order['id_ms'])) {
                $this->deleteFromMs($order['id_ms'], $orderId);
            }

            return array('ok' => true);
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Удалить заказ в МойСклад по id_ms.
     * Ошибки не бросаем — только логируем.
     */
    private function deleteFromMs($msId, $orderId)
    {
        try {
            $ms = new MoySkladApi();
            $url = $ms->getEntityBaseUrl() . 'customerorder/' . $msId;
            $result = $ms->querySend($url, null, 'DELETE');
            $result = json_decode(json_encode($result), true);
            if (!empty($result['errors'])) {
                $err = isset($result['errors'][0]['error']) ? $result['errors'][0]['error'] : 'Unknown MS error';
                order_log('MS DELETE failed for order #' . $orderId . ' (ms:' . $msId . '): ' . $err);
            }
        } catch (Exception $e) {
            order_log('MS DELETE exception for order #' . $orderId . ': ' . $e->getMessage());
        }
    }

    /**
     * Push заказа в МойСклад после успешного сохранения в Papir.
     * Ошибки не бросаем — только логируем, чтобы не прерывать основной поток.
     */
    private function pushToMs($orderId)
    {
        try {
            $sync = new CustomerOrderMsSync();
            $result = $sync->push((int)$orderId);
            if (!$result['ok']) {
                order_log('MS push failed for order #' . $orderId . ': ' . $result['error']);
            }
        } catch (Exception $e) {
            order_log('MS push exception for order #' . $orderId . ': ' . $e->getMessage());
        }
    }
}