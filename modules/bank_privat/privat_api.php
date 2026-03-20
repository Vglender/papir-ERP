<?php

class PrivatApi
{
    protected $config = array(
        'base_url' => 'https://acp.privatbank.ua/api/statements',
        'default_user_agent' => 'Papir',
        'default_charset' => 'utf-8',
        'default_limit' => 100,
        'connect_timeout' => 10,
        'timeout' => 60,
    );

    protected $accounts = array();

    public function __construct(array $config = array())
    {
        $this->config = array_merge($this->config, $config);
    }

    public function loadAccountsFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('Accounts file not found: ' . $filePath);
        }

        $accounts = require $filePath;

        if (!is_array($accounts)) {
            throw new RuntimeException('Accounts file must return array: ' . $filePath);
        }

        $this->accounts = $accounts;

        return $this;
    }

    public function setAccounts(array $accounts)
    {
        $this->accounts = $accounts;
        return $this;
    }

    public function getAccounts()
    {
        return $this->accounts;
    }

    public function getSettings()
    {
        return $this->request('/settings');
    }

    public function getTransactionsByDate($date, $account = null, $limit = null)
    {
        $date = $this->formatDate($date);
        return $this->getTransactions($date, $date, $account, $limit);
    }
	
	public function getTransactionsFromYesterdayToNow($account = null, $limit = null)
		{
			$yesterday = date('Y-m-d', strtotime('-1 day'));

			$finalPart   = $this->getTransactions($yesterday, $yesterday, $account, $limit);
			$interimPart = $this->getInterimTransactions($account, $limit);

			return array_merge($finalPart, $interimPart);
		}

    public function getTransactions($startDate, $endDate = null, $account = null, $limit = null)
    {
        $startDate = $this->formatDate($startDate);
        $endDate   = $endDate ? $this->formatDate($endDate) : null;
        $limit     = $this->normalizeLimit($limit);

        return $this->collectPagedData('/transactions', 'transactions', $startDate, $endDate, $account, $limit);
    }

    public function getBalances($startDate, $endDate = null, $account = null, $limit = null)
    {
        $startDate = $this->formatDate($startDate);
        $endDate   = $endDate ? $this->formatDate($endDate) : null;
        $limit     = $this->normalizeLimit($limit);

        return $this->collectPagedData('/balance', 'balances', $startDate, $endDate, $account, $limit);
    }

    public function getInterimTransactions($account = null, $limit = null)
    {
        $limit = $this->normalizeLimit($limit);
        return $this->collectPagedData('/transactions/interim', 'transactions', null, null, $account, $limit);
    }

    public function getFinalTransactions($account = null, $limit = null)
    {
        $limit = $this->normalizeLimit($limit);
        return $this->collectPagedData('/transactions/final', 'transactions', null, null, $account, $limit);
    }

    public function getInterimBalances($account = null, $limit = null)
    {
        $limit = $this->normalizeLimit($limit);
        return $this->collectPagedData('/balance/interim', 'balances', null, null, $account, $limit);
    }

    public function getFinalBalances($account = null, $limit = null)
    {
        $limit = $this->normalizeLimit($limit);
        return $this->collectPagedData('/balance/final', 'balances', null, null, $account, $limit);
    }

    public function buildTransactionExternalId(array $transaction)
    {
        $ref  = isset($transaction['REF']) ? trim($transaction['REF']) : '';
        $refn = isset($transaction['REFN']) ? trim($transaction['REFN']) : '';

        return $ref . $refn;
    }

    protected function collectPagedData($endpoint, $dataKey, $startDate = null, $endDate = null, $account = null, $limit = 100)
    {
        $result = array();

        if ($account !== null) {
            return $this->fetchAllPages($endpoint, $dataKey, $this->buildBaseParams($account, $startDate, $endDate, $limit), $account);
        }

        foreach ($this->accounts as $acc) {
            $rows = $this->fetchAllPages($endpoint, $dataKey, $this->buildBaseParams($acc, $startDate, $endDate, $limit), $acc);

            foreach ($rows as $row) {
                $result[] = $row;
            }
        }

        return $result;
    }

    protected function buildBaseParams(array $account, $startDate = null, $endDate = null, $limit = 100)
    {
        $params = array(
            'limit' => $limit,
        );

        if (!empty($account['acc'])) {
            $params['acc'] = $account['acc'];
        }

        if ($startDate !== null) {
            $params['startDate'] = $startDate;
        }

        if ($endDate !== null) {
            $params['endDate'] = $endDate;
        }

        return $params;
    }

    protected function fetchAllPages($endpoint, $dataKey, array $params, array $account)
    {
        $result   = array();
        $followId = null;

        do {
            $requestParams = $params;

            if ($followId !== null) {
                $requestParams['followId'] = $followId;
            }

            $response = $this->request($endpoint, $requestParams, $account);

            if (!empty($response[$dataKey]) && is_array($response[$dataKey])) {
                foreach ($response[$dataKey] as $row) {
                    $result[] = $row;
                }
            }

            $followId = (!empty($response['exist_next_page']) && !empty($response['next_page_id']))
                ? $response['next_page_id']
                : null;

        } while ($followId !== null);

        return $result;
    }

    protected function request($endpoint, array $params = array(), array $account = null)
    {
        $url = rtrim($this->config['base_url'], '/') . $endpoint;

        $query = array();
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $query[$key] = $value;
            }
        }

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = $this->buildHeaders($account);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->config['connect_timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($raw === false || !empty($err)) {
            throw new RuntimeException('PrivatBank cURL error: ' . $err);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('PrivatBank HTTP error ' . $httpCode . ': ' . $raw);
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new RuntimeException('PrivatBank invalid JSON response: ' . $raw);
        }

        if (!isset($data['status']) || $data['status'] !== 'SUCCESS') {
            throw new RuntimeException('PrivatBank API error: ' . $raw);
        }

        return $data;
    }

    protected function buildHeaders(array $account = null)
    {
        $userAgent = $this->config['default_user_agent'];

        if ($account !== null && !empty($account['user_agent'])) {
            $userAgent = $account['user_agent'];
        }

        $headers = array(
            'User-Agent: ' . $userAgent,
            'Content-Type: application/json;charset=' . $this->config['default_charset'],
        );

        if ($account !== null && !empty($account['token'])) {
            $headers[] = 'token: ' . $account['token'];
        }

        if ($account !== null && !empty($account['id'])) {
            $headers[] = 'id: ' . $account['id'];
        }

        return $headers;
    }

    protected function formatDate($date)
    {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            throw new InvalidArgumentException('Invalid date: ' . $date);
        }

        return date('d-m-Y', $timestamp);
    }

    protected function normalizeLimit($limit = null)
    {
        if ($limit === null) {
            $limit = (int)$this->config['default_limit'];
        }

        $limit = (int)$limit;

        if ($limit < 1) {
            $limit = 100;
        }

        if ($limit > 500) {
            $limit = 500;
        }

        return $limit;
    }
}