<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

// Collect all params from POST
$params = array(
    'customerorder_id'         => isset($_POST['customerorder_id'])         ? (int)$_POST['customerorder_id']         : 0,
    'demand_id'                => isset($_POST['demand_id'])                ? (int)$_POST['demand_id']                : 0,
    'sender_ref'               => isset($_POST['sender_ref'])               ? trim($_POST['sender_ref'])               : '',
    'sender_address_ref'       => isset($_POST['sender_address_ref'])       ? trim($_POST['sender_address_ref'])       : '',
    'city_sender_ref'          => isset($_POST['city_sender_ref'])          ? trim($_POST['city_sender_ref'])          : '',
    'city_sender_desc'         => isset($_POST['city_sender_desc'])         ? trim($_POST['city_sender_desc'])         : '',
    'city_recipient_ref'       => isset($_POST['city_recipient_ref'])       ? trim($_POST['city_recipient_ref'])       : '',
    'city_recipient_desc'      => isset($_POST['city_recipient_desc'])      ? trim($_POST['city_recipient_desc'])      : '',
    'service_type'             => isset($_POST['service_type'])             ? trim($_POST['service_type'])             : 'WarehouseWarehouse',
    'recipient_type'           => isset($_POST['recipient_type'])           ? trim($_POST['recipient_type'])           : 'PrivatePerson',
    'recipient_first_name'     => isset($_POST['recipient_first_name'])     ? trim($_POST['recipient_first_name'])     : '',
    'recipient_last_name'      => isset($_POST['recipient_last_name'])      ? trim($_POST['recipient_last_name'])      : '',
    'recipient_middle_name'    => isset($_POST['recipient_middle_name'])    ? trim($_POST['recipient_middle_name'])    : '',
    'recipient_full_name'      => isset($_POST['recipient_full_name'])      ? trim($_POST['recipient_full_name'])      : '',
    'recipient_edrpou'         => isset($_POST['recipient_edrpou'])         ? trim($_POST['recipient_edrpou'])         : '',
    'recipient_phone'          => isset($_POST['recipient_phone'])          ? trim($_POST['recipient_phone'])          : '',
    'counterparty_id'          => isset($_POST['counterparty_id'])          ? (int)$_POST['counterparty_id']          : 0,
    // Warehouse delivery
    'recipient_warehouse_ref'  => isset($_POST['recipient_warehouse_ref'])  ? trim($_POST['recipient_warehouse_ref'])  : '',
    'recipient_address_desc'   => isset($_POST['recipient_address_desc'])   ? trim($_POST['recipient_address_desc'])   : '',
    // Address delivery
    'recipient_street_ref'     => isset($_POST['recipient_street_ref'])     ? trim($_POST['recipient_street_ref'])     : '',
    'recipient_building'       => isset($_POST['recipient_building'])       ? trim($_POST['recipient_building'])       : '',
    'recipient_flat'           => isset($_POST['recipient_flat'])           ? trim($_POST['recipient_flat'])           : '',
    // Cargo
    'weight'                   => isset($_POST['weight'])                   ? (float)$_POST['weight']                 : 0.5,
    'seats_amount'             => isset($_POST['seats_amount'])             ? (int)$_POST['seats_amount']             : 1,
    'cargo_type'               => isset($_POST['cargo_type'])               ? trim($_POST['cargo_type'])               : 'Cargo',
    'description'              => isset($_POST['description'])              ? trim($_POST['description'])              : 'Товар',
    'additional_info'          => isset($_POST['additional_info'])          ? trim($_POST['additional_info'])          : '',
    'cost'                     => isset($_POST['cost'])                     ? (int)$_POST['cost']                     : 1,
    // Payment
    'payment_method'           => isset($_POST['payment_method'])           ? trim($_POST['payment_method'])           : 'Cash',
    'payer_type'               => isset($_POST['payer_type'])               ? trim($_POST['payer_type'])               : 'Recipient',
    'backward_delivery_money'  => isset($_POST['backward_delivery_money'])  ? (float)$_POST['backward_delivery_money'] : 0,
    'date'                     => isset($_POST['date'])                     ? trim($_POST['date'])                     : date('d.m.Y'),
    'sender_phone'             => isset($_POST['sender_phone'])             ? trim($_POST['sender_phone'])             : '',
    'options_seat'             => isset($_POST['options_seat'])             ? $_POST['options_seat']                   : '',
);

