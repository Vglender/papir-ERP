<?php

/**
 * CustomerOrderMsSync — Papir → МойСклад
 *
 * Пушить заказ и его позиции в МС при изменениях в Papir CRM.
 * CREATE (POST) если id_ms = null, UPDATE (PUT) если id_ms уже есть.
 * После успеха: сохраняет id_ms, ставит sync_state = 'synced'.
 * При ошибке: ставит sync_state = 'error', пишет в sync_error.
 *
 * Маппинг статусов Papir → UUID состояния МС:
 *   new              → Новый
 *   confirmed        → Принят
 *   in_progress      → Передан в сборку
 *   waiting_payment  → Ждем оплату
 *   paid             → Оплаченный
 *   partially_shipped→ Передан в сборку
 *   shipped          → Передан в доставку
 *   completed        → Выполнен
 *   cancelled        → Отменен
 *   draft            → (не маппится, статус в МС не меняется)
 */
class CustomerOrderMsSync
{
    private $ms;

    // Papir status → MS state UUID
    private static $statusMap = array(
        'new'               => 'c2fc692f-dd59-11ea-0a80-03fa00051f8e',
        'confirmed'         => '8b9e1475-dce9-11ea-0a80-006100019351',
        'in_progress'       => '5f821bb6-0877-11eb-0a80-049300051d5e',
        'waiting_payment'   => '34fe6465-f5be-11eb-0a80-0d4800058863',
        'paid'              => 'ad2d88b8-7abf-11eb-0a80-03f80037a302',
        'partially_shipped' => '5f821bb6-0877-11eb-0a80-049300051d5e',
        'shipped'           => '41c486a9-d29a-11ea-0a80-0517000f0d4a',
        'completed'         => 'bc5a77c2-d2ad-11ea-0a80-02ef0007cc9f',
        'cancelled'         => '41c488a7-d29a-11ea-0a80-0517000f0d4d',
    );

    public function __construct()
    {
        $this->ms = new MoySkladApi();
    }

