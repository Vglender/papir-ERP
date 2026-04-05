<?php
/**
 * POST /counterparties/api/save_order
 * Saves full order state with optimistic locking (version field).
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';
require_once __DIR__ . '/../../customerorder/customerorder_bootstrap.php';
require_once __DIR__ . '/../../customerorder/services/CustomerOrderMsSync.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$orderId          = isset($_POST['order_id'])          ? (int)$_POST['order_id']          : 0;
$version          = isset($_POST['version'])          ? (int)$_POST['version']           : 0;
$itemsJson        = isset($_POST['items'])            ? $_POST['items']                   : '[]';
$description      = isset($_POST['description'])      ? trim($_POST['description'])       : null;
$status           = isset($_POST['status'])           ? trim($_POST['status'])            : null;
$organizationId   = isset($_POST['organization_id'])  ? (int)$_POST['organization_id']    : null;
$managerEmployeeId= isset($_POST['manager_employee_id']) ? (int)$_POST['manager_employee_id'] : null;
$paymentMethodId  = isset($_POST['payment_method_id']) && $_POST['payment_method_id'] !== ''
                    ? (int)$_POST['payment_method_id'] : null;
$deliveryMethodId = isset($_POST['delivery_method_id']) && $_POST['delivery_method_id'] !== ''
                    ? (int)$_POST['delivery_method_id'] : null;

// Extra header fields from the standalone edit page
$counterpartyId       = isset($_POST['counterparty_id'])              && $_POST['counterparty_id'] !== ''              ? (int)$_POST['counterparty_id']              : null;
$contactPersonId      = isset($_POST['contact_person_id'])            && $_POST['contact_person_id'] !== ''            ? (int)$_POST['contact_person_id']            : null;
$orgBankAccountId     = isset($_POST['organization_bank_account_id']) && $_POST['organization_bank_account_id'] !== '' ? (int)$_POST['organization_bank_account_id'] : null;
$contractId           = isset($_POST['contract_id'])                  && $_POST['contract_id'] !== ''                  ? (int)$_POST['contract_id']                  : null;
$projectId            = isset($_POST['project_id'])                   && $_POST['project_id'] !== ''                   ? (int)$_POST['project_id']                   : null;
$salesChannel         = isset($_POST['sales_channel'])       ? trim($_POST['sales_channel'])       : null;
$currencyCode         = isset($_POST['currency_code'])        ? trim($_POST['currency_code'])        : null;
$storeId              = isset($_POST['store_id'])             && $_POST['store_id'] !== ''             ? (int)$_POST['store_id'] : null;
$plannedShipmentAt    = isset($_POST['planned_shipment_at'])  && $_POST['planned_shipment_at'] !== '' ? trim($_POST['planned_shipment_at']) : null;
$applicable           = isset($_POST['applicable'])           ? (int)$_POST['applicable']           : null;
$waitCall             = isset($_POST['wait_call'])             ? (int)(bool)$_POST['wait_call']       : null;

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    echo json_encode(array('ok' => false, 'error' => 'items must be JSON array'));
    exit;
}

// Version check + fetch current values for history diff
$rOrder = \Database::fetchRow('Papir',
    "SELECT id, version, status, manager_employee_id,
            organization_id, delivery_method_id, payment_method_id,
            description, applicable, wait_call, sales_channel, currency_code,
            store_id, planned_shipment_at, counterparty_id, contact_person_id,
            organization_bank_account_id, contract_id, project_id
     FROM customerorder WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");

if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}

$currentVersion = (int)$rOrder['row']['version'];
$_oldOrder = $rOrder['row'];  // snapshot for history diff

if ($version > 0 && $currentVersion !== $version) {
    echo json_encode(array(
        'ok'              => false,
        'conflict'        => true,
        'current_version' => $currentVersion,
        'error'           => 'Замовлення було змінено іншим користувачем',
    ));
    exit;
}

// ── Снапшот позиций ДО изменений (для диффа в истории) ────────────────────
$_trackFields   = \DocumentHistory::getItemTrackableFields('customerorder');
$_trackFieldStr = implode(', ', array_map(function($f) { return "`{$f}`"; }, array_keys($_trackFields)));

$_rSnap = \Database::fetchAll('Papir',
    "SELECT id, product_name, sku, line_no, {$_trackFieldStr}
     FROM customerorder_item WHERE customerorder_id = {$orderId}");
$_itemsBefore = array();
if ($_rSnap['ok'] && !empty($_rSnap['rows'])) {
    foreach ($_rSnap['rows'] as $_si) {
        $_itemsBefore[(int)$_si['id']] = $_si;
    }
}
$_itemsHistory = array(); // накапливаем события, пишем после commit

\Database::begin('Papir');

try {
    // Update header
    $headerData = array('updated_at' => date('Y-m-d H:i:s'));
    if ($description !== null) $headerData['description'] = $description;
    if ($organizationId !== null && $organizationId > 0) $headerData['organization_id'] = $organizationId;
    if ($managerEmployeeId !== null) $headerData['manager_employee_id'] = $managerEmployeeId > 0 ? $managerEmployeeId : null;
    if ($paymentMethodId  !== null) $headerData['payment_method_id']  = $paymentMethodId  > 0 ? $paymentMethodId  : null;
    if ($deliveryMethodId !== null) $headerData['delivery_method_id'] = $deliveryMethodId > 0 ? $deliveryMethodId : null;
    if ($counterpartyId   !== null) $headerData['counterparty_id']               = $counterpartyId   > 0 ? $counterpartyId   : null;
    if ($contactPersonId  !== null) $headerData['contact_person_id']             = $contactPersonId  > 0 ? $contactPersonId  : null;
    if ($orgBankAccountId !== null) $headerData['organization_bank_account_id']  = $orgBankAccountId > 0 ? $orgBankAccountId : null;
    if ($contractId       !== null) $headerData['contract_id']                   = $contractId       > 0 ? $contractId       : null;
    if ($projectId        !== null) $headerData['project_id']                    = $projectId        > 0 ? $projectId        : null;
    if ($salesChannel  !== null && $salesChannel  !== '') $headerData['sales_channel']  = $salesChannel;
    if ($currencyCode  !== null && $currencyCode  !== '') $headerData['currency_code']   = $currencyCode;
    if ($storeId       !== null) $headerData['store_id']          = $storeId > 0 ? $storeId : null;
    if ($plannedShipmentAt !== null) $headerData['planned_shipment_at'] = $plannedShipmentAt;
    if ($applicable    !== null) $headerData['applicable']        = $applicable;
    if ($waitCall      !== null) $headerData['wait_call']         = $waitCall;
    if ($status !== null) {
        $allowed = array('draft','new','confirmed','in_progress','waiting_payment','paid',
                         'partially_shipped','shipped','completed','cancelled');
        if (in_array($status, $allowed)) $headerData['status'] = $status;
    }
    $rUpd = \Database::update('Papir', 'customerorder', $headerData, array('id' => $orderId));
    if (!$rUpd['ok']) throw new Exception('Header update failed');

    // Process items
    foreach ($items as $item) {
        $itemId  = isset($item['id'])       ? (int)$item['id'] : 0;
        $deleted = !empty($item['_deleted']);

        if ($deleted) {
            if ($itemId > 0) {
                $r = \Database::query('Papir',
                    "DELETE FROM customerorder_item WHERE id={$itemId} AND customerorder_id={$orderId}");
                if (!$r['ok']) throw new Exception('Delete item ' . $itemId . ' failed');
                // History: запомнить удалённую позицию
                $_itemsHistory[] = array(
                    'action'  => 'delete_item',
                    'item_id' => $itemId,
                    'before'  => isset($_itemsBefore[$itemId]) ? $_itemsBefore[$itemId] : null,
                    'after'   => null,
                );
            }
            continue;
        }

        // Calculate fields (mirrors prepareItemData logic)
        $qty     = max((float)(isset($item['quantity'])         ? $item['quantity']         : 1), 0.001);
        $price   =     (float)(isset($item['price'])            ? $item['price']            : 0);
        $disc    =     (float)(isset($item['discount_percent']) ? $item['discount_percent'] : 0);
        $vatRate =     (float)(isset($item['vat_rate'])         ? $item['vat_rate']         : 0);

        $gross      = round($qty * $price, 2);
        $discAmt    = round($gross * $disc / 100, 2);
        $sumRow     = round($gross - $discAmt, 2);
        $vatAmt     = $vatRate > 0 ? round($sumRow - $sumRow / (1 + $vatRate / 100), 2) : 0;
        $sumWithout = $gross;

        $fields = array(
            'quantity'             => $qty,
            'price'                => round($price, 2),
            'discount_percent'     => $disc,
            'discount_amount'      => $discAmt,
            'vat_rate'             => $vatRate,
            'vat_amount'           => $vatAmt,
            'sum_without_discount' => $sumWithout,
            'sum_row'              => $sumRow,
            'product_name'         => isset($item['product_name']) ? $item['product_name'] : null,
            'sku'                  => isset($item['sku'])           ? $item['sku']           : null,
            'unit'                 => isset($item['unit'])          ? $item['unit']          : null,
            'updated_at'           => date('Y-m-d H:i:s'),
        );

        if ($itemId > 0) {
            $r = \Database::update('Papir', 'customerorder_item', $fields,
                array('id' => $itemId, 'customerorder_id' => $orderId));
            if (!$r['ok']) throw new Exception('Update item ' . $itemId . ' failed');
            // History: запомнить изменённую позицию (before уже в снапшоте)
            $_itemsHistory[] = array(
                'action'  => 'update_item',
                'item_id' => $itemId,
                'before'  => isset($_itemsBefore[$itemId]) ? $_itemsBefore[$itemId] : null,
                'after'   => $fields,  // trackable поля входят в $fields
            );
        } else {
            // INSERT new item
            $rLine = \Database::fetchRow('Papir',
                "SELECT COALESCE(MAX(line_no),0)+1 AS nxt FROM customerorder_item WHERE customerorder_id={$orderId}");
            $lineNo = ($rLine['ok'] && $rLine['row']) ? (int)$rLine['row']['nxt'] : 1;

            $fields['customerorder_id']        = $orderId;
            $fields['line_no']                 = $lineNo;
            $fields['product_id']              = isset($item['product_id']) ? (int)$item['product_id'] : null;
            $fields['stock_quantity']          = isset($item['stock_quantity']) ? (float)$item['stock_quantity'] : 0;
            $fields['reserved_stock_quantity'] = 0;
            $fields['expected_quantity']       = 0;
            $fields['reserved_quantity']       = 0;
            $fields['shipped_quantity']        = 0;
            $fields['weight']                  = isset($item['weight']) ? (float)$item['weight'] : 0;
            $fields['created_at']              = date('Y-m-d H:i:s');

            $r = \Database::insert('Papir', 'customerorder_item', $fields);
            if (!$r['ok']) throw new Exception('Insert item failed: ' . (isset($r['error']) ? $r['error'] : ''));
            // History: новая позиция
            $_itemsHistory[] = array(
                'action'    => 'add_item',
                'item_id'   => isset($r['insert_id']) ? (int)$r['insert_id'] : null,
                'before'    => null,
                'after'     => $fields,
            );
        }
    }

    // sum_items / sum_discount / sum_vat / sum_total оновлюються автоматично
    // тригерами trg_co_item_after_insert/update/delete на customerorder_item
    $newVersion = $currentVersion + 1;

    $rFinal = \Database::update('Papir', 'customerorder',
        array('version' => $newVersion),
        array('id' => $orderId));
    if (!$rFinal['ok']) throw new Exception('Final update failed');

    \Database::commit('Papir');

    // ── History logging ────────────────────────────────────────────────────────
    $currentUser = \Papir\Crm\AuthService::getCurrentUser();
    $_actor = $currentUser
        ? array('actor_type' => 'user', 'actor_id' => (int)$currentUser['user_id'], 'actor_label' => $currentUser['display_name'])
        : array('actor_type' => 'system', 'actor_id' => null, 'actor_label' => 'Система');

    // Лейблы полей
    $_fieldLabels = array(
        'status'                       => 'Статус',
        'manager_employee_id'          => 'Менеджер',
        'organization_id'              => 'Організація',
        'delivery_method_id'           => 'Спосіб доставки',
        'payment_method_id'            => 'Спосіб оплати',
        'description'                  => 'Коментар',
        'applicable'                   => 'Проведено',
        'sales_channel'                => 'Канал продажу',
        'store_id'                     => 'Склад',
        'planned_shipment_at'          => 'Планове відвантаження',
        'counterparty_id'              => 'Контрагент',
        'contact_person_id'            => 'Контактна особа',
        'organization_bank_account_id' => 'Банківський рахунок',
        'contract_id'                  => 'Договір',
        'currency_code'                => 'Валюта',
    );

    // Резолвер: ID → человекочитаемый текст (вызывается для old и new)
    // Важно: резолвить ДО записи, чтобы история хранила актуальные названия на момент изменения
    function _resolveFieldValue($field, $rawVal) {
        if ($rawVal === null || $rawVal === '') return null;
        $id = (int)$rawVal;

        if ($field === 'manager_employee_id') {
            $r = \Database::fetchRow('Papir', "SELECT full_name FROM employee WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['full_name'] : $rawVal;
        }
        if ($field === 'organization_id') {
            $r = \Database::fetchRow('Papir', "SELECT name FROM organization WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['name'] : $rawVal;
        }
        if ($field === 'delivery_method_id') {
            $r = \Database::fetchRow('Papir', "SELECT name_uk FROM delivery_method WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['name_uk'] : $rawVal;
        }
        if ($field === 'payment_method_id') {
            $r = \Database::fetchRow('Papir', "SELECT name_uk FROM payment_method WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['name_uk'] : $rawVal;
        }
        if ($field === 'store_id') {
            $r = \Database::fetchRow('Papir', "SELECT name FROM store WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['name'] : $rawVal;
        }
        if ($field === 'counterparty_id') {
            $r = \Database::fetchRow('Papir', "SELECT name FROM counterparty WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['name'] : $rawVal;
        }
        if ($field === 'contact_person_id') {
            $r = \Database::fetchRow('Papir',
                "SELECT CONCAT(COALESCE(last_name,''),' ',COALESCE(first_name,'')) AS nm
                 FROM counterparty WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row'] && trim($r['row']['nm'])) ? trim($r['row']['nm']) : $rawVal;
        }
        if ($field === 'contract_id') {
            $r = \Database::fetchRow('Papir', "SELECT name FROM contract WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['name'] : $rawVal;
        }
        if ($field === 'organization_bank_account_id') {
            $r = \Database::fetchRow('Papir', "SELECT iban FROM organization_bank_account WHERE id={$id} LIMIT 1");
            return ($r['ok'] && $r['row']) ? $r['row']['iban'] : $rawVal;
        }
        if ($field === 'applicable') {
            return $rawVal ? 'Так' : 'Ні';
        }
        if ($field === 'status') {
            $map = array(
                'draft'=>'Чернетка','new'=>'Нове','confirmed'=>'Підтверджено',
                'in_progress'=>'В роботі','waiting_payment'=>'Очік. оплати',
                'paid'=>'Оплачено','partially_shipped'=>'Частк. відвантаж.',
                'shipped'=>'Відвантажено','completed'=>'Виконано','cancelled'=>'Скасовано',
            );
            return isset($map[$rawVal]) ? $map[$rawVal] : $rawVal;
        }
        // Прочие поля — возвращаем as-is
        return (string)$rawVal;
    }

    $_skipFields = array('updated_at', 'version');
    foreach ($headerData as $_field => $_newVal) {
        if (in_array($_field, $_skipFields, true)) continue;
        $_oldVal = isset($_oldOrder[$_field]) ? $_oldOrder[$_field] : null;
        if ((string)$_oldVal === (string)$_newVal) continue;

        $_label      = isset($_fieldLabels[$_field]) ? $_fieldLabels[$_field] : $_field;
        $_oldDisplay = _resolveFieldValue($_field, $_oldVal);
        $_newDisplay = _resolveFieldValue($_field, $_newVal);

        \DocumentHistory::log('customerorder', $orderId, 'update', array_merge($_actor, array(
            'field_name'  => $_field,
            'field_label' => $_label,
            'old_value'   => $_oldDisplay,
            'new_value'   => $_newDisplay,
        )));
    }

    // ── History: позиции ──────────────────────────────────────────────────────
    foreach ($_itemsHistory as $_ih) {
        $_action    = $_ih['action'];
        $_iid       = $_ih['item_id'];
        $_before    = $_ih['before'];
        $_after     = $_ih['after'];
        $_itemLabel = $_before ? \DocumentHistory::buildItemLabel($_before)
                               : ($_after ? \DocumentHistory::buildItemLabel($_after) : 'Позиція');

        if ($_action === 'add_item') {
            // Одна запись: суммарное "що додали"
            $_parts = array();
            foreach ($_trackFields as $_tf => $_tl) {
                if (isset($_after[$_tf]) && $_after[$_tf] !== null && (string)$_after[$_tf] !== '') {
                    $_fv = \DocumentHistory::formatItemFieldValue($_tf, $_after[$_tf]);
                    if ($_fv !== null) $_parts[] = $_tl . ': ' . $_fv;
                }
            }
            \DocumentHistory::log('customerorder', $orderId, 'add_item', array_merge($_actor, array(
                'item_id'    => $_iid,
                'item_label' => $_itemLabel,
                'old_value'  => null,
                'new_value'  => implode(', ', $_parts),
            )));

        } elseif ($_action === 'delete_item') {
            // Одна запись: суммарное "що було"
            $_parts = array();
            if ($_before) {
                foreach ($_trackFields as $_tf => $_tl) {
                    if (isset($_before[$_tf]) && $_before[$_tf] !== null && (string)$_before[$_tf] !== '') {
                        $_fv = \DocumentHistory::formatItemFieldValue($_tf, $_before[$_tf]);
                        if ($_fv !== null) $_parts[] = $_tl . ': ' . $_fv;
                    }
                }
            }
            \DocumentHistory::log('customerorder', $orderId, 'delete_item', array_merge($_actor, array(
                'item_id'    => $_iid,
                'item_label' => $_itemLabel,
                'old_value'  => implode(', ', $_parts),
                'new_value'  => null,
            )));

        } elseif ($_action === 'update_item') {
            // По записи на каждое изменённое trackable поле
            foreach ($_trackFields as $_tf => $_tl) {
                $_oldRaw = isset($_before[$_tf]) ? $_before[$_tf] : null;
                $_newRaw = isset($_after[$_tf])  ? $_after[$_tf]  : null;
                // Нормализуем через форматтер — "0.000", 0.0, "0" → все станут "0"
                $_oldFmt = \DocumentHistory::formatItemFieldValue($_tf, $_oldRaw);
                $_newFmt = \DocumentHistory::formatItemFieldValue($_tf, $_newRaw);
                if ($_oldFmt === $_newFmt) continue;
                \DocumentHistory::log('customerorder', $orderId, 'update_item', array_merge($_actor, array(
                    'item_id'    => $_iid,
                    'item_label' => $_itemLabel,
                    'field_name' => $_tf,
                    'field_label'=> $_tl,
                    'old_value'  => $_oldFmt,
                    'new_value'  => $_newFmt,
                )));
            }
        }
    }
    // ── End History ────────────────────────────────────────────────────────────

    // Return fresh data
    $rO = \Database::fetchRow('Papir',
        "SELECT co.id, co.version, co.number, co.status, co.payment_status, co.shipment_status,
                co.sum_items, co.sum_discount, co.sum_vat, co.sum_total,
                co.moment, co.description, co.applicable, co.sales_channel,
                co.organization_id, co.manager_employee_id,
                co.delivery_method_id, dm.code AS delivery_method_code,
                dm.name_uk AS delivery_method_name, dm.has_ttn AS delivery_method_has_ttn,
                co.payment_method_id, pm.code AS payment_method_code, pm.name_uk AS payment_method_name,
                o.name AS org_name, o.vat_number AS org_vat_number,
                e.full_name AS manager_name,
                co.counterparty_id, co.contact_person_id, co.organization_bank_account_id,
                co.contract_id, co.project_id, co.currency_code, co.store_id, co.planned_shipment_at
         FROM customerorder co
         LEFT JOIN organization o    ON o.id  = co.organization_id
         LEFT JOIN employee e        ON e.id  = co.manager_employee_id
         LEFT JOIN delivery_method dm ON dm.id = co.delivery_method_id
         LEFT JOIN payment_method pm  ON pm.id = co.payment_method_id
         WHERE co.id={$orderId} LIMIT 1");

    $rI = \Database::fetchAll('Papir',
        "SELECT ci.id, ci.product_id, ci.line_no, ci.quantity,
                ci.price, ci.discount_percent, ci.vat_rate, ci.vat_amount,
                ci.sum_without_discount, ci.sum_row AS sum,
                ci.stock_quantity, ci.shipped_quantity, ci.reserved_quantity,
                COALESCE(NULLIF(ci.product_name,''),NULLIF(pd_uk.name,''),NULLIF(pd_ru.name,''),'') AS name,
                COALESCE(NULLIF(ci.sku,''),pp.product_article,'') AS article
         FROM customerorder_item ci
         LEFT JOIN product_papir pp ON pp.product_id = ci.product_id
         LEFT JOIN product_description pd_uk ON pd_uk.product_id=ci.product_id AND pd_uk.language_id=2
         LEFT JOIN product_description pd_ru ON pd_ru.product_id=ci.product_id AND pd_ru.language_id=1
         WHERE ci.customerorder_id={$orderId}
         ORDER BY ci.line_no ASC");

    echo json_encode(array(
        'ok'      => true,
        'version' => $newVersion,
        'order'   => $rO['row'],
        'items'   => $rI['rows'],
    ));

    // Fire automation triggers for status changes
    if (isset($headerData['status']) && $headerData['status'] !== $_oldOrder['status']) {
        $_cpId2 = isset($_oldOrder['counterparty_id']) ? (int)$_oldOrder['counterparty_id'] : 0;
        $_orderCtx2 = array(
            'order'           => array_merge($_oldOrder, array('status' => $headerData['status'])),
            'order_id'        => $orderId,
            'counterparty_id' => $_cpId2,
            'old_status'      => $_oldOrder['status'],
            'new_status'      => $headerData['status'],
        );
        TriggerEngine::fire('order_status_changed', $_orderCtx2);
        if ($headerData['status'] === 'cancelled') {
            TriggerEngine::fire('order_cancelled', $_orderCtx2);
        }
    }

    // Push to MoySklad after response sent
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
    try {
        $msSync = new CustomerOrderMsSync();
        $msSync->push($orderId);
    } catch (Exception $ex) {
        // silent
    }

} catch (Exception $e) {
    \Database::rollback('Papir');
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}