// Basic validation
if (!$params['sender_ref']) {
    echo json_encode(array('ok' => false, 'error' => 'sender_ref required'));
    exit;
}
if (!$params['city_recipient_ref']) {
    echo json_encode(array('ok' => false, 'error' => 'city_recipient_ref required'));
    exit;
}
if (!$params['recipient_phone']) {
    echo json_encode(array('ok' => false, 'error' => 'recipient_phone required'));
    exit;
}
if ($params['weight'] <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'weight must be > 0'));
    exit;
}

$result = \Papir\Crm\TtnService::create($params);

// Mirror demand to MoySklad: set TTN attribute + status "shipped"
if (!empty($result['ok']) && $params['customerorder_id'] > 0) {
    $ttnNumber = isset($result['int_doc_number']) ? (string)$result['int_doc_number'] : '';
    // Resolve demand_id: from params or find by customerorder_id
    $demandId = (int)$params['demand_id'];
    if (!$demandId) {
        $rDemLookup = \Database::fetchRow('Papir',
            "SELECT id FROM demand WHERE customerorder_id = " . (int)$params['customerorder_id']
            . " AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
        if ($rDemLookup['ok'] && !empty($rDemLookup['row'])) {
            $demandId = (int)$rDemLookup['row']['id'];
        }
    }
    if ($demandId && $ttnNumber !== '') {
        require_once __DIR__ . '/../../moysklad/moysklad_api.php';
        $rDem = \Database::fetchRow('Papir',
            "SELECT id_ms FROM demand WHERE id = {$demandId} AND deleted_at IS NULL LIMIT 1");
        if ($rDem['ok'] && !empty($rDem['row']['id_ms'])) {
            $ms = new MoySkladApi();
            $entityBase = $ms->getEntityBaseUrl();
            $demandMsId = $rDem['row']['id_ms'];
            $patchData = array(
                'state' => array('meta' => array(
                    'href'      => $entityBase . 'demand/metadata/states/ac913c39-eaa9-11eb-0a80-064900024c02',
                    'type'      => 'state',
                    'mediaType' => 'application/json',
                )),
                'attributes' => array(
                    array(
                        'meta' => array(
                            'href'      => $entityBase . 'demand/metadata/attributes/b4b3bfd9-789f-11ed-0a80-04910022fa7b',
                            'type'      => 'attributemetadata',
                            'mediaType' => 'application/json',
                        ),
                        'value' => $ttnNumber,
                    ),
                ),
            );
            $ms->querySend($entityBase . 'demand/' . $demandMsId, $patchData, 'PUT');

            // Update local demand status to shipped
            \Database::update('Papir', 'demand',
                array('status' => 'shipped', 'sync_state' => 'synced'),
                array('id' => $demandId));
        }
    }
    // Recalc linked customerorder finance after demand status change
    if ($demandId) {
        require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';
        OrderFinanceHelper::recalc((int)$params['customerorder_id']);
    }
}

// Fire trigger event for scenarios
if (!empty($result['ok']) && $params['customerorder_id'] > 0) {
    $orderId = (int)$params['customerorder_id'];
    require_once __DIR__ . '/../../counterparties/counterparties_bootstrap.php';
    $rOrd = \Database::fetchRow('Papir',
        "SELECT * FROM customerorder WHERE id={$orderId} LIMIT 1");
    if ($rOrd['ok'] && !empty($rOrd['row'])) {
        $order = $rOrd['row'];
        TriggerEngine::fire('order_ttn_created', array(
            'order'           => $order,
            'order_id'        => $orderId,
            'counterparty_id' => (int)$order['counterparty_id'],
            'ttn_type'        => 'novaposhta',
        ));
        // Виконати задачі зі сценарію негайно
        TaskQueueRunner::runPending();
    }
}

echo json_encode($result);