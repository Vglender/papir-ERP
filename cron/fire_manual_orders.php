<?php
/**
 * Cron: Fire TriggerEngine('order_created') for manual orders that have been
 * sitting in status='new' for at least 5 minutes.
 *
 * Rationale: manual orders created via /customerorder/save.php are considered
 * "committed" if the operator hasn't modified or deleted them within 5 minutes.
 * After that window we treat them the same as imported orders — run full
 * scenarios flow (send_message to client, create demand for cash-on-delivery,
 * etc.) as if they came from site/Prom.
 *
 * Run every 5 minutes:
 *   * /5 * * * *  php /var/www/papir/cron/fire_manual_orders.php >> /var/log/papir/fire_manual_orders.log 2>&1
 *
 * Idempotency: customerorder.scenarios_fired_at — set once when fire() succeeds,
 * never fired again for the same order.
 */

define('CRON_MODE', true);

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/counterparties/services/TriggerEngine.php';
require_once __DIR__ . '/../modules/counterparties/repositories/ScenarioRepository.php';

$start = microtime(true);
echo '[' . date('Y-m-d H:i:s') . '] === FIRE MANUAL ORDERS ===' . PHP_EOL;

// Кандидати: source=manual, status=new, ще не fired, пролежали >= 5 хвилин.
$r = Database::fetchAll('Papir',
    "SELECT id, counterparty_id, number, created_at
     FROM customerorder
     WHERE source = 'manual'
       AND status = 'new'
       AND scenarios_fired_at IS NULL
       AND deleted_at IS NULL
       AND created_at <= (NOW() - INTERVAL 5 MINUTE)
     ORDER BY id ASC
     LIMIT 100"
);

if (!$r['ok']) {
    echo '[ERROR] DB query failed: ' . $r['error'] . PHP_EOL;
    exit(1);
}

$candidates = $r['rows'];
$count = count($candidates);
echo "[" . date('H:i:s') . "] Found {$count} manual order(s) ready to fire" . PHP_EOL;

$fired = 0;
$failed = 0;

foreach ($candidates as $row) {
    $orderId = (int)$row['id'];
    $cpId    = (int)$row['counterparty_id'];
    $number  = $row['number'];

    // Перезавантажуємо увесь order row у контекст (для умов сценаріїв)
    $ord = Database::fetchRow('Papir', "SELECT * FROM customerorder WHERE id={$orderId} LIMIT 1");
    if (!$ord['ok'] || empty($ord['row'])) {
        echo "[{$orderId}] skip: not found" . PHP_EOL;
        continue;
    }

    $context = array(
        'order_id'        => $orderId,
        'counterparty_id' => $cpId,
        'source'          => 'manual',
        'sales_channel'   => null,
        'order'           => $ord['row'],
    );

    try {
        TriggerEngine::fire('order_created', $context);

        // Позначаємо незалежно від того, чи були тригери які заматчили умови —
        // сам fire вважається виконаним; повторного виклику для цього заказу не буде.
        Database::query('Papir',
            "UPDATE customerorder
             SET scenarios_fired_at = NOW()
             WHERE id = {$orderId} AND scenarios_fired_at IS NULL"
        );

        echo "[{$orderId}] fired order_created ({$number})" . PHP_EOL;
        $fired++;
    } catch (Exception $e) {
        echo "[{$orderId}] FAIL: " . $e->getMessage() . PHP_EOL;
        $failed++;
    }
}

$elapsed = round(microtime(true) - $start, 2);
echo '[' . date('Y-m-d H:i:s') . "] === DONE: fired={$fired}, failed={$failed}, elapsed={$elapsed}s ===" . PHP_EOL;