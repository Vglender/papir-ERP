<?php
/**
 * LiqPay Phase 2 — одноразовий backfill за поточний місяць.
 *
 * МЕТА: привести finance_bank + document_link за April 2026 до єдиної логіки
 * "gross paymentin + commission paymentout" (Phase 2). Історія до 01.04.2026
 * не чіпається.
 *
 * Для кожної LIQPAY-стрічки direction='in' у finance_bank з moment у діапазоні
 * [startDate, endDate]:
 *   1. Перевірити що split ще не зроблено (відсутній sibling external_code із
 *      суфіксом '_liqpay_fee').
 *   2. Знайти order_payment_receipt за LIQPAY ID.
 *   3. gross = receipt.amount, commission = gross - existing_row.sum.
 *   4. UPDATE finance_bank SET sum=gross, is_posted=0 WHERE id = оригінал.
 *      (is_posted→0 щоб ре-синкнути в МС на наступному syncMs-проходу).
 *   5. UPDATE document_link SET linked_sum=gross, to_id=receipt.customerorder_id.
 *   6. INSERT finance_bank direction='out' sum=commission external_code='{orig}_liqpay_fee'
 *      description='Комiсiя LiqPay ID N' agent_ms=bank_fee_agent_id
 *      expense_item_ms=bank_fee_expense_item_id.
 *
 * MS-сторона (PUT paymentin з новим sum + POST paymentout) — ПОЗА скоупом цього
 * скрипта. Скрипт виводить список МС-документів, які треба оновити окремим
 * кроком. Папір-сторона після бекфілу буде коректно звітувати payment_status
 * на замовленнях (через OrderFinanceHelper::recalc).
 *
 * Параметри CLI:
 *   --dry-run           лише показати що буде зроблено, нічого не писати
 *   --from=YYYY-MM-DD   початок діапазону (за замовчуванням 2026-04-01)
 *   --to=YYYY-MM-DD     кінець діапазону (за замовчуванням сьогодні 23:59:59)
 *   --limit=N           обмежити кількість рядків (для тестування)
 */

define('CRON_MODE', true);

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../integrations/AppRegistry.php';
AppRegistry::guard('liqpay');

require_once __DIR__ . '/../../customerorder/services/OrderFinanceHelper.php';

