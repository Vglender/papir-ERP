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
     * Оценивает условия триггера/шага.
     * Приоритет: JSON `conditions` (массив с logic AND/OR) > простое condition_key/op/value.
     * Если условий нет — всегда true.
     */
    public static function evaluateCondition($trigger, $context)
    {
        // JSON multi-conditions (новый формат)
        $condJson = isset($trigger['conditions']) ? trim((string)$trigger['conditions']) : '';
        if ($condJson !== '' && $condJson !== 'null') {
            $cond = json_decode($condJson, true);
            if (is_array($cond) && !empty($cond['rules'])) {
                return self::evaluateJsonConditions($cond, $context);
            }
        }

        // Legacy: одно условие
        $key = isset($trigger['condition_key']) ? $trigger['condition_key'] : null;
        if (!$key) return true;

        $actual = self::resolveKey($key, $context);
        if ($actual === null) return false;

        $op  = isset($trigger['condition_op'])    ? $trigger['condition_op']    : '=';
        $val = isset($trigger['condition_value']) ? $trigger['condition_value'] : '';

        return self::compare($actual, $op, $val);
    }

    /**
     * Оценивает JSON-условия формата:
     * {"logic":"AND","rules":[{"key":"order.payment_method_id","op":"in","value":[1,3]},…]}
     */
    private static function evaluateJsonConditions($cond, $context)
    {
        $logic = (isset($cond['logic']) && strtoupper($cond['logic']) === 'OR') ? 'OR' : 'AND';
        $rules = $cond['rules'];

        foreach ($rules as $rule) {
            $key = isset($rule['key']) ? $rule['key'] : '';
            if (!$key) continue;

            $actual   = self::resolveKey($key, $context);
            $op       = isset($rule['op'])    ? $rule['op']    : '=';
            $expected = isset($rule['value']) ? $rule['value'] : '';

            // Для in/not_in expected может быть массивом или строкой
            $result = self::compare($actual, $op, $expected);

            if ($logic === 'OR'  && $result)  return true;
            if ($logic === 'AND' && !$result) return false;
        }

        return $logic === 'AND'; // AND: все прошли → true; OR: ни одного → false
    }

    private static function resolveKey($key, $context)
    {
        // ── Віртуальні поля (обчислювані через БД) ───────────────────────────

        // order.all_items_in_stock → '1' якщо всі позиції є в достатній кількості
        if ($key === 'order.all_items_in_stock') {
            $ordId = isset($context['order_id']) ? (int)$context['order_id'] : 0;
            if (!$ordId) return '0';
            $r = Database::fetchRow('Papir',
                "SELECT COUNT(*) AS cnt
                 FROM customerorder_item ci
                 LEFT JOIN product_papir pp ON pp.product_id = ci.product_id
                 WHERE ci.customerorder_id = {$ordId}
                   AND (pp.product_id IS NULL OR pp.quantity < ci.quantity)");
            return ($r['ok'] && $r['row'] && (int)$r['row']['cnt'] === 0) ? '1' : '0';
        }

        // order.has_shipment_tracking → '1' якщо є активна ТТН (НП/УП) або доставка (sent/delivered)
        if ($key === 'order.has_shipment_tracking') {
            $ordId = isset($context['order_id']) ? (int)$context['order_id'] : 0;
            if (!$ordId) return '0';
            $cnt = 0;
            // ТТН Нова Пошта
            $r = Database::fetchRow('Papir',
                "SELECT COUNT(*) AS cnt FROM document_link dl
                 JOIN ttn_novaposhta tn ON tn.id = dl.from_id
                 WHERE dl.from_type='ttn_np' AND dl.to_type='customerorder' AND dl.to_id={$ordId}
                   AND (tn.deletion_mark IS NULL OR tn.deletion_mark = 0)
                   AND tn.state_define NOT IN (102, 105)
                   AND LOWER(tn.state_name) NOT LIKE '%відмов%'
                   AND LOWER(tn.state_name) NOT LIKE '%отказ%'");
            if ($r['ok'] && !empty($r['row'])) $cnt += (int)$r['row']['cnt'];
            // ТТН Укрпошта
            $r = Database::fetchRow('Papir',
                "SELECT COUNT(*) AS cnt FROM document_link dl
                 JOIN ttn_ukrposhta tu ON tu.id = dl.from_id
                 WHERE dl.from_type='ttn_up' AND dl.to_type='customerorder' AND dl.to_id={$ordId}
                   AND tu.lifecycle_status NOT IN ('RETURNED','RETURNING','CANCELLED','DELETED')");
            if ($r['ok'] && !empty($r['row'])) $cnt += (int)$r['row']['cnt'];
            // order_delivery (кур'єр/самовивіз)
            $r = Database::fetchRow('Papir',
                "SELECT COUNT(*) AS cnt FROM order_delivery od
                 WHERE od.customerorder_id={$ordId} AND od.status IN ('sent','delivered')");
            if ($r['ok'] && !empty($r['row'])) $cnt += (int)$r['row']['cnt'];
            return $cnt > 0 ? '1' : '0';
        }

        // order.is_paid → '1' якщо замовлення фактично оплачене:
        //   (a) order.payment_status = 'paid' (єдина точка істини, ставиться через
        //       OrderFinanceHelper::recalc з толерантністю max(5 грн, 0.5%)), АБО
        //   (b) є успішний LiqPay receipt (race-fallback: receipt прийшов, але recalc
        //       ще не пробігся — потрібно щоб сценарії стартували одразу).
        //
        // ВАЖЛИВО: НЕ повертаємо '1' просто за фактом document_link.linked_sum > 0.
        // Це раніше робило 1 копійку оплати достатньою для закриття замовлення,
        // що неправильно для часткових оплат і призводить до завчасного `completed`.
        if ($key === 'order.is_paid') {
            $ordId = isset($context['order_id']) ? (int)$context['order_id'] : 0;
            if (!$ordId) return '0';

            $r = Database::fetchRow('Papir',
                "SELECT payment_status FROM customerorder WHERE id={$ordId} LIMIT 1");
            if ($r['ok'] && !empty($r['row']) && $r['row']['payment_status'] === 'paid') return '1';

            // Race-fallback для LiqPay: receipt вже є, але recalc ще не пройшов
            $r = Database::fetchRow('Papir',
                "SELECT 1 AS ok FROM order_payment_receipt
                 WHERE customerorder_id={$ordId} AND provider='liqpay' AND status='success'
                 LIMIT 1");
            return ($r['ok'] && !empty($r['row'])) ? '1' : '0';
        }

        // order.has_demand → '1' якщо є активне відвантаження
        if ($key === 'order.has_demand') {
            $ordId = isset($context['order_id']) ? (int)$context['order_id'] : 0;
            if (!$ordId) return '0';
            $r = Database::fetchRow('Papir',
                "SELECT COUNT(*) AS cnt
                 FROM document_link dl
                 JOIN demand d ON (d.id_ms = dl.from_ms_id OR (dl.from_ms_id IS NULL AND d.id = dl.from_id))
                 WHERE dl.from_type = 'demand' AND dl.to_type = 'customerorder' AND dl.to_id = {$ordId}
                   AND d.deleted_at IS NULL AND d.status NOT IN ('cancelled','returned')");
            return ($r['ok'] && !empty($r['row']) && (int)$r['row']['cnt'] > 0) ? '1' : '0';
        }

        // ── Звичайні поля контексту ───────────────────────────────────────────

        $parts = explode('.', $key, 2);
        $root  = isset($parts[0]) ? $parts[0] : '';
        $field = isset($parts[1]) ? $parts[1] : '';

        if ($root === 'order') {
            // Спочатку шукаємо в масиві order, якщо немає — підвантажуємо з БД
            if (isset($context['order'][$field])) {
                return $context['order'][$field];
            }
            // Fallback: підвантажити поле напряму з БД
            $ordId = isset($context['order_id']) ? (int)$context['order_id'] : 0;
            if ($ordId && $field) {
                $safeField = preg_replace('/[^a-z0-9_]/i', '', $field);
                $r = Database::fetchRow('Papir',
                    "SELECT `{$safeField}` FROM customerorder WHERE id={$ordId} LIMIT 1");
                if ($r['ok'] && $r['row'] && array_key_exists($safeField, $r['row'])) {
                    return $r['row'][$safeField];
                }
            }
            return null;
        }
        if ($root === 'ttn' && isset($context['ttn'][$field])) {
            return $context['ttn'][$field];
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

        switch ($op) {
            case '=':   return $a === strtolower((string)$expected);
            case '!=':  return $a !== strtolower((string)$expected);
            case '>':   return (float)$actual >  (float)$expected;
            case '<':   return (float)$actual <  (float)$expected;
            case '>=':  return (float)$actual >= (float)$expected;
            case '<=':  return (float)$actual <= (float)$expected;
            case 'contains':
                return mb_strpos(
                    mb_strtolower((string)$actual, 'UTF-8'),
                    mb_strtolower((string)$expected, 'UTF-8'),
                    0, 'UTF-8'
                ) !== false;
            case 'in':
                // expected может быть PHP-массивом (из JSON) или строкой "a,b,c"
                if (is_array($expected)) {
                    $vals = array_map(function($v) { return strtolower((string)$v); }, $expected);
                } else {
                    $vals = array_map('trim', explode(',', strtolower((string)$expected)));
                }
                return in_array($a, $vals);
            case 'not_in':
                if (is_array($expected)) {
                    $vals = array_map(function($v) { return strtolower((string)$v); }, $expected);
                } else {
                    $vals = array_map('trim', explode(',', strtolower((string)$expected)));
                }
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