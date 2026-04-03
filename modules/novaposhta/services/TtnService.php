<?php
namespace Papir\Crm;

/**
 * TTN create / update / delete via NP API + DB sync.
 */
class TtnService
{
    /**
     * Create a TTN in NP API and save to ttn_novaposhta.
     *
     * Required params:
     *   sender_ref, sender_address_ref, city_sender_ref,
     *   city_recipient_ref, recipient_warehouse_ref,  // warehouse delivery
     *   OR recipient_street_ref + building + flat      // address delivery
     *   recipient_first_name, recipient_last_name, recipient_phone,
     *   weight, seats_amount, description, cost (declared value),
     *   payment_method (Cash|NonCash), payer_type (Sender|Recipient|ThirdPerson),
     *   service_type (WarehouseWarehouse|WarehouseDoors|DoorsWarehouse|DoorsDoor),
     *   backward_delivery_money (0 = no COD),
     *   customerorder_id (optional), demand_id (optional)
     */
    public static function create($params)
    {
        $senderRef = isset($params['sender_ref']) ? $params['sender_ref'] : '';
        if (!$senderRef) {
            return array('ok' => false, 'error' => 'sender_ref required');
        }

        $sender = SenderRepository::getByRef($senderRef);
        if (!$sender) {
            return array('ok' => false, 'error' => 'Sender not found');
        }

        $np = new NovaPoshta($sender['api']);

        // 1. Ensure recipient NP counterparty exists
        $recipientResult = self::ensureRecipient($np, $params, $senderRef);
        if (!$recipientResult['ok']) {
            return $recipientResult;
        }
        $npRecipientRef = $recipientResult['ref'];         // generic PrivatePerson ref → Recipient field
        $npContactRef   = $recipientResult['contact_ref']; // unique person ref → ContactRecipient field

        // 2. Ensure recipient address
        $addrResult = self::ensureRecipientAddress($np, $params, $npRecipientRef);
        if (!$addrResult['ok']) {
            return $addrResult;
        }
        $npAddressRef = $addrResult['ref'];

        // 4. Prepare InternetDocument.save payload
        $serviceType = isset($params['service_type']) ? $params['service_type'] : 'WarehouseWarehouse';
        $senderAddrRef = isset($params['sender_address_ref']) ? $params['sender_address_ref'] : '';

        // Resolve ContactSender — must be contact person ref, not org counterparty ref
        $senderPhone = self::normalizePhone(isset($params['sender_phone']) ? $params['sender_phone'] : '');
        $contactSenderRef = null;
        if ($senderPhone) {
            $eSPhone = \Database::escape('Papir', $senderPhone);
            $eSRef   = \Database::escape('Papir', $senderRef);
            $rCs = \Database::fetchRow('Papir',
                "SELECT Ref FROM np_sender_contact_persons
                 WHERE sender_ref = '{$eSRef}'
                   AND REGEXP_REPLACE(phone,'[^0-9]','') LIKE '%{$eSPhone}'
                 LIMIT 1");
            if ($rCs['ok'] && !empty($rCs['row']['Ref'])) {
                $contactSenderRef = $rCs['row']['Ref'];
            }
        }
        if (!$contactSenderRef) {
            $eSRef = \Database::escape('Papir', $senderRef);
            $rCs = \Database::fetchRow('Papir',
                "SELECT Ref FROM np_sender_contact_persons WHERE sender_ref = '{$eSRef}' LIMIT 1");
            if ($rCs['ok'] && !empty($rCs['row']['Ref'])) {
                $contactSenderRef = $rCs['row']['Ref'];
            }
        }
        if (!$contactSenderRef) {
            $contactSenderRef = $sender['Ref'];
        }

        $docProps = array(
            'CitySender'      => isset($params['city_sender_ref']) ? $params['city_sender_ref'] : '',
            'Sender'          => $sender['Counterparty'],
            'SenderAddress'   => $senderAddrRef,
            'ContactSender'   => $contactSenderRef,
            'SendersPhone'    => $senderPhone,
            'CityRecipient'   => isset($params['city_recipient_ref']) ? $params['city_recipient_ref'] : '',
            'Recipient'       => $npRecipientRef,
            'RecipientAddress'=> $npAddressRef,
            'ContactRecipient'=> $npContactRef ? $npContactRef : $npRecipientRef,
            'RecipientsPhone' => self::normalizePhone($params['recipient_phone']),
            'ServiceType'     => $serviceType,
            'PaymentMethod'   => isset($params['payment_method']) ? $params['payment_method'] : 'Cash',
            'PayerType'       => isset($params['payer_type'])     ? $params['payer_type']     : 'Recipient',
            'CargoType'       => isset($params['cargo_type'])     ? $params['cargo_type']     : 'Cargo',
            'Weight'          => isset($params['weight'])         ? (float)$params['weight']  : 0.5,
            'SeatsAmount'     => isset($params['seats_amount'])   ? (int)$params['seats_amount'] : 1,
            'Description'     => isset($params['description'])    ? $params['description']    : 'Товар',
            'Cost'            => isset($params['cost'])           ? (int)$params['cost']      : 1,
            'DateTime'        => isset($params['date'])           ? $params['date'] : date('d.m.Y'),
        );

        // COD (backward delivery)
        // use_payment_control=1 → NovaPay: AfterpaymentOnGoodsCost (поле на рівні документа)
        // use_payment_control=0 → готівка: BackwardDeliveryData.CargoType=Money
        $backMoney = isset($params['backward_delivery_money']) ? (float)$params['backward_delivery_money'] : 0;
        if ($backMoney > 0) {
            if (!empty($sender['use_payment_control'])) {
                $docProps['AfterpaymentOnGoodsCost'] = (string)(int)round($backMoney);
            } else {
                $docProps['BackwardDeliveryData'] = array(array(
                    'PayerType'        => 'Recipient',
                    'CargoType'        => 'Money',
                    'RedeliveryString' => (string)(int)round($backMoney),
                ));
            }
        }

        // OptionsSeat (per-seat dimensions)
        if (!empty($params['options_seat'])) {
            $rawSeats = $params['options_seat'];
            $decoded  = is_array($rawSeats) ? $rawSeats : json_decode($rawSeats, true);
            if (is_array($decoded) && !empty($decoded)) {
                $manualHandling = !empty($params['manual_handling']);
                $seatsArr = array();
                foreach ($decoded as $s) {
                    $w  = isset($s['weight']) ? (float)$s['weight'] : 0;
                    $l  = isset($s['length']) ? (int)$s['length']   : 0;
                    $wi = isset($s['width'])  ? (int)$s['width']    : 0;
                    $hh = isset($s['height']) ? (int)$s['height']   : 0;
                    $vol = ($l > 0 && $wi > 0 && $hh > 0) ? round($l * $wi * $hh / 4000, 2) : 0;
                    $seat = array(
                        'weight'           => (string)$w,
                        'volumetricWidth'  => (string)$wi,
                        'volumetricLength' => (string)$l,
                        'volumetricHeight' => (string)$hh,
                        'volumetricVolume' => (string)$vol,
                    );
                    // Per-seat manual handling
                    $seatManual = !empty($s['manual']) || $manualHandling;
                    if ($seatManual) {
                        $seat['optionsSeat'] = 'MANUALSORT';
                    }
                    $seatsArr[] = $seat;
                }
                if ($seatsArr) {
                    $docProps['OptionsSeat'] = $seatsArr;
                }
            }
        }

        $r = $np->call('InternetDocument', 'save', $docProps);
        if (!$r['ok']) {
            return array('ok' => false, 'error' => $r['error']);
        }

        $npDoc = isset($r['data'][0]) ? $r['data'][0] : array();

        // 5. Save to DB
        $dbData = array(
            'ref'                      => isset($npDoc['Ref'])              ? $npDoc['Ref']              : '',
            'int_doc_number'           => isset($npDoc['IntDocNumber'])     ? $npDoc['IntDocNumber']     : null,
            'customerorder_id'         => !empty($params['customerorder_id']) ? (int)$params['customerorder_id'] : null,
            'demand_id'                => !empty($params['demand_id'])        ? (int)$params['demand_id']        : null,
            'moment'                   => date('Y-m-d H:i:s'),
            'ew_date_created'          => date('Y-m-d H:i:s'),
            'estimated_delivery_date'  => isset($npDoc['EstimatedDeliveryDate']) ? self::parseNpDate($npDoc['EstimatedDeliveryDate']) : null,
            'sender_ref'               => $senderRef,
            'sender_address_ref'       => $senderAddrRef ?: null,
            'city_sender_desc'         => isset($params['city_sender_desc'])    ? $params['city_sender_desc']    : null,
            'city_sender_ref'          => isset($params['city_sender_ref'])     ? $params['city_sender_ref']     : null,
            'city_recipient_desc'      => isset($params['city_recipient_desc']) ? $params['city_recipient_desc'] : null,
            'city_recipient_ref'       => isset($params['city_recipient_ref'])  ? $params['city_recipient_ref']  : null,
            'recipient_np_ref'         => $npContactRef ?: null,
            'recipient_address'        => $npAddressRef,
            'recipient_address_desc'   => isset($params['recipient_address_desc']) ? $params['recipient_address_desc'] : null,
            'recipients_phone'         => $params['recipient_phone'],
            'recipient_contact_person' => trim(
                (isset($params['recipient_last_name'])   ? $params['recipient_last_name']   : '') . ' ' .
                (isset($params['recipient_first_name'])  ? $params['recipient_first_name']  : '') . ' ' .
                (isset($params['recipient_middle_name']) ? $params['recipient_middle_name'] : '')
            ),
            'service_type'             => $serviceType,
            'payment_method'           => $docProps['PaymentMethod'],
            'payer_type'               => $docProps['PayerType'],
            'cost'                     => isset($npDoc['CostOnSite']) ? (float)$npDoc['CostOnSite'] : null,
            'cost_on_site'             => isset($npDoc['CostOnSite']) ? (float)$npDoc['CostOnSite'] : null,
            'backward_delivery_money'  => $backMoney > 0 ? $backMoney : 0,
            'description'              => isset($params['description'])    ? $params['description']    : null,
            'declared_value'           => isset($params['cost'])           ? (int)$params['cost']      : null,
            'weight'                   => $docProps['Weight'],
            'seats_amount'             => $docProps['SeatsAmount'],
            'options_seat'             => !empty($params['options_seat']) ? (is_array($params['options_seat']) ? json_encode($params['options_seat']) : $params['options_seat']) : null,
            'manual_handling'          => !empty($params['manual_handling']) ? 1 : 0,
            'state_name'               => 'Відправник самостійно створив цю накладну, але ще не надав до відправки',
            'state_define'             => 1,
            'deletion_mark'            => 0,
        );

        $rSave = TtnRepository::save($dbData);
        if (!$rSave['ok']) {
            return array('ok' => false, 'error' => 'TTN saved in NP but DB write failed: ' . $rSave['error']);
        }
        $ttnId = $rSave['insert_id'];

        // 6. Link to order via document_link
        if (!empty($params['customerorder_id'])) {
            \Database::upsertOne('Papir', 'document_link', array(
                'from_type' => 'ttn_np',
                'from_id'   => $ttnId,
                'to_type'   => 'customerorder',
                'to_id'     => (int)$params['customerorder_id'],
                'link_type' => 'shipment',
            ), array('from_type', 'from_id', 'to_type', 'to_id'));
        }

        return array('ok' => true, 'ttn_id' => $ttnId, 'int_doc_number' => $dbData['int_doc_number'], 'np_doc' => $npDoc);
    }

    /**
     * Спроба автоматично прив'язати ТТН до заказу.
     * Алгоритм: телефон отримувача → контрагент → відкриті закази → збіг по сумі.
     * Прив'язує тільки якщо знайдено рівно 1 збіг.
     *
     * @param  int    $ttnId  ID запису в ttn_novaposhta
     * @param  string $phone  Телефон отримувача (будь-який формат)
     * @param  float  $sum    Сума ТТН (backward_delivery_money або cost)
     * @return int|null  order_id якщо прив'язано, null якщо ні
     */
    public static function autoMatchOrder($ttnId, $phone, $sum)
    {
        if (!$ttnId || !$phone || $sum <= 0) return null;

        // Нормалізуємо: беремо останні 9 цифр
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 7) return null;
        $last9 = substr($digits, -9);

        $sumEsc  = (float)$sum;
        $last9Esc = \Database::escape('Papir', $last9);

        $finalStatuses = "'completed','cancelled'";

        $r = \Database::fetchAll('Papir',
            "SELECT co.id AS order_id, co.number, co.sum_total
             FROM counterparty_person cpd
             JOIN counterparty cp ON cp.id = cpd.counterparty_id AND cp.status = 1
             JOIN customerorder co ON co.counterparty_id = cp.id
             WHERE RIGHT(REGEXP_REPLACE(cpd.phone, '[^0-9]', ''), 9) = '{$last9Esc}'
               AND co.status NOT IN ({$finalStatuses})
               AND co.deleted_at IS NULL
               AND ABS(co.sum_total - {$sumEsc}) < 1
             ORDER BY co.id DESC
             LIMIT 3"
        );

        if (!$r['ok'] || count($r['rows']) !== 1) return null;

        $orderId = (int)$r['rows'][0]['order_id'];

        // Записуємо customerorder_id
        \Database::update('Papir', 'ttn_novaposhta',
            array('customerorder_id' => $orderId),
            array('id' => $ttnId)
        );

        // Створюємо document_link
        \Database::query('Papir',
            "DELETE FROM document_link
             WHERE from_type='ttn_np' AND from_id={$ttnId} AND to_type='customerorder'"
        );
        \Database::insert('Papir', 'document_link', array(
            'from_type' => 'ttn_np',
            'from_id'   => $ttnId,
            'to_type'   => 'customerorder',
            'to_id'     => $orderId,
            'link_type' => 'shipment',
        ));

        return $orderId;
    }

