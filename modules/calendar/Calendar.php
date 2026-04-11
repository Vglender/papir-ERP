<?php
/**
 * Calendar — робочий календар (робочі/вихідні/святкові дні України).
 *
 * Дефолт: Пн-Пт = робочий день, Сб-Нд = вихідний. Таблиця `calendar_days`
 * зберігає лише винятки (свята, перенесені робочі суботи).
 *
 * Використання:
 *   Calendar::isBusinessDay('2026-04-13');            // false (Великдень-перенос)
 *   Calendar::nextBusinessDay('2026-04-11');          // '2026-04-14'
 *   Calendar::formatNextBusinessDay();                 // 'у вівторок 14 квітня'
 *   Calendar::addBusinessDays('2026-04-11', 3);       // '2026-04-16'
 */
class Calendar
{
    private static $cache = array();

    public static function isBusinessDay($date)
    {
        $d = self::_normalize($date);
        if (!$d) return true;

        if (!array_key_exists($d, self::$cache)) {
            $safe = Database::escape('Papir', $d);
            $r = Database::fetchRow('Papir',
                "SELECT kind FROM calendar_days WHERE date='{$safe}' AND country='UA' LIMIT 1"
            );
            self::$cache[$d] = ($r['ok'] && !empty($r['row'])) ? $r['row']['kind'] : null;
        }
        $kind = self::$cache[$d];

        if ($kind !== null) {
            return in_array($kind, array('workday', 'shortday'), true);
        }
        $dow = (int)date('N', strtotime($d));
        return $dow >= 1 && $dow <= 5;
    }

    public static function nextBusinessDay($from = null, $includeToday = false)
    {
        $dt = $from ? new DateTime(self::_normalize($from)) : new DateTime('today');
        if (!$includeToday) $dt->modify('+1 day');
        for ($i = 0; $i < 60; $i++) {
            if (self::isBusinessDay($dt->format('Y-m-d'))) {
                return $dt->format('Y-m-d');
            }
            $dt->modify('+1 day');
        }
        return $dt->format('Y-m-d');
    }

    public static function addBusinessDays($from, $days)
    {
        $dt = new DateTime(self::_normalize($from));
        $added = 0;
        $safety = 0;
        while ($added < $days && $safety < 365) {
            $dt->modify('+1 day');
            if (self::isBusinessDay($dt->format('Y-m-d'))) $added++;
            $safety++;
        }
        return $dt->format('Y-m-d');
    }

    /**
     * Людська фраза: "сьогодні до кінця дня", "завтра 12 квітня",
     * "післязавтра 13 квітня", "у понеділок 14 квітня".
     *
     * Cutoff: якщо зараз робочий день і година < 15 — повертає "сьогодні до кінця дня".
     * Після 15:00 перекидає на наступний робочий.
     */
    public static function formatNextBusinessDay($fromDateTime = null, $cutoffHour = 15)
    {
        $now = $fromDateTime ? new DateTime($fromDateTime) : new DateTime();
        $todayStr = $now->format('Y-m-d');
        $hour = (int)$now->format('G');

        if (self::isBusinessDay($todayStr) && $hour < $cutoffHour) {
            return 'сьогодні до кінця дня';
        }

        $next = new DateTime(self::nextBusinessDay($todayStr, false));
        $today = new DateTime($todayStr);
        $diff = (int)$today->diff($next)->days;

        $monthsGen = array(
            1 => 'січня', 2 => 'лютого', 3 => 'березня', 4 => 'квітня',
            5 => 'травня', 6 => 'червня', 7 => 'липня', 8 => 'серпня',
            9 => 'вересня', 10 => 'жовтня', 11 => 'листопада', 12 => 'грудня',
        );
        $dowNames = array(
            1 => 'у понеділок', 2 => 'у вівторок', 3 => 'у середу',
            4 => 'у четвер', 5 => 'у п\'ятницю', 6 => 'у суботу', 7 => 'у неділю',
        );

        $day = (int)$next->format('j');
        $month = $monthsGen[(int)$next->format('n')];
        $datePart = $day . ' ' . $month;

        if ($diff === 1) return 'завтра ' . $datePart;
        if ($diff === 2) return 'післязавтра ' . $datePart;

        $dow = (int)$next->format('N');
        return $dowNames[$dow] . ' ' . $datePart;
    }

    /** Очистити кеш (для тестів / довгих CLI-процесів). */
    public static function clearCache()
    {
        self::$cache = array();
    }

    private static function _normalize($date)
    {
        if ($date instanceof DateTime) return $date->format('Y-m-d');
        if (is_numeric($date)) return date('Y-m-d', (int)$date);
        $ts = strtotime((string)$date);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}