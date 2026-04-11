<?php
/**
 * LiqpayClient — тонкий клієнт LiqPay API (v3).
 *
 * API endpoint: https://www.liqpay.ua/api/request
 * Всі запити — POST application/x-www-form-urlencoded з двома полями:
 *   data      = base64( json_encode(params) )
 *   signature = base64( sha1_bin( private_key . data . private_key ) )
 *
 * Callback від LiqPay до нашого webhook'а приходить у тому ж форматі
 * (data + signature у POST). Підпис перевіряється тим же алгоритмом,
 * тільки на нашій стороні (ми знаємо private_key, LiqPay — ні).
 *
 * Використання:
 *   $client = new LiqpayClient($publicKey, $privateKey);
 *   $resp   = $client->request(array('action'=>'status', 'order_id'=>'98681', 'version'=>3));
 *   $ok     = $client->verifySignature($dataB64, $signatureB64);
 */
class LiqpayClient
{
    const API_URL = 'https://www.liqpay.ua/api/request';
    const VERSION = 3;

    private $publicKey;
    private $privateKey;

    public function __construct($publicKey, $privateKey)
    {
        $this->publicKey  = $publicKey;
        $this->privateKey = $privateKey;
    }

    /**
     * Викликати LiqPay API (action=status|refund|reports|...).
     * Автоматично підставляє public_key + version. Повертає декодований JSON.
     *
     * @param array $params LiqPay params (повинен містити 'action')
     * @return array ['ok'=>bool, 'data'=>array, 'raw'=>string, 'error'=>string|null]
     */
    public function request(array $params)
    {
        if (empty($params['action'])) {
            return array('ok' => false, 'error' => 'action is required');
        }
        $params['public_key'] = $this->publicKey;
        if (empty($params['version'])) $params['version'] = self::VERSION;

        $data = base64_encode(json_encode($params, JSON_UNESCAPED_UNICODE));
        $sig  = $this->signData($data);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(array('data' => $data, 'signature' => $sig)),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err) {
            return array('ok' => false, 'error' => 'curl: ' . $err, 'raw' => null);
        }
        if ($code < 200 || $code >= 300) {
            return array('ok' => false, 'error' => 'HTTP ' . $code, 'raw' => $raw);
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return array('ok' => false, 'error' => 'Invalid JSON response', 'raw' => $raw);
        }
        // LiqPay повертає 'result' => 'ok' (status/refund) або 'success' (reports).
        // Негативний кейс: 'error' + err_description.
        $result = isset($json['result']) ? $json['result'] : '';
        $okFlag = ($result === 'ok' || $result === 'success');
        return array(
            'ok'    => $okFlag,
            'data'  => $json,
            'raw'   => $raw,
            'error' => $okFlag ? null : (isset($json['err_description']) ? $json['err_description'] : 'LiqPay error'),
        );
    }

    /**
     * Перевірити отриманий колбек: sha1_bin(privKey + data + privKey) == signature.
     *
     * @param string $dataB64    POST['data'] (base64 JSON від LiqPay)
     * @param string $signatureB64  POST['signature'] (base64 sha1)
     * @return bool
     */
    public function verifySignature($dataB64, $signatureB64)
    {
        if (!$dataB64 || !$signatureB64) return false;
        $expected = $this->signData($dataB64);
        return hash_equals($expected, (string)$signatureB64);
    }

    /**
     * Розпакувати data (base64-json) з callback'а в масив.
     */
    public static function decodeData($dataB64)
    {
        if (!$dataB64) return array();
        $json = base64_decode($dataB64, true);
        if ($json === false) return array();
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : array();
    }

    private function signData($data)
    {
        return base64_encode(sha1($this->privateKey . $data . $this->privateKey, true));
    }

    public function getPublicKey()
    {
        return $this->publicKey;
    }
}