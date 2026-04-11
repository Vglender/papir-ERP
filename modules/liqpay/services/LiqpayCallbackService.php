<?php
/**
 * LiqpayCallbackService — обробляє дані від LiqPay (webhook або status()).
 *
 * Єдина точка обробки:
 *   LiqpayCallbackService::processPaymentData(array $lp) : array
 *
 * Куди приходить $lp:
 *   1. Webhook /liqpay/webhook/callback.php (POST data=base64,signature=base64 → decode)
 *   2. Cron backfill через reports API (LiqpayClient::request('reports'))
 *   3. Ручний імпорт (UI або скрипт) з тим же масивом параметрів
 *
 * Що робить:
 *   1. Знаходить connection за public_key → визначає site_id / site_code.
 *   2. Резолвить Papir-замовлення за (site_code, order_id) через customerorder.number
 *      (шаблон: "{oc_id}{BADGE}", напр. "98671OFF", "10511MF").
 *   3. Upsert в order_payment_receipt (unique key: provider+payment_id).
 *   4. Якщо status=success і order знайдено — ставить на замовленні
 *      payment_status='paid' + sum_paid=gross_amount НАПРЯМУ (без cashin) і
 *      fire'ить order_payment_changed. Фінансовий cashin/paymentin з'явиться
 *      окремо через Фазу 2 (банківська реконсіляція з комісією). Ця логіка
 *      дає сценаріям реагувати одразу, не чекаючи приходу грошей на рахунок.
 *
 * Повертає масив з результатом для логування.
 */
class LiqpayCallbackService
{
    const PROVIDER = 'liqpay';