    /**
     * Запушить заказ $orderId в МС.
     * Возвращает array('ok'=>bool, 'ms_id'=>string|null, 'created'=>bool, 'error'=>string)
     */
    public function push($orderId)
    {
        $orderId = (int)$orderId;

        // Загрузить заказ
        $r = Database::fetchRow('Papir',
            "SELECT co.*, o.id_ms AS org_ms, cp.id_ms AS cp_ms
             FROM customerorder co
             LEFT JOIN organization o ON o.id = co.organization_id
             LEFT JOIN counterparty cp ON cp.id = co.counterparty_id
             WHERE co.id = {$orderId} AND co.deleted_at IS NULL
             LIMIT 1");
        if (!$r['ok'] || empty($r['row'])) {
            return array('ok' => false, 'error' => 'Order not found: ' . $orderId);
        }
        $order = $r['row'];

        // Загрузить позиции (с product_papir.id_ms как fallback)
        $ri = Database::fetchAll('Papir',
            "SELECT ci.*,
                    COALESCE(ci.product_ms_id, pp.id_ms) AS resolved_product_ms_id
             FROM customerorder_item ci
             LEFT JOIN product_papir pp ON pp.product_id = ci.product_id
             WHERE ci.customerorder_id = {$orderId}
             ORDER BY ci.line_no");
        $items = ($ri['ok'] && !empty($ri['rows'])) ? $ri['rows'] : array();

        // Собрать payload
        $payload = $this->buildPayload($order, $items);
        if (!$payload['ok']) {
            $this->markError($orderId, $payload['error']);
            return array('ok' => false, 'error' => $payload['error']);
        }

        $entityBase = $this->ms->getEntityBaseUrl();
        $msId = !empty($order['id_ms']) ? $order['id_ms'] : null;

        if ($msId) {
            // UPDATE
            $url    = $entityBase . 'customerorder/' . $msId;
            $result = $this->ms->querySend($url, $payload['data'], 'PUT');
            $created = false;
        } else {
            // CREATE
            $url    = $entityBase . 'customerorder';
            $result = $this->ms->querySend($url, $payload['data'], 'POST');
            $created = true;
        }

        // querySend возвращает stdClass — конвертируем в массив
        $result = json_decode(json_encode($result), true);

        if (empty($result) || !empty($result['errors'])) {
            $errMsg = $this->extractMsError($result);
            $this->markError($orderId, $errMsg);
            return array('ok' => false, 'error' => $errMsg);
        }

        // Извлечь id_ms из ответа
        $returnedMsId = !empty($result['id']) ? (string)$result['id'] : $msId;

        // Сохранить id_ms + sync_state = synced
        $upd = array(
            'sync_state' => 'synced',
            'sync_error' => null,
        );
        if ($created && $returnedMsId) {
            $upd['id_ms'] = $returnedMsId;
        }
        Database::update('Papir', 'customerorder', $upd, array('id' => $orderId));

        return array(
            'ok'      => true,
            'ms_id'   => $returnedMsId,
            'created' => $created,
        );
    }

    // ─────────────────────────────────────────────────────────────────

    private function buildPayload(array $order, array $items)
    {
        $entityBase = $this->ms->getEntityBaseUrl();

        // organization — обязательно
        if (empty($order['org_ms'])) {
            return array('ok' => false,
                'error' => 'organization.id_ms is empty for organization_id=' . $order['organization_id']);
        }

        $data = array(
            'applicable' => !empty($order['applicable']),
            'organization' => array('meta' => array(
                'href'        => $entityBase . 'organization/' . $order['org_ms'],
                'type'        => 'organization',
                'mediaType'   => 'application/json',
            )),
        );

        // moment
        if (!empty($order['moment'])) {
            $data['moment'] = $order['moment'];
        }

        // description
        if (!empty($order['description'])) {
            $data['description'] = $order['description'];
        }

        // agent (counterparty)
        if (!empty($order['cp_ms'])) {
            $data['agent'] = array('meta' => array(
                'href'      => $entityBase . 'counterparty/' . $order['cp_ms'],
                'type'      => 'counterparty',
                'mediaType' => 'application/json',
            ));
        }

        // state (status)
        $statusUuid = isset(self::$statusMap[$order['status']]) ? self::$statusMap[$order['status']] : null;
        if ($statusUuid) {
            $data['state'] = array('meta' => array(
                'href'      => $entityBase . 'customerorder/metadata/states/' . $statusUuid,
                'type'      => 'state',
                'mediaType' => 'application/json',
            ));
        }

        // positions
        if (!empty($items)) {
            $positions = array();
            foreach ($items as $item) {
                $productMsId = !empty($item['resolved_product_ms_id']) ? $item['resolved_product_ms_id'] : null;
                if (!$productMsId) {
                    continue; // пропускаем позиции без привязки к МС-товару
                }
                $positions[] = array(
                    'quantity' => (float)$item['quantity'],
                    'price'    => (int)round((float)$item['price'] * 100),
                    'discount' => (float)$item['discount_percent'],
                    'vat'      => (float)$item['vat_rate'],
                    'assortment' => array('meta' => array(
                        'href'      => $entityBase . 'product/' . $productMsId,
                        'type'      => 'product',
                        'mediaType' => 'application/json',
                    )),
                );
            }
            if (!empty($positions)) {
                $data['positions'] = $positions;
            }
        }

        return array('ok' => true, 'data' => $data);
    }

    private function markError($orderId, $errorMsg)
    {
        Database::update('Papir', 'customerorder',
            array('sync_state' => 'error', 'sync_error' => mb_substr($errorMsg, 0, 1000, 'UTF-8')),
            array('id' => $orderId)
        );
    }

    private function extractMsError($result)
    {
        if (empty($result)) {
            return 'Empty response from MoySklad';
        }
        if (!empty($result['errors']) && is_array($result['errors'])) {
            $msgs = array();
            foreach ($result['errors'] as $e) {
                $msgs[] = isset($e['error']) ? $e['error'] : json_encode($e);
            }
            return implode('; ', $msgs);
        }
        return 'Unknown MoySklad error';
    }
}