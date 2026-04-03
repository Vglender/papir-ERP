<?php
/**
 * StatusColors — єдине джерело правди для кольорів і лейблів статусів документів.
 *
 * Використання в PHP (HTML-бейдж):
 *   $cls = StatusColors::badge('customerorder', $status);   // 'badge-indigo'
 *   $lbl = StatusColors::label('customerorder', $status);   // 'Підтверджено'
 *
 * Використання в PHP (SVG/JS hex-колір):
 *   $hex = StatusColors::hex('customerorder', $status);     // '#6366f1'
 *
 * Використання в JS (всі hex-кольори одного типу):
 *   <script>var STATUS_HEX = <?= StatusColors::jsonHex('customerorder') ?>;</script>
 *   <script>var STATUS_LBL = <?= StatusColors::jsonLabels('customerorder') ?>;</script>
 *
 * Формат запису в $_map:
 *   'status_code' => [ label_uk, badge_class, hex_color ]
 */
class StatusColors
{
    private static $_map = array(

        // ── Замовлення покупця ──────────────────────────────────────────────────
        'customerorder' => array(
            'draft'             => array('Чернетка',              'badge-gray',    '#9ca3af'),
            'new'               => array('Нове',                  'badge-blue',    '#3b82f6'),
            'confirmed'         => array('Підтверджено',          'badge-indigo',  '#6366f1'),
            'in_progress'       => array('В роботі',              'badge-purple',  '#8b5cf6'),
            'waiting_payment'   => array('Очік. оплати',          'badge-orange',  '#f59e0b'),
            'paid'              => array('Оплачено',              'badge-teal',    '#0d9488'),
            'partially_shipped' => array('Частк. відвантаж.',     'badge-indigo',  '#6366f1'),
            'shipped'           => array('Відвантажено',          'badge-green',   '#16a34a'),
            'completed'         => array('Виконано',              'badge-green',   '#15803d'),
            'cancelled'         => array('Скасовано',             'badge-red',     '#ef4444'),
        ),

        // ── Відвантаження (demand) ──────────────────────────────────────────────
        'demand' => array(
            'new'        => array('Нове',         'badge-gray',    '#9ca3af'),
            'assembling' => array('Збирається',   'badge-orange',  '#f59e0b'),
            'assembled'  => array('Зібрано',      'badge-blue',    '#3b82f6'),
            'shipped'    => array('Відвантажено', 'badge-green',   '#16a34a'),
            'arrived'    => array('Отримано',     'badge-green',   '#15803d'),
            'transfer'   => array('Передача',     'badge-indigo',  '#6366f1'),
            'robot'      => array('Робот',        'badge-purple',  '#d946ef'),
            'cancelled'  => array('Скасовано',    'badge-red',     '#ef4444'),
        ),

        // ── ТТН Нова Пошта ─────────────────────────────────────────────────────
        'ttn_np' => array(
            'created'    => array('Створено',    'badge-gray',    '#9ca3af'),
            'in_transit' => array('В дорозі',    'badge-blue',    '#3b82f6'),
            'delivered'  => array('Доставлено',  'badge-green',   '#16a34a'),
            'returned'   => array('Повернено',   'badge-orange',  '#f59e0b'),
            'deleted'    => array('Видалено',    'badge-red',     '#ef4444'),
        ),

        // ── Фінанси (платежі, каса) ────────────────────────────────────────────
        'finance' => array(
            'posted'  => array('Проведено',  'badge-green',   '#10b981'),
            'draft'   => array('Чернетка',   'badge-gray',    '#9ca3af'),
        ),
    );

    // ── Public API ──────────────────────────────────────────────────────────────

    public static function label($docType, $status, $fallback = null)
    {
        $entry = self::_entry($docType, $status);
        if ($entry) return $entry[0];
        return $fallback !== null ? $fallback : (string)$status;
    }

    public static function badge($docType, $status, $fallback = 'badge-gray')
    {
        $entry = self::_entry($docType, $status);
        return $entry ? $entry[1] : $fallback;
    }

    public static function hex($docType, $status, $fallback = '#9ca3af')
    {
        $entry = self::_entry($docType, $status);
        return $entry ? $entry[2] : $fallback;
    }

    /** Returns JSON object {status: hex, ...} for use in <script> tags */
    public static function jsonHex($docType)
    {
        $out = array();
        if (!isset(self::$_map[$docType])) return '{}';
        foreach (self::$_map[$docType] as $s => $e) {
            $out[$s] = $e[2];
        }
        return json_encode($out);
    }

    /** Returns JSON object {status: label, ...} for use in <script> tags */
    public static function jsonLabels($docType)
    {
        $out = array();
        if (!isset(self::$_map[$docType])) return '{}';
        foreach (self::$_map[$docType] as $s => $e) {
            $out[$s] = $e[0];
        }
        return json_encode($out);
    }

    /** Returns full map array for one docType: [ status => [label, badge, hex] ] */
    public static function all($docType)
    {
        return isset(self::$_map[$docType]) ? self::$_map[$docType] : array();
    }

    private static function _entry($docType, $status)
    {
        if (isset(self::$_map[$docType][$status])) {
            return self::$_map[$docType][$status];
        }
        // Finance types share the same map
        if (in_array($docType, array('paymentin', 'cashin', 'paymentout', 'cashout', 'salesreturn'))
            && isset(self::$_map['finance'][$status])) {
            return self::$_map['finance'][$status];
        }
        return null;
    }
}