    /**
     * @param array $lp  Декодовані параметри LiqPay (callback data або status-response).
     * @param bool  $fireScenarios  якщо false — НЕ fire'ить order_payment_changed
     *        навіть коли замовлення реально закривається. Використовується для
     *        backfill (історичних транзакцій), щоб не активувати сценарії на
     *        уже завершених замовленнях. Для webhook'а (живі події) = true.
     * @return array ['ok'=>bool, 'action'=>'inserted|updated|skipped|rejected',
     *                'receipt_id'=>int, 'order_id'=>int, 'order_closed'=>bool,
     *                'reason'=>string|null]
     */
    public static function processPaymentData(array $lp, $fireScenarios = true)
    {
        require_once __DIR__ . '/LiqpayConnectionRegistry.php';
        require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';

        // ── 1. Identify merchant ────────────────────────────────────────────
        $publicKey = isset($lp['public_key']) ? (string)$lp['public_key'] : '';
        if ($publicKey === '') {
            return self::fail('no_public_key', 'public_key missing in payload');
        }
        $conn = LiqpayConnectionRegistry::findByPublicKey($publicKey);
        if (!$conn) {
            return self::fail('unknown_merchant', "No LiqPay connection for public_key={$publicKey}");
        }
        $siteId   = (int)$conn['site_id'];
        $siteCode = $conn['site_code'];

        // ── 2. Extract core fields ──────────────────────────────────────────
        $paymentId       = isset($lp['payment_id']) ? (string)$lp['payment_id'] : '';
        $liqpayOrderId   = isset($lp['liqpay_order_id']) ? (string)$lp['liqpay_order_id'] : null;
        $ocOrderId       = isset($lp['order_id']) ? (string)$lp['order_id'] : '';
        $status          = isset($lp['status']) ? (string)$lp['status'] : 'unknown';
        $paytype         = isset($lp['paytype']) ? (string)$lp['paytype'] : null;
        $amount          = isset($lp['amount']) ? (float)$lp['amount'] : 0.0;
        $currency        = isset($lp['currency']) ? (string)$lp['currency'] : 'UAH';
        $createDateMs    = isset($lp['create_date']) ? (int)$lp['create_date'] : 0;
        $momentStr       = $createDateMs > 0 ? date('Y-m-d H:i:s', (int)($createDateMs / 1000)) : date('Y-m-d H:i:s');

        if ($paymentId === '') {
            return self::fail('no_payment_id', 'LiqPay payment_id missing');
        }
        if ($ocOrderId === '') {
            return self::fail('no_order_id', 'LiqPay order_id missing');
        }

        // ── 3. Resolve Papir order by site + oc_order_id ────────────────────
        $papirOrderId = self::resolveOrder($siteId, $siteCode, $ocOrderId);
        // Не fail'имо — receipt можемо зберегти навіть якщо замовлення ще
        // не імпортоване (прилетить пізніше). Але cashin без order не робимо.

        // ── 4. Upsert order_payment_receipt ─────────────────────────────────
        $rawJson = json_encode($lp, JSON_UNESCAPED_UNICODE);
        $existing = Database::fetchRow('Papir',
            "SELECT id, customerorder_id, status FROM order_payment_receipt
             WHERE provider='" . self::PROVIDER . "'
               AND payment_id='" . Database::escape('Papir', $paymentId) . "'
             LIMIT 1");

        if ($existing['ok'] && !empty($existing['row'])) {
            $receiptId = (int)$existing['row']['id'];
            $updateData = array(
                'status'   => $status,
                'amount'   => $amount,
                'currency' => $currency,
                'paytype'  => $paytype,
                'raw_json' => $rawJson,
            );
            if ($papirOrderId > 0 && (int)$existing['row']['customerorder_id'] !== $papirOrderId) {
                $updateData['customerorder_id'] = $papirOrderId;
            }
            Database::update('Papir', 'order_payment_receipt', $updateData, array('id' => $receiptId));
            $action = 'updated';
        } else {
            $rIns = Database::insert('Papir', 'order_payment_receipt', array(
                'customerorder_id'  => $papirOrderId > 0 ? $papirOrderId : 0,
                'site_id'           => $siteId,
                'site_order_id'     => (int)$ocOrderId,
                'provider'          => self::PROVIDER,
                'payment_id'        => $paymentId,
                'provider_order_id' => $liqpayOrderId,
                'status'            => $status,
                'paytype'           => $paytype,
                'amount'            => $amount,
                'currency'          => $currency,
                'raw_json'          => $rawJson,
            ));
            if (!$rIns['ok']) {
                return self::fail('receipt_insert_failed',
                    isset($rIns['error']) ? $rIns['error'] : 'insert failed');
            }
            $receiptId = (int)$rIns['insert_id'];
            $action = 'inserted';
        }

        // ── 5. Якщо success — оновити payment_status на замовленні ──────────
        // Важливо: НЕ створюємо cashin. Cashin створиться окремо в Фазі 2
        // (банківська реконсіляція з розщепленням на gross+commission).
        // Тут лише даємо сценаріям можливість реагувати одразу.
        $orderClosed = false;
        if ($status === 'success' && $papirOrderId > 0 && $amount > 0) {
            $orderClosed = self::markOrderPaid($papirOrderId, $amount, $fireScenarios);
        }

        return array(
            'ok'           => true,
            'action'       => $action,
            'receipt_id'   => $receiptId,
            'order_id'     => $papirOrderId,
            'order_closed' => $orderClosed,
            'site_id'      => $siteId,
            'site_code'    => $siteCode,
            'status'       => $status,
        );
    }

    /**
     * Знайти Papir customerorder.id за кодом сайту і OC order_id.
     * Шаблон номера: "{oc_id}{badge_uppercase}" (наприклад "10511MF", "98671OFF").
     */
    private static function resolveOrder($siteId, $siteCode, $ocOrderId)
    {
        $siteId = (int)$siteId;
        $ocOrderId = (string)$ocOrderId;

        // Читаємо badge з sites (fallback — siteCode)
        $rs = Database::fetchRow('Papir', "SELECT badge, code FROM sites WHERE site_id={$siteId} LIMIT 1");
        $badge = ($rs['ok'] && !empty($rs['row']['badge'])) ? strtoupper($rs['row']['badge']) : strtoupper($siteCode);
        $number = $ocOrderId . $badge;
        $numSql = Database::escape('Papir', $number);

        $r = Database::fetchRow('Papir',
            "SELECT id FROM customerorder WHERE number='{$numSql}' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) return (int)$r['row']['id'];

        // Fallback: спроба через external_code={siteCode}_{ocOrderId} (SiteOrderImporter)
        $extCode = $siteCode . '_' . $ocOrderId;
        $extSql = Database::escape('Papir', $extCode);
        $r = Database::fetchRow('Papir',
            "SELECT id FROM customerorder WHERE external_code='{$extSql}' LIMIT 1");
        if ($r['ok'] && !empty($r['row'])) return (int)$r['row']['id'];

        return 0;
    }

    /**
     * Закрити замовлення за оплатою: payment_status='paid' + sum_paid=gross,
     * fire order_payment_changed для сценаріїв.
     *
     * Ідемпотентно: якщо вже 'paid' — нічого не робить.
     * НЕ торкається shipment_status і не чіпає sum_total.
     * НЕ створює cashin — це задача Фази 2 (банківська реконсіляція).
     *
     * @return bool true якщо статус реально змінився (треба для логу/сценаріїв)
     */
    private static function markOrderPaid($orderId, $amount, $fireScenarios = true)
    {
        $orderId = (int)$orderId;
        $amount  = round((float)$amount, 2);

        $r = Database::fetchRow('Papir',
            "SELECT id, payment_status, sum_paid, sum_total, counterparty_id
             FROM customerorder WHERE id={$orderId} LIMIT 1");
        if (!$r['ok'] || empty($r['row'])) return false;
        $order = $r['row'];

        $oldStatus = $order['payment_status'];
        if ($oldStatus === 'paid') {
            // Уже paid — просто забезпечимо щоб sum_paid був >= amount
            if ((float)$order['sum_paid'] + 0.01 < $amount) {
                Database::update('Papir', 'customerorder',
                    array('sum_paid' => $amount),
                    array('id' => $orderId));
            }
            return false;
        }

        Database::update('Papir', 'customerorder', array(
            'payment_status' => 'paid',
            'sum_paid'       => $amount,
        ), array('id' => $orderId));

        // Fire order_payment_changed — scenarios react now (scen#13 та інші).
        // Backfill (fireScenarios=false) — тихо оновлюємо статус без тригерів,
        // бо історичні замовлення не мають реагувати задним числом.
        if ($fireScenarios && class_exists('TriggerEngine')) {
            $orderRow = array_merge($order, array(
                'payment_status' => 'paid',
                'sum_paid'       => $amount,
            ));
            \TriggerEngine::fire('order_payment_changed', array(
                'order'              => $orderRow,
                'order_id'           => $orderId,
                'counterparty_id'    => (int)$order['counterparty_id'],
                'old_payment_status' => $oldStatus,
                'new_payment_status' => 'paid',
                'source'             => 'liqpay',
            ));
        }

        return true;
    }

    private static function fail($code, $reason)
    {
        error_log('[liqpay] ' . $code . ': ' . $reason);
        return array(
            'ok'     => false,
            'action' => 'rejected',
            'code'   => $code,
            'reason' => $reason,
        );
    }
}
