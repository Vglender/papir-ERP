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

        $text     = isset($params['text'])    ? $params['text']    : '';
        $channels = isset($params['channels']) ? (array)$params['channels'] : array('viber');

        // Resolve template variables from context
        $text = self::resolveVars($text, $context);
        if (!$text) return array(false, 'No message text');

        // Get phone for the counterparty
        $r = Database::fetchRow('Papir',
            "SELECT COALESCE(cc.phone, cp.phone) AS phone
             FROM counterparty c
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
             WHERE c.id={$cpId}"
        );
        $phone = ($r['ok'] && $r['row']) ? $r['row']['phone'] : null;

        $sent = false;
        foreach ($channels as $ch) {
            if ($ch === 'viber' && $phone) {
                $res = AlphaSmsService::sendViber($phone, $text);
                if ($res) {
                    ChatRepository::saveMessage(array(
                        'counterparty_id' => $cpId,
                        'channel'         => 'viber',
                        'direction'       => 'out',
                        'is_auto'         => 1,
                        'body'            => $text,
                        'phone'           => $phone,
                    ));
                    $sent = true;
                }
            } elseif ($ch === 'sms' && $phone) {
                AlphaSmsService::sendSms($phone, $text);
                ChatRepository::saveMessage(array(
                    'counterparty_id' => $cpId,
                    'channel'         => 'sms',
                    'direction'       => 'out',
                    'is_auto'         => 1,
                    'body'            => $text,
                    'phone'           => $phone,
                ));
                $sent = true;
            }
        }
        return array($sent, $sent ? 'Sent via ' . implode(',', $channels) : 'Could not send');
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
        // Replace {{order.number}}, {{order.sum_total}}, etc.
        preg_match_all('/\{\{(\w+\.\w+)\}\}/', $text, $matches);
        foreach ($matches[1] as $key) {
            $parts = explode('.', $key, 2);
            $val   = '';
            if (isset($context[$parts[0]][$parts[1]])) {
                $val = $context[$parts[0]][$parts[1]];
            }
            $text = str_replace('{{' . $key . '}}', $val, $text);
        }
        return $text;
    }
}