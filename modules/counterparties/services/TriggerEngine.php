<?php
/**
 * TriggerEngine — оценивает условия триггеров и ставит задачи в очередь.
 *
 * Вызывается из точек событий (смена статуса заказа, создание заказа, и т.п.).
 *
 * Пример вызова:
 *   TriggerEngine::fire('order_created', array(
 *     'order'          => $orderRow,
 *     'counterparty_id'=> $cpId,
 *     'order_id'       => $orderId,
 *   ));
 */
class TriggerEngine
{
    /**
     * Основная точка входа. $context — массив данных события.
     * Обязательные ключи: counterparty_id, order_id (если есть).
     */
    public static function fire($eventType, $context)
    {
        $triggers = ScenarioRepository::getActiveTriggersByEvent($eventType);
        if (empty($triggers)) return;

        foreach ($triggers as $trigger) {
            if (!self::evaluateCondition($trigger, $context)) continue;

            // Записываем первый шаг сценария в очередь
            self::scheduleScenario(
                $trigger,
                $context,
                (int)$trigger['delay_minutes']
            );

            // Обновляем статистику триггера
            Database::query('Papir',
                "UPDATE cp_triggers SET fired_count=fired_count+1, last_fired_at=NOW()
                 WHERE id=" . (int)$trigger['id']
            );
        }
    }

    // ── Condition evaluator ───────────────────────────────────────────────────

    /**
     * Простая оценка условия: condition_key, condition_op, condition_value.
     * Ключи вида: "order.payment_type", "order.status", "order.sum_total".
     * Если условия нет — триггер всегда срабатывает.
     */
    private static function evaluateCondition($trigger, $context)
    {
        $key = isset($trigger['condition_key']) ? $trigger['condition_key'] : null;
        if (!$key) return true;

        $actual = self::resolveKey($key, $context);
        if ($actual === null) return false;

        $op  = isset($trigger['condition_op'])    ? $trigger['condition_op']    : '=';
        $val = isset($trigger['condition_value']) ? $trigger['condition_value'] : '';

        return self::compare($actual, $op, $val);
    }

    private static function resolveKey($key, $context)
    {
        $parts = explode('.', $key, 2);
        $root  = isset($parts[0]) ? $parts[0] : '';
        $field = isset($parts[1]) ? $parts[1] : '';

        if ($root === 'order' && isset($context['order'][$field])) {
            return $context['order'][$field];
        }
        if ($root === 'task' && isset($context['task'][$field])) {
            return $context['task'][$field];
        }
        if (isset($context[$key])) {
            return $context[$key];
        }
        return null;
    }

    private static function compare($actual, $op, $expected)
    {
        $a = strtolower((string)$actual);
        $e = strtolower((string)$expected);

        switch ($op) {
            case '=':      return $a === $e;
            case '!=':     return $a !== $e;
            case '>':      return (float)$actual >  (float)$expected;
            case '<':      return (float)$actual <  (float)$expected;
            case 'in':
                $vals = array_map('trim', explode(',', $e));
                return in_array($a, $vals);
            case 'not_in':
                $vals = array_map('trim', explode(',', $e));
                return !in_array($a, $vals);
        }
        return false;
    }

    // ── Queue scheduling ──────────────────────────────────────────────────────

    /**
     * Берёт первый шаг сценария и ставит в очередь.
     * Последующие шаги будут поставлены TaskQueueRunner после выполнения предыдущего.
     */
    private static function scheduleScenario($trigger, $context, $extraDelayMin)
    {
        $scenarioId = (int)$trigger['scenario_id'];
        $steps      = ScenarioRepository::getSteps($scenarioId);
        if (empty($steps)) return;

        $cpId    = isset($context['counterparty_id']) ? (int)$context['counterparty_id'] : 0;
        $orderId = isset($context['order_id'])        ? (int)$context['order_id']        : 0;
        $ctxJson = Database::escape('Papir', json_encode($context, JSON_UNESCAPED_UNICODE));

        // Schedule first step
        $step = $steps[0];
        self::enqueueStep($step, $scenarioId, (int)$trigger['id'], $cpId, $orderId, $ctxJson, $extraDelayMin);
    }

    public static function enqueueStep($step, $scenarioId, $triggerId, $cpId, $orderId, $ctxJson, $extraDelayMin)
    {
        $stepId     = (int)$step['id'];
        $executor   = Database::escape('Papir', $step['executor']);
        $actionType = Database::escape('Papir', $step['action_type']);
        $params     = Database::escape('Papir', isset($step['action_params']) ? $step['action_params'] : '');
        $totalDelay = max(0, (int)$step['delay_minutes'] + (int)$extraDelayMin);
        $fireAt     = Database::escape('Papir',
            date('Y-m-d H:i:s', time() + $totalDelay * 60)
        );
        $orderSql = $orderId ? $orderId : 'NULL';
        $cpSql    = $cpId    ? $cpId    : 'NULL';

        Database::query('Papir',
            "INSERT INTO cp_task_queue
             (trigger_id, scenario_id, step_id, counterparty_id, order_id, context,
              executor, action_type, action_params, fire_at)
             VALUES
             ({$triggerId}, {$scenarioId}, {$stepId}, {$cpSql}, {$orderSql}, '{$ctxJson}',
              '{$executor}', '{$actionType}', '{$params}', '{$fireAt}')"
        );
    }
}