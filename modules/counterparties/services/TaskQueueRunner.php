<?php
/**
 * TaskQueueRunner — выполняет задачи из cp_task_queue.
 * Запускается кроном каждые 5 минут.
 *
 * Поддерживаемые action_type:
 *   robot:    send_message, send_invoice (stub), change_status (stub), wait
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

        // Fetch pending queue items that are due
        $r = Database::fetchAll('Papir',
            "SELECT * FROM cp_task_queue
             WHERE status='pending' AND fire_at <= NOW()
             ORDER BY fire_at ASC
             LIMIT " . self::BATCH
        );
        if (!$r['ok'] || empty($r['rows'])) return 0;

        $processed = 0;
        foreach ($r['rows'] as $item) {
            self::runItem($item);
            $processed++;
        }
        return $processed;
    }

    private static function runItem($item)
    {
        $id = (int)$item['id'];

        // Mark running
        Database::query('Papir',
            "UPDATE cp_task_queue SET status='running', started_at=NOW() WHERE id={$id}"
        );

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

            switch ($item['action_type']) {
                case 'send_message':
                    list($ok, $note) = self::doSendMessage($item, $params, $context);
                    break;
                case 'create_task':
                    list($ok, $note) = self::doCreateTask($item, $params);
                    break;
                case 'send_invoice':
                    list($ok, $note) = self::doSendInvoiceStub($item, $params);
                    break;
                case 'change_status':
                    list($ok, $note) = self::doChangeStatus($item, $params);
                    break;
                case 'create_demand':
                    list($ok, $note) = self::doCreateDemand($item, $params);
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
        $cpId = (int)$item['counterparty_id'];
        if (!$cpId) return array(false, 'No counterparty_id');

        $text = isset($params['text']) ? $params['text'] : '';
        $text = self::resolveVars($text, $context);
        if (!$text) return array(false, 'No message text');

        // Priority mode (new) — используем MessageDispatchService
        if (isset($params['mode']) && $params['mode'] === 'priority') {
            $priority = isset($params['priority_channels']) && is_array($params['priority_channels'])
                ? $params['priority_channels']
                : MessageDispatchService::DEFAULT_PRIORITY;
            $res = MessageDispatchService::send($cpId, $text, $priority);
            $note = $res['ok']
                ? 'Sent via ' . $res['channel']
                : 'Failed: ' . $res['error'] . ' (tried: ' . implode(',', array_column($res['tried'], 'channel')) . ')';
            return array($res['ok'], $note);
        }

        // Legacy mode — список каналів з чекбоксів
        $channels = isset($params['channels']) ? (array)$params['channels'] : array('viber');
        $res = MessageDispatchService::send($cpId, $text, $channels);
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

    private static function doSendInvoiceStub($item, $params)
    {
        // Stub: create operator task to send invoice manually
        return self::doCreateTask($item, array(
            'task_title' => 'Надіслати рахунок клієнту (авто-задача)',
            'task_type'  => 'send_docs',
            'priority'   => 4,
            'due_hours'  => 2,
        ));
    }

    private static function doChangeStatus($item, $params)
    {
        $orderId = (int)$item['order_id'];
        if (!$orderId) return array(false, 'No order_id');
        $newStatus = isset($params['status']) ? Database::escape('Papir', $params['status']) : '';
        if (!$newStatus) return array(false, 'No status in params');
        Database::query('Papir',
            "UPDATE customerorder SET status='{$newStatus}', updated_at=NOW() WHERE id={$orderId}"
        );
        return array(true, "Status changed to {$newStatus}");
    }

    // ── Next step scheduling ──────────────────────────────────────────────────

    private static function scheduleNextStep($doneItem, $context)
    {
        $currentStepId = (int)$doneItem['step_id'];
        $scenarioId    = (int)$doneItem['scenario_id'];

        $r = Database::fetchRow('Papir',
            "SELECT step_order FROM cp_scenario_steps WHERE id={$currentStepId}"
        );
        if (!$r['ok'] || !$r['row']) return;
        $currentOrder = (int)$r['row']['step_order'];

        // Find next step
        $r2 = Database::fetchRow('Papir',
            "SELECT * FROM cp_scenario_steps
             WHERE scenario_id={$scenarioId} AND step_order > {$currentOrder}
             ORDER BY step_order ASC LIMIT 1"
        );
        if (!$r2['ok'] || !$r2['row']) return;

        $nextStep   = $r2['row'];
        $cpId       = (int)$doneItem['counterparty_id'];
        $orderId    = (int)$doneItem['order_id'];
        $triggerId  = (int)$doneItem['trigger_id'];
        $ctxJson    = Database::escape('Papir', json_encode($context, JSON_UNESCAPED_UNICODE));

        // Check next step condition before scheduling
        if (!empty($nextStep['condition_key'])) {
            $mockTrigger = array(
                'condition_key'   => $nextStep['condition_key'],
                'condition_op'    => $nextStep['condition_op'],
                'condition_value' => $nextStep['condition_value'],
            );
            // Reload fresh order context for condition check
            if ($orderId) {
                $or = Database::fetchRow('Papir',
                    "SELECT * FROM customerorder WHERE id={$orderId}"
                );
                if ($or['ok'] && $or['row']) {
                    $context['order'] = $or['row'];
                }
            }
        }

        TriggerEngine::enqueueStep($nextStep, $scenarioId, $triggerId, $cpId, $orderId, $ctxJson, 0);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function resolveVars($text, $context)
    {
        // Replace {{order.number}}, {{order.sum_total}}, {{counterparty.name}} etc.
        preg_match_all('/\{\{(\w+\.\w+)\}\}/', $text, $matches);
        foreach ($matches[1] as $key) {
            $parts = explode('.', $key, 2);
            $root  = $parts[0];
            $field = $parts[1];
            $val   = '';

            if (isset($context[$root][$field])) {
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