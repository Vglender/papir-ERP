<?php
/**
 * POST /counterparties/api/save_order_status
 * Quick status update for an order from the workspace panel.
 * Validates business rules before changing status.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';
require_once __DIR__ . '/../../customerorder/services/CustomerOrderMsSync.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

if (!\Papir\Crm\AuthService::isLoggedIn()) {
    echo json_encode(array('ok' => false, 'error' => 'Unauthorized'));
    exit;
}

$orderId     = isset($_POST['order_id'])    ? (int)$_POST['order_id']    : 0;
$status      = isset($_POST['status'])      ? trim($_POST['status'])      : null;
$description = isset($_POST['description']) ? trim($_POST['description']) : null;

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'Invalid params'));
    exit;
}

$data = array();

if ($status !== null) {
    $allowed = array('draft','new','confirmed','in_progress','waiting_payment','paid','shipped','partially_shipped','completed','cancelled');
    if (!in_array($status, $allowed)) {
        echo json_encode(array('ok' => false, 'error' => 'Invalid status'));
        exit;
    }
    $data['status'] = $status;
}

if ($description !== null) {
    $data['description'] = $description;
}

if (empty($data)) {
    echo json_encode(array('ok' => false, 'error' => 'Nothing to update'));
    exit;
}

// Fetch old status before update (for history log and transition validation)
$oldStatus = null;
if ($status !== null) {
    $rOld = \Database::fetchRow('Papir', "SELECT status FROM customerorder WHERE id={$orderId} AND deleted_at IS NULL");
    if (!$rOld['ok'] || empty($rOld['row'])) {
        echo json_encode(array('ok' => false, 'error' => 'Order not found'));
        exit;
    }
    $oldStatus = $rOld['row']['status'];
}

// Validate transition rules
if ($status !== null && $oldStatus !== null && $oldStatus !== $status) {
    $validationResult = validateStatusTransition($orderId, $oldStatus, $status);
    if (!$validationResult['ok']) {
        echo json_encode(array('ok' => false, 'error' => $validationResult['error']));
        exit;
    }
}

$r = \Database::update('Papir', 'customerorder',
    $data,
    array('id' => $orderId)
);

if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'DB error'));
    exit;
}

if ($status !== null) {
    // Write to customerorder_history (is_auto=0 — manual change from UI)
    \Database::insert('Papir', 'customerorder_history', array(
        'customerorder_id' => $orderId,
        'event_type'       => 'status_change',
        'field_name'       => 'status',
        'old_value'        => $oldStatus,
        'new_value'        => $status,
        'is_auto'          => 0,
        'comment'          => 'Зміна статусу вручну',
    ));
    \Papir\Crm\AuthService::log('status_change', 'customerorder', $orderId, $status);
}
echo json_encode(array('ok' => true));

// Push to MoySklad (after response sent)
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
try {
    $msSync = new CustomerOrderMsSync();
    $msSync->push($orderId);
} catch (Exception $e) {
    // silent — main operation succeeded
}

// ────────────────────────────────────────────────────────────────────────────
// Business rule validation for status transitions
// ────────────────────────────────────────────────────────────────────────────

/**
 * Pipeline step order.
 * Lower number = earlier in the flow.
 * cancelled is treated separately (not a linear step).
 */
function getStepOrder($status) {
    $steps = array(
        'draft'             => 0,
        'new'               => 0,
        'confirmed'         => 1,
        'waiting_payment'   => 2,
        'in_progress'       => 3,
        'partially_shipped' => 4,
        'shipped'           => 4,
        'completed'         => 5,
        'cancelled'         => -1,   // special branch
    );
    return isset($steps[$status]) ? $steps[$status] : -2;
}

/**
 * Returns array('ok'=>true) or array('ok'=>false,'error'=>string).
 */
