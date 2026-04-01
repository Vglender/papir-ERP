<?php

class PaymentMatcher
{
    protected $config;
    protected $ms;
    protected $rules;

	public function __construct(array $config, MoySkladApi $ms, array $rules = [])
	{
		$this->config = $config;
		$this->ms = $ms;
		$this->rules = $rules;

		if (empty($this->rules['defaults'])) {
			throw new RuntimeException('PaymentMatcher rules not loaded: defaults section is missing');
		}
	}
    public function enrich(array $payment)
    {
        if (!isset($payment['inner'])) {
            $payment['inner'] = false;
        }
        if (!isset($payment['id_agent'])) {
            $payment['id_agent'] = null;
        }
        if (!isset($payment['id_order'])) {
            $payment['id_order'] = null;
        }
        if (!isset($payment['id_exp'])) {
            $payment['id_exp'] = null;
        }

        $payment = $this->resolveInternalByAccount($payment);
        $payment = $this->resolveAgentByCode($payment);
        $payment = $this->resolveAgentByName($payment);
        $payment = $this->applyDescriptionRules($payment);
        $payment = $this->resolveLinkedOrder($payment);

        if ($payment['type'] === 'out') {
            if (empty($payment['id_agent'])) {
                $payment['id_agent'] = $this->rules['defaults']['agent_id'];
            }
            if (empty($payment['id_exp'])) {
                $payment['id_exp'] = $this->rules['defaults']['expense_item_id'];
            }
        } else {
            if (empty($payment['id_agent'])) {
                $payment['id_agent'] = $this->rules['defaults']['agent_id'];
            }
        }

        return $payment;
    }

    protected function resolveInternalByAccount(array $payment)
    {
        if (empty($payment['acc_klient'])) {
            return $payment;
        }

        // Перевіряємо по Papir.our_bank_accounts (замість ms.acc — зеркало більше не потрібне)
        $iban = addslashes($payment['acc_klient']);
        $sql  = "SELECT organization_ms FROM our_bank_accounts WHERE iban = '{$iban}' LIMIT 1";
        $row  = Database::fetchRow('Papir', $sql);

        if (!$row['ok']) {
            throw new RuntimeException('resolveInternalByAccount failed: ' . $row['error']);
        }

        if (!empty($row['row']['organization_ms'])) {
            $payment['id_agent'] = $row['row']['organization_ms'];
            $payment['inner']    = true;
        }

        return $payment;
    }

    protected function resolveAgentByCode(array $payment)
    {
        if (!empty($payment['id_agent']) || empty($payment['edrpoy_klient'])) {
            return $payment;
        }

        $link = $this->ms->getEntityBaseUrl() . 'counterparty?filter=' . urlencode('code=' . $payment['edrpoy_klient']);
        $response = $this->safeMsQuery($link);

        if (!empty($response['meta']['size']) && (int)$response['meta']['size'] === 1) {
            $payment['id_agent'] = $response['rows'][0]['id'];
        }

        return $payment;
    }

    protected function resolveAgentByName(array $payment)
    {
        if (!empty($payment['id_agent']) || empty($payment['name_kl'])) {
            return $payment;
        }

        $link = $this->ms->getEntityBaseUrl() . 'counterparty?search=' . urlencode($payment['name_kl']);
        $response = $this->safeMsQuery($link);

        if (!empty($response['meta']['size']) && (int)$response['meta']['size'] === 1) {
            $payment['id_agent'] = $response['rows'][0]['id'];
        }

        if (stripos($payment['name_kl'], 'Укрпошта') !== false) {
            $payment['id_agent'] = $this->rules['defaults']['ukrposhta_agent_id'];
        }

        return $payment;
    }

	protected function applyDescriptionRules(array $payment)
	{
		$description = isset($payment['description']) ? $payment['description'] : '';

		$dividentKeywords = isset($this->rules['divident_keywords']) && is_array($this->rules['divident_keywords'])
			? $this->rules['divident_keywords']
			: [];

		$agentRules = isset($this->rules['agent_rules']) && is_array($this->rules['agent_rules'])
			? $this->rules['agent_rules']
			: [];

		/**
		 * 1. Сначала точные/специальные правила
		 * Они важнее общих бытовых расходов
		 */
		foreach ($agentRules as $rule) {
			if (!isset($rule[0], $rule[1], $rule[2], $rule[3])) {
				continue;
			}

			list($needle, $agentId, $expenseItemId, $inner) = $rule;

			if ($description !== '' && mb_stripos($description, $needle) !== false) {
				$payment['id_agent'] = $agentId;
				$payment['inner'] = (bool)$inner;

				if ($payment['type'] === 'out') {
					$payment['id_exp'] = $expenseItemId;
				}

				return $payment;
			}
		}

		/**
		 * 2. Потом уже общие "личные/бытовые" расходы
		 */
		if ($payment['type'] === 'out') {
			foreach ($dividentKeywords as $keyword) {
				if ($description !== '' && mb_stripos($description, $keyword) !== false) {
					$payment['id_agent'] = $this->rules['defaults']['divident_agent_id'];
					$payment['id_exp'] = $this->rules['defaults']['divident_expense_item_id'];
					return $payment;
				}
			}
		}

		return $payment;
	}

