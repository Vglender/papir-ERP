<?php

class PaymentsSyncService
{
    protected $config;
    protected $accountsMap;

    protected $ms;
    protected $collector;
    protected $duplicateChecker;
    protected $matcher;
    protected $mapper;
	protected $matchRules;
	
	

	public function __construct()
	{
		$this->config = require __DIR__ . '/../config/payments_sync_config.php';
		$this->accountsMap = require __DIR__ . '/../config/accounts_map.php';
		$this->matchRules = require __DIR__ . '/../config/payment_match_rules.php';

		$this->ms = new MoySkladApi();

		$this->collector = new BankPaymentCollector($this->config, $this->accountsMap);
		$this->duplicateChecker = new PaymentDuplicateChecker();
		$this->matcher = new PaymentMatcher($this->config, $this->ms, $this->matchRules);
		$this->mapper = new PaymentMsMapper($this->config, $this->ms);
	}

    public function sync($dateFrom)
    {
        $rawPayments = $this->collector->collect($dateFrom);

        $result = [
            'date_from' => $dateFrom,
            'collected' => count($rawPayments),

            'in_total' => 0,
            'out_total' => 0,

            'duplicates_db_in' => 0,
            'duplicates_db_out' => 0,

            'duplicates_ms_in' => 0,
            'duplicates_ms_out' => 0,

            'skipped_duplicates' => 0,

            'prepared_in' => 0,
            'prepared_out' => 0,

            'created_in' => 0,
            'created_out' => 0,

            'payload_in_count' => 0,
            'payload_out_count' => 0,

            'errors' => [],
            'bank_errors' => $this->collector->getBankErrors(),
        ];

        if (empty($rawPayments)) {
            return $result;
        }

        $paymentsIn = [];
        $paymentsOut = [];

        foreach ($rawPayments as $payment) {
            if (!isset($payment['type'])) {
                $result['errors'][] = [
                    'stage' => 'split_by_type',
                    'message' => 'Payment without type',
                    'payment' => $payment,
                ];
                continue;
            }

            if ($payment['type'] === 'out') {
                $paymentsOut[] = $payment;
            } else {
                $paymentsIn[] = $payment;
            }
        }

        $result['in_total'] = count($paymentsIn);
        $result['out_total'] = count($paymentsOut);

        /**
         * 1. Пакетный отсев дублей по локальной БД
         */
        if (!empty($this->config['checks']['duplicates_db'])) {
            $filteredInDb = $this->duplicateChecker->filterNotExistingInDb('in', $paymentsIn);
            $filteredOutDb = $this->duplicateChecker->filterNotExistingInDb('out', $paymentsOut);

            $result['duplicates_db_in'] = count($filteredInDb['duplicates']);
            $result['duplicates_db_out'] = count($filteredOutDb['duplicates']);

            $paymentsIn = $filteredInDb['new'];
            $paymentsOut = $filteredOutDb['new'];
        } else {
            $result['duplicates_db_in'] = 0;
            $result['duplicates_db_out'] = 0;
        }

        /**
         * 2. Дополнительный отсев дублей по МойСклад
         * Это вторая линия защиты на случай, если БД еще не успела обновиться
         * или документ попал в МС другим путем.
         */
        if (!empty($this->config['checks']['duplicates_ms'])) {
            $filteredInMs = $this->duplicateChecker->filterNotExistingInMs('in', $paymentsIn, $this->ms);
            $filteredOutMs = $this->duplicateChecker->filterNotExistingInMs('out', $paymentsOut, $this->ms);

            $result['duplicates_ms_in'] = count($filteredInMs['duplicates']);
            $result['duplicates_ms_out'] = count($filteredOutMs['duplicates']);

            $paymentsIn = $filteredInMs['new'];
            $paymentsOut = $filteredOutMs['new'];
        } else {
            $result['duplicates_ms_in'] = 0;
            $result['duplicates_ms_out'] = 0;
        }

        $result['skipped_duplicates'] =
            $result['duplicates_db_in']
            + $result['duplicates_db_out']
            + $result['duplicates_ms_in']
            + $result['duplicates_ms_out'];

        /**
         * 3. Збагачення + збереження локально (Papir першим)
         * finance_bank записується ДО відправки в МС.
         * Це робить Papir джерелом правди — МС є приймачем даних.
         */
        $payloadIn  = [];
        $payloadOut = [];
        $prepared   = [];
        $localIds   = []; // extCode → finance_bank.id

        foreach ($paymentsIn as $payment) {
            try {
                $payment = $this->matcher->enrich($payment);
                $prepared[$payment['id_paid']] = $payment;
                $localIds[$payment['id_paid']] = $this->savePaymentLocally($payment);
                $payloadIn[] = $this->mapper->map($payment);
            } catch (Exception $e) {
                $result['errors'][] = [
                    'stage'        => 'prepare_in',
                    'externalCode' => isset($payment['id_paid']) ? $payment['id_paid'] : null,
                    'message'      => $e->getMessage(),
                ];
            }
        }

        foreach ($paymentsOut as $payment) {
            try {
                $payment = $this->matcher->enrich($payment);
                $prepared[$payment['id_paid']] = $payment;
                $localIds[$payment['id_paid']] = $this->savePaymentLocally($payment);
                $payloadOut[] = $this->mapper->map($payment);
            } catch (Exception $e) {
                $result['errors'][] = [
                    'stage'        => 'prepare_out',
                    'externalCode' => isset($payment['id_paid']) ? $payment['id_paid'] : null,
                    'message'      => $e->getMessage(),
                ];
            }
        }

        $result['prepared_in']      = count($payloadIn);
        $result['prepared_out']     = count($payloadOut);
        $result['payload_in_count'] = count($payloadIn);
        $result['payload_out_count'] = count($payloadOut);

        /**
         * 4. Відправка приходів у МС → оновити id_ms в finance_bank
         */
        if (!empty($payloadIn)) {
            $responseIn   = $this->sendBatch('paymentin', $payloadIn);
            $normalizedIn = $this->normalizeBatchResponse($responseIn);

            if (!empty($normalizedIn['rows'])) {
                foreach ($normalizedIn['rows'] as $row) {
                    $extCode = isset($row['externalCode']) ? $row['externalCode'] : null;
                    if ($extCode && isset($prepared[$extCode])) {
                        $result['created_in']++;
                        // Оновити id_ms після підтвердження від МС
                        if (!empty($localIds[$extCode]) && !empty($row['id'])) {
                            $this->updatePaymentMsId($localIds[$extCode], $row, 'in');
                        }
                    }
                }
            }

            if (!empty($normalizedIn['errors'])) {
                $result['errors'][] = [
                    'stage'   => 'send_paymentin',
                    'message' => 'Batch contains element-level errors',
                    'details' => $this->flattenBatchErrors('paymentin', $normalizedIn['errors'], $payloadIn),
                ];
            }
        }

        /**
         * 5. Відправка витрат у МС → оновити id_ms в finance_bank
         */
        if (!empty($payloadOut)) {
            $responseOut   = $this->sendBatch('paymentout', $payloadOut);
            $normalizedOut = $this->normalizeBatchResponse($responseOut);

            if (!empty($normalizedOut['rows'])) {
                foreach ($normalizedOut['rows'] as $row) {
                    $extCode = isset($row['externalCode']) ? $row['externalCode'] : null;
                    if ($extCode && isset($prepared[$extCode])) {
                        $result['created_out']++;
                        if (!empty($localIds[$extCode]) && !empty($row['id'])) {
                            $this->updatePaymentMsId($localIds[$extCode], $row, 'out');
                        }
                    }
                }
            }

            if (!empty($normalizedOut['errors'])) {
                $result['errors'][] = [
                    'stage'   => 'send_paymentout',
                    'message' => 'Batch contains element-level errors',
                    'details' => $this->flattenBatchErrors('paymentout', $normalizedOut['errors'], $payloadOut),
                ];
            }
        }
		
		if (!empty($result['errors'])) {
			$this->logError($result['errors']);
		}

        return $result;
    }

