<?php
/**
 * OrderFinanceHelper — розрахунок payment_status / shipment_status
 * із локальних документів (document_link + demand), без залежності від МойСклад.
 */
class OrderFinanceHelper
{
    /**
     * Сума оплат, прив'язаних до замовлення (bank + cash).
     */
    public static function sumPayments($orderId)
    {
        $orderId = (int)$orderId;
        $r = \Database::fetchRow('Papir',
            "SELECT COALESCE(SUM(dl.linked_sum), 0) AS total
             FROM document_link dl
             WHERE dl.from_type IN ('paymentin', 'cashin')
               AND dl.to_type   = 'customerorder'
               AND dl.to_id     = {$orderId}");
        return ($r['ok'] && !empty($r['row'])) ? (float)$r['row']['total'] : 0.0;
    }

    /**
     * Сума відвантажень (активні demand зі статусом shipped/arrived/transfer).
     */
    public static function sumShipped($orderId)
    {
        $orderId = (int)$orderId;
        $r = \Database::fetchRow('Papir',
            "SELECT COALESCE(SUM(d.sum_total), 0) AS total
             FROM document_link dl
             JOIN demand d ON (d.id_ms = dl.from_ms_id OR (dl.from_ms_id IS NULL AND d.id = dl.from_id))
             WHERE dl.from_type = 'demand'
               AND dl.to_type   = 'customerorder'
               AND dl.to_id     = {$orderId}
               AND d.deleted_at IS NULL
               AND d.status NOT IN ('cancelled','returned')
               AND d.status IN ('shipped','arrived','transfer')");
        return ($r['ok'] && !empty($r['row'])) ? (float)$r['row']['total'] : 0.0;
    }

    /**
     * Сума резерву (активні demand, які ще не відвантажені).
     */
    public static function sumReserved($orderId)
    {
        $orderId = (int)$orderId;
        $r = \Database::fetchRow('Papir',
            "SELECT COALESCE(SUM(d.sum_total), 0) AS total
             FROM document_link dl
             JOIN demand d ON (d.id_ms = dl.from_ms_id OR (dl.from_ms_id IS NULL AND d.id = dl.from_id))
             WHERE dl.from_type = 'demand'
               AND dl.to_type   = 'customerorder'
               AND dl.to_id     = {$orderId}
               AND d.deleted_at IS NULL
               AND d.status NOT IN ('cancelled','returned','shipped','arrived','transfer')");
        return ($r['ok'] && !empty($r['row'])) ? (float)$r['row']['total'] : 0.0;
    }

    /**
     * Визначити payment_status за сумою оплат і сумою замовлення.
     *
     * Універсальна толерантність: max(5 грн, 0.5%) для будь-яких джерел оплати.
     * Покриває комісії банку, округлення кур'єрських служб (накладений платіж),
     * мікродонеплати клієнтів. Залишок піде у модуль взаєморозрахунків,
     * нічого не «втрачається».
     *
     * LiqPay-aware: якщо на замовленні є успішний LiqPay receipt — вважаємо
     * його оплаченим навіть коли банківський paymentin ще не прийшов (sum_paid=0).
     * Це потрібно, щоб сценарії реагували одразу після колбека LiqPay, а не
     * через 1-2 дні коли банк зарахує гроші.
     */
    public static function resolvePaymentStatus($sumPaid, $sumTotal, $orderId = 0)
    {
        $sumPaid  = (float)$sumPaid;
        $sumTotal = (float)$sumTotal;

        $hasLiqpay = ($orderId > 0) ? self::hasSuccessfulLiqpayReceipt((int)$orderId) : false;

        if ($sumPaid <= 0) {
            return $hasLiqpay ? 'paid' : 'not_paid';
        }

        if ($sumTotal > 0) {
            $tolerance = max(5.00, $sumTotal * 0.005);
            if ($sumPaid >= $sumTotal - $tolerance) return 'paid';
        }

        return 'partially_paid';
    }

    /**
     * Чи має замовлення успішний receipt від LiqPay (незалежно від document_link).
     */
    public static function hasSuccessfulLiqpayReceipt($orderId)
    {
        $orderId = (int)$orderId;
        if (!$orderId) return false;
        $r = \Database::fetchRow('Papir',
            "SELECT 1 AS ok FROM order_payment_receipt
             WHERE customerorder_id={$orderId}
               AND provider='liqpay'
               AND status='success'
             LIMIT 1");
        return ($r['ok'] && !empty($r['row']));
    }

    /**
     * Визначити shipment_status за сумами відвантаження/резерву і сумою замовлення.
     */
    public static function resolveShipmentStatus($sumShipped, $sumReserved, $sumTotal)
    {
        $sumShipped  = (float)$sumShipped;
        $sumReserved = (float)$sumReserved;
        $sumTotal    = (float)$sumTotal;
        if ($sumShipped >= $sumTotal - 0.01 && $sumTotal > 0) return 'shipped';
        if ($sumShipped > 0)                                   return 'partially_shipped';
        if ($sumReserved > 0)                                  return 'reserved';
        return 'not_shipped';
    }

    /**
     * Перерахувати та оновити payment_status, shipment_status, sum_paid, sum_shipped, sum_reserved
     * на замовленні із локальних документів.
     *
     * @param int $orderId
     * @return array ['payment_status' => ..., 'shipment_status' => ..., 'sum_paid' => ..., ...]
     */
    public static function recalc($orderId)
    {
        $orderId = (int)$orderId;

        // Отримати поточний стан замовлення
        $r = \Database::fetchRow('Papir',
            "SELECT sum_total, payment_status, shipment_status, counterparty_id
             FROM customerorder WHERE id = {$orderId} LIMIT 1");
        if (!$r['ok'] || empty($r['row'])) return array();

        $order    = $r['row'];
        $sumTotal = (float)$order['sum_total'];
        $oldPaymentStatus  = $order['payment_status'];
        $oldShipmentStatus = $order['shipment_status'];

        $sumPaid     = self::sumPayments($orderId);
        $sumShipped  = self::sumShipped($orderId);
        $sumReserved = self::sumReserved($orderId);

        $paymentStatus  = self::resolvePaymentStatus($sumPaid, $sumTotal, $orderId);
        $shipmentStatus = self::resolveShipmentStatus($sumShipped, $sumReserved, $sumTotal);

        $data = array(
            'sum_paid'        => $sumPaid,
            'sum_shipped'     => $sumShipped,
            'sum_reserved'    => $sumReserved,
            'payment_status'  => $paymentStatus,
            'shipment_status' => $shipmentStatus,
        );

        \Database::update('Papir', 'customerorder', $data, array('id' => $orderId));

        // Якщо статус оплати або відвантаження змінився — fire triggers
        if (class_exists('TriggerEngine')) {
            $cpId = !empty($order['counterparty_id']) ? (int)$order['counterparty_id'] : 0;
            $orderRow = array_merge($order, $data, array('id' => $orderId));

            if ($paymentStatus !== $oldPaymentStatus) {
                \TriggerEngine::fire('order_payment_changed', array(
                    'order'           => $orderRow,
                    'order_id'        => $orderId,
                    'counterparty_id' => $cpId,
                    'old_payment_status' => $oldPaymentStatus,
                    'new_payment_status' => $paymentStatus,
                ));
            }
            if ($shipmentStatus !== $oldShipmentStatus) {
                \TriggerEngine::fire('order_shipment_changed', array(
                    'order'           => $orderRow,
                    'order_id'        => $orderId,
                    'counterparty_id' => $cpId,
                    'old_shipment_status' => $oldShipmentStatus,
                    'new_shipment_status' => $shipmentStatus,
                ));
            }
        }

        return $data;
    }
}