    protected function resolveLinkedOrder(array $payment)
    {
        if (!empty($payment['id_order'])) {
            return $payment;
        }

        $order = $this->searchOrder(
            $payment['type'],
            $payment['sum'],
            $payment['moment'],
            $payment['id_agent'],
            isset($payment['description']) ? $payment['description'] : null
        );

        if (!empty($order['id'])) {
            $payment['id_order'] = $order['id'];

			if (!empty($order['agent']['meta']['href']) && empty($payment['inner'])) {
				$parts = explode('/', $order['agent']['meta']['href']);
				$payment['id_agent'] = end($parts);
			}
        }

        return $payment;
    }

	protected function searchOrder($type, $sum, $moment, $idAgent = null, $description = null)
	{
		$orderList = [];

		if ($idAgent) {
			if ($type === 'in') {
				$link = $this->ms->getEntityBaseUrl()
					. 'customerorder?filter='
					. urlencode('agent=' . $this->ms->getEntityBaseUrl() . 'counterparty/' . $idAgent)
					. '&order=moment,desc';
			} else {
				$link = $this->ms->getEntityBaseUrl()
					. 'purchaseorder?filter='
					. urlencode('agent=' . $this->ms->getEntityBaseUrl() . 'counterparty/' . $idAgent)
					. '&order=moment,desc';
			}

			$orderList = $this->safeMsQuery($link);

			if (!empty($orderList['rows'])) {
				foreach ($orderList['rows'] as $row) {
					$delta = abs((int)$row['sum'] - (int)$sum);
					if ($delta <= 100) {
						return $row;
					}
				}

				return $orderList['rows'][0];
			}
		}

		if ($description) {
			if (preg_match('/(- prom - )[\d]{9}/u', $description, $nameOrd)) {
				$name = preg_replace('/(- prom - )/u', '', $nameOrd[0]);
				$link = $this->ms->getEntityBaseUrl()
					. 'customerorder?filter=' . urlencode('name=' . $name . 'PROM')
					. '&order=moment,desc';

				$orderList = $this->safeMsQuery($link);

				if (!empty($orderList['meta']['size']) && (int)$orderList['meta']['size'] === 1) {
					return $orderList['rows'][0];
				}
			} elseif (preg_match('/LiqPay/u', $description)) {
				$timeLiq = date('Y-m-d H:i:s', strtotime($moment . ' -2 hours'));
				$sum1 = $sum - 200;
				$sum2 = $sum + 200;

				$filter = $this->ms->getEntityBaseUrl()
					. 'customerorder/metadata/attributes/'
					. $this->config['customerorder_liqpay_attribute_id']
					. '~LiqPay;moment>' . $timeLiq . ';sum>' . $sum1 . ';sum<' . $sum2;

				$link = $this->ms->getEntityBaseUrl() . 'customerorder?filter=' . urlencode($filter);
				$orderListLiq = $this->safeMsQuery($link);

				if (!empty($orderListLiq['rows'])) {
					foreach ($orderListLiq['rows'] as $liqpay) {
						if (empty($liqpay['payedSum'])) {
							return $liqpay;
						}
					}
				}
			}
		}

		$sDate = date('Y-m-d H:i:s', strtotime($moment) - 36000);
		$entity = ($type === 'in') ? 'customerorder' : 'purchaseorder';

		/**
		 * 1. Сначала точное совпадение по сумме
		 */
		$filterExact = 'sum=' . $sum . ';moment>' . $sDate;
		$link = $this->ms->getEntityBaseUrl() . $entity . '?filter=' . urlencode($filterExact);
		$orderList = $this->safeMsQuery($link);

		if (!empty($orderList['meta']['size'])) {
			if ((int)$orderList['meta']['size'] === 1) {
				return $orderList['rows'][0];
			}

			if ((int)$orderList['meta']['size'] > 1) {
				foreach ($orderList['rows'] as $value) {
					if (($value['sum'] - $value['payedSum']) >= $sum) {
						return $value;
					}
				}

				return $orderList['rows'][0];
			}
		}

		/**
		 * 2. Если не нашли — ищем с допуском ±100
		 */
		$sumMin = $sum - 100;
		$sumMax = $sum + 100;
		$filterRange = 'sum>' . $sumMin . ';sum<' . $sumMax . ';moment>' . $sDate;

		$link = $this->ms->getEntityBaseUrl() . $entity . '?filter=' . urlencode($filterRange);
		$orderList = $this->safeMsQuery($link);

		if (!empty($orderList['meta']['size'])) {
			if ((int)$orderList['meta']['size'] === 1) {
				return $orderList['rows'][0];
			}

			if ((int)$orderList['meta']['size'] > 1) {
				foreach ($orderList['rows'] as $value) {
					if (($value['sum'] - $value['payedSum']) >= $sum) {
						return $value;
					}
				}

				usort($orderList['rows'], function ($a, $b) use ($sum) {
					$deltaA = abs((int)$a['sum'] - (int)$sum);
					$deltaB = abs((int)$b['sum'] - (int)$sum);
					return $deltaA - $deltaB;
				});

				return $orderList['rows'][0];
			}
		}

		return null;
	}

    protected function safeMsQuery($link, $maxTry = 5)
    {
        $try = 0;

        while ($try < $maxTry) {
            $response = $this->ms->query($link);
            $response = json_decode(json_encode($response), true);

            if (!isset($response['errors']) && !empty($response)) {
                return $response;
            }

            usleep(42000);
            $try++;
        }

        return [];
    }
}