    /**
     * Update an existing TTN (only allowed if not yet sent).
     */
    public static function update($ttnId, $params)
    {
        $ttn = TtnRepository::getById($ttnId);
        if (!$ttn) return array('ok' => false, 'error' => 'TTN not found');
        if (!$ttn['sender_api']) return array('ok' => false, 'error' => 'Sender API key not found');

        $np = new NovaPoshta($ttn['sender_api']);

        $props = array('Ref' => $ttn['ref']);
        $allowedFields = array(
            'weight' => 'Weight', 'seats_amount' => 'SeatsAmount',
            'description' => 'Description', 'cost' => 'Cost',
            'payer_type' => 'PayerType', 'payment_method' => 'PaymentMethod',
        );
        foreach ($allowedFields as $k => $npK) {
            if (isset($params[$k])) $props[$npK] = $params[$k];
        }

        $r = $np->call('InternetDocument', 'update', $props);
        if (!$r['ok']) return array('ok' => false, 'error' => $r['error']);

        // Update DB
        $upd = array();
        if (isset($params['weight']))        $upd['weight']        = $params['weight'];
        if (isset($params['seats_amount']))  $upd['seats_amount']  = $params['seats_amount'];
        if (isset($params['payment_method']))$upd['payment_method']= $params['payment_method'];
        if (isset($params['payer_type']))    $upd['payer_type']    = $params['payer_type'];
        if (isset($params['backward_delivery_money'])) $upd['backward_delivery_money'] = $params['backward_delivery_money'];
        if ($upd) {
            $upd['id'] = $ttnId;
            TtnRepository::save($upd);
        }

        return array('ok' => true);
    }

