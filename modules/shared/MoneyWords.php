<?php
/**
 * MoneyWords — перетворення суми в рядок прописом (українська).
 *
 * Використання:
 *   MoneyWords::format(1250.50)
 *   // → "Одна тисяча двісті п'ятдесят гривень 50 копійок"
 *
 * Підключати: require_once __DIR__ . '/../../shared/MoneyWords.php';
 * Клас не залежить від Database або інших модулів.
 *
 * ВАЖЛИВО: Файл у UTF-8 без BOM. Апостроф у словах (п'ять, дев'ять)
 * — звичайний ASCII 0x27, безпечний у PHP-рядках. Екранування потрібне
 * тільки при вставці в SQL — використовуйте Database::escape().
 */
class MoneyWords
{
    /**
     * Форматує суму прописом.
     *
     * @param float  $amount
     * @param bool   $ucfirst  Перша літера велика (default true)
     * @return string  напр. "Одна тисяча двісті п'ятдесят гривень 50 копійок"
     */
    public static function format($amount, $ucfirst = true)
    {
        $amount = (float)$amount;
        $int    = (int)floor(abs($amount));
        $kop    = (int)round((abs($amount) - $int) * 100);

        $hrWord  = self::pluralize($int, 'гривня', 'гривні', 'гривень');
        $kopWord = self::pluralize($kop, 'копійка', 'копійки', 'копійок');

        $words = self::numToWords($int, true) . ' ' . $hrWord
               . ' ' . sprintf('%02d', $kop) . ' ' . $kopWord;

        return $ucfirst ? ucfirst($words) : $words;
    }

    // ── internal ──────────────────────────────────────────────────────────────

    /**
     * @param int  $n
     * @param bool $feminine  true для гривня/тисяча (жіночий рід)
     */
    private static function numToWords($n, $feminine = false)
    {
        $n = (int)$n;
        if ($n === 0) {
            return 'нуль';
        }

        $onesM = array(
            '', 'один', 'два', 'три', 'чотири', "п'ять", 'шість', 'сім', 'вісім', "дев'ять",
            'десять', 'одинадцять', 'дванадцять', 'тринадцять', 'чотирнадцять', "п'ятнадцять",
            'шістнадцять', 'сімнадцять', 'вісімнадцять', "дев'ятнадцять",
        );
        $onesF = array(
            '', 'одна', 'дві', 'три', 'чотири', "п'ять", 'шість', 'сім', 'вісім', "дев'ять",
            'десять', 'одинадцять', 'дванадцять', 'тринадцять', 'чотирнадцять', "п'ятнадцять",
            'шістнадцять', 'сімнадцять', 'вісімнадцять', "дев'ятнадцять",
        );
        $tens = array(
            '', '', 'двадцять', 'тридцять', 'сорок', "п'ятдесят",
            'шістдесят', 'сімдесят', 'вісімдесят', "дев'яносто",
        );
        $hunds = array(
            '', 'сто', 'двісті', 'триста', 'чотириста', "п'ятсот",
            'шістсот', 'сімсот', 'вісімсот', "дев'ятсот",
        );

        $parts = array();

        if ($n >= 1000000000) {
            $b = (int)($n / 1000000000);
            $parts[] = self::numToWords($b, false) . ' '
                     . self::pluralize($b, 'мільярд', 'мільярди', 'мільярдів');
            $n %= 1000000000;
        }
        if ($n >= 1000000) {
            $m = (int)($n / 1000000);
            $parts[] = self::numToWords($m, false) . ' '
                     . self::pluralize($m, 'мільйон', 'мільйони', 'мільйонів');
            $n %= 1000000;
        }
        if ($n >= 1000) {
            $t = (int)($n / 1000);
            $parts[] = self::numToWords($t, true) . ' '
                     . self::pluralize($t, 'тисяча', 'тисячі', 'тисяч');
            $n %= 1000;
        }
        if ($n >= 100) {
            $parts[] = $hunds[(int)($n / 100)];
            $n %= 100;
        }
        if ($n >= 20) {
            $parts[] = $tens[(int)($n / 10)];
            $n %= 10;
        }
        if ($n > 0) {
            $ones    = $feminine ? $onesF : $onesM;
            $parts[] = $ones[$n];
        }

        return implode(' ', array_filter($parts));
    }

    private static function pluralize($n, $one, $few, $many)
    {
        $n  = abs((int)$n) % 100;
        $n1 = $n % 10;
        if ($n >= 11 && $n <= 19) { return $many; }
        if ($n1 === 1)             { return $one; }
        if ($n1 >= 2 && $n1 <= 4) { return $few; }
        return $many;
    }
}
