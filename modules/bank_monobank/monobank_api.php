<?php

class MonobankApi
{
    protected $baseUrl = 'https://api.monobank.ua/';
    protected $storagePath;
    protected $accounts = array();
    protected $rateLimitFile;

    /**
     * @param string|null $storagePath Путь к папке storage
     * @throws Exception
     */
    public function __construct($storagePath = null)
    {
        $this->storagePath = $storagePath ?: __DIR__ . '/storage';
        $this->rateLimitFile = $this->storagePath . '/monobank_rate_limits.json';

        if (!is_dir($this->storagePath)) {
            throw new Exception('Storage directory not found: ' . $this->storagePath);
        }

        $accountsFile = $this->storagePath . '/monobank_accounts.php';
        if (!file_exists($accountsFile)) {
            throw new Exception('Accounts config not found: ' . $accountsFile);
        }

        $accounts = require $accountsFile;

        if (!is_array($accounts)) {
            throw new Exception('Accounts config must return array');
        }

        $this->accounts = $accounts;
    }

    /**
     * Получить информацию о клиенте по токену
     *
     * @param string $token
     * @return array
     * @throws Exception
     */
    public function getClientInfoByToken($token)
    {
        $this->checkRateLimit($token, 'personal/client-info');

        return $this->request(
            'GET',
            'personal/client-info',
            array(
                'X-Token: ' . $token,
                'Content-Type: application/json',
            )
        );
    }

    /**
     * Получить информацию о клиенте по индексу конфига
     *
     * @param int $index
     * @return array
     * @throws Exception
     */
    public function getClientInfoByIndex($index)
    {
        if (!isset($this->accounts[$index]['api'])) {
            throw new Exception('Token not found by index: ' . $index);
        }

        return $this->getClientInfoByToken($this->accounts[$index]['api']);
    }

    /**
     * Получить информацию по всем уникальным токенам
     *
     * @return array
     */
    public function getAllClientsInfo()
    {
        $result = array();
        $uniqueTokens = $this->getUniqueTokens();

        foreach ($uniqueTokens as $token) {
            try {
                $result[$token] = $this->getClientInfoByToken($token);
            } catch (Exception $e) {
                $result[$token] = array(
                    'success' => false,
                    'error'   => $e->getMessage(),
                );
            }
        }

        return $result;
    }

    /**
     * Получить выписку по одному аккаунту
     *
     * @param string $token
     * @param string $accountId
     * @param int $from Unix time
     * @param int|null $to Unix time
     * @return array
     * @throws Exception
     */
    public function getStatement($token, $accountId, $from, $to = null)
    {
        $from = (int)$from;
        $to = $to !== null ? (int)$to : time();

        if ($from <= 0) {
            throw new Exception('Parameter $from must be valid Unix timestamp');
        }

        if ($to < $from) {
            throw new Exception('Parameter $to must be greater than or equal to $from');
        }

        // 31 день + 1 час = 2682000 секунд
        if (($to - $from) > 2682000) {
            throw new Exception('Monobank statement period cannot exceed 2682000 seconds (31 days + 1 hour)');
        }

        $endpoint = 'personal/statement/' . rawurlencode($accountId) . '/' . $from;

        if ($to !== null) {
            $endpoint .= '/' . $to;
        }


        return $this->request(
            'GET',
            $endpoint,
            array(
                'X-Token: ' . $token,
                'Content-Type: application/json',
            )
        );
    }

    /**
     * Получить выписки по всем аккаунтам из конфига
     *
     * @param int $from Unix time
     * @param int|null $to Unix time
     * @return array
     */
	public function getAllStatements($from, $to = null)
	{
		if (empty($from)) {
			throw new Exception('Parameter $from is required');
		}

		$from = (int)$from;

		if ($to === null) {
			$to = time();
		} else {
			$to = (int)$to;
		}

		if ($to < $from) {
			throw new Exception('$to must be greater than $from');
		}

		if (($to - $from) > 2682000) {
			throw new Exception('Monobank allows max 31 days + 1 hour');
		}

		$paidList = array();

		foreach ($this->accounts as $item) {

			$accountId = $item['id'];
			$token     = $item['api'];

			try {

				$paidList[$accountId] = $this->getStatement(
					$token,
					$accountId,
					$from,
					$to
				);

			} catch (Exception $e) {

				$paidList[$accountId] = array(
					'success' => false,
					'error'   => $e->getMessage()
				);

			}
			sleep(3);
		}

		return $paidList;
	}
    /**
     * Получить список аккаунтов из конфига
     *
     * @return array
     */
    public function getConfiguredAccounts()
    {
        return $this->accounts;
    }

    /**
     * Выполнить HTTP запрос
     *
     * @param string $method
     * @param string $endpoint
     * @param array $headers
     * @return array
     * @throws Exception
     */
    protected function request($method, $endpoint, array $headers = array())
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new Exception('cURL error: ' . $curlError);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception(
                'Monobank API error. HTTP ' . $httpCode . '. Response: ' . $response
            );
        }

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . $response);
        }

        return $decoded;
    }

    /**
     * Контроль ограничения: не чаще 1 раза в 60 секунд
     *
     * @param string $token
     * @param string $group
     * @throws Exception
     */
    protected function checkRateLimit($token, $group)
    {
        $limits = $this->loadRateLimits();
        $hash = md5($token);
        $key = $hash . '|' . $group;
        $now = time();

        if (isset($limits[$key])) {
            $diff = $now - (int)$limits[$key];
            if ($diff < 60) {
                throw new Exception(
                    'Rate limit exceeded for "' . $group . '". Try again in ' . (60 - $diff) . ' sec.'
                );
            }
        }

        $limits[$key] = $now;
        $this->saveRateLimits($limits);
    }

    /**
     * @return array
     */
    protected function loadRateLimits()
    {
        if (!file_exists($this->rateLimitFile)) {
            return array();
        }

        $content = file_get_contents($this->rateLimitFile);
        if ($content === false || trim($content) === '') {
            return array();
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : array();
    }

    /**
     * @param array $data
     * @throws Exception
     */
    protected function saveRateLimits(array $data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('Failed to encode rate limits JSON');
        }

        if (file_put_contents($this->rateLimitFile, $json, LOCK_EX) === false) {
            throw new Exception('Failed to write rate limit file: ' . $this->rateLimitFile);
        }
    }

    /**
     * @return array
     */
    protected function getUniqueTokens()
    {
        $tokens = array();

        foreach ($this->accounts as $item) {
            if (!empty($item['api'])) {
                $tokens[$item['api']] = $item['api'];
            }
        }

        return array_values($tokens);
    }
	
		function request_mono($date_from)
	{
		$mono = new MonobankApi(
			'/var/www/papir/modules/bank_monobank/storage'
		);

		return $mono->getAllStatements(
			strtotime($date_from)
		);
	}
}