    public function collectOnly($dateFrom)
    {
        return $this->collector->collect($dateFrom);
    }

    public function prepareOnly($dateFrom)
    {
        $rawPayments = $this->collector->collect($dateFrom);

        $paymentsIn = [];
        $paymentsOut = [];

        foreach ($rawPayments as $payment) {
            if (isset($payment['type']) && $payment['type'] === 'out') {
                $paymentsOut[] = $payment;
            } else {
                $paymentsIn[] = $payment;
            }
        }

        $filteredInDb = $this->duplicateChecker->filterNotExistingInDb('in', $paymentsIn);
        $filteredOutDb = $this->duplicateChecker->filterNotExistingInDb('out', $paymentsOut);

        $filteredInMs = $this->duplicateChecker->filterNotExistingInMs('in', $filteredInDb['new'], $this->ms);
        $filteredOutMs = $this->duplicateChecker->filterNotExistingInMs('out', $filteredOutDb['new'], $this->ms);

        $payloadIn = [];
        $payloadOut = [];

        foreach ($filteredInMs['new'] as $payment) {
            $payment = $this->matcher->enrich($payment);
            $payloadIn[] = $this->mapper->map($payment);
        }

        foreach ($filteredOutMs['new'] as $payment) {
            $payment = $this->matcher->enrich($payment);
            $payloadOut[] = $this->mapper->map($payment);
        }

        return [
            'raw' => $rawPayments,
            'in' => [
                'duplicates_db' => $filteredInDb['duplicates'],
                'duplicates_ms' => $filteredInMs['duplicates'],
                'new' => $filteredInMs['new'],
                'payload' => $payloadIn,
            ],
            'out' => [
                'duplicates_db' => $filteredOutDb['duplicates'],
                'duplicates_ms' => $filteredOutMs['duplicates'],
                'new' => $filteredOutMs['new'],
                'payload' => $payloadOut,
            ],
        ];
    }
	public function syncDetailed($dateFrom, $realSend = false)
{
    $rawPayments = $this->collector->collect($dateFrom);

    $report = [
        'date_from' => $dateFrom,
        'real_send' => (bool)$realSend,
        'collected' => count($rawPayments),

        'in_total' => 0,
        'out_total' => 0,

        'duplicates_db_in' => 0,
        'duplicates_db_out' => 0,
        'duplicates_ms_in' => 0,
        'duplicates_ms_out' => 0,
        'skipped_duplicates' => 0,

        'prepared_in' => 0,
        'prepared_out' => 0,

        'orders_found_in' => 0,
        'orders_found_out' => 0,
        'orders_not_found_in' => 0,
        'orders_not_found_out' => 0,

        'created_in' => 0,
        'created_out' => 0,

        'payload_in_count' => 0,
        'payload_out_count' => 0,

        'banks' => [],
        'dates' => [],
        'samples' => [
            'raw' => [],
            'prepared_in' => [],
            'prepared_out' => [],
        ],
		'checks' => [
			'duplicates_db' => !empty($this->config['checks']['duplicates_db']['enabled']),
			'duplicates_ms' => !empty($this->config['checks']['duplicates_ms']['enabled']),
		],
		'skipped_due_to_db_check_error' => 0,
		'skipped_due_to_ms_check_error' => 0,
		'fatal_duplicate_check_error' => false,

        'errors' => [],
    ];
	
	$report['raw_payments'] = $rawPayments;

    if (empty($rawPayments)) {
        return $report;
    }

    foreach ($rawPayments as $payment) {
        $bank = isset($payment['bank']) ? $payment['bank'] : 'unknown';
        $date = isset($payment['moment']) ? substr($payment['moment'], 0, 10) : 'unknown';

        if (!isset($report['banks'][$bank])) {
            $report['banks'][$bank] = [
                'total' => 0,
                'in' => 0,
                'out' => 0,
            ];
        }

        if (!isset($report['dates'][$date])) {
            $report['dates'][$date] = [
                'total' => 0,
                'in' => 0,
                'out' => 0,
            ];
        }

        $report['banks'][$bank]['total']++;
        $report['dates'][$date]['total']++;

        if (isset($payment['type']) && $payment['type'] === 'out') {
            $report['banks'][$bank]['out']++;
            $report['dates'][$date]['out']++;
            $report['out_total']++;
        } else {
            $report['banks'][$bank]['in']++;
            $report['dates'][$date]['in']++;
            $report['in_total']++;
        }
    }

    $paymentsIn = [];
    $paymentsOut = [];

    foreach ($rawPayments as $payment) {
        if (!isset($payment['type'])) {
            $report['errors'][] = [
                'stage' => 'split_by_type',
                'message' => 'Payment without type',
                'payment' => $payment,
            ];
            continue;
        }

        if ($payment['type'] === 'out') {
            $paymentsOut[] = $payment;
        } else {
            $paymentsIn[] = $payment;
        }
    }

        /**
     * 1. Пакетный отсев дублей по локальной БД
     * Если strict=true и БД недоступна — дальше не идем.
     */
    try {
        if (!empty($this->config['checks']['duplicates_db']['enabled'])) {
            $filteredInDb = $this->duplicateChecker->filterNotExistingInDb('in', $paymentsIn);
            $filteredOutDb = $this->duplicateChecker->filterNotExistingInDb('out', $paymentsOut);

            $report['duplicates_db_in'] = count($filteredInDb['duplicates']);
            $report['duplicates_db_out'] = count($filteredOutDb['duplicates']);

            $paymentsIn = $filteredInDb['new'];
            $paymentsOut = $filteredOutDb['new'];
        } else {
            $report['duplicates_db_in'] = 0;
            $report['duplicates_db_out'] = 0;
        }
    } catch (Throwable $e) {
        $report['errors'][] = [
            'stage' => 'duplicates_db',
            'message' => $e->getMessage(),
        ];

        $report['skipped_due_to_db_check_error'] = count($paymentsIn) + count($paymentsOut);

        if (!empty($this->config['checks']['duplicates_db']['strict'])) {
            $report['fatal_duplicate_check_error'] = true;
            $report['skipped_duplicates'] =
                $report['duplicates_db_in']
                + $report['duplicates_db_out']
                + $report['duplicates_ms_in']
                + $report['duplicates_ms_out'];

            return $report;
        }
    }

    /**
     * 2. Дополнительный отсев дублей по МойСклад
     * Если strict=false и МС недоступен — просто откладываем платежи до следующего cron.
     */
    try {
        if (!empty($this->config['checks']['duplicates_ms']['enabled'])) {
            $filteredInMs = $this->duplicateChecker->filterNotExistingInMs('in', $paymentsIn, $this->ms);
            $filteredOutMs = $this->duplicateChecker->filterNotExistingInMs('out', $paymentsOut, $this->ms);

            $report['duplicates_ms_in'] = count($filteredInMs['duplicates']);
            $report['duplicates_ms_out'] = count($filteredOutMs['duplicates']);

            $paymentsIn = $filteredInMs['new'];
            $paymentsOut = $filteredOutMs['new'];
        } else {
            $report['duplicates_ms_in'] = 0;
            $report['duplicates_ms_out'] = 0;
        }
    } catch (Throwable $e) {
        $report['errors'][] = [
            'stage' => 'duplicates_ms',
            'message' => $e->getMessage(),
        ];

        $report['skipped_due_to_ms_check_error'] = count($paymentsIn) + count($paymentsOut);

        if (!empty($this->config['checks']['duplicates_ms']['strict'])) {
            $report['fatal_duplicate_check_error'] = true;
            $report['skipped_duplicates'] =
                $report['duplicates_db_in']
                + $report['duplicates_db_out']
                + $report['duplicates_ms_in']
                + $report['duplicates_ms_out'];

            return $report;
        }

        /**
         * non-strict режим:
         * не отправляем дальше, а откладываем до следующего cron
         */
        return $report;
    }

    $report['skipped_duplicates'] =
        $report['duplicates_db_in']
        + $report['duplicates_db_out']
        + $report['duplicates_ms_in']
        + $report['duplicates_ms_out'];
		
    $payloadIn = [];
    $payloadOut = [];
    $prepared = [];

    foreach ($paymentsIn as $payment) {
        try {

            $payment = $this->matcher->enrich($payment);
            $prepared[$payment['id_paid']] = $payment;
            $payloadIn[] = $this->mapper->map($payment);

            if (!empty($payment['id_order'])) {
                $report['orders_found_in']++;
            } else {
                $report['orders_not_found_in']++;
            }

            if (count($report['samples']['prepared_in']) < 10) {
                $report['samples']['prepared_in'][] = $payment;
            }
        } catch (Exception $e) {
            $report['errors'][] = [
                'stage' => 'prepare_in',
                'externalCode' => isset($payment['id_paid']) ? $payment['id_paid'] : null,
                'message' => $e->getMessage(),
            ];
        }
    }

    foreach ($paymentsOut as $payment) {
        try {
            $payment = $this->matcher->enrich($payment);
            $prepared[$payment['id_paid']] = $payment;
            $payloadOut[] = $this->mapper->map($payment);

            if (!empty($payment['id_order'])) {
                $report['orders_found_out']++;
            } else {
                $report['orders_not_found_out']++;
            }

            if (count($report['samples']['prepared_out']) < 10) {
                $report['samples']['prepared_out'][] = $payment;
            }
        } catch (Exception $e) {
            $report['errors'][] = [
                'stage' => 'prepare_out',
                'externalCode' => isset($payment['id_paid']) ? $payment['id_paid'] : null,
                'message' => $e->getMessage(),
            ];
        }
    }

    $report['prepared_in'] = count($payloadIn);
    $report['prepared_out'] = count($payloadOut);
    $report['payload_in_count'] = count($payloadIn);
    $report['payload_out_count'] = count($payloadOut);
    $report['samples']['raw'] = array_slice($rawPayments, 0, 10);

    if ($realSend && !empty($payloadIn)) {
        $responseIn = $this->sendBatch('paymentin', $payloadIn);

        if (!empty($responseIn['rows'])) {
            foreach ($responseIn['rows'] as $row) {
                if (!empty($row['externalCode']) && isset($prepared[$row['externalCode']])) {
                    $report['created_in']++;
                }
            }
        } elseif (!empty($responseIn['errors'])) {
            $report['errors'][] = [
                'stage' => 'send_paymentin',
                'message' => 'Errors returned from MoySklad',
                'details' => $responseIn['errors'],
            ];
        } else {
            $report['errors'][] = [
                'stage' => 'send_paymentin',
                'message' => 'Empty response from MoySklad',
            ];
        }
    }

    if ($realSend && !empty($payloadOut)) {
        $responseOut = $this->sendBatch('paymentout', $payloadOut);

        if (!empty($responseOut['rows'])) {
            foreach ($responseOut['rows'] as $row) {
                if (!empty($row['externalCode']) && isset($prepared[$row['externalCode']])) {
                    $report['created_out']++;
                }
            }
        } elseif (!empty($responseOut['errors'])) {
            $report['errors'][] = [
                'stage' => 'send_paymentout',
                'message' => 'Errors returned from MoySklad',
                'details' => $responseOut['errors'],
            ];
        } else {
            $report['errors'][] = [
                'stage' => 'send_paymentout',
                'message' => 'Empty response from MoySklad',
            ];
        }
    }

    return $report;
}

