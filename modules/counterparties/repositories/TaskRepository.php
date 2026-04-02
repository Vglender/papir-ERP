<?php
/**
 * TaskRepository — CRUD for cp_tasks + denormalized stats on counterparty.
 *
 * Priority scale: 1=low  2=normal  3=high  4=urgent  5=critical
 * Decay model: priority * 20 (base) + bonus from proximity to due_at
 *              → computed client-side in renderInbox; server stores raw priority + next_task_due_at
 */
class TaskRepository
{
    // ── Read ─────────────────────────────────────────────────────────────────

    /**
     * Tasks for counterparty (open + unsnoozed first, then snoozed, then done limit 20).
     */
    public static function getForCounterparty($cpId)
    {
        $cpId = (int)$cpId;
        $r = Database::fetchAll('Papir',
            "SELECT id, counterparty_id, lead_id, title, task_type, priority, due_at,
                    status, snoozed_until, created_at, done_at
             FROM cp_tasks
             WHERE counterparty_id = {$cpId}
               AND (status != 'done' OR done_at >= DATE_SUB(NOW(), INTERVAL 3 DAY))
             ORDER BY
               CASE status WHEN 'open' THEN 0 WHEN 'snoozed' THEN 1 ELSE 2 END,
               CASE WHEN due_at IS NULL THEN 1 ELSE 0 END,
               due_at ASC,
               priority DESC,
               id ASC
             LIMIT 50"
        );
        return ($r['ok']) ? $r['rows'] : array();
    }

    /**
     * Tasks for lead.
     */
    public static function getForLead($leadId)
    {
        $leadId = (int)$leadId;
        $r = Database::fetchAll('Papir',
            "SELECT id, counterparty_id, lead_id, title, task_type, priority, due_at,
                    status, snoozed_until, created_at, done_at
             FROM cp_tasks
             WHERE lead_id = {$leadId}
               AND (status != 'done' OR done_at >= DATE_SUB(NOW(), INTERVAL 3 DAY))
             ORDER BY
               CASE status WHEN 'open' THEN 0 WHEN 'snoozed' THEN 1 ELSE 2 END,
               CASE WHEN due_at IS NULL THEN 1 ELSE 0 END,
               due_at ASC,
               priority DESC,
               id ASC
             LIMIT 50"
        );
        return ($r['ok']) ? $r['rows'] : array();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public static function create($data)
    {
        $cpId   = isset($data['counterparty_id']) ? (int)$data['counterparty_id'] : 0;
        $leadId = isset($data['lead_id'])          ? (int)$data['lead_id']         : null;

        $title    = Database::escape('Papir', trim($data['title']));
        $type     = Database::escape('Papir', $data['task_type'] ?: 'other');
        $priority = max(1, min(5, (int)($data['priority'] ?: 3)));

        $dueAt = null;
        if (!empty($data['due_at'])) {
            $ts = strtotime($data['due_at']);
            if ($ts) $dueAt = date('Y-m-d H:i:s', $ts);
        }

        $leadSql = $leadId ? $leadId : 'NULL';
        $dueSql  = $dueAt  ? "'" . Database::escape('Papir', $dueAt) . "'" : 'NULL';

        $r = Database::query('Papir',
            "INSERT INTO cp_tasks (counterparty_id, lead_id, title, task_type, priority, due_at)
             VALUES ({$cpId}, {$leadSql}, '{$title}', '{$type}', {$priority}, {$dueSql})"
        );
        if (!$r['ok']) return 0;

        $id = Database::fetchRow('Papir', "SELECT LAST_INSERT_ID() AS id");
        $newId = ($id['ok'] && $id['row']) ? (int)$id['row']['id'] : 0;

        if ($cpId) self::updateStats($cpId);

        return $newId;
    }

    public static function markDone($taskId)
    {
        $taskId = (int)$taskId;
        $r = Database::query('Papir',
            "UPDATE cp_tasks SET status='done', done_at=NOW()
             WHERE id={$taskId} AND status != 'done'"
        );
        if ($r['ok'] && $r['affected_rows'] > 0) {
            $cpId = self::getCpId($taskId);
            if ($cpId) self::updateStats($cpId);
            return true;
        }
        return false;
    }

    public static function snooze($taskId, $minutes)
    {
        $taskId  = (int)$taskId;
        $minutes = max(1, (int)$minutes);
        $r = Database::query('Papir',
            "UPDATE cp_tasks
             SET status='snoozed', snoozed_until=DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE)
             WHERE id={$taskId} AND status='open'"
        );
        if ($r['ok'] && $r['affected_rows'] > 0) {
            $cpId = self::getCpId($taskId);
            if ($cpId) self::updateStats($cpId);
            return true;
        }
        return false;
    }

