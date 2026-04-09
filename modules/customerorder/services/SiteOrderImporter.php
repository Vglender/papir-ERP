<?php
/**
 * SiteOrderImporter — imports orders from OpenCart sites into Papir.
 *
 * For each active site:
 *   1. Finds last imported OC order_id via external_code
 *   2. Fetches new orders via SiteSyncService
 *   3. Creates counterparty (or updates existing by phone)
 *   4. Creates customerorder + items + shipping + payment receipts
 *   5. Fires 'order_created' trigger
 *
 * Dedup: external_code = '{site_code}_{oc_order_id}' (e.g. 'off_98643')
 */

require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';

class SiteOrderImporter
{
    /** @var SiteSyncService */
    private $sync;

    /** @var array shipping_code => {delivery_method_id, delivery_code} */
    private $deliveryMap = array();

    /** @var array payment_code => payment_method_id */
    private $paymentMap = array();

    /** @var callable|null */
    private $log;

    public function __construct($logCallback = null)
    {
        $this->sync = new SiteSyncService();
        $this->log  = $logCallback;
        $this->loadMappings();
    }

    /**
     * Run import for all active sites.
     * @return array ['imported' => int, 'errors' => array]
     */
    public function importAll()
    {
        $sites = $this->sync->getActiveSites();
        $totalImported = 0;
        $allErrors     = array();

        foreach ($sites as $site) {
            $siteId   = (int)$site['site_id'];
            $siteCode = $site['code'];

            $this->log("=== Site: {$site['name']} (id={$siteId}, code={$siteCode}) ===");

            $lastOcId = $this->getLastImportedOcOrderId($siteCode);
            $this->log("Last imported OC order_id: {$lastOcId}");

            $r = $this->sync->ordersList($siteId, array(
                'limit'     => 50,
                'date_from' => date('Y-m-d', strtotime('-7 days')),
            ));

            if (!$r['ok'] || empty($r['orders'])) {
                $this->log("No orders or error");
                continue;
            }

            // Filter only new orders (id > last imported)
            $newOrders = array();
            foreach ($r['orders'] as $ocOrder) {
                $ocId = (int)$ocOrder['order_id'];
                if ($ocId > $lastOcId) {
                    $newOrders[] = $ocOrder;
                }
            }

            // Sort ascending so we import in order
            usort($newOrders, function($a, $b) {
                return (int)$a['order_id'] - (int)$b['order_id'];
            });

            $this->log("New orders to import: " . count($newOrders));

            foreach ($newOrders as $ocOrder) {
                $ocId       = (int)$ocOrder['order_id'];
                $extCode    = $siteCode . '_' . $ocId;

                // Dedup check: by external_code AND by number (might exist from MoySklad)
                $exists = Database::fetchRow('Papir',
                    "SELECT id, source FROM customerorder
                     WHERE external_code = '" . Database::escape('Papir', $extCode) . "'
                        OR number = '" . Database::escape('Papir', $extCode) . "'
                     LIMIT 1");
                if ($exists['ok'] && !empty($exists['row'])) {
                    $this->log("Skip #{$ocId} — exists (order #{$exists['row']['id']}, source={$exists['row']['source']})");
                    continue;
                }
                // Also check by number pattern (e.g. 98643OFF from MoySklad)
                $numberCheck = $ocId . strtoupper(isset($site['badge']) ? $site['badge'] : $siteCode);
                $exists2 = Database::fetchRow('Papir',
                    "SELECT id FROM customerorder WHERE number = '" . Database::escape('Papir', $numberCheck) . "' LIMIT 1");
                if ($exists2['ok'] && !empty($exists2['row'])) {
                    $this->log("Skip #{$ocId} — exists by number {$numberCheck} (order #{$exists2['row']['id']})");
                    continue;
                }

                try {
                    // Fetch full order with positions
                    $full = $this->sync->ordersGet($siteId, $ocId);
                    if (!$full['ok'] || empty($full['order'])) {
                        $allErrors[] = "{$extCode}: Failed to fetch full order";
                        continue;
                    }

                    $orderId = $this->importOne($siteId, $site, $full['order']);
                    if ($orderId) {
                        $totalImported++;
                        $this->log("Imported #{$ocId} → Papir order #{$orderId}");
                    }
                } catch (Exception $e) {
                    $allErrors[] = "{$extCode}: " . $e->getMessage();
                    $this->log("ERROR #{$ocId}: " . $e->getMessage());
                }
            }
        }

        return array('imported' => $totalImported, 'errors' => $allErrors);
    }

    /**
     * Import a single OC order.
     *
     * @param int   $siteId
     * @param array $site     site row from sites table
     * @param array $ocOrder  full order from SiteSyncService::ordersGet()
     * @return int|null  Papir order ID or null on failure
     */
    private function importOne($siteId, array $site, array $ocOrder)
    {
        $siteCode = $site['code'];
        $ocId     = (int)$ocOrder['order_id'];
        $extCode  = $siteCode . '_' . $ocId;
        $badge    = strtoupper(isset($site['badge']) ? $site['badge'] : $siteCode);

        // ── Resolve counterparty ────────────────────────────────────────
        $phone     = $this->normalizePhone(isset($ocOrder['telephone']) ? $ocOrder['telephone'] : '');
        $firstName = isset($ocOrder['firstname']) ? trim($ocOrder['firstname']) : '';
        $lastName  = isset($ocOrder['lastname']) ? trim($ocOrder['lastname']) : '';
        $email     = isset($ocOrder['email']) ? trim($ocOrder['email']) : '';
        $customerId = isset($ocOrder['customer_id']) ? (int)$ocOrder['customer_id'] : 0;

        // Check for EDRPOU in simple_fields
        $edrpou = '';
        if (isset($ocOrder['simple_fields']) && is_array($ocOrder['simple_fields'])) {
            foreach ($ocOrder['simple_fields'] as $sf) {
                if (isset($sf['edrpou']) && trim($sf['edrpou']) !== '') {
                    $edrpou = trim($sf['edrpou']);
                }
            }
        }
        // simple_fields might be a single row (not array of rows)
        if (!$edrpou && isset($ocOrder['edrpou']) && trim($ocOrder['edrpou']) !== '') {
            $edrpou = trim($ocOrder['edrpou']);
        }

        $cpId = $this->resolveCounterparty($phone, $firstName, $lastName, $email,
            $edrpou, $siteCode, $customerId);

        // ── Parse delivery / payment ────────────────────────────────────
        $shippingCode = isset($ocOrder['shipping_code']) ? $ocOrder['shipping_code'] : '';
        $paymentCode  = isset($ocOrder['payment_code']) ? $ocOrder['payment_code'] : '';

        $deliveryMethodId = $this->resolveDeliveryMethod($shippingCode);
        $paymentMethodId  = $this->resolvePaymentMethod($paymentCode);

        // ── Parse wait_call from simple_fields ──────────────────────────
        $waitCall = 0;
        $noCallValue = '';
        if (isset($ocOrder['simple_fields']) && is_array($ocOrder['simple_fields'])) {
            foreach ($ocOrder['simple_fields'] as $sf) {
                if (isset($sf['no_call'])) $noCallValue = trim($sf['no_call']);
            }
        }
        // "Ні" = don't wait call, anything else (or empty/missing) = wait call
        if ($noCallValue !== '' && mb_strtolower($noCallValue, 'UTF-8') !== 'ні') {
            $waitCall = 1;
        }
        // Fallback: check comment for legacy pattern
        $comment = isset($ocOrder['comment']) ? $ocOrder['comment'] : '';
        if (!$waitCall && mb_stripos($comment, 'Чекаю на дзвінок') !== false) {
            $waitCall = 1;
        }

        // ── Calculate totals ────────────────────────────────────────────
        $sumTotal = isset($ocOrder['total']) ? (float)$ocOrder['total'] : 0;
        $sumItems = 0;
        $products = isset($ocOrder['products']) ? $ocOrder['products'] : array();
        foreach ($products as $p) {
            $sumItems += (float)$p['total'];
        }
        if ($sumItems <= 0) $sumItems = $sumTotal;

        // VAT total (calculated after we know if company)
        $sumVat = 0;

        // ── Order number ────────────────────────────────────────────────
        $number = $ocId . $badge;

        // ── Create UUID ─────────────────────────────────────────────────
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        // ── Resolve organization, bank account, VAT ────────────────────
        $isCompany = ($edrpou !== '' && strlen($edrpou) >= 8);

        // Defaults for physical persons: ФОП Чумаченко + ПриватБанк
        $organizationId = 6;        // ФОП Чумаченко Вікторія Вікторівна
        $bankAccountId  = 3;        // ПриватБанк
        $vatRate        = 0;

        if ($isCompany) {
            $organizationId = 8;    // ТОВ Архкор
            $bankAccountId  = 4;    // УкрСибБанк
            $vatRate        = 20;   // ПДВ 20%
        }

        // Sales channel: site code matching edit.php dropdown
        $salesChannel = $siteCode; // 'off' or 'mff'

        // ── INSERT customerorder ────────────────────────────────────────
        $orderData = array(
            'uuid'                       => $uuid,
            'source'                     => 'site_import',
            'external_code'              => $extCode,
            'number'                     => $number,
            'moment'                     => isset($ocOrder['date_added']) ? $ocOrder['date_added'] : date('Y-m-d H:i:s'),
            'applicable'                 => 1,
            'organization_id'            => $organizationId,
            'organization_bank_account_id' => $bankAccountId,
            'store_id'                   => 1,  // Основний склад
            'manager_employee_id'        => 5,  // Василь Робот
            'counterparty_id'            => $cpId,
            'status'                     => 'new',
            'payment_status'             => 'not_paid',
            'shipment_status'            => 'not_shipped',
            'currency_code'              => isset($ocOrder['currency_code']) ? $ocOrder['currency_code'] : 'UAH',
            'sum_items'                  => $sumItems,
            'sum_vat'                    => $isCompany ? round($sumTotal - ($sumTotal / 1.2), 2) : 0,
            'sum_total'                  => $sumTotal,
            'sales_channel'              => $salesChannel,
            'delivery_method_id'         => $deliveryMethodId,
            'payment_method_id'          => $paymentMethodId,
            'description'                => $comment,
            'wait_call'                  => $waitCall,
            'sync_state'                 => 'new',
        );

        $rIns = Database::insert('Papir', 'customerorder', $orderData);
        if (!$rIns['ok']) {
            throw new Exception('Failed to insert customerorder: ' . (isset($rIns['error']) ? $rIns['error'] : 'unknown'));
        }
        $orderId = (int)$rIns['insert_id'];

        // ── INSERT customerorder_item ────────────────────────────────────
        $lineNo = 1;
        foreach ($products as $p) {
            $ocProductId = (int)$p['product_id'];
            $productName = isset($p['name']) ? $p['name'] : '';
            $sku         = isset($p['model']) ? $p['model'] : (isset($p['sku']) ? $p['sku'] : '');
            $qty         = isset($p['quantity']) ? (float)$p['quantity'] : 1;
            $price       = isset($p['price']) ? (float)$p['price'] : 0;
            $total       = isset($p['total']) ? (float)$p['total'] : ($qty * $price);

            // Resolve Papir product_id from site_product_id
            $papirProductId = null;
            if ($ocProductId > 0) {
                $psR = Database::fetchRow('Papir',
                    "SELECT product_id FROM product_site
                     WHERE site_id = {$siteId} AND site_product_id = {$ocProductId} LIMIT 1");
                if ($psR['ok'] && !empty($psR['row'])) {
                    $papirProductId = (int)$psR['row']['product_id'];
                }
            }

            // Options → comment (MFF)
            $optionsComment = '';
            if (isset($p['options']) && is_array($p['options']) && !empty($p['options'])) {
                $optParts = array();
                foreach ($p['options'] as $opt) {
                    $optName  = isset($opt['name']) ? $opt['name'] : '';
                    $optValue = isset($opt['value']) ? $opt['value'] : '';
                    if ($optName && $optValue) {
                        $optParts[] = $optName . ': ' . $optValue;
                    }
                }
                if (!empty($optParts)) {
                    $optionsComment = implode('; ', $optParts);
                }
            }

            // VAT for companies
            $itemVatRate   = $vatRate;
            $itemVatAmount = 0;
            if ($itemVatRate > 0) {
                // Price in OC includes VAT, calculate VAT amount
                $itemVatAmount = round($total - ($total / (1 + $itemVatRate / 100)), 2);
            }

            $itemData = array(
                'customerorder_id' => $orderId,
                'line_no'          => $lineNo,
                'product_id'       => $papirProductId,
                'product_name'     => $productName,
                'sku'              => $sku,
                'quantity'         => $qty,
                'price'            => $price,
                'vat_rate'         => $itemVatRate,
                'vat_amount'       => $itemVatAmount,
                'sum_without_discount' => $total,
                'sum_row'          => $total,
                'comment'          => $optionsComment !== '' ? $optionsComment : null,
            );

            Database::insert('Papir', 'customerorder_item', $itemData);
            $lineNo++;
        }

        // ── INSERT customerorder_shipping ────────────────────────────────
        $shippingData = array(
            'customerorder_id'    => $orderId,
            'counterparty_id'     => $cpId,
            'recipient_first_name' => $firstName,
            'recipient_last_name'  => $lastName,
            'recipient_phone'      => $phone,
            'city_name'            => isset($ocOrder['shipping_city']) ? $ocOrder['shipping_city'] : '',
            'delivery_code'        => $this->resolveDeliveryCode($shippingCode),
            'delivery_method_name' => isset($ocOrder['shipping_method']) ? trim($ocOrder['shipping_method']) : '',
            'no_call'              => ($waitCall === 0) ? 1 : 0,
            'source'               => 'site_' . $siteCode,
        );

        // Address from simple_fields
        if (isset($ocOrder['simple_fields']) && is_array($ocOrder['simple_fields'])) {
            foreach ($ocOrder['simple_fields'] as $sf) {
                if (isset($sf['shipping_street']) && trim($sf['shipping_street']) !== '')
                    $shippingData['street'] = trim($sf['shipping_street']);
                if (isset($sf['shipping_house']) && trim($sf['shipping_house']) !== '')
                    $shippingData['house'] = trim($sf['shipping_house']);
                if (isset($sf['shipping_flat']) && trim($sf['shipping_flat']) !== '')
                    $shippingData['flat'] = trim($sf['shipping_flat']);
            }
        }

        // Branch name from shipping_address_1
        if (isset($ocOrder['shipping_address_1']) && trim($ocOrder['shipping_address_1']) !== '') {
            $shippingData['branch_name'] = trim($ocOrder['shipping_address_1']);
        }

        // NP tracking number
        if (isset($ocOrder['novaposhta_cn_number']) && trim($ocOrder['novaposhta_cn_number']) !== '') {
            $shippingData['comment'] = 'ТТН: ' . trim($ocOrder['novaposhta_cn_number']);
        }

        Database::insert('Papir', 'customerorder_shipping', $shippingData);

        // ── LiqPay / payment receipts ───────────────────────────────────
        if (isset($ocOrder['payment_receipts']) && is_array($ocOrder['payment_receipts'])) {
            foreach ($ocOrder['payment_receipts'] as $receipt) {
                $this->savePaymentReceipt($orderId, $siteId, $ocId, $receipt);
            }
        }

        // ── Document history ────────────────────────────────────────────
        Database::insert('Papir', 'document_history', array(
            'document_type' => 'customerorder',
            'document_id'   => $orderId,
            'action'        => 'create',
            'field_name'    => 'status',
            'new_value'     => 'new',
            'actor_type'    => 'system',
            'actor_label'   => 'SiteOrderImporter (' . $siteCode . ')',
            'comment'       => 'Imported from ' . $site['name'] . ' order #' . $ocId,
        ));

        // ── Fire trigger ────────────────────────────────────────────────
        if (class_exists('TriggerEngine')) {
            TriggerEngine::fire('order_created', array(
                'order_id'        => $orderId,
                'counterparty_id' => $cpId,
                'source'          => 'site_import',
                'sales_channel'   => $siteCode,
            ));
        }

        return $orderId;
    }

    // =====================================================================
    // COUNTERPARTY
    // =====================================================================

    private function resolveCounterparty($phone, $firstName, $lastName, $email,
                                         $edrpou, $siteCode, $ocCustomerId)
    {
        $isCompany = ($edrpou !== '' && strlen($edrpou) >= 8);

        // Search by phone
        $cpId = 0;
        if ($phone !== '') {
            $phoneEsc = Database::escape('Papir', $phone);
            $r = Database::fetchRow('Papir',
                "SELECT cp.id FROM counterparty cp
                 JOIN counterparty_person cpp ON cpp.counterparty_id = cp.id
                 WHERE cpp.phone = '{$phoneEsc}' AND cp.status = 1
                 LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                $cpId = (int)$r['row']['id'];
            }
        }

        if ($cpId > 0) {
            // Update FIO if changed
            $this->updateCounterpartyPerson($cpId, $firstName, $lastName, $email);
            // Update site_customer_ids
            $this->linkSiteCustomer($cpId, $siteCode, $ocCustomerId);
            return $cpId;
        }

        // Create new counterparty
        $fullName = trim($lastName . ' ' . $firstName);
        if ($fullName === '') $fullName = $email;
        if ($fullName === '') $fullName = $phone;

        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        if ($isCompany) {
            // Company: name = EDRPOU-based, contact person separate
            $companyName = 'Юрособа ЄДРПОУ ' . $edrpou;

            $rCp = Database::insert('Papir', 'counterparty', array(
                'uuid'   => $uuid,
                'type'   => 'company',
                'name'   => $companyName,
                'source' => 'site_import',
                'status' => 1,
                'description' => 'ЄДРПОУ: ' . $edrpou,
            ));
            $cpId = (int)$rCp['insert_id'];

            Database::insert('Papir', 'counterparty_person', array(
                'counterparty_id' => $cpId,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'full_name'       => $fullName,
                'phone'           => $phone,
                'email'           => $email,
                'position_name'   => 'Контактна особа',
            ));
        } else {
            // Person
            $rCp = Database::insert('Papir', 'counterparty', array(
                'uuid'   => $uuid,
                'type'   => 'person',
                'name'   => $fullName,
                'source' => 'site_import',
                'status' => 1,
            ));
            $cpId = (int)$rCp['insert_id'];

            Database::insert('Papir', 'counterparty_person', array(
                'counterparty_id' => $cpId,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'full_name'       => $fullName,
                'phone'           => $phone,
                'email'           => $email,
            ));
        }

        $this->linkSiteCustomer($cpId, $siteCode, $ocCustomerId);
        return $cpId;
    }

    private function updateCounterpartyPerson($cpId, $firstName, $lastName, $email)
    {
        $updates = array();
        $fullName = trim($lastName . ' ' . $firstName);

        $current = Database::fetchRow('Papir',
            "SELECT first_name, last_name, email FROM counterparty_person WHERE counterparty_id = {$cpId}");
        if (!$current['ok'] || empty($current['row'])) return;

        $cur = $current['row'];
        if ($firstName !== '' && $cur['first_name'] !== $firstName) $updates['first_name'] = $firstName;
        if ($lastName !== ''  && $cur['last_name'] !== $lastName)   $updates['last_name']  = $lastName;
        if ($email !== ''     && $cur['email'] !== $email)          $updates['email']      = $email;

        if (!empty($updates)) {
            if (isset($updates['first_name']) || isset($updates['last_name'])) {
                $newFirst = isset($updates['first_name']) ? $updates['first_name'] : $cur['first_name'];
                $newLast  = isset($updates['last_name']) ? $updates['last_name'] : $cur['last_name'];
                $updates['full_name'] = trim($newLast . ' ' . $newFirst);
            }
            Database::update('Papir', 'counterparty_person', $updates,
                array('counterparty_id' => $cpId));

            // Update counterparty.name too
            if (isset($updates['full_name'])) {
                Database::update('Papir', 'counterparty',
                    array('name' => $updates['full_name']),
                    array('id' => $cpId));
            }
        }

        // Update last_activity
        Database::query('Papir',
            "UPDATE counterparty SET last_activity_at = NOW() WHERE id = {$cpId}");
    }

    private function linkSiteCustomer($cpId, $siteCode, $ocCustomerId)
    {
        if ($ocCustomerId <= 0) return;

        $r = Database::fetchRow('Papir',
            "SELECT site_customer_ids FROM counterparty WHERE id = {$cpId}");
        if (!$r['ok'] || empty($r['row'])) return;

        $ids = !empty($r['row']['site_customer_ids'])
            ? json_decode($r['row']['site_customer_ids'], true) : array();
        if (!is_array($ids)) $ids = array();

        if (!isset($ids[$siteCode]) || (int)$ids[$siteCode] !== $ocCustomerId) {
            $ids[$siteCode] = $ocCustomerId;
            Database::update('Papir', 'counterparty',
                array('site_customer_ids' => json_encode($ids)),
                array('id' => $cpId));
        }
    }

    // =====================================================================
    // PAYMENT RECEIPTS
    // =====================================================================

    private function savePaymentReceipt($orderId, $siteId, $ocOrderId, array $receipt)
    {
        $json = isset($receipt['all_json_data']) ? $receipt['all_json_data'] : '';
        $data = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($data)) $data = array();

        Database::insert('Papir', 'order_payment_receipt', array(
            'customerorder_id'  => $orderId,
            'site_id'           => $siteId,
            'site_order_id'     => $ocOrderId,
            'provider'          => isset($receipt['payment_type']) ? $receipt['payment_type'] : 'liqpay',
            'payment_id'        => isset($data['payment_id']) ? (string)$data['payment_id'] : null,
            'provider_order_id' => isset($data['liqpay_order_id']) ? $data['liqpay_order_id'] : null,
            'status'            => isset($data['status']) ? $data['status'] : 'unknown',
            'paytype'           => isset($data['paytype']) ? $data['paytype'] : null,
            'amount'            => isset($data['amount']) ? (float)$data['amount'] : 0,
            'currency'          => isset($data['currency']) ? $data['currency'] : 'UAH',
            'raw_json'          => $json,
        ));
    }

    // =====================================================================
    // MAPPINGS & HELPERS
    // =====================================================================

    private function loadMappings()
    {
        $r = Database::fetchAll('Papir', "SELECT * FROM site_delivery_method_map");
        if ($r['ok']) {
            foreach ($r['rows'] as $row) {
                $this->deliveryMap[$row['shipping_code']] = array(
                    'delivery_method_id' => (int)$row['delivery_method_id'],
                    'delivery_code'      => $row['delivery_code'],
                );
            }
        }

        $r = Database::fetchAll('Papir', "SELECT * FROM site_payment_method_map");
        if ($r['ok']) {
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
        // Fuzzy match: novaposhta.* → 3
        if (strpos($shippingCode, 'novaposhta') !== false) return 3;
        if (strpos($shippingCode, 'ukrposhta') !== false)  return 4;
        if (strpos($shippingCode, 'pickup') !== false)      return 1;
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
        // Fuzzy
        if (strpos($paymentCode, 'liqpay') !== false || strpos($paymentCode, 'revpay') !== false) return 5;
        if (strpos($paymentCode, 'cod') !== false) return 4;
        return null;
    }

    private function getLastImportedOcOrderId($siteCode)
    {
        $prefix = Database::escape('Papir', $siteCode . '_');
        $r = Database::fetchRow('Papir',
            "SELECT external_code FROM customerorder
             WHERE external_code LIKE '{$prefix}%' AND source = 'site_import'
             ORDER BY id DESC LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) {
            $parts = explode('_', $r['row']['external_code']);
            return (int)end($parts);
        }
        return 0;
    }

    private function normalizePhone($phone)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '+38' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 2) === '38') {
            $phone = '+' . $phone;
        }
        return $phone;
    }

    private function log($msg)
    {
        if ($this->log) {
            call_user_func($this->log, $msg);
        }
    }
}