		protected function normalizeBatchResponse($response)
		{
			$result = [
				'rows' => [],
				'errors' => [],
				'raw' => $response,
			];

			if (empty($response)) {
				return $result;
			}

			if (isset($response['errors'])) {
				$result['errors'] = $response['errors'];
				return $result;
			}

			if (isset($response['rows']) && is_array($response['rows'])) {
				$result['rows'] = $response['rows'];
				return $result;
			}

			if (is_array($response) && isset($response[0])) {
				foreach ($response as $item) {
					if (isset($item['errors'])) {
						$result['errors'][] = $item;
					} else {
						$result['rows'][] = $item;
					}
				}
			}

			return $result;
		}
		
		protected function flattenBatchErrors($entity, array $errorItems, array $payload)
			{
				$result = [];

				foreach ($errorItems as $index => $item) {
					$payloadItem = isset($payload[$index]) ? $payload[$index] : null;

					if (!empty($item['errors']) && is_array($item['errors'])) {
						foreach ($item['errors'] as $error) {
							$result[] = [
								'entity' => $entity,
								'payload_index' => $index,
								'externalCode' => isset($payloadItem['externalCode']) ? $payloadItem['externalCode'] : null,
								'name' => isset($payloadItem['name']) ? $payloadItem['name'] : null,
								'agent_href' => isset($payloadItem['agent']['meta']['href']) ? $payloadItem['agent']['meta']['href'] : null,
								'organization_href' => isset($payloadItem['organization']['meta']['href']) ? $payloadItem['organization']['meta']['href'] : null,
								'organizationAccount_href' => isset($payloadItem['organizationAccount']['meta']['href']) ? $payloadItem['organizationAccount']['meta']['href'] : null,
								'error' => isset($error['error']) ? $error['error'] : 'Unknown error',
								'code' => isset($error['code']) ? $error['code'] : null,
								'line' => isset($error['line']) ? $error['line'] : null,
								'column' => isset($error['column']) ? $error['column'] : null,
							];
						}
					}
				}

				return $result;
			}
			
