<?php
/**
 * TaskQueueRunner — выполняет задачи из cp_task_queue.
 * Запускается кроном каждые 5 минут.
 *
 * Поддерживаемые action_type:
 *   robot:    send_message, send_invoice, change_status, set_next_action,
 *             create_demand, create_salesreturn, create_cashin, wait
 *   operator: create_task
 */
class TaskQueueRunner
{
    const BATCH = 20;

    public static function runPending()
    {
        // Wake snoozed tasks first
        Database::query('Papir',
            "UPDATE cp_tasks SET status='open', snoozed_until=NULL
             WHERE status='snoozed' AND snoozed_until <= NOW()"
        );

        $processed = 0;
        $maxPasses = 5; // prevent infinite loops

        // Re-fetch after each pass: scenario steps schedule follow-up tasks
        // during execution, so a single SELECT misses them.
        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $r = Database::fetchAll('Papir',
                "SELECT * FROM cp_task_queue
                 WHERE status='pending' AND fire_at <= NOW()
                 ORDER BY fire_at ASC
                 LIMIT " . self::BATCH
            );
            if (!$r['ok'] || empty($r['rows'])) break;

            foreach ($r['rows'] as $item) {
                self::runItem($item);
                $processed++;
            }
        }

        return $processed;
    }

    private static function runItem($item)
    {
        $id = (int)$item['id'];

        // Атомарний claim: тільки той процес, чий UPDATE справді змінив pending→running,
        // має право виконати задачу. Захищає від подвоєння кроків, коли runPending()
        // викликається паралельно з крона і з синхронних точок (create_ttn,
        // save_order_delivery): обидва процеси бачать однакові pending-рядки у
        // fetchAll, але тільки один отримає affected_rows=1.
        $claim = Database::query('Papir',
            "UPDATE cp_task_queue SET status='running', started_at=NOW()
             WHERE id={$id} AND status='pending'"
        );
        if (!$claim['ok'] || (int)$claim['affected_rows'] === 0) {
            return;
        }

        $ok   = true;
        $note = '';

        try {
            $params = array();
            if (!empty($item['action_params'])) {
                $params = json_decode($item['action_params'], true) ?: array();
            }
            $context = array();
            if (!empty($item['context'])) {
                $context = json_decode($item['context'], true) ?: array();
            }

            // Re-check step conditions at execution time (not just at scheduling)
            // to handle race conditions with parallel scenarios
            $stepId = (int)$item['step_id'];
            if ($stepId) {
                $rStep = Database::fetchRow('Papir',
                    "SELECT conditions, condition_key, condition_op, condition_value
                     FROM cp_scenario_steps WHERE id={$stepId}");
                if ($rStep['ok'] && $rStep['row']) {
                    $orderId = (int)$item['order_id'];
                    $ctx = $context;
                    if ($orderId) {
                        $ctx['order_id'] = $orderId;
                    }
                    if (!\TriggerEngine::evaluateCondition($rStep['row'], $ctx)) {
                        Database::query('Papir',
                            "UPDATE cp_task_queue SET status='skipped', done_at=NOW(),
                             result_note='Step condition not met at execution time'
                             WHERE id={$id}");
                        self::scheduleNextStep($item, $context);
                        return;
                    }
                }
            }

            switch ($item['action_type']) {
                case 'send_message':
                    list($ok, $note) = self::doSendMessage($item, $params, $context);
                    break;
                case 'create_task':
                    list($ok, $note) = self::doCreateTask($item, $params);
                    break;
                case 'send_invoice':
                    list($ok, $note) = self::doSendInvoice($item, $params, $context);
                    break;
                case 'change_status':
                    list($ok, $note) = self::doChangeStatus($item, $params);
                    break;
                case 'create_demand':
                    list($ok, $note) = self::doCreateDemand($item, $params);
                    break;
                case 'set_next_action':
                    list($ok, $note) = self::doSetNextAction($item, $params);
                    break;
                case 'create_salesreturn':
                    list($ok, $note) = self::doCreateSalesreturn($item, $params);
                    break;
                case 'create_cashin':
                    list($ok, $note) = self::doCreateCashin($item, $params);
                    break;
                case 'wait':
                    $ok   = true;
                    $note = 'Waited';
                    break;
                default:
                    $ok   = false;
                    $note = 'Unknown action: ' . $item['action_type'];
            }
        } catch (Exception $e) {
            $ok   = false;
            $note = $e->getMessage();
        }

        $status  = $ok ? 'done' : 'failed';
        $noteSql = Database::escape('Papir', $note);
        Database::query('Papir',
            "UPDATE cp_task_queue SET status='{$status}', done_at=NOW(), result_note='{$noteSql}'
             WHERE id={$id}"
        );

        // If done OK and executor=robot → schedule next step
        if ($ok && $item['executor'] === 'robot' && $item['step_id']) {
            self::scheduleNextStep($item, $context);
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    private static function doSendMessage($item, $params, $context)
    {
        $cpId    = (int)$item['counterparty_id'];
        $orderId = (int)$item['order_id'];
        if (!$cpId) return array(false, 'No counterparty_id');

        $text = isset($params['text']) ? $params['text'] : '';
        $text = self::resolveVars($text, $context);
        if (!$text) return array(false, 'No message text');

        $alsoEmail = !empty($params['also_email']);

        // Priority mode (new) — используем MessageDispatchService
        if (isset($params['mode']) && $params['mode'] === 'priority') {
            $priority = isset($params['priority_channels']) && is_array($params['priority_channels'])
                ? $params['priority_channels']
                : MessageDispatchService::DEFAULT_PRIORITY;
            $res = MessageDispatchService::send($cpId, $text, $priority, $alsoEmail, $orderId);
            if ($res['ok']) {
                $note = 'Sent via ' . $res['channel'];
                if (!empty($res['email_copy'])) {
                    $note .= $res['email_copy']['ok'] ? ' + email copy' : ' (email copy failed)';
                }
            } else {
                $note = 'Failed: ' . $res['error'] . ' (tried: ' . implode(',', array_column($res['tried'], 'channel')) . ')';
            }
            return array($res['ok'], $note);
        }

        // Legacy mode — список каналів з чекбоксів
        $channels = isset($params['channels']) ? (array)$params['channels'] : array('viber');
        $res = MessageDispatchService::send($cpId, $text, $channels, $alsoEmail, $orderId);
        $note = $res['ok']
            ? 'Sent via ' . $res['channel']
            : 'Could not send: ' . $res['error'];
        return array($res['ok'], $note);
    }

    // ── Create demand (відвантаження) ─────────────────────────────────────────

    private static function doCreateDemand($item, $params)
    {
        $orderId = (int)$item['order_id'];
        if (!$orderId) return array(false, 'No order_id in context');

        // Перевіряємо чи вже є відвантаження
        $rEx = Database::fetchRow('Papir',
            "SELECT id FROM demand WHERE customerorder_id={$orderId} AND deleted_at IS NULL LIMIT 1");
        if ($rEx['ok'] && !empty($rEx['row'])) {
            return array(true, 'Demand already exists: #' . (int)$rEx['row']['id']);
        }

        // Завантажуємо замовлення
        $rOrder = Database::fetchRow('Papir',
            "SELECT * FROM customerorder WHERE id={$orderId} LIMIT 1");
        if (!$rOrder['ok'] || empty($rOrder['row'])) {
            return array(false, 'Order not found');
        }
        $order = $rOrder['row'];

        // Завантажуємо позиції
        $rItems = Database::fetchAll('Papir',
            "SELECT ci.*, pp.product_article AS sku_fallback, pp.id_ms AS product_ms_id
             FROM customerorder_item ci
             LEFT JOIN product_papir pp ON pp.product_id = ci.product_id
             WHERE ci.customerorder_id = {$orderId}
             ORDER BY ci.line_no ASC");
        if (!$rItems['ok']) return array(false, 'Failed to load order items');

        // Генеруємо UUID
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

        $demandStatus = isset($params['status']) ? $params['status'] : 'new';
        $storeId = isset($params['store_id']) && $params['store_id']
            ? (int)$params['store_id']
            : (isset($order['store_id']) ? (int)$order['store_id'] : null);

        Database::begin('Papir');
        try {
            $rIns = Database::insert('Papir', 'demand', array(
                'uuid'             => $uuid,
                'customerorder_id' => $orderId,
                'counterparty_id'  => isset($order['counterparty_id']) ? (int)$order['counterparty_id'] : null,
                'organization_id'  => isset($order['organization_id']) ? (int)$order['organization_id'] : null,
                'store_id'         => $storeId ?: null,
                'moment'           => date('Y-m-d H:i:s'),
                'status'           => $demandStatus,
                'applicable'       => 0,
                'source'           => 'auto',
                'sync_state'       => 'new',
                'sum_total'        => isset($order['sum_total']) ? (float)$order['sum_total'] : 0,
                'sum_vat'          => isset($order['sum_vat'])   ? (float)$order['sum_vat']   : 0,
                'description'      => isset($params['description']) ? $params['description'] : 'Авто-відвантаження',
            ));
            if (!$rIns['ok']) throw new Exception('Demand insert failed');
            $demandId = (int)$rIns['insert_id'];

            // Копіюємо позиції
            foreach ($rItems['rows'] as $ci) {
                $sku = $ci['sku'] ?: $ci['sku_fallback'];
                Database::insert('Papir', 'demand_item', array(
                    'demand_id'        => $demandId,
                    'line_no'          => (int)$ci['line_no'],
                    'product_id'       => $ci['product_id'] ? (int)$ci['product_id'] : null,
                    'product_ms_id'    => $ci['product_ms_id'] ?: null,
                    'product_name'     => $ci['product_name'],
                    'sku'              => $sku,
                    'quantity'         => (float)$ci['quantity'],
                    'price'            => (float)$ci['price'],
                    'discount_percent' => (float)$ci['discount_percent'],
                    'vat_rate'         => (float)$ci['vat_rate'],
                    'sum_row'          => (float)$ci['sum_row'],
                ));
            }

            // Зв'язуємо з замовленням
            Database::insert('Papir', 'document_link', array(
                'from_type' => 'demand',
                'from_id'   => $demandId,
                'to_type'   => 'customerorder',
                'to_id'     => $orderId,
                'link_type' => 'shipment',
            ));

            Database::commit('Papir');
            return array(true, "Demand #{$demandId} created (status={$demandStatus}, items=" . count($rItems['rows']) . ')');
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(false, $e->getMessage());
        }
    }

    // ── Create salesreturn (повернення) from order's demand ─────────────────

    private static function doCreateSalesreturn($item, $params)
    {
        $orderId = (int)$item['order_id'];
        if (!$orderId) return array(false, 'No order_id in context');

        // Find active demand linked to this order
        $rDem = Database::fetchRow('Papir',
            "SELECT d.id, d.id_ms, d.counterparty_id, d.description,
                    o.id_ms   AS org_ms,
                    cp.id_ms  AS cp_ms,
                    co.organization_id AS order_org_id
             FROM document_link dl
             JOIN demand d ON (d.id_ms = dl.from_ms_id OR (dl.from_ms_id IS NULL AND d.id = dl.from_id))
             LEFT JOIN customerorder co ON co.id = dl.to_id
             LEFT JOIN organization  o  ON o.id  = co.organization_id
             LEFT JOIN counterparty  cp ON cp.id = d.counterparty_id
             WHERE dl.from_type = 'demand'
               AND dl.to_type   = 'customerorder'
               AND dl.to_id     = {$orderId}
               AND d.deleted_at IS NULL
               AND d.status NOT IN ('cancelled','returned')
             ORDER BY d.moment DESC
             LIMIT 1");
        if (!$rDem['ok'] || empty($rDem['row'])) {
            return array(false, 'No active demand found for order #' . $orderId);
        }
        $demand   = $rDem['row'];
        $demandId = (int)$demand['id'];

        // Check if salesreturn already exists for this demand
        $rEx = Database::fetchRow('Papir',
            "SELECT sr.id FROM document_link dl
             JOIN salesreturn sr ON sr.id = dl.from_id
             WHERE dl.from_type = 'salesreturn' AND dl.to_type = 'demand' AND dl.to_id = {$demandId}
             LIMIT 1");
        if ($rEx['ok'] && !empty($rEx['row'])) {
            return array(true, 'Salesreturn already exists: #' . (int)$rEx['row']['id']);
        }

        // Load demand items
        $rItems = Database::fetchAll('Papir',
            "SELECT di.product_id,
                    COALESCE(di.product_ms_id, pp.id_ms) AS product_ms_id,
                    COALESCE(NULLIF(di.product_name,''), pd_uk.name, pd_ru.name, '') AS product_name,
                    di.quantity, di.price, di.discount_percent, di.vat_rate, di.sum_row, di.line_no
             FROM demand_item di
             LEFT JOIN product_papir pp ON pp.product_id = di.product_id
             LEFT JOIN product_description pd_uk ON pd_uk.product_id = di.product_id AND pd_uk.language_id = 2
             LEFT JOIN product_description pd_ru ON pd_ru.product_id = di.product_id AND pd_ru.language_id = 1
             WHERE di.demand_id = {$demandId}
             ORDER BY di.line_no ASC");
        $items = ($rItems['ok'] && !empty($rItems['rows'])) ? $rItems['rows'] : array();
        if (empty($items)) {
            return array(false, 'No items in demand #' . $demandId);
        }

        $sumTotal = 0.0;
        foreach ($items as $it) { $sumTotal += (float)$it['sum_row']; }
        $sumTotal = round($sumTotal * 100) / 100;

        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));

        Database::begin('Papir');
        try {
            $rIns = Database::insert('Papir', 'salesreturn', array(
                'uuid'            => $uuid,
                'source'          => 'papir',
                'moment'          => date('Y-m-d H:i:s'),
                'applicable'      => 0,
                'counterparty_id' => !empty($demand['counterparty_id']) ? (int)$demand['counterparty_id'] : null,
                'demand_id'       => $demandId,
                'sum_total'       => $sumTotal,
                'sum_paid'        => 0,
                'description'     => isset($params['description']) ? $params['description'] : 'Авто-повернення (сценарій)',
                'sync_state'      => 'new',
            ));
            if (!$rIns['ok'] || empty($rIns['insert_id'])) throw new Exception('Salesreturn insert failed');
            $newId = (int)$rIns['insert_id'];

            // Copy items
            $ln = 1;
            foreach ($items as $it) {
                $pid   = !empty($it['product_id'])   ? (int)$it['product_id']  : null;
                $pmsId = !empty($it['product_ms_id']) ? $it['product_ms_id']   : null;
                $qty   = (float)$it['quantity'];
                $price = (float)$it['price'];
                $disc  = (float)$it['discount_percent'];
                $gross = round($qty * $price * 100) / 100;
                $discAmt = round($gross * $disc / 100 * 100) / 100;
                $sumRow  = round(($gross - $discAmt) * 100) / 100;

                $pmsEsc = $pmsId ? "'" . Database::escape('Papir', $pmsId) . "'" : 'NULL';
                $pidSql = $pid   ? $pid : 'NULL';

                Database::query('Papir',
                    "INSERT INTO salesreturn_item (salesreturn_id, line_no, product_id, product_ms_id, quantity, price, sum_row)
                     VALUES ({$newId}, {$ln}, {$pidSql}, {$pmsEsc}, {$qty}, {$price}, {$sumRow})");
                $ln++;
            }

            // document_link: salesreturn → demand
            Database::insert('Papir', 'document_link', array(
                'from_type'  => 'salesreturn',
                'from_id'    => $newId,
                'to_type'    => 'demand',
                'to_id'      => $demandId,
                'link_type'  => 'return',
                'linked_sum' => $sumTotal,
            ));

            Database::commit('Papir');

            // Try push to MoySklad (non-blocking)
            self::pushSalesreturnToMs($newId, $demand, $items);

            return array(true, "Salesreturn #{$newId} created from demand #{$demandId} (sum={$sumTotal})");
        } catch (Exception $e) {
            Database::rollback('Papir');
            return array(false, $e->getMessage());
        }
    }

    /**
     * Push salesreturn to MoySklad (best-effort, non-blocking).
     */
    private static function pushSalesreturnToMs($salesreturnId, $demand, $items)
    {
        if (empty($demand['id_ms']) || empty($demand['org_ms'])) return;

        try {
            require_once __DIR__ . '/../../moysklad/moysklad_api.php';
            $ms   = new \MoySkladApi();
            $base = $ms->getEntityBaseUrl();

            $payload = array(
                'applicable'   => false,
                'organization' => array('meta' => array(
                    'href' => $base . 'organization/' . $demand['org_ms'],
                    'type' => 'organization', 'mediaType' => 'application/json',
                )),
                'demand' => array('meta' => array(
                    'href' => $base . 'demand/' . $demand['id_ms'],
                    'type' => 'demand', 'mediaType' => 'application/json',
                )),
            );

            if (!empty($demand['cp_ms'])) {
                $payload['agent'] = array('meta' => array(
                    'href' => $base . 'counterparty/' . $demand['cp_ms'],
                    'type' => 'counterparty', 'mediaType' => 'application/json',
                ));
            }

            $positions = array();
            foreach ($items as $it) {
                if (empty($it['product_ms_id'])) continue;
                $positions[] = array(
                    'quantity'   => (float)$it['quantity'],
                    'price'      => (int)round((float)$it['price'] * 100),
                    'discount'   => (float)$it['discount_percent'],
                    'vat'        => (float)$it['vat_rate'],
                    'assortment' => array('meta' => array(
                        'href' => $base . 'product/' . $it['product_ms_id'],
                        'type' => 'product', 'mediaType' => 'application/json',
                    )),
                );
            }
            if (!empty($positions)) $payload['positions'] = $positions;

            $msResult = $ms->querySend($base . 'salesreturn', $payload, 'POST');
            if (!empty($msResult['id'])) {
                Database::update('Papir', 'salesreturn',
                    array('id_ms' => $msResult['id'],
                          'number' => !empty($msResult['name']) ? $msResult['name'] : null,
                          'sync_state' => 'synced'),
                    array('id' => $salesreturnId));
            } else {
                Database::update('Papir', 'salesreturn',
                    array('sync_state' => 'error'),
                    array('id' => $salesreturnId));
            }
        } catch (Exception $e) {
            Database::update('Papir', 'salesreturn',
                array('sync_state' => 'error'),
                array('id' => $salesreturnId));
        }
    }

    /**
     * Створити ПКО (прибутковий касовий ордер) по накладеному платежу.
     * Номер ПКО = номер ТТН. Сума = afterpayment_on_goods_cost з ТТН
     * (→ cost_on_site → sum_total). Дедуп по (direction='in', doc_number).
     * Пуш у МС автоматично через finance_ms_cash_push, якщо модуль moysklad
     * активний і finance.C.to дозволений.
     */
    private static function doCreateCashin($item, $params)
    {
        $orderId = (int)$item['order_id'];
        if (!$orderId) return array(false, 'No order_id');

        // 1. ТТН для заказу
        $rTtn = Database::fetchRow('Papir',
            "SELECT id, int_doc_number, afterpayment_on_goods_cost, cost_on_site, moment
             FROM ttn_novaposhta
             WHERE customerorder_id = {$orderId}
               AND (deletion_mark IS NULL OR deletion_mark = 0)
               AND int_doc_number IS NOT NULL AND int_doc_number != ''
             ORDER BY id DESC LIMIT 1");
        if (!$rTtn['ok'] || empty($rTtn['row'])) {
            return array(false, 'No active TTN found for order #' . $orderId);
        }
        $ttnId     = (int)$rTtn['row']['id'];
        $docNumber = trim((string)$rTtn['row']['int_doc_number']);
        if ($docNumber === '') return array(false, 'TTN has empty int_doc_number');

        // 2. Замовлення + контрагент + організація.
        // Локальні counterparty_id / organization_id — джерело правди.
        // id_ms — суто маппінг для пушу в МС; може бути NULL, тоді push скіпається.
        $rOrder = Database::fetchRow('Papir',
            "SELECT co.id, co.number, co.sum_total, co.counterparty_id, co.organization_id,
                    cp.id_ms AS agent_ms, org.id_ms AS organization_ms
             FROM customerorder co
             LEFT JOIN counterparty cp  ON cp.id  = co.counterparty_id
             LEFT JOIN organization  org ON org.id = co.organization_id
             WHERE co.id = {$orderId} LIMIT 1");
        if (!$rOrder['ok'] || empty($rOrder['row'])) {
            return array(false, 'Order #' . $orderId . ' not found');
        }
        $order = $rOrder['row'];

        // 3. Сума: з ТТН у пріоритеті
        $sum = (float)$rTtn['row']['afterpayment_on_goods_cost'];
        if ($sum <= 0) $sum = (float)$rTtn['row']['cost_on_site'];
        if ($sum <= 0) $sum = (float)$order['sum_total'];
        if ($sum <= 0) return array(false, 'Cannot determine sum for cashin');
        $sum = round($sum, 2);

        // 4. Дедуп по doc_number
        $docNumberEsc = Database::escape('Papir', $docNumber);
        $rDup = Database::fetchRow('Papir',
            "SELECT id FROM finance_cash
             WHERE direction='in' AND doc_number='{$docNumberEsc}' LIMIT 1");
        if ($rDup['ok'] && !empty($rDup['row'])) {
            $existingId = (int)$rDup['row']['id'];
            // Переконатись що document_link існує
            $rLink = Database::fetchRow('Papir',
                "SELECT id FROM document_link
                 WHERE from_type='cashin' AND from_id={$existingId}
                   AND to_type='customerorder' AND to_id={$orderId} LIMIT 1");
            if (!$rLink['ok'] || empty($rLink['row'])) {
                Database::insert('Papir', 'document_link', array(
                    'from_type'  => 'cashin',
                    'from_id'    => $existingId,
                    'to_type'    => 'customerorder',
                    'to_id'      => $orderId,
                    'link_type'  => 'payment',
                    'linked_sum' => $sum,
                ));
            }
            return array(true, "Cashin #{$existingId} already exists (doc_number={$docNumber})");
        }

        // 5. INSERT finance_cash
        $description = 'Накладений платіж по ТТН ' . $docNumber
                     . ', замовлення №' . (!empty($order['number']) ? $order['number'] : $orderId);

        Database::begin('Papir');
        try {
            $rIns = Database::insert('Papir', 'finance_cash', array(
                'direction'       => 'in',
                'moment'          => date('Y-m-d H:i:s'),
                'doc_number'      => $docNumber,
                'sum'             => $sum,
                // Локальні FK — основа правди:
                'counterparty_id' => !empty($order['counterparty_id']) ? (int)$order['counterparty_id'] : null,
                'organization_id' => !empty($order['organization_id']) ? (int)$order['organization_id'] : null,
                // МС-маппінг — заповнюємо тільки якщо локальні сутності вже мають id_ms.
                // Якщо ще нема (cp/org свіжі) — це нормально, push в МС просто скіпнеться.
                'agent_ms'        => !empty($order['agent_ms'])        ? $order['agent_ms']        : null,
                'agent_ms_type'   => 'counterparty',
                'organization_ms' => !empty($order['organization_ms']) ? $order['organization_ms'] : null,
                'is_posted'       => 1,
                'is_moving'       => 0,
                'description'     => $description,
                'payment_purpose' => 'Накладений платіж, ТТН ' . $docNumber,
                'source'          => 'papir',
                'operations'      => $sum,
            ));
            if (!$rIns['ok'] || empty($rIns['insert_id'])) {
                throw new Exception('finance_cash insert failed');
            }
            $cashinId = (int)$rIns['insert_id'];

            Database::insert('Papir', 'document_link', array(
                'from_type'  => 'cashin',
                'from_id'    => $cashinId,
                'to_type'    => 'customerorder',
                'to_id'      => $orderId,
                'link_type'  => 'payment',
                'linked_sum' => $sum,
            ));

            Database::commit('Papir');
        } catch (Exception $e) {
            Database::rollback('Papir');
            // Defense in depth: якщо UNIQUE uk_finance_cash_papir_in зловив race
            // (claim runItem'ом не врятував — наприклад, два різні step_id з однієї
            // ТТН), переходимо в дедуп-гілку замість падіння.
            $rDup2 = Database::fetchRow('Papir',
                "SELECT id FROM finance_cash
                 WHERE source='papir' AND direction='in'
                   AND doc_number='{$docNumberEsc}' LIMIT 1");
            if ($rDup2['ok'] && !empty($rDup2['row'])) {
                $existingId = (int)$rDup2['row']['id'];
                $rLink2 = Database::fetchRow('Papir',
                    "SELECT id FROM document_link
                     WHERE from_type='cashin' AND from_id={$existingId}
                       AND to_type='customerorder' AND to_id={$orderId} LIMIT 1");
                if (!$rLink2['ok'] || empty($rLink2['row'])) {
                    Database::insert('Papir', 'document_link', array(
                        'from_type'  => 'cashin',
                        'from_id'    => $existingId,
                        'to_type'    => 'customerorder',
                        'to_id'      => $orderId,
                        'link_type'  => 'payment',
                        'linked_sum' => $sum,
                    ));
                }
                return array(true, "Cashin #{$existingId} already exists (doc_number={$docNumber}, recovered)");
            }
            return array(false, $e->getMessage());
        }

        // 6. Push у МС — тільки якщо модуль МС активний і finance.C.to дозволено.
        //    Якщо локальний cp/org ще не запушений у МС (cp.id_ms=NULL),
        //    push скіпнеться всередині finance_ms_cash_push (бракує agent.meta).
        //    Локальний finance_cash вже закоммічено — це достатньо.
        self::pushCashinToMs($cashinId, $order, $sum, $docNumber, $description);

        // 7. Перерахувати payment_status / sum_paid замовлення
        self::recalcOrderFinance($orderId);

        return array(true, "Cashin #{$cashinId} created (doc={$docNumber}, sum={$sum})");
    }

    private static function pushCashinToMs($cashinId, $order, $sum, $docNumber, $description)
    {
        try {
            require_once __DIR__ . '/../../integrations/AppRegistry.php';
            require_once __DIR__ . '/../../integrations/MsExchangeGuard.php';
            if (!\AppRegistry::isActive('moysklad')) return;
            if (!\MsExchangeGuard::isAllowed('finance_cashin', 'C', 'to')) return;

            require_once __DIR__ . '/../../finance/api/finance_ms_sync.php';
            finance_ms_cash_push($cashinId, array(
                'direction'       => 'in',
                'moment'          => date('Y-m-d H:i:s'),
                'sum'             => $sum,
                'doc_number'      => $docNumber,
                'description'     => $description,
                'payment_purpose' => 'Накладений платіж, ТТН ' . $docNumber,
                // Локальні FK — головні; UUID підтягне finance_ms_cash_push.
                'counterparty_id' => !empty($order['counterparty_id']) ? (int)$order['counterparty_id'] : 0,
                'organization_id' => !empty($order['organization_id']) ? (int)$order['organization_id'] : 0,
                'agent_ms'        => !empty($order['agent_ms'])        ? $order['agent_ms']        : '',
                'agent_ms_type'   => 'counterparty',
                'organization_ms' => !empty($order['organization_ms']) ? $order['organization_ms'] : '',
                'is_posted'       => 1,
            ), '');
        } catch (Exception $e) {
            // non-blocking: локальний ПКО вже закоммічено
        }
    }

    private static function recalcOrderFinance($orderId)
    {
        try {
            require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';
            \OrderFinanceHelper::recalc($orderId);
        } catch (Exception $e) {
            // non-blocking
        }
    }

    private static function doCreateTask($item, $params)
    {
        $cpId = (int)$item['counterparty_id'];
        if (!$cpId) return array(false, 'No counterparty_id');

        $title    = isset($params['task_title']) ? $params['task_title'] : 'Задача від сценарію';
        $taskType = isset($params['task_type'])  ? $params['task_type']  : 'other';
        $priority = isset($params['priority'])   ? (int)$params['priority'] : 3;
        $dueAt    = null;
        if (isset($params['due_hours'])) {
            $dueAt = date('Y-m-d H:i:s', time() + (int)$params['due_hours'] * 3600);
        }

        $newId = TaskRepository::create(array(
            'counterparty_id' => $cpId,
            'title'           => $title,
            'task_type'       => $taskType,
            'priority'        => $priority,
            'due_at'          => $dueAt,
        ));
        return array((bool)$newId, $newId ? "Task #{$newId} created" : 'Failed to create task');
    }

    private static function doSendInvoice($item, $params, $context)
    {
        // TODO: прикрутити реальну генерацію PDF рахунку (з печаткою)
        // і надсилати його як вкладення в email. Поки шлемо текстове
        // повідомлення з посиланням на клієнтський портал — там клієнт
        // бачить реквізити і може зв'язатися з менеджером.
        $text = isset($params['text']) && $params['text'] !== ''
            ? $params['text']
            : 'Ваш рахунок на оплату замовлення №{{order.number}}: {{portal.link}}';

        $msgParams = array(
            'mode'              => 'priority',
            'priority_channels' => isset($params['priority_channels'])
                ? $params['priority_channels']
                : MessageDispatchService::DEFAULT_PRIORITY,
            'also_email'        => isset($params['also_email']) ? $params['also_email'] : true,
            'text'              => $text,
        );
        return self::doSendMessage($item, $msgParams, $context);
    }

    private static function doChangeStatus($item, $params)
    {
        $orderId = (int)$item['order_id'];
        if (!$orderId) return array(false, 'No order_id');
        $newStatus = isset($params['status']) ? Database::escape('Papir', $params['status']) : '';
        if (!$newStatus) return array(false, 'No status in params');
        Database::query('Papir',
            "UPDATE customerorder
             SET status='{$newStatus}',
                 next_action=NULL,
                 next_action_label=NULL,
                 updated_at=NOW()
             WHERE id={$orderId}"
        );
        return array(true, "Status changed to {$newStatus}");
    }

    private static function doSetNextAction($item, $params)
    {
        $orderId = (int)$item['order_id'];
        if (!$orderId) return array(false, 'No order_id');

        $action = isset($params['action']) ? trim($params['action']) : '';
        $label  = isset($params['label'])  ? trim($params['label'])  : $action;

        if ($action === '' && $label === '') {
            // Очистити наступну дію
            Database::query('Papir',
                "UPDATE customerorder SET next_action=NULL, next_action_label=NULL, updated_at=NOW()
                 WHERE id={$orderId}");
            return array(true, 'Next action cleared');
        }

        $actionSql = Database::escape('Papir', $action);
        $labelSql  = Database::escape('Papir', $label);
        Database::query('Papir',
            "UPDATE customerorder SET next_action='{$actionSql}', next_action_label='{$labelSql}', updated_at=NOW()
             WHERE id={$orderId}");
        return array(true, "Next action set: {$label}");
    }

    // ── Next step scheduling ──────────────────────────────────────────────────

    private static function scheduleNextStep($doneItem, $context)
    {
        $currentStepId = (int)$doneItem['step_id'];
        $scenarioId    = (int)$doneItem['scenario_id'];
        $cpId          = (int)$doneItem['counterparty_id'];
        $orderId       = (int)$doneItem['order_id'];
        $triggerId     = (int)$doneItem['trigger_id'];

        $r = Database::fetchRow('Papir',
            "SELECT step_order FROM cp_scenario_steps WHERE id={$currentStepId}"
        );
        if (!$r['ok'] || !$r['row']) return;
        $currentOrder = (int)$r['row']['step_order'];

        // Reload fresh order context once (для оцінки умов наступних кроків).
        if ($orderId) {
            $or = Database::fetchRow('Papir',
                "SELECT * FROM customerorder WHERE id={$orderId}"
            );
            if ($or['ok'] && $or['row']) {
                $context['order'] = $or['row'];
            }
            $context['order_id'] = $orderId;
        }
        $ctxJson = Database::escape('Papir', json_encode($context, JSON_UNESCAPED_UNICODE));

        // Шукаємо наступний крок, умова якого виконана. Якщо умова крока N не
        // виконана — скіпаємо його і пробуємо N+1, щоб ланцюжок не обривався
        // через необов'язкові (умовні) кроки (напр. create_cashin тільки для
        // накладенки — для інших методів оплати пропускаємо і йдемо далі).
        while (true) {
            $r2 = Database::fetchRow('Papir',
                "SELECT * FROM cp_scenario_steps
                 WHERE scenario_id={$scenarioId} AND step_order > {$currentOrder}
                 ORDER BY step_order ASC LIMIT 1"
            );
            if (!$r2['ok'] || !$r2['row']) return; // кінець сценарію
            $nextStep = $r2['row'];

            $hasCondition = !empty($nextStep['condition_key']) || !empty($nextStep['conditions']);
            if ($hasCondition && !\TriggerEngine::evaluateCondition($nextStep, $context)) {
                // Умова не виконана — скіпаємо цей крок і шукаємо далі.
                $currentOrder = (int)$nextStep['step_order'];
                continue;
            }

            TriggerEngine::enqueueStep($nextStep, $scenarioId, $triggerId, $cpId, $orderId, $ctxJson, 0);
            return;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function resolveVars($text, $context)
    {
          // Replace {{order.number}}, {{order.sum_total}}, {{counterparty.name}},
        // {{portal.link}} etc.
        preg_match_all('/\{\{(\w+\.\w+)\}\}/', $text, $matches);
        foreach ($matches[1] as $key) {
            $parts = explode('.', $key, 2);
            $root  = $parts[0];
            $field = $parts[1];
            $val   = '';

            if ($root === 'portal') {
                $ordId = isset($context['order_id']) ? (int)$context['order_id'] : 0;
                if ($ordId) {
                    require_once __DIR__ . '/../../client_portal/ClientPortalService.php';
                    if ($field === 'link') {
                        $val = \ClientPortalService::getOrCreateUrl($ordId);
                    } elseif (in_array($field, array('invoice', 'requisites', 'delivery_note'), true)) {
                        $val = \ClientPortalService::getSubpageUrl($ordId, $field);
                    }
                }
            } elseif ($root === 'calendar') {
                require_once __DIR__ . '/../../calendar/Calendar.php';
                if ($field === 'next_business_day') {
                    $val = \Calendar::formatNextBusinessDay();
                } elseif ($field === 'today') {
                    $val = date('Y-m-d');
                }
            } elseif (isset($context[$root][$field])) {
                $val = (string)$context[$root][$field];
            } elseif ($root === 'counterparty') {
                $cpId = isset($context['counterparty_id']) ? (int)$context['counterparty_id'] : 0;
                if ($cpId) {
                    $safeField = preg_replace('/[^a-z0-9_]/i', '', $field);
                    $r = Database::fetchRow('Papir',
                        "SELECT `{$safeField}` FROM counterparty WHERE id={$cpId} LIMIT 1");
                    if ($r['ok'] && !empty($r['row']) && array_key_exists($safeField, $r['row'])) {
                        $val = (string)$r['row'][$safeField];
                    }
                }
            } elseif ($root === 'order' && isset($context['order_id'])) {
                $ordId     = (int)$context['order_id'];
                $safeField = preg_replace('/[^a-z0-9_]/i', '', $field);
                $r = Database::fetchRow('Papir',
                    "SELECT `{$safeField}` FROM customerorder WHERE id={$ordId} LIMIT 1");
                if ($r['ok'] && !empty($r['row']) && array_key_exists($safeField, $r['row'])) {
                    $val = (string)$r['row'][$safeField];
                }
            }

            $text = str_replace('{{' . $key . '}}', $val, $text);
        }
        return $text;
    }
}