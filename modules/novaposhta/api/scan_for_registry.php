<?php
/**
 * GET /novaposhta/api/scan_for_registry?code=20451408559805
 *
 * Processes a scanned barcode:
 *  - NP (starts with "20"): add TTN to scan sheet + courier call
 *  - Returns JSON with TTN info, errors, warnings
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';
require_once __DIR__ . '/../../shared/AlphaSmsService.php';

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
//  Невідомий формат
// ═══════════════════════════════════════════════════════════════════════════
} else {
    $response['errors'][] = 'Невідомий формат штрих-коду (очікується НП: 20...)';
}

// ── SMS при успішному скануванні ─────────────────────────────────────────
$phone = isset($response['data']['phone']) ? $response['data']['phone'] : '';
if (empty($response['errors']) && $phone) {
    $message = 'Ваше замовлення зібране та проскановане на складі. Сьогодні буде передане перевізнику';
    \AlphaSmsService::sendViber($phone, $message);
}

$response['ok'] = empty($response['errors']);
echo json_encode($response, JSON_UNESCAPED_UNICODE);