		protected function logError($data)
			{
				file_put_contents(
					PAYMENTS_SYNC_ROOT . '/storage/errors.log',
					date('Y-m-d H:i:s') . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL,
					FILE_APPEND
				);
			}

	/**
	 * Зберегти платіж у finance_bank ДО відправки в МС.
	 * id_ms поки null — заповнюється після підтвердження від МС через updatePaymentMsId().
	 * Повертає finance_bank.id (insert_id).
	 *
	 * payment['sum'] — в копійках (формат банківських API), зберігаємо в гривнях (÷100).
	 */
	protected function savePaymentLocally(array $payment)
	{
		$direction   = ($payment['type'] === 'out') ? 'out' : 'in';
		$extCode     = isset($payment['id_paid']) ? (string)$payment['id_paid'] : '';
		$sum         = round((float)$payment['sum'] / 100, 2);
		$moment      = isset($payment['moment']) ? $payment['moment'] : null;
		$agentMs     = isset($payment['id_agent']) ? (string)$payment['id_agent'] : null;
		$orgMs       = isset($payment['id_org'])   ? (string)$payment['id_org']   : null;
		$isMoving    = !empty($payment['inner']) ? 1 : 0;
		$expItemMs   = isset($payment['id_exp'])  ? (string)$payment['id_exp']  : null;
		$description = isset($payment['description']) ? (string)$payment['description'] : null;
		$source      = isset($payment['bank']) ? (string)$payment['bank'] : 'bank_sync';

		// Визначити expense_category_id за UUID статті витрат МС
		$expCategoryId = null;
		if ($expItemMs) {
			$catRow = Database::fetchRow('Papir',
				"SELECT id FROM finance_expense_category
				 WHERE expense_item_ms = '" . Database::escape('Papir', $expItemMs) . "'
				 LIMIT 1"
			);
			if ($catRow['ok'] && !empty($catRow['row'])) {
				$expCategoryId = (int)$catRow['row']['id'];
			}
		}

		$insertResult = Database::insert('Papir', 'finance_bank', array(
			'direction'           => $direction,
			'moment'              => $moment,
			'sum'                 => $sum,
			'agent_ms'            => $agentMs,
			'organization_ms'     => $orgMs,
			'is_moving'           => $isMoving,
			'is_posted'           => 0,
			'expense_item_ms'     => $expItemMs,
			'expense_category_id' => $expCategoryId,
			'description'         => $description,
			'external_code'       => $extCode ?: null,
			'source'              => $source,
		));

		if (!$insertResult['ok'] || empty($insertResult['insert_id'])) {
			return null;
		}

		$localId = (int)$insertResult['insert_id'];

		// Якщо є прив'язаний замовлення — відразу записати в document_link
		if (!empty($payment['id_order'])) {
			$this->saveOrderLink($localId, $direction, $payment);
		}

		return $localId;
	}

