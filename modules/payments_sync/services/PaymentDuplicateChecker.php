<?php

class PaymentDuplicateChecker
{
    protected $dbName;
    protected $ms;

    public function __construct($dbName, MoySkladApi $ms = null)
    {
        $this->dbName = $dbName;
        $this->ms = $ms;
    }

    /**
     * Одиночная проверка дубля в локальной БД
     */
	public function exists($type, $externalCode)
	{
		$table = ($type === 'out') ? 'paymentout' : 'paymentin';

		$sql = "SELECT id
				FROM `{$table}`
				WHERE externalCode = '" . addslashes($externalCode) . "'
				LIMIT 1";

		$row = Database::fetchRow($this->dbName, $sql);

		if (!$row['ok']) {
			throw new RuntimeException(
				'DB duplicate exists() failed for table ' . $table . ': '
				. (isset($row['error']) ? $row['error'] : 'unknown error')
			);
		}

		return !empty($row['row']);
	}

    /**
     * Получить id существующего документа в локальной БД
     */
    public function findId($type, $externalCode)
    {
        $table = ($type === 'out') ? 'paymentout' : 'paymentin';

        $sql = "SELECT id
                FROM `{$table}`
                WHERE externalCode = '" . addslashes($externalCode) . "'
                LIMIT 1";

        $row = Database::fetchRow($this->dbName, $sql);

        if ($row['ok'] && !empty($row['row']['id'])) {
            return $row['row']['id'];
        }

        return null;
    }

    /**
     * Пакетно получить уже существующие externalCode из локальной БД
     */
	public function getExistingExternalCodes($type, array $externalCodes)
	{
		if (empty($externalCodes)) {
			return [];
		}

		$table = ($type === 'out') ? 'paymentout' : 'paymentin';

		$escaped = [];
		foreach ($externalCodes as $code) {
			if ($code === null || $code === '') {
				continue;
			}
			$escaped[] = "'" . addslashes($code) . "'";
		}

		if (empty($escaped)) {
			return [];
		}

		$sql = "SELECT externalCode
				FROM `{$table}`
				WHERE externalCode IN (" . implode(',', $escaped) . ")";

		$rows = Database::fetchAll($this->dbName, $sql);

		if (!$rows['ok']) {
			throw new RuntimeException(
				'DB duplicate check failed for table ' . $table . ': '
				. (isset($rows['error']) ? $rows['error'] : 'unknown error')
			);
		}

		if (empty($rows['rows'])) {
			return [];
		}

		$result = [];
		foreach ($rows['rows'] as $row) {
			if (isset($row['externalCode'])) {
				$result[] = $row['externalCode'];
			}
		}

		return $result;
	}

    /**
     * Одиночная проверка дубля в МойСклад
     * Вторая линия защиты
     */
    public function existsInMs($type, $externalCode)
    {
        if (!$this->ms || !$externalCode) {
            return false;
        }

        $entity = ($type === 'out') ? 'paymentout' : 'paymentin';
        $link = $this->ms->getEntityBaseUrl()
            . $entity
            . '?filter=' . urlencode('externalCode=' . $externalCode);

        $response = $this->ms->query($link);
        $response = json_decode(json_encode($response), true);

        return !empty($response['rows']);
    }

    /**
     * Пакетный отсев дублей по локальной БД
     * Возвращает:
     * [
     *   'new' => [...],
     *   'duplicates' => [...]
     * ]
     */
    public function filterNotExistingInDb($type, array $payments)
    {
        if (empty($payments)) {
            return [
                'new' => [],
                'duplicates' => [],
            ];
        }

        $codes = [];
        foreach ($payments as $payment) {
            if (!empty($payment['id_paid'])) {
                $codes[] = $payment['id_paid'];
            }
        }

        $existingCodes = $this->getExistingExternalCodes($type, $codes);
        $existingMap = array_flip($existingCodes);

        $new = [];
        $duplicates = [];

        foreach ($payments as $payment) {
            $code = isset($payment['id_paid']) ? $payment['id_paid'] : null;

            if ($code && isset($existingMap[$code])) {
                $duplicates[] = $payment;
            } else {
                $new[] = $payment;
            }
        }

        return [
            'new' => $new,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * Дополнительный отсев дублей по МойСклад
     * Сейчас точечный, потому что API МС не очень удобно пакетно фильтровать по externalCode
     */
    public function filterNotExistingInMs($type, array $payments)
    {
        if (!$this->ms || empty($payments)) {
            return [
                'new' => $payments,
                'duplicates' => [],
            ];
        }

        $new = [];
        $duplicates = [];

        foreach ($payments as $payment) {
            $code = isset($payment['id_paid']) ? $payment['id_paid'] : null;

            if ($code && $this->existsInMs($type, $code)) {
                $duplicates[] = $payment;
            } else {
                $new[] = $payment;
            }
        }

        return [
            'new' => $new,
            'duplicates' => $duplicates,
        ];
    }
}