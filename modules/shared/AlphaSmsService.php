<?php

class AlphaSmsService
{
    const API_URL    = 'https://alphasms.com.ua/api/json.php';
    const API_KEY    = 'b91da24ccc1696f137169368f81e4033da5deffd';
    const ALPHA_NAME = 'OfficeTorg';

    public static function sendViber($phone, $text)
    {
        $phone   = self::normalizePhone($phone);
        $payload = array(
            'auth' => self::API_KEY,
            'data' => array(array(
                'type'            => 'viber+sms',
                'phone'           => $phone,
                'viber_signature' => self::ALPHA_NAME,
                'viber_type'      => 'text',
                'viber_message'   => $text,
                'sms_signature'   => self::ALPHA_NAME,
                'sms_message'     => $text,
            ))
        );
        $resp = self::post($payload);
        if (!$resp || empty($resp['success'])) {
            $err = isset($resp['error']) ? $resp['error'] : 'Alpha SMS error';
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
            'viber_signature' => self::ALPHA_NAME,
            'viber_type'      => 'image',
            'viber_image'     => $imageUrl,
        );
        if ($caption) {
            $item['viber_caption'] = $caption;
        }
        $payload = array('auth' => self::API_KEY, 'data' => array($item));
        $resp = self::post($payload);
        if (!$resp || empty($resp['success'])) {
            $err = isset($resp['error']) ? $resp['error'] : 'Alpha SMS error';
            return array('ok' => false, 'error' => $err);
        }
        $msgId = isset($resp['data'][0]['data']['msg_id']) ? $resp['data'][0]['data']['msg_id'] : null;
        return array('ok' => true, 'msg_id' => $msgId);
    }

    public static function sendSms($phone, $text)
    {
        $phone   = self::normalizePhone($phone);
        $payload = array(
            'auth' => self::API_KEY,
            'data' => array(array(
                'type'          => 'sms',
                'phone'         => $phone,
                'sms_signature' => self::ALPHA_NAME,
                'sms_message'   => $text,
            ))
        );
        $resp = self::post($payload);
        if (!$resp || empty($resp['success'])) {
            $err = isset($resp['error']) ? $resp['error'] : 'Alpha SMS error';
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
        $ch = curl_init(self::API_URL);
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