    public static function wakeSnooze($taskId)
    {
        $taskId = (int)$taskId;
        $r = Database::query('Papir',
            "UPDATE cp_tasks SET status='open', snoozed_until=NULL
             WHERE id={$taskId} AND status='snoozed'"
        );
        if ($r['ok'] && $r['affected_rows'] > 0) {
            $cpId = self::getCpId($taskId);
            if ($cpId) self::updateStats($cpId);
            return true;
        }
        return false;
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * Recompute open_task_count, next_task_due_at, next_task_priority on counterparty.
     * "Next task" = highest priority open (unsnoozed) task; ties broken by soonest due_at.
     */
    public static function updateStats($cpId)
    {
        $cpId = (int)$cpId;

        $r = Database::fetchAll('Papir',
            "SELECT COUNT(*) AS cnt,
                    MAX(priority) AS max_pri
             FROM cp_tasks
             WHERE counterparty_id = {$cpId}
               AND status = 'open'
               AND (snoozed_until IS NULL OR snoozed_until <= NOW())"
        );
        if (!$r['ok']) return;

        $count   = (int)(isset($r['rows'][0]['cnt'])     ? $r['rows'][0]['cnt']     : 0);
        $maxPri  = (int)(isset($r['rows'][0]['max_pri']) ? $r['rows'][0]['max_pri'] : 0);

        // Most urgent: highest priority first, then soonest due (NULLs last)
        $nextRow = Database::fetchRow('Papir',
            "SELECT priority, due_at
             FROM cp_tasks
             WHERE counterparty_id = {$cpId}
               AND status = 'open'
               AND (snoozed_until IS NULL OR snoozed_until <= NOW())
             ORDER BY priority DESC,
                      CASE WHEN due_at IS NULL THEN 1 ELSE 0 END,
                      due_at ASC
             LIMIT 1"
        );

        $dueAt   = null;
        $nextPri = 0;
        if ($nextRow['ok'] && $nextRow['row']) {
            $nextPri = (int)$nextRow['row']['priority'];
            $dueAt   = $nextRow['row']['due_at'];
        }

        $dueSql = $dueAt ? "'" . Database::escape('Papir', $dueAt) . "'" : 'NULL';

        Database::query('Papir',
            "UPDATE counterparty
             SET open_task_count={$count},
                 next_task_due_at={$dueSql},
                 next_task_priority={$nextPri}
             WHERE id={$cpId}"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function getCpId($taskId)
    {
        $r = Database::fetchRow('Papir',
            "SELECT counterparty_id FROM cp_tasks WHERE id=" . (int)$taskId
        );
        return ($r['ok'] && $r['row']) ? (int)$r['row']['counterparty_id'] : 0;
    }

    public static function typeLabel($type)
    {
        $map = array(
            'call_back'  => 'Передзвонити',
            'follow_up'  => 'Нагадати',
            'send_docs'  => 'Надіслати документи',
            'payment'    => 'Платіж',
            'meeting'    => 'Зустріч',
            'other'      => 'Інше',
        );
        return isset($map[$type]) ? $map[$type] : 'Інше';
    }

    public static function typeIcon($type)
    {
        $map = array(
            'call_back'  => '📞',
            'follow_up'  => '💬',
            'send_docs'  => '📄',
            'payment'    => '💰',
            'meeting'    => '📅',
            'other'      => '✔',
        );
        return isset($map[$type]) ? $map[$type] : '✔';
    }
}
