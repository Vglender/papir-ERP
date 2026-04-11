<?php
/**
 * PromOrderImporter — imports orders from Prom.ua marketplace into Papir.
 *
 * Parallel to SiteOrderImporter (which only handles OpenCart direct_db sites).
 *
 * Pipeline per order:
 *   1. Fetch via PromApi::getOrders / getOrder
 *   2. Dedup by external_code = 'prom_{order_id}'
 *   3. Resolve / create counterparty (by phone, plus link site_customer_ids.prom)
 *   4. Resolve product_id via product_papir.id_prom (or external_id, or sku)
 *   5. Insert customerorder + items + shipping
 *   6. Fire 'order_created' trigger
 */

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
require_once __DIR__ . '/../PromApi.php';

class PromOrderImporter
{
    const SITE_ID    = 3;
    const SITE_CODE  = 'prom';
    const SITE_BADGE = 'PROM';

    const ORG_ID            = 6;  // ФОП Чумаченко Вікторія Вікторівна
    const BANK_ACCOUNT_ID   = 3;  // ПриватБанк
    const STORE_ID          = 1;  // Основний склад
    const MANAGER_ID        = 5;  // Василь Робот

    /** @var PromApi */
    private $api;

    /** @var array shipping_code => ['delivery_method_id'=>int,'delivery_code'=>string] */
    private $deliveryMap = array();

    /** @var array payment_code => payment_method_id */
    private $paymentMap = array();

    /** @var callable|null */
    private $log;

    public function __construct($logCallback = null)
    {
        $this->api = new PromApi();
        $this->log = $logCallback;
        $this->loadMappings();
    }