function validateStatusTransition($orderId, $fromStatus, $toStatus) {
    $fromStep = getStepOrder($fromStatus);
    $toStep   = getStepOrder($toStatus);

    // ── shipped/partially_shipped cannot be set manually ────────────────────
    // These statuses are set automatically when a delivery or TTN is registered.
    if ($toStatus === 'shipped' || $toStatus === 'partially_shipped') {
        return array('ok' => false, 'error' => 'Статус "Відправлено" встановлюється автоматично при реєстрації доставки або ТТН. Використовуйте кнопку "Доставка/ТТН" у схемі замовлення');
    }

    // ── Moving to "cancelled" ────────────────────────────────────────────────
    // Allowed from any status (warnings are shown client-side).
    // Exception: from completed — also allowed but requires client confirmation.
    if ($toStatus === 'cancelled') {
        return array('ok' => true);
    }

    // ── Moving from "cancelled" → restore ───────────────────────────────────
    // Not allowed (cancellation is irreversible via this button).
    if ($fromStatus === 'cancelled') {
        return array('ok' => false, 'error' => 'Скасоване замовлення не можна відновити через цей інтерфейс');
    }

    $isForward  = $toStep > $fromStep;
    $isBackward = $toStep < $fromStep;

    // ── FORWARD transitions ──────────────────────────────────────────────────
    if ($isForward) {

        // → shipped (step 4): requires active demand + (active TTN or order_delivery sent/delivered)
        if ($toStep >= 4 && $fromStep < 4) {
            $demandCnt = countActiveDemands($orderId);
            if ($demandCnt === 0) {
                return array('ok' => false,
                    'error' => 'Для переходу в "Відправлено" потрібна накладна (відвантаження)');
            }
            $ttnCnt = countActiveTtns($orderId);
            $odlCnt = countActiveOrderDeliveries($orderId);
            if ($ttnCnt === 0 && $odlCnt === 0) {
                return array('ok' => false,
                    'error' => 'Для переходу в "Відправлено" потрібна ТТН або зареєстрована доставка (кур\'єр/самовивіз)');
            }
        }

        // → completed (step 5): requires active demand + payment + delivered delivery
        if ($toStep >= 5) {
            $demandCnt = countActiveDemands($orderId);
            if ($demandCnt === 0) {
                return array('ok' => false,
                    'error' => 'Для переходу в "Виконано" потрібна накладна (відвантаження)');
            }
            $paymentSum = sumPayments($orderId);
            if ($paymentSum <= 0) {
                return array('ok' => false,
                    'error' => 'Для переходу в "Виконано" потрібна оплата');
            }
            $deliveredCnt = countDeliveredDeliveries($orderId);
            if ($deliveredCnt === 0) {
                return array('ok' => false,
                    'error' => 'Для переходу в "Виконано" потрібно підтвердження отримання: статус доставки "Доставлено" або ТТН зі статусом отримання');
            }
        }
    }

    // ── BACKWARD transitions ─────────────────────────────────────────────────
    if ($isBackward) {

        // From "completed" (step 5): only cancelled is allowed (handled above).
        if ($fromStep >= 5) {
            return array('ok' => false,
                'error' => 'Із "Виконано" можна перейти лише в "Скасовано"');
        }

        // From "shipped" (step 4): no active demand, no payment,
        // and if active TTN exists — must have a return logistics record.
        if ($fromStep >= 4) {
            $demandCnt = countActiveDemands($orderId);
            if ($demandCnt > 0) {
                return array('ok' => false,
                    'error' => 'Неможливо знизити статус: є активне відвантаження (накладна)');
            }
            $paymentSum = sumPayments($orderId);
            if ($paymentSum > 0) {
                return array('ok' => false,
                    'error' => 'Неможливо знизити статус: зареєстровано оплату');
            }
            $activeTtnCnt = countActiveTtns($orderId);
            if ($activeTtnCnt > 0) {
                // Has active TTN — must have a return logistics record (TTN return)
                $returnLogCnt = countReturnLogisticsWithTtn($orderId);
                if ($returnLogCnt === 0) {
                    return array('ok' => false,
                        'error' => 'Є активна ТТН — спочатку зареєструйте повернення ТТН у розділі "Повернення"');
                }
            }
        } elseif ($fromStep >= 3) {
            // From "in_progress" (step 3): no active demand, no payment.
            $demandCnt = countActiveDemands($orderId);
            if ($demandCnt > 0) {
                return array('ok' => false,
                    'error' => 'Неможливо знизити статус: є активне відвантаження (накладна)');
            }
            $paymentSum = sumPayments($orderId);
            if ($paymentSum > 0) {
                return array('ok' => false,
                    'error' => 'Неможливо знизити статус: зареєстровано оплату');
            }
        }
        // From "confirmed" (step 1) or "waiting_payment" (step 2):
        // warning is shown client-side, no server-side block.
    }

    return array('ok' => true);
}

/**
 * Count active demands (не скасовані, не видалені) linked to the order.
 */
function countActiveDemands($orderId) {
    // Demands are linked via document_link (from_type='demand', to_type='customerorder')
    // and joined on demand.id_ms = dl.from_ms_id OR demand.id = dl.from_id
    $r = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt
         FROM document_link dl
         JOIN demand d ON (d.id_ms = dl.from_ms_id OR (dl.from_ms_id IS NULL AND d.id = dl.from_id))
         WHERE dl.from_type = 'demand'
           AND dl.to_type   = 'customerorder'
           AND dl.to_id     = {$orderId}
           AND d.deleted_at IS NULL
           AND d.status NOT IN ('cancelled','returned')");
    return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['cnt'] : 0;
}

/**
 * Count active TTNs linked to the order (not returned/refused).
 * Active NP TTN: state_define NOT IN (102, 105) and not in refused state.
 * Active UP TTN: lifecycle_status NOT IN ('RETURNED','RETURNING','CANCELLED','DELETED').
 */
