<?php

class BankPaymentCollector
{
    protected $config;
    protected $accountsMap;

    public function __construct(array $config, array $accountsMap)
    {
        $this->config = $config;
        $this->accountsMap = $accountsMap;
    }

    public function collect($dateFrom)
    {
        $data = [];

        $mono = $this->collectMono($dateFrom);
        $pb   = $this->collectPrivatUr($dateFrom);
        $ukrsib = $this->collectUkrsib($dateFrom);

        $data = array_merge($data, $mono, $pb, $ukrsib);

        return $data;
    }
	
	protected function generatePaymentName($index = 0, $moment = null)
	{
		if ($moment) {
			$ts = strtotime($moment);
			if ($ts !== false) {
				return (string)($ts + (int)$index);
			}
		}

		return (string)(time() + (int)$index);
	}

    protected function collectMono($dateFrom)
    {
        $result = request_mono($dateFrom);
        $data = [];
        $a = 0;

        if (!$result) {
            return $data;
        }

        foreach ($result as $accountId => $statements) {
            if (!$statements) {
                continue;
            }

            foreach ($statements as $value) {
                if (!isset($value['time'])) {
                    continue;
                }

                $idPaid = $this->resolveExternalCode('mono', $value);

                $row = [
                    'source' => 'mono',
                    'bank' => 'mono',
                    'bank_account_key' => $accountId,
                    'id_paid' => $idPaid,
                    'name' => (string)(strtotime('now') + $a),
                    'type' => ($value['amount'] > 0) ? 'in' : 'out',
                    'moment' => date('Y-m-d H:i:s', $value['time']),
                    'sum' => abs($value['amount']) - (int)$value['commissionRate'],
                    'rate' => (int)$value['commissionRate'],
                    'description' => isset($value['comment'])
                        ? $value['description'] . '/' . $value['comment']
                        : $value['description'],
                    'name_kl' => $this->extractMonoName($value['description']),
                    'edrpoy_klient' => isset($value['counterEdrpou']) ? $value['counterEdrpou'] : null,
                    'acc_klient' => isset($value['counterIban']) ? $value['counterIban'] : null,
                    'id_agent' => null,
                    'inner' => false,
                    'id_order' => null,
                    'id_exp' => null,
                ];

                if (isset($this->accountsMap['mono'][$accountId])) {
                    $row['id_org'] = $this->accountsMap['mono'][$accountId]['id_org'];
                    $row['id_acc'] = $this->accountsMap['mono'][$accountId]['id_acc'];
                }

                $data[] = $row;
                $a++;

                // Комиссия Monobank — всегда расход, отдельным документом.
                // commissionRate положительный (напр. 16000 коп = 160 грн).
                // Описание 'Комiсiя Monobank' подхватывается правилом в payment_match_rules.php
                // → автоматически ставится bank_fee_agent_id + bank_fee_expense_item_id.
                if (!empty($value['commissionRate']) && $value['amount'] < 0) {
                    $feeRow = array(
                        'source'           => 'mono',
                        'bank'             => 'mono',
                        'bank_account_key' => $accountId,
                        'id_paid'          => $idPaid . '_fee',
                        'name'             => (string)(strtotime('now') + $a),
                        'type'             => 'out',
                        'moment'           => date('Y-m-d H:i:s', $value['time']),
                        'sum'              => (int)$value['commissionRate'],
                        'rate'             => 0,
                        'description'      => 'Комiсiя Monobank',
                        'name_kl'          => null,
                        'edrpoy_klient'    => null,
                        'acc_klient'       => null,
                        'id_agent'         => null,
                        'inner'            => false,
                        'id_order'         => null,
                        'id_exp'           => null,
                    );
                    if (isset($this->accountsMap['mono'][$accountId])) {
                        $feeRow['id_org'] = $this->accountsMap['mono'][$accountId]['id_org'];
                        $feeRow['id_acc'] = $this->accountsMap['mono'][$accountId]['id_acc'];
                    }
                    $data[] = $feeRow;
                    $a++;
                }
            }
        }

        return $data;
    }

