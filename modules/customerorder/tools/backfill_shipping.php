<?php
/**
 * Бекфіл customerorder_shipping з oc_order (off/mff).
 *
 * Запуск:
 *   php /var/www/papir/modules/customerorder/tools/backfill_shipping.php
 *   php /var/www/papir/modules/customerorder/tools/backfill_shipping.php --limit=5000
 *   php /var/www/papir/modules/customerorder/tools/backfill_shipping.php --dry-run
 */

require_once __DIR__ . '/../../database/src/Database.php';
require_once __DIR__ . '/../../database/database.php';

$opts = getopt('', array('limit:', 'dry-run'));
$limit  = isset($opts['limit']) ? (int)$opts['limit'] : 0; // 0 = all
$dryRun = isset($opts['dry-run']);

echo "=== Backfill customerorder_shipping ===" . PHP_EOL;
echo "Dry run: " . ($dryRun ? 'YES' : 'NO') . PHP_EOL;

$batchSize = 500;
$inserted  = 0;
$skipped   = 0;
$errors    = 0;
$offset    = 0;

while (true) {
    $batchLimit = $limit > 0 ? min($batchSize, $limit - $inserted) : $batchSize;
    if ($batchLimit <= 0) break;

    // Замовлення з OFF/MFF що ще не мають shipping
    $sql = "SELECT co.id, co.number, co.counterparty_id
            FROM customerorder co
            LEFT JOIN customerorder_shipping cs ON cs.customerorder_id = co.id
            WHERE co.number REGEXP '^[0-9]+(OFF|MFF)$'
              AND co.deleted_at IS NULL
              AND cs.id IS NULL
            ORDER BY co.id ASC
            LIMIT {$batchLimit}";

    $rBatch = Database::fetchAll('Papir', $sql);
    if (!$rBatch['ok'] || empty($rBatch['rows'])) break;

    $orders = $rBatch['rows'];

    // Групуємо по off/mff
    $byDb = array('off' => array(), 'mff' => array());
    foreach ($orders as $o) {
        preg_match('/^(\d+)(OFF|MFF)$/i', $o['number'], $m);
        $ocId = (int)$m[1];
        $db   = strtolower($m[2]) === 'mff' ? 'mff' : 'off';
        $byDb[$db][$ocId] = $o;
    }

    foreach ($byDb as $db => $ordersMap) {
        if (empty($ordersMap)) continue;

        $ocIds = array_keys($ordersMap);
        $chunks = array_chunk($ocIds, 200);

        foreach ($chunks as $chunk) {
            $inList = implode(',', $chunk);
            $rOc = Database::fetchAll($db,
                "SELECT o.order_id, o.shipping_firstname, o.shipping_lastname,
                        o.telephone, o.shipping_city, o.shipping_address_1,
                        o.shipping_method, o.shipping_code,
                        o.novaposhta_cn_ref, o.shipping_postcode,
                        sf.shipping_street, sf.shipping_house, sf.shipping_flat, sf.no_call
                 FROM oc_order o
                 LEFT JOIN oc_order_simple_fields sf ON sf.order_id = o.order_id
                 WHERE o.order_id IN ({$inList})");

            if (!$rOc['ok']) {
                echo "ERROR fetching from {$db}: {$rOc['error']}" . PHP_EOL;
                $errors += count($chunk);
                continue;
            }

            foreach ($rOc['rows'] as $oc) {
                $ocId = (int)$oc['order_id'];
                if (!isset($ordersMap[$ocId])) continue;
                $papirOrder = $ordersMap[$ocId];

                // Пропустити якщо немає корисних даних
                $hasData = !empty($oc['shipping_city']) || !empty($oc['shipping_address_1'])
                        || !empty($oc['shipping_firstname']) || !empty($oc['telephone']);
                if (!$hasData) {
                    $skipped++;
                    continue;
                }

                $row = array(
                    'customerorder_id'      => (int)$papirOrder['id'],
                    'counterparty_id'       => $papirOrder['counterparty_id'] ? (int)$papirOrder['counterparty_id'] : null,
                    'recipient_first_name'  => $oc['shipping_firstname'] ?: null,
                    'recipient_last_name'   => $oc['shipping_lastname'] ?: null,
                    'recipient_phone'       => $oc['telephone'] ?: null,
                    'city_name'             => $oc['shipping_city'] ?: null,
                    'branch_name'           => $oc['shipping_address_1'] ?: null,
                    'np_warehouse_ref'      => $oc['novaposhta_cn_ref'] ?: null,
                    'street'                => !empty($oc['shipping_street']) ? mb_substr($oc['shipping_street'], 0, 128, 'UTF-8') : null,
                    'house'                 => !empty($oc['shipping_house']) ? mb_substr($oc['shipping_house'], 0, 128, 'UTF-8') : null,
                    'flat'                  => !empty($oc['shipping_flat']) ? mb_substr($oc['shipping_flat'], 0, 128, 'UTF-8') : null,
                    'postcode'              => !empty($oc['shipping_postcode']) ? $oc['shipping_postcode'] : null,
                    'delivery_code'         => $oc['shipping_code'] ?: null,
                    'delivery_method_name'  => $oc['shipping_method'] ? trim($oc['shipping_method']) : null,
                    'no_call'               => (!empty($oc['no_call']) && mb_strtolower(trim($oc['no_call'])) !== 'так') ? 1 : 0,
                    'source'                => 'site_' . $db,
                );

                if ($dryRun) {
                    if ($inserted < 3) {
                        echo "  [DRY] order #{$papirOrder['id']} ({$papirOrder['number']}): {$oc['shipping_city']} / {$oc['shipping_address_1']}" . PHP_EOL;
                    }
                } else {
                    $ins = Database::insert('Papir', 'customerorder_shipping', $row);
                    if (!$ins['ok']) {
                        echo "ERROR insert order #{$papirOrder['id']}: {$ins['error']}" . PHP_EOL;
                        $errors++;
                        continue;
                    }
                }
                $inserted++;
            }
        }
    }

    echo "  batch done, inserted: {$inserted}, skipped: {$skipped}" . PHP_EOL;

    if (count($orders) < $batchLimit) break; // Last batch
}

echo PHP_EOL . "=== DONE ===" . PHP_EOL;
echo "Inserted: {$inserted}" . PHP_EOL;
echo "Skipped (no data): {$skipped}" . PHP_EOL;
echo "Errors: {$errors}" . PHP_EOL;