    /**
     * Run import for all unimported Prom orders.
     *
     * @param int $daysBack  How many days back to scan (default 7)
     * @return array ['imported'=>int,'skipped'=>int,'errors'=>array]
     */
    public function importAll($daysBack = 7)
    {
        if (!AppRegistry::isActive('prom')) {
            $this->log("Prom integration is inactive — skipping");
            return array('imported' => 0, 'skipped' => 0, 'errors' => array());
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = array();

        $dateFrom = date('Y-m-d\TH:i:s', strtotime("-{$daysBack} days"));
        $this->log("=== Prom.ua order import (since {$dateFrom}) ===");

        // Pagination via last_id (descending order is default in Prom API)
        $lastId = null;
        $page   = 0;
        $limit  = 50;

        while (true) {
            $page++;
            $params = array(
                'limit'     => $limit,
                'date_from' => $dateFrom,
            );
            if ($lastId !== null) {
                $params['last_id'] = $lastId;
            }

            $r = $this->api->getOrders($params);
            if (empty($r['ok']) || !isset($r['orders'])) {
                $err = isset($r['error']) ? $r['error'] : 'Unknown API error';
                $errors[] = "Page {$page}: {$err}";
                $this->log("API error on page {$page}: {$err}");
                break;
            }

            $batch = $r['orders'];
            if (empty($batch)) break;

            $this->log("Page {$page}: " . count($batch) . " orders");

            foreach ($batch as $orderShort) {
                $promId  = (int)$orderShort['id'];
                $lastId  = $promId;

                if ($this->orderAlreadyExists($promId)) {
                    $skipped++;
                    continue;
                }

                try {
                    // Fetch full order (getOrders returns enough but full call is safer)
                    $full = $this->api->getOrder($promId);
                    if (empty($full['ok']) || empty($full['order'])) {
                        $errors[] = "{$extCode}: failed to fetch full order";
                        continue;
                    }

                    $orderId = $this->importOne($full['order']);
                    if ($orderId) {
                        $imported++;
                        $this->log("Imported #{$promId} → Papir order #{$orderId}");
                    }
                } catch (Exception $e) {
                    $errors[] = "{$extCode}: " . $e->getMessage();
                    $this->log("ERROR #{$promId}: " . $e->getMessage());
                }

                usleep(150000); // 150ms throttle
            }

            if (count($batch) < $limit) break;
        }

        $this->log("=== Done: imported={$imported}, skipped={$skipped}, errors=" . count($errors) . " ===");
        return array('imported' => $imported, 'skipped' => $skipped, 'errors' => $errors);
    }

    /**
     * Dedup check for a Prom order ID.
     *
     * Looks up by all possible historical patterns:
     *   1. external_code = 'prom_{id}'         — new direct imports
     *   2. number = '{id}PROM'                  — historical (came via MoySklad, 3995 rows)
     *   3. number = '{id}' + self::SITE_BADGE  — current convention (same as #2 if PROM)
     *
     * Note: external_code for legacy MS-imported orders is the MS uuid, not prom_id —
     * so the *number* column is the only reliable cross-source key.
     */
    public function orderAlreadyExists($promId)
    {
        $promId = (int)$promId;
        if ($promId <= 0) return false;

        $extCode  = 'prom_' . $promId;
        $numProm  = $promId . 'PROM';
        $numBadge = $promId . self::SITE_BADGE;

        $r = Database::fetchRow('Papir',
            "SELECT id FROM customerorder
             WHERE external_code = '{$extCode}'
                OR number = '{$numProm}'
                OR number = '{$numBadge}'
             LIMIT 1");
        return !empty($r['ok']) && !empty($r['row']);
    }

    /**
     * Import a single Prom order.
     *
     * @param array $promOrder  Full order JSON from /orders/{id}
     * @return int|null  Papir customerorder.id, or null
     */
    public function importOne(array $promOrder)
    {
        $promId  = (int)$promOrder['id'];
        $extCode = 'prom_' . $promId;
        $number  = $promId . self::SITE_BADGE;

        if ($this->orderAlreadyExists($promId)) {
            $this->log("Skip #{$promId} — already exists");
            return null;
        }

        // ── Counterparty ───────────────────────────────────────────────
        $client      = isset($promOrder['client']) ? $promOrder['client'] : array();
        $clientId    = isset($client['id']) ? (int)$client['id'] : 0;
        $firstName   = isset($client['first_name']) ? trim($client['first_name']) : '';
        $lastName    = isset($client['last_name']) ? trim($client['last_name']) : '';
        $middleName  = isset($client['second_name']) ? trim($client['second_name']) : '';
        $phoneRaw    = isset($promOrder['phone']) ? $promOrder['phone'] : (isset($client['phone']) ? $client['phone'] : '');
        $phone       = $this->normalizePhone($phoneRaw);
        $email       = isset($promOrder['email']) && $promOrder['email'] !== null ? trim($promOrder['email']) : '';

        $cpId = $this->resolveCounterparty($phone, $firstName, $lastName, $middleName, $email, $clientId);

        // ── Delivery / payment ─────────────────────────────────────────
        $providerData = isset($promOrder['delivery_provider_data']) && is_array($promOrder['delivery_provider_data'])
            ? $promOrder['delivery_provider_data'] : array();
        $providerCode = isset($providerData['provider']) ? $providerData['provider'] : '';
        $shippingCode = $providerCode !== '' ? 'prom.' . $providerCode : '';

        $deliveryMethodId = $this->resolveDeliveryMethod($shippingCode);

        $paymentName = '';
        if (isset($promOrder['payment_option']['name'])) {
            $paymentName = $promOrder['payment_option']['name'];
        }
        $paymentCode     = $this->resolvePaymentCode($paymentName);
        $paymentMethodId = $this->resolvePaymentMethod($paymentCode);

        // ── Status ─────────────────────────────────────────────────────
        $promStatus = isset($promOrder['status']) ? $promOrder['status'] : 'pending';
        $papirStatus = $this->mapStatus($promStatus);

        // ── Totals ─────────────────────────────────────────────────────
        $sumTotal    = $this->parsePromPrice(isset($promOrder['price']) ? $promOrder['price'] : '0');
        $deliveryCost = isset($promOrder['delivery_cost']) ? (float)$promOrder['delivery_cost'] : 0;

        $sumItems = 0;
        $products = isset($promOrder['products']) && is_array($promOrder['products']) ? $promOrder['products'] : array();
        foreach ($products as $p) {
            $sumItems += $this->parsePromPrice(isset($p['total_price']) ? $p['total_price'] : '0');
        }
        if ($sumItems <= 0) $sumItems = $sumTotal;

        // ── Comment ────────────────────────────────────────────────────
        $description = isset($promOrder['client_notes']) ? trim($promOrder['client_notes']) : '';
        if (!empty($promOrder['cancellation']['title'])) {
            $cancelInfo = 'Скасовано: ' . $promOrder['cancellation']['title'];
            if (!empty($promOrder['cancellation']['initiator'])) {
                $cancelInfo .= ' (' . $promOrder['cancellation']['initiator'] . ')';
            }
            $description = $description !== '' ? $description . "\n" . $cancelInfo : $cancelInfo;
        }

        // ── UUID ───────────────────────────────────────────────────────
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        // ── INSERT customerorder ───────────────────────────────────────
        $rIns = Database::insert('Papir', 'customerorder', array(
            'uuid'                         => $uuid,
            'source'                       => 'prom_import',
            'external_code'                => $extCode,
            'number'                       => $number,
            'moment'                       => $this->parsePromDate(isset($promOrder['date_created']) ? $promOrder['date_created'] : ''),
            'applicable'                   => 1,
            'organization_id'              => self::ORG_ID,
            'organization_bank_account_id' => self::BANK_ACCOUNT_ID,
            'store_id'                     => self::STORE_ID,
            'manager_employee_id'          => self::MANAGER_ID,
            'counterparty_id'              => $cpId,
            'status'                       => $papirStatus,
            'payment_status'               => 'not_paid',
            'shipment_status'              => 'not_shipped',
            'currency_code'                => 'UAH',
            'sum_items'                    => $sumItems,
            'sum_total'                    => $sumTotal,
            'sales_channel'                => self::SITE_CODE,
            'delivery_method_id'           => $deliveryMethodId,
            'payment_method_id'            => $paymentMethodId,
            'description'                  => $description,
            'wait_call'                    => empty($promOrder['dont_call_customer_back']) ? 1 : 0,
            'sync_state'                   => 'new',
        ));
        if (empty($rIns['ok'])) {
            throw new Exception('Failed to insert customerorder: ' . (isset($rIns['error']) ? $rIns['error'] : 'unknown'));
        }
        $orderId = (int)$rIns['insert_id'];

        // ── INSERT customerorder_item rows ─────────────────────────────
        $lineNo = 1;
        foreach ($products as $p) {
            $promProductId = isset($p['id']) ? (int)$p['id'] : 0;
            $externalId    = isset($p['external_id']) ? trim($p['external_id']) : '';
            $sku           = isset($p['sku']) ? trim($p['sku']) : '';
            $name          = isset($p['name']) ? $p['name'] : '';
            $qty           = isset($p['quantity']) ? (float)$p['quantity'] : 1;
            $price         = $this->parsePromPrice(isset($p['price']) ? $p['price'] : '0');
            $total         = $this->parsePromPrice(isset($p['total_price']) ? $p['total_price'] : '0');
            $unit          = isset($p['measure_unit']) ? $p['measure_unit'] : 'шт';

            $papirProductId = $this->resolveProduct($promProductId, $externalId, $sku);

            Database::insert('Papir', 'customerorder_item', array(
                'customerorder_id'      => $orderId,
                'line_no'               => $lineNo,
                'product_id'            => $papirProductId,
                'product_name'          => $name,
                'sku'                   => $sku,
                'unit'                  => $unit,
                'quantity'              => $qty,
                'price'                 => $price,
                'vat_rate'              => 0,
                'vat_amount'            => 0,
                'sum_without_discount'  => $total,
                'sum_row'               => $total,
            ));
            $lineNo++;
        }

        // ── INSERT customerorder_shipping ──────────────────────────────
        $recipient = isset($promOrder['delivery_recipient']) && is_array($promOrder['delivery_recipient'])
            ? $promOrder['delivery_recipient'] : array();
        $rcpFirst  = isset($recipient['first_name']) ? trim($recipient['first_name']) : $firstName;
        $rcpLast   = isset($recipient['last_name']) ? trim($recipient['last_name']) : $lastName;
        $rcpMid    = isset($recipient['second_name']) ? trim($recipient['second_name']) : $middleName;
        $rcpPhone  = isset($recipient['phone']) ? $this->normalizePhone($recipient['phone']) : $phone;

        $addr = isset($providerData['recipient_address']) && is_array($providerData['recipient_address'])
            ? $providerData['recipient_address'] : array();

        $shipping = array(
            'customerorder_id'     => $orderId,
            'counterparty_id'      => $cpId,
            'recipient_first_name' => $rcpFirst,
            'recipient_last_name'  => $rcpLast,
            'recipient_middle_name' => $rcpMid,
            'recipient_phone'      => $rcpPhone,
            'city_name'            => isset($addr['city_name']) ? $addr['city_name'] : '',
            'np_warehouse_ref'     => isset($addr['warehouse_id']) && $addr['warehouse_id'] !== null ? $addr['warehouse_id'] : '',
            'street'               => isset($addr['street_name']) && $addr['street_name'] !== null ? $addr['street_name'] : '',
            'house'                => isset($addr['building_number']) && $addr['building_number'] !== null ? $addr['building_number'] : '',
            'flat'                 => isset($addr['apartment_number']) && $addr['apartment_number'] !== null ? $addr['apartment_number'] : '',
            'delivery_code'        => $this->resolveDeliveryCode($shippingCode),
            'delivery_method_name' => isset($promOrder['delivery_option']['name']) ? $promOrder['delivery_option']['name'] : '',
            'branch_name'          => isset($promOrder['delivery_address']) ? trim($promOrder['delivery_address']) : '',
            'no_call'              => empty($promOrder['dont_call_customer_back']) ? 0 : 1,
            'source'               => 'prom_api',
        );

        // TTN if Prom already has it
        if (!empty($providerData['declaration_number'])) {
            $shipping['comment'] = 'ТТН: ' . $providerData['declaration_number'];
        }

        Database::insert('Papir', 'customerorder_shipping', $shipping);

        // ── Payment status from Prom ───────────────────────────────────
        // Prom returns 'paid' / 'received' / 'delivered' for successfully paid orders.
        if (in_array($promStatus, array('paid', 'received', 'delivered'), true)) {
            Database::update('Papir', 'customerorder',
                array('payment_status' => 'paid'),
                array('id' => $orderId));
        }

        // ── document_history ───────────────────────────────────────────
        Database::insert('Papir', 'document_history', array(
            'document_type' => 'customerorder',
            'document_id'   => $orderId,
            'action'        => 'create',
            'field_name'    => 'status',
            'new_value'     => $papirStatus,
            'actor_type'    => 'system',
            'actor_label'   => 'PromOrderImporter',
            'comment'       => 'Imported from Prom.ua order #' . $promId,
        ));

        // ── Trigger ────────────────────────────────────────────────────
        if (class_exists('TriggerEngine')) {
            TriggerEngine::fire('order_created', array(
                'order_id'        => $orderId,
                'counterparty_id' => $cpId,
                'source'          => 'prom_import',
                'sales_channel'   => self::SITE_CODE,
            ));
        }

        return $orderId;
    }

    // =====================================================================
    // PRODUCT RESOLUTION  (per CLAUDE.md feedback: search by 3 fields)
    // =====================================================================

    /**
     * Resolve Papir product_id from Prom product info.
     * Order: id_prom → external_id (Papir product_id) → product_article (sku).
     */
    private function resolveProduct($promProductId, $externalId, $sku)
    {
        // 1. By id_prom (set via map_products.php)
        if ($promProductId > 0) {
            $r = Database::fetchRow('Papir',
                "SELECT product_id FROM product_papir WHERE id_prom = " . (int)$promProductId . " LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                return (int)$r['row']['product_id'];
            }
        }

        // 2. By external_id (in Prom this is set to Papir's product_id)
        if ($externalId !== '' && ctype_digit($externalId)) {
            $r = Database::fetchRow('Papir',
                "SELECT product_id FROM product_papir WHERE product_id = " . (int)$externalId . " LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                return (int)$r['row']['product_id'];
            }
        }

        // 3. By article / sku
        if ($sku !== '') {
            $skuEsc = Database::escape('Papir', $sku);
            $r = Database::fetchRow('Papir',
                "SELECT product_id FROM product_papir WHERE product_article = '{$skuEsc}' LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                return (int)$r['row']['product_id'];
            }
        }

        return null;
    }

    // =====================================================================
    // COUNTERPARTY RESOLUTION
    // =====================================================================

    private function resolveCounterparty($phone, $firstName, $lastName, $middleName, $email, $promClientId)
    {
        $cpId = 0;

        // Try by site_customer_ids.prom first (most reliable)
        if ($promClientId > 0) {
            $r = Database::fetchRow('Papir',
                "SELECT id FROM counterparty
                 WHERE JSON_EXTRACT(site_customer_ids, '$.prom') = " . (int)$promClientId . "
                 LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                $cpId = (int)$r['row']['id'];
            }
        }

        // Fallback: by phone (try multiple variants)
        if ($cpId === 0 && $phone !== '') {
            $variants = array($phone);
            if (substr($phone, 0, 1) === '+') {
                $variants[] = substr($phone, 1);
            } else {
                $variants[] = '+' . $phone;
            }
            $vList = "'" . implode("','", array_map(function($v) {
                return Database::escape('Papir', $v);
            }, $variants)) . "'";

            $r = Database::fetchRow('Papir',
                "SELECT cp.id FROM counterparty cp
                 JOIN counterparty_person cpp ON cpp.counterparty_id = cp.id
                 WHERE (cpp.phone IN ({$vList}) OR cpp.phone_alt IN ({$vList}))
                   AND cp.status = 1
                 LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                $cpId = (int)$r['row']['id'];
            }
        }

        if ($cpId > 0) {
            $this->updateCounterpartyPerson($cpId, $firstName, $lastName, $middleName, $email);
            $this->linkPromClient($cpId, $promClientId);
            return $cpId;
        }

        // Create new
        $fullName = trim($lastName . ' ' . $firstName);
        if ($fullName === '') $fullName = $email !== '' ? $email : $phone;
        if ($fullName === '') $fullName = 'Prom client #' . $promClientId;

        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        $rCp = Database::insert('Papir', 'counterparty', array(
            'uuid'   => $uuid,
            'type'   => 'person',
            'name'   => $fullName,
            'source' => 'prom_import',
            'status' => 1,
        ));
        if (empty($rCp['ok'])) {
            throw new Exception('Failed to create counterparty: ' . (isset($rCp['error']) ? $rCp['error'] : 'unknown'));
        }
        $cpId = (int)$rCp['insert_id'];

        Database::insert('Papir', 'counterparty_person', array(
            'counterparty_id' => $cpId,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'middle_name'     => $middleName,
            'full_name'       => $fullName,
            'phone'           => $phone,
            'email'           => $email,
        ));

        $this->linkPromClient($cpId, $promClientId);
        return $cpId;
    }

    private function updateCounterpartyPerson($cpId, $firstName, $lastName, $middleName, $email)
    {
        $current = Database::fetchRow('Papir',
            "SELECT first_name, last_name, middle_name, email FROM counterparty_person WHERE counterparty_id = {$cpId}");
        if (empty($current['ok']) || empty($current['row'])) return;

        $cur = $current['row'];
        $updates = array();
        if ($firstName !== '' && $cur['first_name'] !== $firstName) $updates['first_name'] = $firstName;
        if ($lastName !== ''  && $cur['last_name']  !== $lastName)  $updates['last_name']  = $lastName;
        if ($middleName !== '' && $cur['middle_name'] !== $middleName) $updates['middle_name'] = $middleName;
        if ($email !== ''     && empty($cur['email']))             $updates['email']      = $email;

        if (!empty($updates)) {
            Database::update('Papir', 'counterparty_person', $updates, array('counterparty_id' => $cpId));
        }

        Database::query('Papir', "UPDATE counterparty SET last_activity_at = NOW() WHERE id = {$cpId}");
    }

    /**
     * Add or update {"prom": <client_id>} inside counterparty.site_customer_ids JSON.
     */
    public function linkPromClient($cpId, $promClientId)
    {
        if ($promClientId <= 0) return;

        $r = Database::fetchRow('Papir',
            "SELECT site_customer_ids FROM counterparty WHERE id = {$cpId}");
        if (empty($r['ok']) || empty($r['row'])) return;

        $ids = !empty($r['row']['site_customer_ids'])
            ? json_decode($r['row']['site_customer_ids'], true) : array();
        if (!is_array($ids)) $ids = array();

        if (!isset($ids['prom']) || (int)$ids['prom'] !== $promClientId) {
            $ids['prom'] = $promClientId;
            Database::update('Papir', 'counterparty',
                array('site_customer_ids' => json_encode($ids)),
                array('id' => $cpId));
        }
    }

    // =====================================================================
    // MAPPINGS
    // =====================================================================

    private function loadMappings()
    {
        $r = Database::fetchAll('Papir', "SELECT * FROM site_delivery_method_map WHERE shipping_code LIKE 'prom.%'");
        if (!empty($r['ok'])) {
            foreach ($r['rows'] as $row) {
                $this->deliveryMap[$row['shipping_code']] = array(
                    'delivery_method_id' => (int)$row['delivery_method_id'],
                    'delivery_code'      => $row['delivery_code'],
                );
            }
        }

        $r = Database::fetchAll('Papir', "SELECT * FROM site_payment_method_map WHERE payment_code LIKE 'prom.%'");
        if (!empty($r['ok'])) {
            foreach ($r['rows'] as $row) {
                $this->paymentMap[$row['payment_code']] = (int)$row['payment_method_id'];
            }
        }
    }

    private function resolveDeliveryMethod($shippingCode)
    {
        if (isset($this->deliveryMap[$shippingCode])) {
            return $this->deliveryMap[$shippingCode]['delivery_method_id'];
        }
        if (strpos($shippingCode, 'nova_poshta') !== false) return 3;
        if (strpos($shippingCode, 'ukrposhta') !== false)   return 4;
        if (strpos($shippingCode, 'pickup') !== false)      return 1;
        if (strpos($shippingCode, 'meest') !== false)       return 3;
        return null;
    }

    private function resolveDeliveryCode($shippingCode)
    {
        if (isset($this->deliveryMap[$shippingCode])) {
            return $this->deliveryMap[$shippingCode]['delivery_code'];
        }
        return $shippingCode;
    }

    private function resolvePaymentMethod($paymentCode)
    {
        if (isset($this->paymentMap[$paymentCode])) {
            return $this->paymentMap[$paymentCode];
        }
        return null;
    }

    /**
     * Prom doesn't return a machine code for payment, only display name.
     * Map by name (RU + UA variants) to one of the seeded prom.* keys.
     */
    private function resolvePaymentCode($paymentName)
    {
        $n = mb_strtolower($paymentName, 'UTF-8');
        if ($n === '') return '';

        if (mb_strpos($n, 'наклад') !== false || mb_strpos($n, 'налож') !== false || mb_strpos($n, 'cod') !== false) {
            return 'prom.cash_on_delivery';
        }
        if (mb_strpos($n, 'части') !== false || mb_strpos($n, 'частк') !== false || mb_strpos($n, 'кредит') !== false) {
            return 'prom.installment';
        }
        if (mb_strpos($n, 'пром') !== false || mb_strpos($n, 'онлайн') !== false || mb_strpos($n, 'карт') !== false) {
            return 'prom.online';
        }
        if (mb_strpos($n, 'счет') !== false || mb_strpos($n, 'рахун') !== false || mb_strpos($n, 'безгот') !== false) {
            return 'prom.bank';
        }
        return '';
    }

    /**
     * Map Prom status string to Papir customerorder.status enum.
     * Prom flow: pending → received → paid → delivered → (canceled).
     * Оплаченість фіксується окремо через customerorder.payment_status,
     * тому 'paid' мапимо на воркфлоу-статус 'confirmed', а не у
     * мертвий ENUM-стан 'paid'.
     */
    private function mapStatus($promStatus)
    {
        switch ($promStatus) {
            case 'pending':   return 'new';
            case 'received':  return 'confirmed';
            case 'paid':      return 'confirmed';
            case 'delivered': return 'completed';
            case 'canceled':  return 'cancelled';
        }
        return 'new';
    }

    // =====================================================================
    // PARSERS
    // =====================================================================

    /**
     * Prom returns prices as strings: "1 824 грн", "4,56 грн".
     */
    private function parsePromPrice($value)
    {
        if (is_numeric($value)) return (float)$value;
        if (!is_string($value)) return 0.0;

        // Strip currency, spaces (incl. NBSP)
        $clean = preg_replace('/[^\d,.\-]/u', '', $value);
        $clean = str_replace(' ', '', $clean);
        $clean = str_replace(',', '.', $clean);

        // If multiple dots (e.g. "1.824.50"), keep only the last as decimal sep
        if (substr_count($clean, '.') > 1) {
            $pos = strrpos($clean, '.');
            $clean = str_replace('.', '', substr($clean, 0, $pos)) . '.' . substr($clean, $pos + 1);
        }
        return (float)$clean;
    }

    /**
     * Prom date format: "2026-03-03T18:27:51.861924+00:00"
     */
    private function parsePromDate($iso)
    {
        if (!$iso) return date('Y-m-d H:i:s');
        $ts = strtotime($iso);
        return $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    }

    /**
     * Normalize phone to bare digits with country code (380XXXXXXXXX).
     * Matches dominant DB format (87k rows start with '3').
     */
    private function normalizePhone($phone)
    {
        $digits = preg_replace('/[^0-9]/', '', (string)$phone);
        if ($digits === '') return '';
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $digits = '38' . $digits;
        } elseif (strlen($digits) === 9) {
            $digits = '380' . $digits;
        }
        return $digits;
    }

    private function log($msg)
    {
        if ($this->log) {
            call_user_func($this->log, $msg);
        } else {
            echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
        }
    }
}