function countActiveTtns($orderId) {
    $cnt = 0;
    // Nova Poshta
    $rNp = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt
         FROM document_link dl
         JOIN ttn_novaposhta tn ON tn.id = dl.from_id
         WHERE dl.from_type = 'ttn_np'
           AND dl.to_type   = 'customerorder'
           AND dl.to_id     = {$orderId}
           AND (tn.deletion_mark IS NULL OR tn.deletion_mark = 0)
           AND tn.state_define NOT IN (102, 105)
           AND LOWER(tn.state_name) NOT LIKE '%відмов%'
           AND LOWER(tn.state_name) NOT LIKE '%отказ%'");
    if ($rNp['ok'] && !empty($rNp['row'])) {
        $cnt += (int)$rNp['row']['cnt'];
    }
    // Ukrposhta
    $rUp = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt
         FROM document_link dl
         JOIN ttn_ukrposhta tu ON tu.id = dl.from_id
         WHERE dl.from_type = 'ttn_up'
           AND dl.to_type   = 'customerorder'
           AND dl.to_id     = {$orderId}
           AND tu.lifecycle_status NOT IN ('RETURNED','RETURNING','CANCELLED','DELETED')");
    if ($rUp['ok'] && !empty($rUp['row'])) {
        $cnt += (int)$rUp['row']['cnt'];
    }
    return $cnt;
}

/**
 * Sum of all linked payments (bank + cash) for the order.
 */
function sumPayments($orderId) {
    $r = \Database::fetchRow('Papir',
        "SELECT COALESCE(SUM(dl.linked_sum), 0) AS total
         FROM document_link dl
         WHERE dl.from_type IN ('paymentin', 'cashin')
           AND dl.to_type   = 'customerorder'
           AND dl.to_id     = {$orderId}");
    return ($r['ok'] && !empty($r['row'])) ? (float)$r['row']['total'] : 0.0;
}

/**
 * Count active order_delivery records (sent or delivered) for non-TTN delivery methods.
 * Used as an alternative to TTN when checking forward move to "shipped".
 */
function countActiveOrderDeliveries($orderId) {
    $r = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt
         FROM order_delivery od
         JOIN delivery_method dm ON dm.id = od.delivery_method_id
         WHERE od.customerorder_id = {$orderId}
           AND od.status IN ('sent', 'delivered')
           AND dm.has_ttn = 0");
    return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['cnt'] : 0;
}

/**
 * Count non-cancelled return_logistics records with TTN type for the order.
 * Used when checking backward move from "shipped" with active TTN.
 */
function countReturnLogisticsWithTtn($orderId) {
    $r = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt
         FROM return_logistics
         WHERE customerorder_id = {$orderId}
           AND status != 'cancelled'
           AND return_type IN ('novaposhta_ttn', 'ukrposhta_ttn')");
    return ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['cnt'] : 0;
}

/**
 * Count deliveries confirmed as received:
 *  - order_delivery with status='delivered' (courier/pickup)
 *  - NP TTN with state_define=9 (Відправлення отримано) or state_name contains 'Отримано'/'Одержано'
 *  - UP TTN with lifecycle_status='DELIVERED'
 */
function countDeliveredDeliveries($orderId) {
    $cnt = 0;

    // Courier/pickup delivered
    $rOdl = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM order_delivery od
         JOIN delivery_method dm ON dm.id = od.delivery_method_id
         WHERE od.customerorder_id = {$orderId}
           AND od.status = 'delivered'
           AND dm.has_ttn = 0");
    if ($rOdl['ok'] && !empty($rOdl['row'])) $cnt += (int)$rOdl['row']['cnt'];

    // NP TTN delivered (state_define=9 = "Відправлення отримано")
    $rNp = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM document_link dl
         JOIN ttn_novaposhta tn ON tn.id = dl.from_id
         WHERE dl.from_type = 'ttn_np' AND dl.to_type = 'customerorder' AND dl.to_id = {$orderId}
           AND (tn.deletion_mark IS NULL OR tn.deletion_mark = 0)
           AND (tn.state_define = 9
                OR LOWER(tn.state_name) LIKE '%отримано%'
                OR LOWER(tn.state_name) LIKE '%одержано%')");
    if ($rNp['ok'] && !empty($rNp['row'])) $cnt += (int)$rNp['row']['cnt'];

    // UP TTN delivered
    $rUp = \Database::fetchRow('Papir',
        "SELECT COUNT(*) AS cnt FROM document_link dl
         JOIN ttn_ukrposhta tu ON tu.id = dl.from_id
         WHERE dl.from_type = 'ttn_up' AND dl.to_type = 'customerorder' AND dl.to_id = {$orderId}
           AND tu.lifecycle_status IN ('DELIVERED','NOTICE','AWAITING_PICKUP')");
    if ($rUp['ok'] && !empty($rUp['row'])) $cnt += (int)$rUp['row']['cnt'];

    return $cnt;
}
