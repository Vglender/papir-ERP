<?php
/**
 * DocumentHistory — универсальный хелпер записи и чтения истории изменений документов.
 *
 * Таблица: document_history
 *
 * Использование:
 *   // Ручное изменение пользователем
 *   DocumentHistory::log('customerorder', $id, 'update', array(
 *       'field_name'  => 'status',
 *       'field_label' => 'Статус',
 *       'old_value'   => 'new',
 *       'new_value'   => 'confirmed',
 *       'actor_type'  => 'user',
 *       'actor_id'    => $userId,
 *       'actor_label' => 'Вознюк Оля',
 *   ));
 *
 *   // Автоматическое действие (webhook, cron, ai)
 *   DocumentHistory::logAuto('customerorder', $id, 'status_change', array(
 *       'field_name'  => 'status',
 *       'field_label' => 'Статус',
 *       'old_value'   => 'confirmed',
 *       'new_value'   => 'shipped',
 *       'actor_type'  => 'webhook',   // 'cron' | 'api' | 'ai' | 'system'
 *       'actor_label' => 'МС webhook',
 *   ));
 *
 *   // Пагинированный список для API
 *   $result = DocumentHistory::getPage('customerorder', $id, $page, $perPage);
 *   // → array('rows' => [...], 'total' => N, 'pages' => M)
 *
 * actor_type значения:
 *   'user'    — реальный пользователь CRM (actor_id = auth_users.user_id)
 *   'webhook' — входящий вебхук (МойСклад, НП, банк...)
 *   'cron'    — фоновый cron-скрипт
 *   'api'     — внешний API-вызов
 *   'ai'      — AI-ассистент
 *   'system'  — внутренняя автоматика (миграции, пересчёты)
 */
require_once __DIR__ . '/StatusColors.php';

class DocumentHistory
{
    const DB = 'Papir';

    /**
     * Записать событие в историю.
     *
     * @param string $documentType  'customerorder' | 'demand' | 'supply' | ...
     * @param int    $documentId
     * @param string $action        'create'|'update'|'delete'|'status_change'|
     *                              'add_item'|'update_item'|'delete_item'
     * @param array  $params        actor_type, actor_id, actor_label,
     *                              field_name, field_label, item_id, item_label,
     *                              old_value, new_value, comment
     */
    public static function log($documentType, $documentId, $action, $params)
    {
        $actorType  = isset($params['actor_type'])  ? (string)$params['actor_type']  : 'system';
        $actorId    = isset($params['actor_id'])    ? (int)$params['actor_id']        : null;
        $actorLabel = isset($params['actor_label']) ? (string)$params['actor_label'] : '';

        $data = array(
            'document_type' => (string)$documentType,
            'document_id'   => (int)$documentId,
            'action'        => (string)$action,
            'field_name'    => isset($params['field_name'])  ? (string)$params['field_name']  : null,
            'field_label'   => isset($params['field_label']) ? (string)$params['field_label'] : null,
            'item_id'       => isset($params['item_id'])     ? (int)$params['item_id']        : null,
            'item_label'    => isset($params['item_label'])  ? (string)$params['item_label']  : null,
            'old_value'     => isset($params['old_value'])   ? (string)$params['old_value']   : null,
            'new_value'     => isset($params['new_value'])   ? (string)$params['new_value']   : null,
            'actor_type'    => $actorType,
            'actor_id'      => $actorId,
            'actor_label'   => $actorLabel,
            'comment'       => isset($params['comment'])     ? (string)$params['comment']     : null,
            'created_at'    => date('Y-m-d H:i:s'),
        );

        // item_id=0 → null
        if (isset($data['item_id']) && $data['item_id'] === 0) {
            $data['item_id'] = null;
        }
        // actor_id=0 → null
        if ($data['actor_id'] === 0) {
            $data['actor_id'] = null;
        }

        return Database::insert(self::DB, 'document_history', $data);
    }

    /**
     * Записать автоматическое событие (is_auto аналог).
     * actor_type по умолчанию 'system', actor_id всегда null.
     */
    public static function logAuto($documentType, $documentId, $action, $params)
    {
        if (!isset($params['actor_type'])) {
            $params['actor_type'] = 'system';
        }
        $params['actor_id'] = null;
        return self::log($documentType, $documentId, $action, $params);
    }