    protected function collectPrivatUr($dateFrom)
    {
        $result = request_pb_ur($dateFrom);
        $data = [];
		$nameIndex= 1;

        if (!$result) {
            return $data;
        }

        foreach ($result as $value) {

            $nameKl = isset($value['AUT_CNTR_NAM']) ? $value['AUT_CNTR_NAM'] : null;
            if ($nameKl) {
                foreach ($this->config['pb_name_cleanup_patterns'] as $pattern) {
                    $nameKl = preg_replace($pattern, '', $nameKl);
                }
                $nameKl = trim(preg_replace('/\s+/u', ' ', $nameKl));
            }
            $nameIndex++;
            $accInfo = $this->findMsAccountByAccNumber($value['AUT_MY_ACC']);

            $data[] = [
                'source' => 'pb_ur',
                'bank' => 'pb_ur',
                'bank_account_key' => $value['AUT_MY_ACC'],
                'id_paid' => $this->resolveExternalCode('pb_ur', $value),
				'name' => $this->generatePaymentName($nameIndex, date('Y-m-d H:i:s', strtotime($value['DATE_TIME_DAT_OD_TIM_P']))),
                'type' => ($value['TRANTYPE'] === 'C') ? 'in' : 'out',
                'moment' => date('Y-m-d H:i:s', strtotime($value['DATE_TIME_DAT_OD_TIM_P'])),
                'sum' => (float)$value['SUM_E'] * 100,
                'rate' => 0,
                'description' => $value['OSND'] . PHP_EOL
                    . 'name: ' . $value['AUT_CNTR_NAM'] . PHP_EOL
                    . 'edrpou: ' . $value['AUT_CNTR_CRF'],
                'name_kl' => $nameKl,
                'edrpoy_klient' => $value['AUT_CNTR_CRF'],
                'acc_klient' => $value['AUT_CNTR_ACC'],
                'id_org' => $accInfo ? $accInfo['id_agent'] : null,
                'id_acc' => $accInfo ? $accInfo['id_account'] : null,
                'id_agent' => null,
                'inner' => false,
                'id_order' => null,
                'id_exp' => null,
            ];
        }

        return $data;
    }

    protected function collectUkrsib($dateFrom)
    {
		
		$result = request_ukrsib_sync($dateFrom);
        $data = [];
        $nameIndex= 1;

        if (!$result) {
            return $data;
        }

        foreach ($result as $value) {
            $map = isset($this->accountsMap['ukrsib']['default'])
                ? $this->accountsMap['ukrsib']['default']
                : ['id_org' => null, 'id_acc' => null];
            $nameIndex++;

            $data[] = [
                'source' => 'ukrsib',
                'bank' => 'ukrsib',
                'bank_account_key' => 'default',
                'id_paid' => $this->resolveExternalCode('ukrsib', $value),
                'name' => $this->generatePaymentName($nameIndex, $value['provDate']),
                'type' => !empty($value['credit']) ? 'in' : 'out',
                'moment' => date('Y-m-d H:i:s', floor($value['provDate'] / 1000)),
                'sum' => !empty($value['credit'])
                    ? $value['credit'] * 100
                    : $value['debit'] * 100,
                'rate' => 0,
                'description' => $value['_description'] . PHP_EOL
                    . $value['correspondentName'] . PHP_EOL
                    . $value['correspondentCode'],
                'name_kl' => isset($value['correspondentName']) ? $value['correspondentName'] : null,
                'edrpoy_klient' => !empty($value['correspondentIBAN']) ? $value['correspondentCode'] : null,
                'acc_klient' => !empty($value['correspondentIBAN']) ? $value['correspondentIBAN'] : null,
                'id_org' => $map['id_org'],
                'id_acc' => $map['id_acc'],
                'id_agent' => null,
                'inner' => false,
                'id_order' => null,
                'id_exp' => null,
            ];
        }
        return $data;
    }

    protected function extractMonoName($description)
    {
        foreach ($this->config['mono_name_patterns'] as $pattern) {
            if (mb_strpos($description, $pattern) !== false) {
                return trim(str_replace($pattern, '', $description));
            }
        }

        return null;
    }

    protected function findMsAccountByAccNumber($acc)
    {
        $sql = "SELECT id_account, id_agent FROM acc WHERE acc = '" . addslashes($acc) . "' LIMIT 1";
        $row = Database::fetchRow($this->config['db_name'], $sql);

        if (!$row['ok'] || empty($row['row'])) {
            return null;
        }

        return $row['row'];
    }
	
	protected function resolveExternalCode($source, array $value)
{
    switch ($source) {
        case 'pb_ur':
            if (empty($value['REF'])) {
                throw new RuntimeException('pb_ur payment has empty REF');
            }
            return (string)$value['REF'];

        case 'ukrsib':
            if (empty($value['_id'])) {
                throw new RuntimeException('ukrsib payment has empty _id');
            }
            return (string)$value['_id'];

        case 'mono':
			if (empty($value['id'])) {
				throw new RuntimeException('ukrsib payment has empty _id');
			}
			return (string)$value['id'];
 //           return $this->buildMonoExternalCode($value);

        default:
            throw new RuntimeException('Unknown source for externalCode: ' . $source);
    }
}
}