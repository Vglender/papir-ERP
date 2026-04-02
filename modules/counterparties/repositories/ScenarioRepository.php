<?php
/**
 * ScenarioRepository — CRUD для cp_scenarios, cp_scenario_steps, cp_triggers.
 */
class ScenarioRepository
{
    // ── Scenarios ─────────────────────────────────────────────────────────────

    public static function getAllScenarios()
    {
        $r = Database::fetchAll('Papir',
            "SELECT id, name, description, status, created_at FROM cp_scenarios ORDER BY name ASC"
        );
        return $r['ok'] ? $r['rows'] : array();
    }

    public static function getScenario($id)
    {
        $r = Database::fetchRow('Papir',
            "SELECT * FROM cp_scenarios WHERE id=" . (int)$id
        );
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function saveScenario($data)
    {
        $id   = isset($data['id']) ? (int)$data['id'] : 0;
        $name = Database::escape('Papir', trim($data['name']));
        $desc = Database::escape('Papir', isset($data['description']) ? trim($data['description']) : '');

        if ($id) {
            Database::query('Papir',
                "UPDATE cp_scenarios SET name='{$name}', description='{$desc}', updated_at=NOW() WHERE id={$id}"
            );
            return $id;
        }
        Database::query('Papir',
            "INSERT INTO cp_scenarios (name, description) VALUES ('{$name}', '{$desc}')"
        );
        $r = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS id");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['id'] : 0;
    }

    public static function deleteScenario($id)
    {
        $id = (int)$id;
        // Remove steps first
        Database::query('Papir', "DELETE FROM cp_scenario_steps WHERE scenario_id={$id}");
        Database::query('Papir', "DELETE FROM cp_scenarios WHERE id={$id}");
    }

    // ── Steps ─────────────────────────────────────────────────────────────────

    public static function getSteps($scenarioId)
    {
        $r = Database::fetchAll('Papir',
            "SELECT * FROM cp_scenario_steps WHERE scenario_id=" . (int)$scenarioId
            . " ORDER BY step_order ASC, id ASC"
        );
        return $r['ok'] ? $r['rows'] : array();
    }

    public static function saveSteps($scenarioId, $steps)
    {
        $scenarioId = (int)$scenarioId;
        Database::query('Papir', "DELETE FROM cp_scenario_steps WHERE scenario_id={$scenarioId}");

        foreach ($steps as $order => $s) {
            $executor   = in_array($s['executor'], array('robot','operator','ai')) ? $s['executor'] : 'robot';
            $actionType = Database::escape('Papir', $s['action_type']);
            $params     = Database::escape('Papir', isset($s['action_params']) ? $s['action_params'] : '');
            $delay      = max(0, (int)(isset($s['delay_minutes']) ? $s['delay_minutes'] : 0));
            $condKey    = Database::escape('Papir', isset($s['condition_key'])   ? $s['condition_key']   : '');
            $condOp     = Database::escape('Papir', isset($s['condition_op'])    ? $s['condition_op']    : '');
            $condVal    = Database::escape('Papir', isset($s['condition_value']) ? $s['condition_value'] : '');

            $condKeyQ = $condKey ? "'{$condKey}'" : 'NULL';
            $condOpQ  = $condOp  ? "'{$condOp}'"  : 'NULL';
            $condValQ = $condVal ? "'{$condVal}'"  : 'NULL';

            Database::query('Papir',
                "INSERT INTO cp_scenario_steps
                 (scenario_id, step_order, executor, action_type, action_params, delay_minutes,
                  condition_key, condition_op, condition_value)
                 VALUES ({$scenarioId}, {$order}, '{$executor}', '{$actionType}', '{$params}', {$delay},
                         {$condKeyQ}, {$condOpQ}, {$condValQ})"
            );
        }
    }

    // ── Triggers ──────────────────────────────────────────────────────────────

    public static function getAllTriggers()
    {
        $r = Database::fetchAll('Papir',
            "SELECT t.*, s.name AS scenario_name
             FROM cp_triggers t
             JOIN cp_scenarios s ON s.id = t.scenario_id
             ORDER BY t.event_type, t.name"
        );
        return $r['ok'] ? $r['rows'] : array();
    }

    public static function getTrigger($id)
    {
        $r = Database::fetchRow('Papir',
            "SELECT * FROM cp_triggers WHERE id=" . (int)$id
        );
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    public static function getActiveTriggersByEvent($eventType)
    {
        $et = Database::escape('Papir', $eventType);
        $r  = Database::fetchAll('Papir',
            "SELECT * FROM cp_triggers WHERE event_type='{$et}' AND status=1"
        );
        return $r['ok'] ? $r['rows'] : array();
    }

    public static function saveTrigger($data)
    {
        $id         = isset($data['id']) ? (int)$data['id'] : 0;
        $name       = Database::escape('Papir', trim($data['name']));
        $event      = Database::escape('Papir', $data['event_type']);
        $scenarioId = (int)$data['scenario_id'];
        $delay      = max(0, (int)(isset($data['delay_minutes']) ? $data['delay_minutes'] : 0));
        $condKey    = Database::escape('Papir', isset($data['condition_key'])   ? $data['condition_key']   : '');
        $condOp     = Database::escape('Papir', isset($data['condition_op'])    ? $data['condition_op']    : '');
        $condVal    = Database::escape('Papir', isset($data['condition_value']) ? $data['condition_value'] : '');
        $condKeyQ   = $condKey ? "'{$condKey}'" : 'NULL';
        $condOpQ    = $condOp  ? "'{$condOp}'"  : 'NULL';
        $condValQ   = $condVal ? "'{$condVal}'"  : 'NULL';
        $status     = isset($data['status']) ? (int)(bool)$data['status'] : 1;

        if ($id) {
            Database::query('Papir',
                "UPDATE cp_triggers SET name='{$name}', event_type='{$event}', scenario_id={$scenarioId},
                 delay_minutes={$delay}, condition_key={$condKeyQ}, condition_op={$condOpQ},
                 condition_value={$condValQ}, status={$status} WHERE id={$id}"
            );
            return $id;
        }
        Database::query('Papir',
            "INSERT INTO cp_triggers (name, event_type, scenario_id, delay_minutes,
             condition_key, condition_op, condition_value)
             VALUES ('{$name}', '{$event}', {$scenarioId}, {$delay},
                     {$condKeyQ}, {$condOpQ}, {$condValQ})"
        );
        $r = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS id");
        return ($r['ok'] && $r['row']) ? (int)$r['row']['id'] : 0;
    }

    public static function toggleTrigger($id, $status)
    {
        $status = (int)(bool)$status;
        Database::query('Papir', "UPDATE cp_triggers SET status={$status} WHERE id=" . (int)$id);
    }

    public static function deleteTrigger($id)
    {
        Database::query('Papir', "DELETE FROM cp_triggers WHERE id=" . (int)$id);
    }

    // ── Event type labels ─────────────────────────────────────────────────────

    public static function eventLabel($type)
    {
        $map = array(
            'order_created'        => 'Новий заказ',
            'order_status_changed' => 'Зміна статусу заказу',
            'order_cancelled'      => 'Скасування заказу',
            'task_done'            => 'Виконання задачі',
            'task_created'         => 'Створення задачі',
            'document_created'     => 'Новий документ',
        );
        return isset($map[$type]) ? $map[$type] : $type;
    }

    public static function actionLabel($type)
    {
        $map = array(
            'send_message'  => 'Надіслати повідомлення',
            'send_invoice'  => 'Надіслати рахунок',
            'create_task'   => 'Створити задачу оператору',
            'change_status' => 'Змінити статус заказу',
            'wait'          => 'Очікувати',
        );
        return isset($map[$type]) ? $map[$type] : $type;
    }

    public static function executorLabel($executor)
    {
        $map = array('robot' => '🤖 Робот', 'operator' => '👤 Оператор', 'ai' => '✨ AI');
        return isset($map[$executor]) ? $map[$executor] : $executor;
    }
}