	/**
	 * Записати зв'язок платіж → замовлення в document_link.
	 * from_ms_id поки null — заповнюється через updatePaymentMsId() після відповіді МС.
	 */
	protected function saveOrderLink($localId, $direction, array $payment)
	{
		$fromType  = ($direction === 'in') ? 'paymentin' : 'paymentout';
		$orderMsId = (string)$payment['id_order'];

		// Знайти Papir customerorder.id за МС UUID
		$orderRow = Database::fetchRow('Papir',
			"SELECT id FROM customerorder
			 WHERE id_ms = '" . Database::escape('Papir', $orderMsId) . "'
			 LIMIT 1"
		);
		$toId = ($orderRow['ok'] && !empty($orderRow['row'])) ? (int)$orderRow['row']['id'] : null;

		Database::insert('Papir', 'document_link', array(
			'from_type'  => $fromType,
			'from_id'    => $localId,
			'from_ms_id' => null,
			'to_type'    => 'customerorder',
			'to_id'      => $toId,
			'to_ms_id'   => $orderMsId,
			'link_type'  => 'payment',
			'linked_sum' => round((float)$payment['sum'] / 100, 2),
		));
	}

	/**
	 * Після підтвердження від МС: оновити id_ms + doc_number + is_posted у finance_bank
	 * та from_ms_id у document_link.
	 *
	 * $msRow — рядок відповіді МС (contains id, name, applicable, ...)
	 */
	protected function updatePaymentMsId($localId, array $msRow, $direction)
	{
		if (!$localId) {
			return;
		}

		$idMs      = isset($msRow['id'])   ? trim((string)$msRow['id'])   : null;
		$docNumber = isset($msRow['name']) ? (string)$msRow['name']       : null;
		$isPosted  = !empty($msRow['applicable']) ? 1 : 0;

		Database::update('Papir', 'finance_bank', array(
			'id_ms'      => $idMs,
			'doc_number' => $docNumber,
			'is_posted'  => $isPosted,
		), array('id' => $localId));

		if ($idMs) {
			Database::query('Papir',
				"UPDATE document_link SET from_ms_id = '" . Database::escape('Papir', $idMs) . "'
				 WHERE from_id = {$localId} AND from_ms_id IS NULL"
			);
		}
	}

	protected function sendBatch($entity, array $payload, $maxTry = 5)
	{
		$link = $this->ms->getEntityBaseUrl() . $entity;
		$try = 0;
		$lastResponse = [];

/* 		file_put_contents(
			__DIR__ . '/../storage/debug_' . $entity . '_payload.json',
			json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
		); */

		while ($try < $maxTry) {
			$response = $this->ms->querySend($link, $payload, 'POST');
			$response = json_decode(json_encode($response), true);

			$lastResponse = $response;
/* 
			file_put_contents(
				__DIR__ . '/../storage/debug_' . $entity . '_response.json',
				json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
			); */

			if (!empty($response)) {
				return $response;
			}

			$try++;
			usleep(50000);
		}

		return !empty($lastResponse) ? $lastResponse : [
			'errors' => [
				['error' => 'Failed to send batch to ' . $entity]
			]
		];
	}
}