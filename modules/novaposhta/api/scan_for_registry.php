<?php
/**
 * GET /novaposhta/api/scan_for_registry?code=20451408559805
 *
 * Processes a scanned barcode for the hand-over to courier workflow:
 *  - NP (starts with "20"):       add TTN to scan sheet + courier call
 *  - Ukrposhta (starts with "05"): add TTN to current open shipment-group
 *  - Returns JSON with TTN info, errors, warnings
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (!$code) {
    echo json_encode(array('ok' => false, 'errors' => array('Не вказано штрих-код')));
    exit;
}

$point = substr($code, 0, 2);

$response = array('data' => array(), 'errors' => array(), 'warnings' => array());

// ═══════════════════════════════════════════════════════════════════════════
//  НОВА ПОШТА (штрих-код починається з "20")
// ═══════════════════════════════════════════════════════════════════════════
if ($point === '20') {

    $intDocNumber = preg_replace('/[^0-9]/', '', $code);
    if (!$intDocNumber) {
        echo json_encode(array('ok' => false, 'errors' => array('Невірний формат штрих-коду')));
        exit;
    }

    $result = \Papir\Crm\CourierCallService::processScan($intDocNumber);

    $response['data']     = $result['data'];
    $response['errors']   = $result['errors'];
    $response['warnings'] = $result['warnings'];

// ═══════════════════════════════════════════════════════════════════════════
//  УКРПОШТА (штрих-код починається з "05")
// ═══════════════════════════════════════════════════════════════════════════
} elseif ($point === '05') {

    require_once __DIR__ . '/../../ukrposhta/ukrposhta_bootstrap.php';

    $barcode = preg_replace('/[^0-9]/', '', $code);
    if (!$barcode || strlen($barcode) < 13) {
        echo json_encode(array('ok' => false, 'errors' => array('Невірний формат штрих-коду УП')));
        exit;
    }

    // Auto-add to (or create) today's open registry of the TTN's type.
    $result = \Papir\Crm\GroupService::addToOrCreate($barcode);
    if (!$result['ok']) {
        $response['errors'][] = $result['error'];
    } else {
        $ttn = isset($result['ttn']) ? $result['ttn'] : null;
        $grp = isset($result['group']) ? $result['group'] : null;
        $response['data'] = array(
            'carrier'        => 'ukrposhta',
            'barcode'        => $barcode,
            'ttn_id'         => $ttn ? (int)$ttn['id']  : 0,
            'recipient_name' => $ttn ? $ttn['recipient_name'] : '',
            'recipient_city' => $ttn ? $ttn['recipient_city'] : '',
            'group_uuid'     => $grp ? $grp['uuid'] : '',
            'group_name'     => $grp ? $grp['name'] : '',
            'group_count'    => $grp ? (int)$grp['ttn_count'] : 0,
        );

        // Fire ttn_handed_to_courier so scenario #15 clears next_action on the order.
        if ($ttn && !empty($ttn['customerorder_id'])) {
            if (method_exists('\\Papir\\Crm\\TtnService', 'fireTtnHandedToCourier')) {
                // NP-module helper; only exists for NP. Emit our own event via TriggerEngine directly.
            }
            if (!class_exists('TriggerEngine', false)) {
                $p = __DIR__ . '/../../counterparties/services/TriggerEngine.php';
                if (file_exists($p)) require_once $p;
            }
            if (class_exists('TriggerEngine', false)) {
                $cpRow = \Database::fetchRow('Papir',
                    "SELECT counterparty_id FROM customerorder WHERE id = " . (int)$ttn['customerorder_id'] . " LIMIT 1");
                $cpId = ($cpRow['ok'] && !empty($cpRow['row'])) ? (int)$cpRow['row']['counterparty_id'] : 0;
                \TriggerEngine::fire('ttn_handed_to_courier', array(
                    'order_id'        => (int)$ttn['customerorder_id'],
                    'counterparty_id' => $cpId,
                    'ttn' => array(
                        'id'             => (int)$ttn['id'],
                        'int_doc_number' => $barcode,
                        'barcode'        => $barcode,
                        'carrier'        => 'ukrposhta',
                    ),
                ));
            }
        }
    }

// ═══════════════════════════════════════════════════════════════════════════
//  Невідомий формат
// ═══════════════════════════════════════════════════════════════════════════
} else {
    $response['errors'][] = 'Невідомий формат штрих-коду (очікується НП: 20... або УП: 05...)';
}

// Повідомлення клієнту НЕ шлемо тут. Сценарна система (подія
// ttn_handed_to_courier) вирішує чи/що слати — поточна політика:
// не турбуємо клієнта до реального переходу ТТН в in_transit (сценарій 6).

$response['ok'] = empty($response['errors']);
echo json_encode($response, JSON_UNESCAPED_UNICODE);