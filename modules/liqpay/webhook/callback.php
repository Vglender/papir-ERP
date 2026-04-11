<?php
/**
 * LiqPay callback webhook.
 *
 * URL: https://papir.officetorg.com.ua/liqpay/webhook/callback
 * Метод: POST form-urlencoded
 * Поля:  data      — base64(json_encode(payment_data))
 *        signature — base64(sha1_bin(private_key . data . private_key))
 *
 * LiqPay вимагає 200 OK — інакше буде ретраювати. Тому ми:
 *   - при неактивному модулі повертаємо 200 через AppRegistry::guard
 *   - при invalid signature повертаємо 200 (підпис перевіряється нами,
 *     LiqPay про наш результат знати не потрібно — просто логуємо і вихід)
 *   - при помилці обробки — 200 + лог, щоб LiqPay не ретраював вічно
 */

define('CRON_MODE', false);
require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';

AppRegistry::guard('liqpay');

require_once __DIR__ . '/../LiqpayClient.php';
require_once __DIR__ . '/../services/LiqpayConnectionRegistry.php';
require_once __DIR__ . '/../services/LiqpayCallbackService.php';
// Counterparties / TriggerEngine — для recalc fire'а order_payment_changed
require_once __DIR__ . '/../../counterparties/services/TriggerEngine.php';
require_once __DIR__ . '/../../counterparties/repositories/ScenarioRepository.php';

header('Content-Type: application/json; charset=utf-8');

$dataB64 = isset($_POST['data']) ? $_POST['data'] : '';
$sigB64  = isset($_POST['signature']) ? $_POST['signature'] : '';

if (!$dataB64 || !$sigB64) {
    error_log('[liqpay webhook] missing data or signature');
    echo json_encode(array('ok' => false, 'reason' => 'bad_request'));
    exit;
}

// Decode payload first so we can identify merchant by public_key
$lp = LiqpayClient::decodeData($dataB64);
if (empty($lp) || empty($lp['public_key'])) {
    error_log('[liqpay webhook] invalid payload: ' . substr($dataB64, 0, 200));
    echo json_encode(array('ok' => false, 'reason' => 'invalid_payload'));
    exit;
}

$conn = LiqpayConnectionRegistry::findByPublicKey($lp['public_key']);
if (!$conn) {
    error_log('[liqpay webhook] unknown merchant: ' . $lp['public_key']);
    echo json_encode(array('ok' => false, 'reason' => 'unknown_merchant'));
    exit;
}

// Verify signature with matching private_key
$client = new LiqpayClient($conn['public_key'], $conn['private_key']);
if (!$client->verifySignature($dataB64, $sigB64)) {
    error_log('[liqpay webhook] signature mismatch for public_key=' . $lp['public_key']
        . ' order_id=' . (isset($lp['order_id']) ? $lp['order_id'] : '?'));
    echo json_encode(array('ok' => false, 'reason' => 'bad_signature'));
    exit;
}

// Process
$result = LiqpayCallbackService::processPaymentData($lp);

// Log result line
$logLine = '[' . date('Y-m-d H:i:s') . '] liqpay.callback '
    . 'merchant=' . $conn['site_code']
    . ' order_id=' . (isset($lp['order_id']) ? $lp['order_id'] : '?')
    . ' payment_id=' . (isset($lp['payment_id']) ? $lp['payment_id'] : '?')
    . ' status=' . (isset($lp['status']) ? $lp['status'] : '?')
    . ' amount=' . (isset($lp['amount']) ? $lp['amount'] : '?')
    . ' → ' . json_encode($result, JSON_UNESCAPED_UNICODE);
error_log($logLine . PHP_EOL, 3, __DIR__ . '/../../../logs/liqpay_callbacks.log');

echo json_encode(array('ok' => true, 'action' => isset($result['action']) ? $result['action'] : 'processed'));