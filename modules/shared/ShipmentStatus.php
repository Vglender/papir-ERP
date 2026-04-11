<?php
/**
 * ShipmentStatus — єдиний словник статусів відправлень Папір.
 *
 * Будь-який перевізник (Нова Пошта, Укрпошта, у майбутньому — Meest/DPD/inhouse)
 * мапить свої внутрішні статуси на один із цих 7 канонічних значень. UI та
 * сценарії працюють ТІЛЬКИ з канонічними статусами — детальна інформація
 * (конкретні коди, eventName, підстатуси НП) показується лише як довідка
 * всередині модуля.
 *
 *   draft       — Чернетка (створено в Папір, ще не передано перевізнику)
 *   in_transit  — В дорозі
 *   at_branch   — На відділенні
 *   received    — Отримано
 *   returning   — Повертається
 *   returned    — Повернення отримано
 *   cancelled   — Скасовано / видалено
 *
 * Використання:
 *   $status = ShipmentStatus::fromNp((int)$row['state_define']);
 *   $status = ShipmentStatus::fromUp((string)$row['lifecycle_status']);
 *   $label  = ShipmentStatus::label($status);
 *   $badge  = ShipmentStatus::badgeClass($status);
 *   $hex    = ShipmentStatus::hex($status);
 */
class ShipmentStatus
{
    const DRAFT      = 'draft';
    const IN_TRANSIT = 'in_transit';
    const AT_BRANCH  = 'at_branch';
    const RECEIVED   = 'received';
    const RETURNING  = 'returning';
    const RETURNED   = 'returned';
    const CANCELLED  = 'cancelled';

    private static $LABELS = array(
        self::DRAFT      => 'Чернетка',
        self::IN_TRANSIT => 'В дорозі',
        self::AT_BRANCH  => 'На відділенні',
        self::RECEIVED   => 'Отримано',
        self::RETURNING  => 'Повертається',
        self::RETURNED   => 'Повернення отримано',
        self::CANCELLED  => 'Скасовано',
    );

    private static $BADGE = array(
        self::DRAFT      => 'badge-gray',
        self::IN_TRANSIT => 'badge-blue',
        self::AT_BRANCH  => 'badge-orange',
        self::RECEIVED   => 'badge-green',
        self::RETURNING  => 'badge-rose',
        self::RETURNED   => 'badge-gray',
        self::CANCELLED  => 'badge-red',
    );

    private static $HEX = array(
        self::DRAFT      => '#9ca3af',
        self::IN_TRANSIT => '#3b82f6',
        self::AT_BRANCH  => '#ea580c',
        self::RECEIVED   => '#16a34a',
        self::RETURNING  => '#f43f5e',
        self::RETURNED   => '#6b7280',
        self::CANCELLED  => '#ef4444',
    );

    // Статуси, після яких трекати більше не треба
    private static $FINAL = array(
        self::RECEIVED, self::RETURNED, self::CANCELLED,
    );

    /**
     * Нова Пошта state_define → канонічний статус.
     * (Джерело: NP TrackingDocument.getStatusDocuments, див. npStateClass.)
     */
    public static function fromNp($stateDefine)
    {
        $sd = (int)$stateDefine;
        switch ($sd) {
            case 1:   return self::DRAFT;           // Не передана відправником
            case 2:                                 // Видалена
            case 3:                                 // Номер не знайдено
            case 102:                               // Відмовлено від отримання
            case 106: return self::CANCELLED;       // Відмовлено
            case 4:                                 // У місті відправника
            case 5:                                 // В дорозі
            case 6:                                 // У місті одержувача
            case 41:                                // Змінено адресу
            case 101:                               // Кур'єр
            case 104: return self::IN_TRANSIT;      // Адресна доставка
            case 7:                                 // Прибуло на відділення
            case 8:                                 // Прийнято на відділенні
            case 105: return self::AT_BRANCH;       // Прибув у нове відділення
            case 9:   return self::RECEIVED;        // Отримана
            case 10:                                // Повернення до відправника
            case 103: return self::RETURNING;       // Зворотня доставка
            case 11:  return self::RETURNED;        // Повернення отримано
        }
        return self::DRAFT;
    }

    /**
     * Укрпошта lifecycle_status → канонічний статус.
     */
    public static function fromUp($lifecycle)
    {
        $lc = strtoupper(trim((string)$lifecycle));
        switch ($lc) {
            case '':
            case 'CREATED':
            case 'REGISTERED':
            case 'UNKNOWN':     return self::DRAFT;
            case 'DELIVERING':
            case 'FORWARDING':  return self::IN_TRANSIT;
            case 'IN_DEPARTMENT':
            case 'STORAGE':     return self::AT_BRANCH;
            case 'DELIVERED':   return self::RECEIVED;
            case 'RETURNING':   return self::RETURNING;
            case 'RETURNED':    return self::RETURNED;
            case 'CANCELLED':
            case 'DELETED':     return self::CANCELLED;
        }
        return self::DRAFT;
    }

    public static function label($status)
    {
        return isset(self::$LABELS[$status]) ? self::$LABELS[$status] : (string)$status;
    }

    public static function badgeClass($status)
    {
        return isset(self::$BADGE[$status]) ? self::$BADGE[$status] : 'badge-gray';
    }

    public static function hex($status)
    {
        return isset(self::$HEX[$status]) ? self::$HEX[$status] : '#9ca3af';
    }

    public static function isFinal($status)
    {
        return in_array($status, self::$FINAL, true);
    }

    public static function all()
    {
        return self::$LABELS;
    }

    /** JSON map {status: label} for JS embedding. */
    public static function jsonLabels()
    {
        return json_encode(self::$LABELS);
    }

    /** JSON map {status: hex} for JS embedding. */
    public static function jsonHex()
    {
        return json_encode(self::$HEX);
    }
}