// ── CLI args ─────────────────────────────────────────────────────────────────
$dryRun = false;
$dateFrom = '2026-04-01 00:00:00';
$dateTo   = date('Y-m-d 23:59:59');
$limit    = 0;
foreach ($argv as $arg) {
    if ($arg === '--dry-run') $dryRun = true;
    if (preg_match('/^--from=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) $dateFrom = $m[1] . ' 00:00:00';
    if (preg_match('/^--to=(\d{4}-\d{2}-\d{2})$/', $arg, $m))   $dateTo   = $m[1] . ' 23:59:59';
    if (preg_match('/^--limit=(\d+)$/', $arg, $m))              $limit    = (int)$m[1];
}

// Дефолти комісійного контрагента/експенсу — з payment_match_rules.php
$matchRules = require __DIR__ . '/../../payments_sync/config/payment_match_rules.php';
$feeAgentMs     = $matchRules['defaults']['bank_fee_agent_id'];
$feeExpenseMs   = $matchRules['defaults']['bank_fee_expense_item_id'];

echo "[" . date('Y-m-d H:i:s') . "] LiqPay Phase 2 backfill "
   . "from={$dateFrom} to={$dateTo}" . ($dryRun ? ' DRY-RUN' : '')
   . ($limit ? " limit={$limit}" : '') . PHP_EOL;
echo "fee_agent_ms={$feeAgentMs} fee_expense_ms={$feeExpenseMs}\n";

// expense_category_id для commission row
$expCatId = null;
$rc = Database::fetchRow('Papir',
    "SELECT id FROM finance_expense_category WHERE expense_item_ms='" . Database::escape('Papir', $feeExpenseMs) . "' LIMIT 1");
if ($rc['ok'] && !empty($rc['row'])) {
    $expCatId = (int)$rc['row']['id'];
}

// Локальний FK контрагента bank-fee (LiqPay commission отримує)
$feeCpId = null;
$rcp = Database::fetchRow('Papir',
    "SELECT id FROM counterparty WHERE id_ms='" . Database::escape('Papir', $feeAgentMs) . "' LIMIT 1");
if ($rcp['ok'] && !empty($rcp['row'])) {
    $feeCpId = (int)$rcp['row']['id'];
}

// ── 1. Вибірка кандидатів ────────────────────────────────────────────────────
$fromSql = Database::escape('Papir', $dateFrom);
$toSql   = Database::escape('Papir', $dateTo);
$limSql  = $limit > 0 ? "LIMIT {$limit}" : '';

$sql = "
    SELECT fb.id, fb.external_code, fb.sum, fb.moment, fb.description,
           fb.agent_ms, fb.organization_ms, fb.id_ms AS ms_doc_id, fb.is_posted,
           fb.source, fb.cp_id
    FROM finance_bank fb
    WHERE fb.direction = 'in'
      AND fb.description LIKE '%LIQPAY ID %'
      AND fb.moment BETWEEN '{$fromSql}' AND '{$toSql}'
      AND NOT EXISTS (
          SELECT 1 FROM finance_bank fb2
          WHERE fb2.external_code = CONCAT(fb.external_code, '_liqpay_fee')
      )
    ORDER BY fb.moment ASC
    {$limSql}
";

$res = Database::fetchAll('Papir', $sql);
if (!$res['ok']) {
    echo "ERROR: cannot select candidates: " . (isset($res['error']) ? $res['error'] : '?') . "\n";
    exit(1);
}
$rows = isset($res['rows']) ? $res['rows'] : array();
echo "candidates: " . count($rows) . "\n";
if (empty($rows)) exit(0);

$stats = array(
    'processed'            => 0,
    'skipped_no_receipt'   => 0,
    'skipped_bad_amount'   => 0,
    'skipped_zero_fee'     => 0,
    'updated_paymentin'    => 0,
    'inserted_commission'  => 0,
    'orders_affected'      => array(),
    'ms_docs_to_update'    => array(),
);

// ── 2. Прохід по рядках ──────────────────────────────────────────────────────
foreach ($rows as $fb) {
    $stats['processed']++;

    // Витягти LIQPAY ID
    if (!preg_match('/LIQPAY ID (\d+)/u', $fb['description'], $m)) {
        echo "  [fb#{$fb['id']}] no LIQPAY ID in description — skip\n";
        continue;
    }
    $liqpayId = $m[1];

    // Знайти receipt
    $pidSql = Database::escape('Papir', $liqpayId);
    $rr = Database::fetchRow('Papir',
        "SELECT r.id AS receipt_id, r.amount, r.customerorder_id, co.id_ms AS order_ms_id
         FROM order_payment_receipt r
         LEFT JOIN customerorder co ON co.id = r.customerorder_id
         WHERE r.provider='liqpay' AND r.payment_id='{$pidSql}' AND r.status='success'
         LIMIT 1");
    if (!$rr['ok'] || empty($rr['row']) || empty($rr['row']['amount'])) {
        echo "  [fb#{$fb['id']}] LIQPAY ID {$liqpayId} — no success receipt, skip\n";
        $stats['skipped_no_receipt']++;
        continue;
    }
    $receipt = $rr['row'];

    $gross = round((float)$receipt['amount'], 2);
    $net   = round((float)$fb['sum'], 2);
    $commission = round($gross - $net, 2);

    if ($gross <= 0) {
        echo "  [fb#{$fb['id']}] gross <= 0, skip\n";
        $stats['skipped_bad_amount']++;
        continue;
    }
    if ($commission <= 0.009) {
        // Банк уже приніс gross → просто перезв'яжемо document_link з правильним order, якщо треба
        echo "  [fb#{$fb['id']}] commission≈0 (gross={$gross} net={$net}) — no split, relink only\n";
        if (!$dryRun) {
            self_relinkOrder($fb['id'], $receipt);
        }
        $stats['skipped_zero_fee']++;
        if ($receipt['customerorder_id']) $stats['orders_affected'][$receipt['customerorder_id']] = true;
        continue;
    }

    echo "  [fb#{$fb['id']}] LIQPAY {$liqpayId} order_id={$receipt['customerorder_id']} "
       . "gross={$gross} net={$net} commission={$commission}\n";

    if (!$dryRun) {
        // 2a. Оновити існуючий paymentin: sum → gross + cp_id з order'а (локальний FK).
        // is_posted НЕ чіпаємо: якщо id_ms є, МС-документ уже проведений, скидання
        // is_posted→0 лише створить плутанину в UI («платіж не активний»).
        // MS re-sync (un-post→PUT→post) — окремий прохід, не задача backfill'у.
        $updateFields = array('sum' => $gross);
        if (empty($fb['cp_id']) && !empty($receipt['customerorder_id'])) {
            // Підтягнути counterparty_id з замовлення
            $co = Database::fetchRow('Papir',
                "SELECT counterparty_id FROM customerorder WHERE id=" . (int)$receipt['customerorder_id'] . " LIMIT 1");
            if ($co['ok'] && !empty($co['row']['counterparty_id'])) {
                $updateFields['cp_id'] = (int)$co['row']['counterparty_id'];
            }
        }
        Database::update('Papir', 'finance_bank', $updateFields, array('id' => (int)$fb['id']));

        // 2b. Оновити/створити document_link
        self_relinkOrder($fb['id'], $receipt, $gross);

        // 2c. Вставити commission row
        $feeExt = $fb['external_code'] . '_liqpay_fee';
        $ins = Database::insert('Papir', 'finance_bank', array(
            'direction'           => 'out',
            'moment'              => $fb['moment'],
            'sum'                 => $commission,
            'cp_id'               => $feeCpId,
            'agent_ms'            => $feeAgentMs,
            'organization_ms'     => $fb['organization_ms'],
            'is_moving'           => 0,
            'is_posted'           => 0,
            'expense_item_ms'     => $feeExpenseMs,
            'expense_category_id' => $expCatId,
            'description'         => 'Комiсiя LiqPay ID ' . $liqpayId,
            'external_code'       => $feeExt,
            'source'              => isset($fb['source']) ? $fb['source'] : 'bank_sync',
        ));
        if (!$ins['ok'] || empty($ins['insert_id'])) {
            echo "    ERROR: cannot insert commission row: " . (isset($ins['error']) ? $ins['error'] : '?') . "\n";
            continue;
        }
    }
    $stats['updated_paymentin']++;
    $stats['inserted_commission']++;
    if ($receipt['customerorder_id']) $stats['orders_affected'][(int)$receipt['customerorder_id']] = true;
    if (!empty($fb['ms_doc_id'])) $stats['ms_docs_to_update'][] = $fb['ms_doc_id'];
}

// ── 3. Перерахувати payment_status для заторкнутих замовлень ────────────────
if (!$dryRun && !empty($stats['orders_affected'])) {
    echo "recalc " . count($stats['orders_affected']) . " orders…\n";
    foreach (array_keys($stats['orders_affected']) as $orderId) {
        OrderFinanceHelper::recalc((int)$orderId);
    }
}

// ── 4. Summary ───────────────────────────────────────────────────────────────
echo "\n=== SUMMARY ===\n";
echo "processed:           {$stats['processed']}\n";
echo "updated paymentin:   {$stats['updated_paymentin']}\n";
echo "inserted commission: {$stats['inserted_commission']}\n";
echo "skipped (no receipt): {$stats['skipped_no_receipt']}\n";
echo "skipped (bad amount): {$stats['skipped_bad_amount']}\n";
echo "skipped (zero fee):   {$stats['skipped_zero_fee']}\n";
echo "orders recalced:      " . count($stats['orders_affected']) . "\n";
echo "MS paymentin docs to re-sync: " . count($stats['ms_docs_to_update']) . "\n";
echo "  (Папір-сторона оновлена; для МС: PUT paymentin з новим sum + POST paymentout на commission)\n";
echo "[" . date('Y-m-d H:i:s') . "] Done.\n";

// ── helpers ──────────────────────────────────────────────────────────────────

/**
 * Оновити (або створити) document_link paymentin → customerorder з правильним
 * linked_sum і to_id. Викликається і для split, і для relink-only гілки.
 */
function self_relinkOrder($financeBankId, array $receipt, $linkedSum = null)
{
    $financeBankId = (int)$financeBankId;
    $orderId = !empty($receipt['customerorder_id']) ? (int)$receipt['customerorder_id'] : 0;
    $orderMs = !empty($receipt['order_ms_id']) ? (string)$receipt['order_ms_id'] : null;
    $amount  = $linkedSum !== null ? (float)$linkedSum : (float)$receipt['amount'];

    // Шукаємо існуючий document_link для цього finance_bank
    $rl = Database::fetchRow('Papir',
        "SELECT id FROM document_link
         WHERE from_type='paymentin' AND from_id={$financeBankId} LIMIT 1");

    if ($rl['ok'] && !empty($rl['row'])) {
        Database::update('Papir', 'document_link', array(
            'to_type'    => 'customerorder',
            'to_id'      => $orderId ?: null,
            'to_ms_id'   => $orderMs,
            'link_type'  => 'payment',
            'linked_sum' => $amount,
        ), array('id' => (int)$rl['row']['id']));
    } else {
        Database::insert('Papir', 'document_link', array(
            'from_type'  => 'paymentin',
            'from_id'    => $financeBankId,
            'from_ms_id' => null,
            'to_type'    => 'customerorder',
            'to_id'      => $orderId ?: null,
            'to_ms_id'   => $orderMs,
            'link_type'  => 'payment',
            'linked_sum' => $amount,
        ));
    }
}