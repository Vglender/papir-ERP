<?php

class PaymentMsMapper
{
    protected $config;
    protected $ms;

    public function __construct(array $config, MoySkladApi $ms)
    {
        $this->config = $config;
        $this->ms = $ms;
    }

    public function map(array $payment)
    {
        return ($payment['type'] === 'out')
            ? $this->mapPaymentOut($payment)
            : $this->mapPaymentIn($payment);
    }
	
	protected function normalizeMomentForMs($moment)
	{
		if (empty($this->config['time']['enabled'])) {
			return $moment;
		}

		$sourceTimezone = !empty($this->config['time']['source_timezone'])
			? $this->config['time']['source_timezone']
			: 'Europe/Kyiv';

		$targetTimezone = !empty($this->config['time']['target_timezone'])
			? $this->config['time']['target_timezone']
			: 'Europe/Moscow';

		$manualShiftMinutes = isset($this->config['time']['manual_shift_minutes'])
			? (int)$this->config['time']['manual_shift_minutes']
			: 0;

		$dt = new DateTime($moment, new DateTimeZone($sourceTimezone));
		$dt->setTimezone(new DateTimeZone($targetTimezone));

		if ($manualShiftMinutes !== 0) {
			$modifier = ($manualShiftMinutes > 0 ? '+' : '') . $manualShiftMinutes . ' minutes';
			$dt->modify($modifier);
		}

		return $dt->format('Y-m-d H:i:s');
	}

    protected function mapPaymentIn(array $value)
    {
        $payload = [
            'externalCode' => $value['id_paid'],
            'name' => (string)$value['name'],
            'moment' => $this->normalizeMomentForMs($value['moment']),
            'sum' => $value['sum'],
            'applicable' => !empty($value['inner']) || !empty($value['id_order']),
            'state' => [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'paymentin/metadata/states/' . $this->config['paymentin_state_id'],
                    'metadataHref' => $this->ms->getEntityBaseUrl() . 'paymentin/metadata',
                    'type' => 'state',
                    'mediaType' => 'application/json',
                ]
            ],
            'organization' => [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'organization/' . $value['id_org'],
                    'metadataHref' => $this->ms->getEntityBaseUrl() . 'organization/metadata',
                    'type' => 'organization',
                    'mediaType' => 'application/json',
                ]
            ],
            'organizationAccount' => [
                'meta' => [
                    'href' => $this->buildOrganizationAccountHref($value['id_org'], $value['id_acc']),
                    'type' => 'account',
                    'mediaType' => 'application/json',
                ]
            ],
        ];

        if (!empty($value['description'])) {
            $payload['description'] = $value['description'];
        }

        $agentType = !empty($value['inner']) ? 'organization' : 'counterparty';
        $agentId = !empty($value['id_agent']) ? $value['id_agent'] : $this->config['default_agent_id'];

        $payload['agent'] = [
            'meta' => [
                'href' => $this->ms->getEntityBaseUrl() . $agentType . '/' . $agentId,
                'metadataHref' => $this->ms->getEntityBaseUrl() . $agentType . '/metadata',
                'type' => $agentType,
                'mediaType' => 'application/json',
            ]
        ];

        if (!empty($value['inner'])) {
            $payload['attributes'][] = [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'paymentin/metadata/attributes/' . $this->config['paymentin_inner_attribute_id'],
                    'type' => 'attributemetadata',
                    'mediaType' => 'application/json',
                ],
                'id' => $this->config['paymentin_inner_attribute_id'],
                'value' => true,
            ];
        }

        if (!empty($value['id_order'])) {
            $payload['operations'][] = [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'customerorder/' . $value['id_order'],
                    'metadataHref' => $this->ms->getEntityBaseUrl() . 'customerorder/metadata',
                    'type' => 'customerorder',
                    'mediaType' => 'application/json',
                ],
                'linkedSum' => $value['sum'],
            ];
        }

        return $payload;
    }

    protected function mapPaymentOut(array $value)
    {
        $payload = [
            'externalCode' => $value['id_paid'],
            'name' => (string)$value['name'],
            'moment' => $this->normalizeMomentForMs($value['moment']),
            'sum' => $value['sum'],
            'applicable' => !empty($value['id_agent']),
            'state' => [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'paymentout/metadata/states/' . $this->config['paymentout_state_id'],
                    'metadataHref' => $this->ms->getEntityBaseUrl() . 'paymentout/metadata',
                    'type' => 'state',
                    'mediaType' => 'application/json',
                ]
            ],
            'organization' => [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'organization/' . $value['id_org'],
                    'metadataHref' => $this->ms->getEntityBaseUrl() . 'organization/metadata',
                    'type' => 'organization',
                    'mediaType' => 'application/json',
                ]
            ],
            'organizationAccount' => [
                'meta' => [
                    'href' => $this->buildOrganizationAccountHref($value['id_org'], $value['id_acc']),
                    'type' => 'account',
                    'mediaType' => 'application/json',
                ]
            ],
        ];

        if (!empty($value['description'])) {
            $payload['description'] = $value['description'];
        }

        $agentType = !empty($value['inner']) ? 'organization' : 'counterparty';
        $agentId = !empty($value['id_agent']) ? $value['id_agent'] : $this->config['default_agent_id'];

        $payload['agent'] = [
            'meta' => [
                'href' => $this->ms->getEntityBaseUrl() . $agentType . '/' . $agentId,
                'metadataHref' => $this->ms->getEntityBaseUrl() . $agentType . '/metadata',
                'type' => $agentType,
                'mediaType' => 'application/json',
            ]
        ];

        if (!empty($value['inner'])) {
            $payload['attributes'][] = [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'paymentout/metadata/attributes/' . $this->config['paymentout_inner_attribute_id'],
                    'type' => 'attributemetadata',
                    'mediaType' => 'application/json',
                ],
                'id' => $this->config['paymentout_inner_attribute_id'],
                'value' => true,
            ];
        }

        if (!empty($value['id_order'])) {
            $payload['operations'][] = [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'purchaseorder/' . $value['id_order'],
                    'metadataHref' => $this->ms->getEntityBaseUrl() . 'purchaseorder/metadata',
                    'type' => 'purchaseorder',
                    'mediaType' => 'application/json',
                ],
                'linkedSum' => $value['sum'],
            ];
        }

        if (!empty($value['id_exp'])) {
            $payload['expenseItem'] = [
                'meta' => [
                    'href' => $this->ms->getEntityBaseUrl() . 'expenseitem/' . $value['id_exp'],
                    'metadataHref' => $this->ms->getEntityBaseUrl() . 'expenseitem/metadata',
                    'type' => 'expenseitem',
                    'mediaType' => 'application/json',
                ]
            ];
        }

        return $payload;
    }

    protected function buildOrganizationAccountHref($orgId, $accId)
    {
        return $this->ms->getEntityBaseUrl() . 'organization/' . $orgId . '/accounts/' . $accId;
    }
}