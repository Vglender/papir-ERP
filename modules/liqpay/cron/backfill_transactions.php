<?php
/**
 * LiqPay backfill — тягне історичні транзакції з LiqPay API і прогоняє їх
 * через LiqpayCallbackService. Використовується:
 *   1. Одноразово після підключення модуля — імпорт за N днів назад
 *   2. Регулярно з крона — страховка від пропущених webhook'ів
 *
 * Параметри CLI:
 *   --days=N       скільки днів назад тягнути (за замовчуванням 7)
 *   --merchant=X   лише для одного merchant (site_code off|mff). За замовчуванням — всі активні.
 *   --dry-run      не змінювати БД, лише показати що було б зроблено
 *
 * Reports API: action='reports', date_from/date_to у timestamp мс (UTC).
 * Повертає CSV (!) — parsable, але простіше викликати status() для кожного order_id.
 * Ми використовуємо 'reports' + CSV-парсинг.
 */

define('CRON_MODE', true);

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
AppRegistry::guard('liqpay');

require_once __DIR__ . '/../LiqpayClient.php';
require_once __DIR__ . '/../services/LiqpayConnectionRegistry.php';
require_once __DIR__ . '/../services/LiqpayCallbackService.php';
require_once __DIR__ . '/../../counterparties/services/TriggerEngine.php';
require_once __DIR__ . '/../../counterparties/repositories/ScenarioRepository.php';
require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';

// ── CLI args ─────────────────────────────────────────────────────────────────
$days = 7;
$filterMerchant = null;
$dryRun = false;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) $days = (int)$m[1];
    if (preg_match('/^--merchant=(\w+)$/', $arg, $m)) $filterMerchant = $m[1];
    if ($arg === '--dry-run') $dryRun = true;
}

echo "[" . date('Y-m-d H:i:s') . "] LiqPay backfill: days={$days}"
   . ($filterMerchant ? " merchant={$filterMerchant}" : '')
   . ($dryRun ? ' DRY-RUN' : '') . PHP_EOL;

$connections = LiqpayConnectionRegistry::getAll();
if (empty($connections)) {
    echo "No active LiqPay connections\n";
    exit(0);
}

$dateFrom = (time() - $days * 86400) * 1000;   // ms
$dateTo   = time() * 1000;

$totalProcessed = 0;
$totalErrors    = 0;

foreach ($connections as $conn) {
    if ($filterMerchant && $conn['site_code'] !== $filterMerchant) continue;

    echo "── merchant: {$conn['name']} ({$conn['site_code']}, pk={$conn['public_key']}) ──\n";

    $client = new LiqpayClient($conn['public_key'], $conn['private_key']);
    $resp = $client->request(array(
        'action'    => 'reports',
        'version'   => 3,
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'resp_format' => 'json',
    ));

    if (!$resp['ok']) {
        echo "  ERROR: " . (isset($resp['error']) ? $resp['error'] : 'unknown') . "\n";
        $totalErrors++;
        continue;
    }

    // reports з resp_format=json повертає { result:ok, data:[...] }
    $rows = array();
    if (isset($resp['data']['data']) && is_array($resp['data']['data'])) {
        $rows = $resp['data']['data'];
    }
    echo "  transactions fetched: " . count($rows) . "\n";

    foreach ($rows as $lp) {
        // reports API повертає повну структуру, ту саму що status() — немає
        // потреби в додатковому запиті на транзакцію.
        if (empty($lp['order_id'])) continue;
        if (empty($lp['public_key'])) $lp['public_key'] = $conn['public_key'];

        if ($dryRun) {
            echo "    [dry] order_id={$lp['order_id']} status=" . (isset($lp['status']) ? $lp['status'] : '?')
                . " amount=" . (isset($lp['amount']) ? $lp['amount'] : '?')
                . " paytype=" . (isset($lp['paytype']) ? $lp['paytype'] : '?') . "\n";
            continue;
        }

        // Backfill історичних транзакцій — НЕ fire'ємо сценарії:
        // уже завершені замовлення не повинні отримувати ретроактивних повідомлень.
        $result = LiqpayCallbackService::processPaymentData($lp, false);
        if (!empty($result['ok'])) {
            $totalProcessed++;
        } else {
            $totalErrors++;
        }
        echo "    order_id={$lp['order_id']} → "
            . (isset($result['action']) ? $result['action'] : 'noop')
            . ' receipt=' . (isset($result['receipt_id']) ? $result['receipt_id'] : '-')
            . ' cashin=' . (isset($result['cashin_id']) ? $result['cashin_id'] : '-')
            . ' papir_order=' . (isset($result['order_id']) ? $result['order_id'] : '-')
            . (isset($result['reason']) ? ' reason=' . $result['reason'] : '')
            . "\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. processed={$totalProcessed} errors={$totalErrors}\n";