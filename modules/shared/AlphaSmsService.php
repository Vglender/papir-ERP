<?php

require_once __DIR__ . '/../integrations/IntegrationSettingsService.php';

class AlphaSmsService
{
    private static $cfg = null;

    private static function cfg($key)
    {
        if (self::$cfg === null) {
            $all = IntegrationSettingsService::getAll('alphasms');
            self::$cfg = array(
                'api_url'    => isset($all['api_url'])    ? $all['api_url']['value']    : 'https://alphasms.com.ua/api/json.php',
                'api_key'    => isset($all['api_key'])    ? $all['api_key']['value']    : '',
                'alpha_name' => isset($all['alpha_name']) ? $all['alpha_name']['value'] : 'OfficeTorg',
            );
        }
        return self::$cfg[$key];
    }

    public static function sendViber($phone, $text)
    {
        $phone = self::normalizePhone($phone);

        // SMS can't display 4-byte emoji (U+10000..U+10FFFF).
        // AlphaSMS returns "Please enter SMS text" and drops the message
        // if sms_message consists solely of such characters.
        // Strip them for the SMS fallback; use a placeholder if nothing remains.
        $smsText = preg_replace('/[^\x{0000}-\x{FFFF}]/u', '', $text);
        $smsText = trim(preg_replace('/\s+/u', ' ', $smsText));
        if ($smsText === '') {
            $smsText = 'Повідомлення від ' . self::cfg('alpha_name');
        }

        $payload = array(
            'auth' => self::cfg('api_key'),
            'data' => array(array(
                'type'            => 'viber+sms',
                'phone'           => $phone,
                'viber_signature' => self::cfg('alpha_name'),
                'viber_type'      => 'text',
                'viber_message'   => $text,
                'sms_signature'   => self::cfg('alpha_name'),
                'sms_message'     => $smsText,
            ))
        );
        $resp = self::post($payload);
        if (!$resp || empty($resp['success'])) {
            $err = isset($resp['error']) ? $resp['error'] : 'Alpha SMS error';
            error_log('[AlphaSMS] sendViber FAIL phone=' . $phone . ' error=' . $err);
            return array('ok' => false, 'error' => $err);
        }
        $msgId = isset($resp['data'][0]['data']['msg_id']) ? $resp['data'][0]['data']['msg_id'] : null;
        return array('ok' => true, 'msg_id' => $msgId);
    }

    public static function sendViberImage($phone, $imageUrl, $caption = '')
    {
        $phone   = self::normalizePhone($phone);
        $item    = array(
            'type'            => 'viber',
            'phone'           => $phone,
            'viber_signature' => self::cfg('alpha_name'),
            'viber_type'      => 'image',
            'viber_image'     => $imageUrl,
        );
        if ($caption) {
            $item['viber_caption'] = $caption;
        }
        $payload = array('auth' => self::cfg('api_key'), 'data' => array($item));
        $resp = self::post($payload);
        if (!$resp || empty($resp['success'])) {
            $err = isset($resp['error']) ? $resp['error'] : 'Alpha SMS error';
            error_log('[AlphaSMS] sendViberImage FAIL phone=' . $phone . ' error=' . $err);
            return array('ok' => false, 'error' => $err);
        }
        $msgId = isset($resp['data'][0]['data']['msg_id']) ? $resp['data'][0]['data']['msg_id'] : null;
        return array('ok' => true, 'msg_id' => $msgId);
    }

    public static function sendSms($phone, $text)
    {
        $phone   = self::normalizePhone($phone);
        $payload = array(
            'auth' => self::cfg('api_key'),
            'data' => array(array(
                'type'          => 'sms',
                'phone'         => $phone,
                'sms_signature' => self::cfg('alpha_name'),
                'sms_message'   => $text,
            ))
        );
        $resp = self::post($payload);
        if (!$resp || empty($resp['success'])) {
            $err = isset($resp['error']) ? $resp['error'] : 'Alpha SMS error';
            error_log('[AlphaSMS] sendSms FAIL phone=' . $phone . ' error=' . $err);
            return array('ok' => false, 'error' => $err);
        }
        $msgId = isset($resp['data'][0]['data']['msg_id']) ? $resp['data'][0]['data']['msg_id'] : null;
        return array('ok' => true, 'msg_id' => $msgId);
    }

    // Normalize to 12-digit format 380XXXXXXXXX
    public static function normalizePhone($phone)
    {
        $p = preg_replace('/\D/', '', $phone);
        if (strlen($p) === 10 && $p[0] === '0') {
            // 0XXXXXXXXX → 380XXXXXXXXX
            $p = '38' . $p;
        } elseif (strlen($p) === 9) {
            // XXXXXXXXX → 380XXXXXXXXX
            $p = '380' . $p;
        } elseif (strlen($p) === 11 && $p[0] === '8') {
            // 80XXXXXXXXX → 380XXXXXXXXX
            $p = '3' . $p;
        } elseif (strlen($p) === 12 && substr($p, 0, 2) === '38') {
            // 38 (097) 350-51-89 → already 380XXXXXXXXX, no change
        }
        return $p;
    }

    // Normalize if result is a valid UA number (380XXXXXXXXX), else return original trimmed.
    // Use when saving user input — preserves non-UA numbers as typed.
    public static function normalizePhoneLoose($phone)
    {
        $phone = trim($phone);
        if ($phone === '') return '';
        $normalized = self::normalizePhone($phone);
        if (strlen($normalized) === 12 && substr($normalized, 0, 3) === '380') {
            return $normalized;
        }
        return $phone;
    }

    // Normalize to last 9 digits for fuzzy matching in DB
    public static function phoneLast9($phone)
    {
        $p = preg_replace('/\D/', '', $phone);
        return substr($p, -9);
    }

    private static function post($data)
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ch = curl_init(self::cfg('api_url'));
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
        ));
        $res = curl_exec($ch);
        curl_close($ch);
        return $res ? json_decode($res, true) : null;
    }
}