    /**
     * Delete a TTN from NP API and mark deleted in DB.
     */
    public static function delete($ttnId)
    {
        $ttn = TtnRepository::getById($ttnId);
        if (!$ttn) return array('ok' => false, 'error' => 'TTN not found');
        if (!$ttn['sender_api']) return array('ok' => false, 'error' => 'Sender API key not found');

        // Don't delete manual TTNs via API
        if (strpos($ttn['ref'], 'manual_') === 0) {
            TtnRepository::markDeleted($ttnId);
            return array('ok' => true);
        }

        $np = new NovaPoshta($ttn['sender_api']);
        $r = $np->call('InternetDocument', 'delete', array('DocumentRefs' => $ttn['ref']));
        if (!$r['ok']) {
            // Якщо НП каже що документ/баркод не існує — просто видаляємо у себе
            if (strpos($r['error'], 'invalid DocumentBarcodes') !== false
             || strpos($r['error'], 'invalid DocumentRefs')     !== false) {
                TtnRepository::markDeleted($ttnId);
                return array('ok' => true);
            }
            return array('ok' => false, 'error' => $r['error']);
        }

        TtnRepository::markDeleted($ttnId);
        return array('ok' => true);
    }

    // ── Prefill form data from order ─────────────────────────────────────────

    /**
     * Get prefill data for TTN creation form from a customerorder.
     */
    public static function getFormPrefill($orderId)
    {
        $rOrder = \Database::fetchRow('Papir',
            "SELECT co.id, co.number, co.source, co.sum_total, co.organization_id,
                    co.counterparty_id, co.contact_person_id
             FROM customerorder co
             WHERE co.id = " . (int)$orderId . " AND co.deleted_at IS NULL LIMIT 1");

        if (!$rOrder['ok'] || !$rOrder['row']) {
            return array('ok' => false, 'error' => 'Order not found');
        }
        $order = $rOrder['row'];

        // Recipient from counterparty
        $recipient = self::getRecipientFromOrder($order);

        // Sender: match by organization_id
        $senderRef = null;
        if ($order['organization_id']) {
            $sender = SenderRepository::getByOrganization($order['organization_id']);
            if ($sender) $senderRef = $sender['Ref'];
        }
        if (!$senderRef) {
            $defSender = SenderRepository::getDefault();
            if ($defSender) $senderRef = $defSender['Ref'];
        }

        // Existing TTNs for this order
        $existingTtns = TtnRepository::getByOrder($orderId);

        // Prefill from oc_order if available (number like "12345OFF" / "12345MFF")
        $ocData = self::getOcOrderData($order['number'], $order['source']);

        $result = array(
            'order_id'             => $orderId,
            'sum_total'            => $order['sum_total'],
            'backward_money_hint'  => (float)$order['sum_total'],
            'recipient'            => $recipient,
            'sender_ref'           => $senderRef,
            'senders'              => SenderRepository::getAll(),
            'existing_ttns'        => $existingTtns,
        );

        // If oc_order has data — enrich recipient
        if ($ocData) {
            if (empty($recipient['phone']) && !empty($ocData['telephone'])) {
                $result['recipient']['phone'] = $ocData['telephone'];
            }
            if (empty($recipient['first_name']) && !empty($ocData['shipping_firstname'])) {
                $result['recipient']['first_name'] = $ocData['shipping_firstname'];
            }
            if (empty($recipient['last_name']) && !empty($ocData['shipping_lastname'])) {
                $result['recipient']['last_name'] = $ocData['shipping_lastname'];
            }
            // City/address hints from site order
            if (!empty($ocData['shipping_city'])) {
                $result['recipient']['city_hint'] = $ocData['shipping_city'];
            }
            if (!empty($ocData['shipping_address_1'])) {
                $result['recipient']['address_hint'] = $ocData['shipping_address_1'];
            }
            if (!empty($ocData['novaposhta_cn_ref'])) {
                $result['recipient']['np_warehouse_ref'] = $ocData['novaposhta_cn_ref'];
            }
        }

        return array('ok' => true, 'data' => $result);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function getRecipientFromOrder($order)
    {
        $recipient = array(
            'first_name' => '', 'last_name' => '', 'middle_name' => '', 'phone' => '',
            'edrpou' => '', 'counterparty_type' => 'PrivatePerson', 'full_name' => '',
        );

        $cpId = $order['contact_person_id'] ?: $order['counterparty_id'];
        if (!$cpId) return $recipient;

        $rCp = \Database::fetchRow('Papir',
            "SELECT cp.type, cp.name,
                    cpp.first_name, cpp.last_name, cpp.middle_name, cpp.phone,
                    cpc.phone AS company_phone, cpc.short_name, cpc.okpo AS edrpou
             FROM counterparty cp
             LEFT JOIN counterparty_person  cpp ON cpp.counterparty_id = cp.id
             LEFT JOIN counterparty_company cpc ON cpc.counterparty_id = cp.id
             WHERE cp.id = " . (int)$cpId . " LIMIT 1");

        if (!$rCp['ok'] || !$rCp['row']) return $recipient;
        $cp = $rCp['row'];

        if ($cp['type'] === 'person') {
            $recipient['first_name']       = $cp['first_name']  ?: '';
            $recipient['last_name']        = $cp['last_name']   ?: '';
            $recipient['middle_name']      = $cp['middle_name'] ?: '';
            $recipient['phone']            = $cp['phone']       ?: '';
            $recipient['counterparty_type']= 'PrivatePerson';
        } else {
            $recipient['full_name']        = $cp['short_name'] ?: $cp['name'];
            $recipient['phone']            = $cp['company_phone'] ?: '';
            $recipient['edrpou']           = $cp['edrpou']     ?: '';
            $recipient['counterparty_type']= 'Organization';
        }
        $recipient['counterparty_id'] = (int)$cpId;
        $recipient['papir_type']      = $cp['type'];

        return $recipient;
    }

    private static function getOcOrderData($orderNumber, $source)
    {
        if (!$orderNumber) return null;
        // Extract numeric part: "98267OFF" → 98267, "98267MFF" → 98267
        if (!preg_match('/^(\d+)(OFF|MFF)?$/i', $orderNumber, $m)) return null;
        $ocOrderId = (int)$m[1];
        if ($ocOrderId <= 0) return null;

        $db = (isset($m[2]) && strtoupper($m[2]) === 'MFF') ? 'mff' : 'off';

        $r = \Database::fetchRow($db,
            "SELECT telephone, shipping_firstname, shipping_lastname,
                    novaposhta_cn_ref, novaposhta_cn_number,
                    shipping_city, shipping_address_1
             FROM oc_order WHERE order_id = {$ocOrderId} LIMIT 1");
        return ($r['ok'] && $r['row']) ? $r['row'] : null;
    }

    /**
     * Ensure NP recipient counterparty exists (create if needed).
     * Returns ['ok'=>bool, 'ref'=>string]
     */
    private static function ensureRecipient($np, $params, $senderRef)
    {
        $counterpartyId = !empty($params['counterparty_id']) ? (int)$params['counterparty_id'] : 0;

        // Check cache
        if ($counterpartyId) {
            $cached = NpCounterpartyRepository::getNpRefForCounterparty($counterpartyId, $senderRef);
            if ($cached) return array('ok' => true, 'ref' => $cached);
        }

        $isOrg = (!empty($params['recipient_type']) && $params['recipient_type'] === 'Organization');

        if ($isOrg) {
            $cpProps = array(
                'CounterpartyProperty' => 'Recipient',
                'CounterpartyType'     => 'Organization',
                'CounterpartyFullName' => isset($params['recipient_full_name']) ? $params['recipient_full_name'] : '',
                'EDRPOU'               => isset($params['recipient_edrpou'])    ? $params['recipient_edrpou']    : '',
                'Phone'                => self::normalizePhone($params['recipient_phone']),
            );
        } else {
            $cpProps = array(
                'CounterpartyProperty' => 'Recipient',
                'CounterpartyType'     => 'PrivatePerson',
                'FirstName'            => isset($params['recipient_first_name'])  ? $params['recipient_first_name']  : '',
                'MiddleName'           => isset($params['recipient_middle_name']) ? $params['recipient_middle_name'] : '',
                'LastName'             => isset($params['recipient_last_name'])   ? $params['recipient_last_name']   : '',
                'Phone'                => self::normalizePhone($params['recipient_phone']),
            );
        }

        $r = $np->call('CounterpartyGeneral', 'save', $cpProps);
        if (!$r['ok']) return array('ok' => false, 'error' => 'Cannot create recipient: ' . $r['error']);

        $npData = isset($r['data'][0]) ? $r['data'][0] : array();
        // data[0].Ref = generic PrivatePerson ref (same for all, used in Recipient field)
        // data[0].ContactPerson.data[0].Ref = unique contact person ref (used in ContactRecipient)
        $npRef     = isset($npData['Ref']) ? $npData['Ref'] : '';
        $npContact = isset($npData['ContactPerson']['data'][0]['Ref'])
            ? $npData['ContactPerson']['data'][0]['Ref']
            : $npRef;
        if (!$npRef) return array('ok' => false, 'error' => 'No Ref in NP counterparty response');

        // Cache
        NpCounterpartyRepository::upsert($npData, $senderRef, $counterpartyId ?: null);

        return array('ok' => true, 'ref' => $npRef, 'contact_ref' => $npContact);
    }

    /**
     * Ensure recipient address (warehouse or street address) exists.
     * Returns ['ok'=>bool, 'ref'=>string]
     */
    private static function ensureRecipientAddress($np, $params, $recipientRef)
    {
        $serviceType = isset($params['service_type']) ? $params['service_type'] : 'WarehouseWarehouse';

        // Warehouse delivery: address ref is the warehouse ref directly
        if (in_array($serviceType, array('WarehouseWarehouse', 'DoorsWarehouse'))) {
            $warehouseRef = isset($params['recipient_warehouse_ref']) ? $params['recipient_warehouse_ref'] : '';
            if (!$warehouseRef) return array('ok' => false, 'error' => 'recipient_warehouse_ref required');
            return array('ok' => true, 'ref' => $warehouseRef);
        }

        // Address delivery: need to create address via API
        $streetRef = isset($params['recipient_street_ref']) ? $params['recipient_street_ref'] : '';
        $building  = isset($params['recipient_building'])   ? $params['recipient_building']   : '';
        $flat      = isset($params['recipient_flat'])       ? $params['recipient_flat']       : '';

        if (!$streetRef || !$building) {
            return array('ok' => false, 'error' => 'Street ref and building required for address delivery');
        }

        $r = $np->call('Address', 'save', array(
            'CounterpartyRef'  => $recipientRef,
            'StreetRef'        => $streetRef,
            'BuildingNumber'   => $building,
            'Flat'             => $flat,
            'Note'             => '',
        ));

        if (!$r['ok']) return array('ok' => false, 'error' => 'Cannot create address: ' . $r['error']);
        $addrRef = isset($r['data'][0]['Ref']) ? $r['data'][0]['Ref'] : '';
        if (!$addrRef) return array('ok' => false, 'error' => 'No Ref in NP address response');

        return array('ok' => true, 'ref' => $addrRef);
    }

    private static function getContactPerson($np, $recipientRef)
    {
        $r = $np->call('Counterparty', 'getCounterpartyContactPersons',
            array('Ref' => $recipientRef, 'Page' => 1));
        if (!$r['ok'] || empty($r['data'])) return array('ok' => false, 'ref' => null);
        $first = $r['data'][0];
        $ref = isset($first['Ref']) ? $first['Ref'] : '';
        return array('ok' => (bool)$ref, 'ref' => $ref);
    }

    /**
     * Normalize phone to 380XXXXXXXXX (12 digits, NP API format).
     */
    public static function normalizePhone($phone)
    {
        $digits = preg_replace('/\D/', '', (string)$phone);
        // 8XXXXXXXXX → 38XXXXXXXXX
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '3' . $digits;
        }
        // 0XXXXXXXXX → 380XXXXXXXXX
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $digits = '38' . $digits;
        }
        return $digits; // 380XXXXXXXXX
    }

    private static function parseNpDate($dateStr)
    {
        if (!$dateStr) return null;
        // NP returns dates in various formats; try to parse
        $ts = strtotime($dateStr);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}