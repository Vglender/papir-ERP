<?php

class UkrsibApi
{
    /** @var array<string, mixed> */
    protected $config = array();

    public function __construct(array $config = array())
    {
        if (!$config) {
            $config = require __DIR__ . '/ukrsib_config.php';
        }

        $this->config = $config;
    }

    /**
     * Получить весь конфиг модуля.
     *
     * @return array<string, mixed>
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Текущее время в миллисекундах.
     *
     * @return string
     */
    public function nowMs()
    {
        return (string) round(microtime(true) * 1000);
    }

    /**
     * Дата YYYY-mm-dd -> timestamp в миллисекундах.
     *
     * @param string $date
     * @return string
     */
    public function dateToMs($date)
    {
        return (string) (strtotime($date) * 1000);
    }

    /**
     * UUID v4.
     *
     * @return string
     */
    public function uuidV4()
    {
        $data = openssl_random_pseudo_bytes(16);

        if ($data === false || strlen($data) < 16) {
            $data = md5(uniqid(mt_rand(), true), true);
        }

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Загрузить приватный ключ.
     *
     * @param string $path
     * @return resource|OpenSSLAsymmetricKey|false
     */
    protected function loadPrivateKeyResource($path)
    {
        if (!file_exists($path)) {
            return false;
        }

        $pem = file_get_contents($path);
        if ($pem === false || trim($pem) === '') {
            return false;
        }

        return openssl_pkey_get_private($pem);
    }

    /**
     * Подписать строку SHA512WithRSA и вернуть base64.
     *
     * @param string $signSource
     * @return string|false
     */
    public function signString($signSource)
    {
        $privateKey = $this->loadPrivateKeyResource($this->config['private_key']);
        if ($privateKey === false) {
            return false;
        }

        $signature = '';
        $ok = openssl_sign($signSource, $signature, $privateKey, 'SHA512');

        if (!$ok) {
            return false;
        }

        return base64_encode($signature);
    }

    /**
     * Прочитать токены из json.
     *
     * @return array<string, mixed>
     */
    public function tokenRead()
    {
        if (!file_exists($this->config['token_file'])) {
            return array();
        }

        $json = file_get_contents($this->config['token_file']);
        if ($json === false || trim($json) === '') {
            return array();
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : array();
    }

    /**
     * Записать токены в json.
     *
     * @param array<string, mixed> $data
     * @return int|false
     */
    public function tokenWrite(array $data)
    {
        return file_put_contents(
            $this->config['token_file'],
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Один раз вручную записать initial pair токенов.
     *
     * @param string $accessToken
     * @param string $refreshToken
     * @param int $expiresIn
     * @return array<string, mixed>
     */
    public function setInitialTokens($accessToken, $refreshToken, $expiresIn)
    {
        $data = array(
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => time() + (int) $expiresIn - 60
        );

        $this->tokenWrite($data);

        return $data;
    }

    /**
     * POST x-www-form-urlencoded.
     *
     * @param string $url
     * @param array<string, mixed> $fields
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    public function httpPostForm($url, array $fields, array $headers)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        curl_close($ch);

        return array(
            'http_code'  => $httpCode,
            'response'   => $response,
            'curl_error' => $curlErr
        );
    }

    /**
     * POST json.
     *
     * @param string $url
     * @param array<string, mixed> $body
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    public function httpPostJson($url, array $body, array $headers)
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        curl_close($ch);

        return array(
            'http_code'  => $httpCode,
            'response'   => $response,
            'curl_error' => $curlErr
        );
    }

    /**
     * Обновить access_token по refresh_token.
     *
     * @return array<string, mixed>
     */
    public function refreshAccessToken()
    {
        $stored = $this->tokenRead();

        if (empty($stored['refresh_token'])) {
            return array(
                '_error' => 'UKRSIB refresh_token not found. Set initial tokens first.'
            );
        }

        $url = rtrim($this->config['base_url'], '/') . '/token';

        $fields = array(
            'grant_type'    => 'refresh_token',
            'refresh_token' => $stored['refresh_token'],
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret']
        );

        $headers = array(
            'Content-Type: application/x-www-form-urlencoded'
        );

        $res = $this->httpPostForm($url, $fields, $headers);

        if ($res['curl_error']) {
            return array(
                '_error'      => 'UKRSIB refresh token cURL error',
                '_curl_error' => $res['curl_error']
            );
        }

        $decoded = json_decode($res['response'], true);

        if ($res['http_code'] != 200 || !is_array($decoded) || empty($decoded['access_token'])) {
            return array(
                '_error'     => 'UKRSIB token refresh failed',
                '_http_code' => $res['http_code'],
                '_raw'       => $res['response']
            );
        }

        $newData = array(
            'access_token'  => $decoded['access_token'],
            'refresh_token' => isset($decoded['refresh_token']) ? $decoded['refresh_token'] : $stored['refresh_token'],
            'expires_at'    => time() + (isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3600) - 60
        );

        $this->tokenWrite($newData);

        return $newData;
    }

    /**
     * Вернуть действующий access_token.
     *
     * @return string|array<string, mixed>
     */
    public function getAccessToken()
    {
        $stored = $this->tokenRead();

        if (!empty($stored['access_token']) && !empty($stored['expires_at']) && time() < (int) $stored['expires_at']) {
            return $stored['access_token'];
        }

        $refreshed = $this->refreshAccessToken();

        if (isset($refreshed['_error'])) {
            return $refreshed;
        }

        return $refreshed['access_token'];
    }

    /**
     * Взять первое непустое значение из массива по списку ключей.
     *
     * @param array<string, mixed>|mixed $arr
     * @param array<int, string> $keys
     * @param mixed $default
     * @return mixed
     */
    protected function pick($arr, array $keys, $default)
    {
        if (!is_array($arr)) {
            return $default;
        }

        foreach ($keys as $k) {
            if (isset($arr[$k]) && $arr[$k] !== '') {
                return $arr[$k];
            }
        }

        return $default;
    }

    /**
     * Преобразовать строку выписки в формат проекта.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $account
     * @return array<string, mixed>
     */
    public function normalizeStatementRow(array $row, array $account)
    {
        $item = $row;

        $credit = (float) $this->pick($row, array('credit'), 0);
        $debit  = (float) $this->pick($row, array('debit'), 0);

        $amount = 0;
        if ($credit > 0) {
            $amount = $credit;
        } elseif ($debit > 0) {
            $amount = -1 * $debit;
        } elseif (isset($row['amount'])) {
            $amount = $row['amount'];
        }

        $item['_bank']         = 'ukrsib';
        $item['_acc']          = $account['acc'];
        $item['_id']           = $this->pick($row, array('id', 'documentId', 'reference', 'docNumber'), '');
        $item['_date']         = $this->pick($row, array('provDate', 'docDate', 'date', 'valueDate'), '');
        $item['_amount']       = $amount;
        $item['_currency']     = $this->pick($row, array('currency'), '');
        $item['_description']  = $this->pick($row, array('paymentPurpose', 'purpose'), '');
        $item['_reference']    = $this->pick($row, array('reference', 'docNumber'), '');
        $item['_counterparty'] = $this->pick($row, array('correspondentName', 'recipientName'), '');
        $item['_iban']         = $this->pick($row, array('clientIBAN'), '');
        $item['_counter_iban'] = $this->pick($row, array('correspondentIBAN'), '');

        return $item;
    }

    /**
     * Один запрос выписки по счету.
     *
     * @param array<string, mixed> $account
     * @param string|int $dateFromMs
     * @param string|int $dateToMs
     * @param int $firstResult
     * @param int $maxResult
     * @return array<string, mixed>
     */
    public function statementRequest(array $account, $dateFromMs, $dateToMs, $firstResult, $maxResult)
    {
        $accessToken = $this->getAccessToken();

        if (is_array($accessToken) && isset($accessToken['_error'])) {
            return $accessToken;
        }

        $url = rtrim($this->config['base_url'], '/') . '/v2/statements';

        $body = array(
            'accounts'    => $account['acc'],
            'dateFrom'    => (int) $dateFromMs,
            'dateTo'      => (int) $dateToMs,
            'firstResult' => (int) $firstResult,
            'maxResult'   => (int) $maxResult
        );

        $signSource = $body['accounts']
            . '|' . $body['dateFrom']
            . '|' . $body['dateTo']
            . '|' . $body['firstResult']
            . '|' . $body['maxResult'];

        $sign = $this->signString($signSource);

        if ($sign === false) {
            return array(
                '_error' => 'Cannot create UKRSIB RSA signature'
            );
        }

        $headers = array(
            'Authorization: Bearer ' . $accessToken,
            'Sign: ' . $sign,
            'X-Request-ID: ' . $this->uuidV4(),
            'Content-Type: application/json',
            'Accept: application/json'
        );

        $res = $this->httpPostJson($url, $body, $headers);

        if ($res['curl_error']) {
            return array(
                '_error'      => 'UKRSIB statements cURL error',
                '_curl_error' => $res['curl_error']
            );
        }

        $decoded = json_decode($res['response'], true);

        if (!is_array($decoded)) {
            return array(
                '_error'     => 'UKRSIB invalid JSON response',
                '_http_code' => $res['http_code'],
                '_raw'       => $res['response']
            );
        }

        $decoded['_http_code'] = $res['http_code'];

        return $decoded;
    }

    /**
     * Получить выписку по всем счетам в формате проекта.
     *
     * @param string $dateFrom
     * @return array<int, array<string, mixed>>
     */
    public function getStatements($dateFrom)
    {
        $payment = array();

        $dateFromMs = $this->dateToMs($dateFrom);
        $dateToMs   = $this->nowMs();

        foreach ($this->config['accounts'] as $account) {
            $firstResult = 0;
            $maxResult   = 1000;

            while (true) {
                $result = $this->statementRequest(
                    $account,
                    $dateFromMs,
                    $dateToMs,
                    $firstResult,
                    $maxResult
                );

                if (isset($result['_error'])) {
                    $payment[] = array(
                        '_bank'       => 'ukrsib',
                        '_acc'        => $account['acc'],
                        '_error'      => $result['_error'],
                        '_http_code'  => isset($result['_http_code']) ? $result['_http_code'] : '',
                        '_curl_error' => isset($result['_curl_error']) ? $result['_curl_error'] : '',
                        '_raw'        => isset($result['_raw']) ? $result['_raw'] : ''
                    );
                    break;
                }

                $rows = array();

                if (isset($result['data']) && is_array($result['data'])) {
                    $rows = $result['data'];
                } elseif (isset($result['statements']) && is_array($result['statements'])) {
                    $rows = $result['statements'];
                }

                foreach ($rows as $row) {
                    $payment[] = $this->normalizeStatementRow($row, $account);
                }

                $countRows = count($rows);
                $total = isset($result['total']) ? (int) $result['total'] : $countRows;

                if ($countRows < $maxResult) {
                    break;
                }

                $firstResult += $maxResult;

                if ($firstResult >= $total) {
                    break;
                }
            }
        }

        return $payment;
    }

    /**
     * Вернуть список счетов из конфига.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccounts()
    {
        return isset($this->config['accounts']) && is_array($this->config['accounts'])
            ? $this->config['accounts']
            : array();
    }
}


/* -------------------- OPTIONAL WRAPPERS -------------------- */

$ukrsibApi = new UkrsibApi();

function request_ukrsib($dateFrom)
{
    global $ukrsibApi;
    return $ukrsibApi->getStatements($dateFrom);
}