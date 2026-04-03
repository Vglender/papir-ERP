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

if ($orderId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    echo json_encode(array('ok' => false, 'error' => 'items must be JSON array'));
    exit;
}

// Version check
$rOrder = \Database::fetchRow('Papir',
    "SELECT id, version FROM customerorder WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1");

if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Order not found'));
    exit;
}

$currentVersion = (int)$rOrder['row']['version'];

if ($version > 0 && $currentVersion !== $version) {
    echo json_encode(array(
        'ok'              => false,
        'conflict'        => true,
        'current_version' => $currentVersion,
        'error'           => 'Замовлення було змінено іншим користувачем',
    ));
    exit;
}

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
