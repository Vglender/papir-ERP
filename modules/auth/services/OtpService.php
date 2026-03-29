<?php
namespace Papir\Crm;



/**
 * OtpService — генерація та перевірка одноразових кодів.
 * Зараз підтримує SMS через AlphaSms.
 */
class OtpService
{
    const CODE_LENGTH = 6;
    const TTL_SECONDS = 300;    // 5 хвилин
    const MAX_ATTEMPTS = 5;     // спроб перевірки
    const COOLDOWN_SECONDS = 60; // між повторними відправками

    /**
     * Відправити OTP на телефон через SMS.
     * @return array ['ok'=>bool, 'error'=>string|null, 'expires_at'=>string|null]
     */
    public static function sendSms($phone)
    {
        $phone = \AlphaSmsService::normalizePhone($phone);
        if (!$phone) {
            return array('ok' => false, 'error' => 'Невірний формат телефону');
        }

        // Cooldown — перевірити чи не відправляли нещодавно
        $r = \Database::fetchRow('Papir',
            "SELECT created_at FROM auth_otp_codes
             WHERE target = '" . \Database::escape('Papir', $phone) . "'
               AND provider = 'sms'
               AND used_at IS NULL
               AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1");
        if ($r['ok'] && $r['row']) {
            $sent = strtotime($r['row']['created_at']);
            if (time() - $sent < self::COOLDOWN_SECONDS) {
                $wait = self::COOLDOWN_SECONDS - (time() - $sent);
                return array('ok' => false, 'error' => "Почекайте {$wait} секунд перед повторною відправкою");
            }
        }

        // Анулювати старі коди
        \Database::query('Papir',
            "UPDATE auth_otp_codes SET used_at = NOW()
             WHERE target = '" . \Database::escape('Papir', $phone) . "'
               AND provider = 'sms' AND used_at IS NULL");

        $code    = str_pad((string)rand(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $ip      = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        \Database::insert('Papir', 'auth_otp_codes', array(
            'target'     => $phone,
            'provider'   => 'sms',
            'code'       => $code,
            'expires_at' => $expires,
            'ip_address' => $ip,
        ));

        // Відправка через AlphaSms
        $text   = "Papir ERP: код входу {$code}";
        $result = \AlphaSmsService::sendSms($phone, $text);

        if (!$result['ok']) {
            return array('ok' => false, 'error' => 'Помилка відправки SMS: ' . $result['error']);
        }

        return array('ok' => true, 'expires_at' => $expires, 'phone' => $phone);
    }

    /**
     * Перевірити OTP.
     * @return array ['ok'=>bool, 'error'=>string|null, 'phone'=>string|null]
     */
    public static function verifySms($phone, $code)
    {
        $phone = \AlphaSmsService::normalizePhone($phone);
        if (!$phone) {
            return array('ok' => false, 'error' => 'Невірний формат телефону');
        }

        $code = preg_replace('/\D/', '', trim($code));
        if (strlen($code) !== self::CODE_LENGTH) {
            return array('ok' => false, 'error' => 'Код має містити ' . self::CODE_LENGTH . ' цифр');
        }

        $escPhone = \Database::escape('Papir', $phone);

        // Знайти актуальний код
        $r = \Database::fetchRow('Papir',
            "SELECT otp_id, code, attempts FROM auth_otp_codes
             WHERE target = '{$escPhone}'
               AND provider = 'sms'
               AND used_at IS NULL
               AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1");

        if (!$r['ok'] || empty($r['row'])) {
            return array('ok' => false, 'error' => 'Код застарів або не існує. Запросіть новий.');
        }

        $row = $r['row'];

        if ((int)$row['attempts'] >= self::MAX_ATTEMPTS) {
            return array('ok' => false, 'error' => 'Занадто багато спроб. Запросіть новий код.');
        }

        // Збільшити лічильник спроб
        \Database::query('Papir',
            "UPDATE auth_otp_codes SET attempts = attempts + 1 WHERE otp_id = " . (int)$row['otp_id']);

        if ($row['code'] !== $code) {
            return array('ok' => false, 'error' => 'Невірний код');
        }

        // Погасити код
        \Database::query('Papir',
            "UPDATE auth_otp_codes SET used_at = NOW() WHERE otp_id = " . (int)$row['otp_id']);

        return array('ok' => true, 'phone' => $phone);
    }
}