    /**
     * Пагинированный список истории для API.
     *
     * @return array  array('rows' => [...], 'total' => int, 'pages' => int)
     */
    public static function getPage($documentType, $documentId, $page, $perPage)
    {
        $documentType = Database::escape(self::DB, (string)$documentType);
        $documentId   = (int)$documentId;
        $perPage      = max(1, min(100, (int)$perPage));
        $page         = max(1, (int)$page);
        $offset       = ($page - 1) * $perPage;

        $countResult = Database::fetchRow(self::DB,
            "SELECT COUNT(*) AS cnt
             FROM document_history
             WHERE document_type = '{$documentType}' AND document_id = {$documentId}"
        );
        $total = ($countResult['ok'] && $countResult['row']) ? (int)$countResult['row']['cnt'] : 0;
        $pages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $result = Database::fetchAll(self::DB,
            "SELECT id, action, field_name, field_label, item_id, item_label,
                    old_value, new_value,
                    actor_type, actor_id, actor_label,
                    comment, created_at
             FROM document_history
             WHERE document_type = '{$documentType}' AND document_id = {$documentId}
             ORDER BY id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );

        $rows = ($result['ok'] && !empty($result['rows'])) ? $result['rows'] : array();

        // Форматируем для UI
        foreach ($rows as &$row) {
            $row['created_at_fmt'] = self::formatDatetime($row['created_at']);
            $row['action_label']   = self::actionLabel($row['action']);
            // Переводим статусы в читаемые лейблы
            if ($row['field_name'] === 'status') {
                if ($row['old_value'] !== null && $row['old_value'] !== '') {
                    $row['old_value'] = StatusColors::label($documentType, $row['old_value'], $row['old_value']);
                }
                if ($row['new_value'] !== null && $row['new_value'] !== '') {
                    $row['new_value'] = StatusColors::label($documentType, $row['new_value'], $row['new_value']);
                }
            }
        }
        unset($row);

        return array(
            'rows'  => $rows,
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        );
    }

    /**
     * Построить actor_label из объекта текущего пользователя (auth_users).
     * Используется в контроллерах где есть $currentUser.
     */
    public static function actorFromUser($user)
    {
        if (empty($user)) {
            return array('actor_type' => 'system', 'actor_id' => null, 'actor_label' => 'Система');
        }
        return array(
            'actor_type'  => 'user',
            'actor_id'    => isset($user['user_id']) ? (int)$user['user_id'] : null,
            'actor_label' => isset($user['display_name']) ? (string)$user['display_name'] : 'Користувач',
        );
    }

    /**
     * Поля позиции, подлежащие трекингу, по типу документа.
     * Ключ → человекочитаемый лейбл.
     * Расчётные поля (sum_row, vat_amount, discount_amount и т.д.) не включаем.
     */
    public static function getItemTrackableFields($documentType)
    {
        $map = array(
            'customerorder' => array(
                'quantity'         => 'Кількість',
                'price'            => 'Ціна',
                'discount_percent' => 'Знижка, %',
                'vat_rate'         => 'ПДВ, %',
                'comment'          => 'Коментар',
            ),
            'demand' => array(
                'quantity'         => 'Кількість',
                'price'            => 'Ціна',
                'discount_percent' => 'Знижка, %',
                'vat_rate'         => 'ПДВ, %',
                'overhead'         => 'Доп. витрати',
            ),
            'supply' => array(
                'quantity'         => 'Кількість',
                'price'            => 'Ціна',
            ),
        );
        return isset($map[$documentType]) ? $map[$documentType] : array(
            'quantity' => 'Кількість',
            'price'    => 'Ціна',
        );
    }

    /**
     * Строит читаемый лейбл позиции: "Назва товару" или "SKU: XXX" или "Позиція #N".
     */
    public static function buildItemLabel($item)
    {
        if (!empty($item['product_name'])) return $item['product_name'];
        if (!empty($item['sku']))          return 'SKU: ' . $item['sku'];
        if (!empty($item['line_no']))      return 'Позиція #' . $item['line_no'];
        if (!empty($item['id']))           return 'Позиція id=' . $item['id'];
        return 'Позиція';
    }

    /**
     * Форматирует значение поля позиции для отображения в истории.
     */
    public static function formatItemFieldValue($field, $value)
    {
        if ($value === null || $value === '') return null;
        // Числа: убираем лишние нули
        if (in_array($field, array('quantity', 'price', 'discount_percent', 'vat_rate', 'overhead'), true)) {
            $f = (float)$value;
            return rtrim(rtrim(number_format($f, 4, '.', ''), '0'), '.');
        }
        return (string)$value;
    }

    // ── Вспомогательные ────────────────────────────────────────────────────────

    private static function formatDatetime($dt)
    {
        if (!$dt) return '';
        $ts = strtotime($dt);
        return $ts ? date('d.m.Y H:i', $ts) : $dt;
    }

    private static function actionLabel($action)
    {
        $map = array(
            'create'       => 'Створення',
            'update'       => 'Зміна',
            'delete'       => 'Видалення',
            'status_change'=> 'Статус',
            'add_item'     => 'Додано позицію',
            'update_item'  => 'Змінено позицію',
            'delete_item'  => 'Видалено позицію',
            'refund_needed'    => 'Повернення',
            'return_registered'=> 'Повернення',
        );
        return isset($map[$action]) ? $map[$action] : $action;
    }
}