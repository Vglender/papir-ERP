<?php
/**
 * BankLiqpaySplitter — Фаза 2 LiqPay реконсіляції.
 *
 * Банк (ПриватБанк-еквайринг) шле до нас НЕТТО по кожній LiqPay-транзакції:
 *   net = gross (сума, яку заплатив клієнт) − commission (комісія LiqPay/еквайрингу).
 *
 * На кожну таку банківську стрічку ми маємо LiqPay receipt (order_payment_receipt),
 * де amount=gross. Цей сервіс з колектора (BankPaymentCollector::collect) отримує
 * масив сирих рядків і на місці їх розщеплює:
 *
 *   1) paymentin (тип 'in')  sum = gross  →  до customerorder (за receipt.customerorder_id)
 *   2) paymentout (тип 'out') sum = commission (gross − net)
 *      description = 'Комiсiя LiqPay ID N' → існуюче правило PaymentMatcher
 *      ('Комiсiя' у payment_match_rules.php) автоматично підставить
 *      bank_fee_agent_id + bank_fee_expense_item_id.
 *
 * Ідемпотентність: splitter працює над сирими рядками ДО dup-check, тому
 * PaymentDuplicateChecker пізніше відсіє вже збережені платежі за external_code.
 * Оригінальний id_paid зберігається у gross-рядку; commission-рядок отримує
 * суфікс '_liqpay_fee' (аналогічно до Monobank fee split у BankPaymentCollector).
 *
 * Якщо receipt не знайдено або commission ≤ 0 — рядок не розщеплюється, а
 * повертається як є (fallback на стару логіку PaymentMatcher:/LiqPay/ для пошуку
 * замовлення у МС).
 *
 * Одиниці: $row['sum'] у КОПІЙКАХ (формат BankPaymentCollector), $receipt.amount
 * у ГРИВНЯХ (формат LiqPay API). Усі обчислення йдуть у копійках.
 */
class BankLiqpaySplitter
{
    const LIQPAY_ID_PATTERN = '/LIQPAY ID (\d+)/u';

    /**
     * @param array $rows — сирі рядки від BankPaymentCollector::collect()
     * @return array — той самий масив, але з розщепленими LIQPAY-стрічками
     */
    public static function splitRows(array $rows)
    {
        $output = array();

        foreach ($rows as $row) {
            $split = self::trySplit($row);
            if (is_array($split)) {
                foreach ($split as $r) {
                    $output[] = $r;
                }
            } else {
                $output[] = $row;
            }
        }

        return $output;
    }

    /**
     * Спробувати розщепити один рядок.
     * @return array|null  масив 1-2 рядків якщо розщепити вдалося, null якщо ні
     */
    protected static function trySplit(array $row)
    {
        if (empty($row['type']) || $row['type'] !== 'in') return null;
        if (empty($row['description'])) return null;

        if (!preg_match(self::LIQPAY_ID_PATTERN, $row['description'], $m)) return null;
        $paymentId = $m[1];

        $receipt = self::findReceipt($paymentId);
        if (!$receipt) {
            error_log('[liqpay_splitter] receipt not found for LIQPAY ID ' . $paymentId);
            return null;
        }

        $grossKopecks = (int)round((float)$receipt['amount'] * 100);
        if ($grossKopecks <= 0) {
            error_log('[liqpay_splitter] receipt amount <= 0 for LIQPAY ID ' . $paymentId);
            return null;
        }

        $netKopecks  = (int)$row['sum'];
        $commissionKopecks = $grossKopecks - $netKopecks;

        // Gross-рядок: мутуємо оригінал, додаємо id_order + cp_id + id_agent.
        // cp_id — локальний FK (джерело правди, CLAUDE.md:Local IDs). Обов'язковий.
        // id_agent (МС UUID) — лише маппінг; якщо counterparty ще не синкнутий
        // у МС (cp.id_ms=NULL), лишаємо null → PaymentMatcher підставить fallback.
        // id_order обов'язковий теж, але робить early-return у PaymentMatcher::resolveLinkedOrder,
        // тому ми дублюємо підтягування agent тут, бо ту гілку у матчері буде пропущено.
        $grossRow = $row;
        $grossRow['sum'] = $grossKopecks;
        if (!empty($receipt['order_ms_id'])) {
            $grossRow['id_order'] = $receipt['order_ms_id'];
        }
        if (!empty($receipt['cp_id'])) {
            $grossRow['cp_id'] = (int)$receipt['cp_id'];
        }
        if (!empty($receipt['agent_ms_id'])) {
            $grossRow['id_agent'] = $receipt['agent_ms_id'];
        }

        // Якщо банк прийшов уже з gross (commission <= 0) — лише проставимо order і не розщеплюємо
        if ($commissionKopecks <= 0) {
            return array($grossRow);
        }

        // Commission row: окремий paymentout з суфіксом id_paid
        $commissionRow = array(
            'source'           => isset($row['source']) ? $row['source'] : 'bank_sync',
            'bank'             => isset($row['bank']) ? $row['bank'] : null,
            'bank_account_key' => isset($row['bank_account_key']) ? $row['bank_account_key'] : null,
            'id_paid'          => (isset($row['id_paid']) ? $row['id_paid'] : '') . '_liqpay_fee',
            'name'             => (string)(strtotime('now') + mt_rand(1, 99)),
            'type'             => 'out',
            'moment'           => isset($row['moment']) ? $row['moment'] : null,
            'sum'              => $commissionKopecks,
            'rate'             => 0,
            'description'      => 'Комiсiя LiqPay ID ' . $paymentId,
            'name_kl'          => null,
            'edrpoy_klient'    => null,
            'acc_klient'       => null,
            'id_agent'         => null,
            'inner'            => false,
            'id_order'         => null,
            'id_exp'           => null,
        );
        if (isset($row['id_org'])) $commissionRow['id_org'] = $row['id_org'];
        if (isset($row['id_acc'])) $commissionRow['id_acc'] = $row['id_acc'];

        return array($grossRow, $commissionRow);
    }

    /**
     * Знайти LiqPay receipt за payment_id.
     * Повертає amount (gross, грн), id_ms замовлення (для id_order) та
     * id_ms контрагента замовлення (для id_agent) — це обходить PaymentMatcher::resolveLinkedOrder
     * early-return, який би пропустив гілку підтягування agent з order'а.
     *
     * Якщо counterparty.id_ms NULL (контрагент у Папірі є, але ще не синкнутий у МС)
     * — залишаємо agent_ms_id null, тоді PaymentMatcher підставить дефолтного
     * fallback-агента з payment_match_rules. Це краще ніж чіпляти неправильний UUID.
     */
    protected static function findReceipt($paymentId)
    {
        $pidSql = \Database::escape('Papir', (string)$paymentId);
        $r = \Database::fetchRow('Papir',
            "SELECT r.amount,
                    r.customerorder_id,
                    co.id_ms  AS order_ms_id,
                    co.counterparty_id AS cp_id,
                    cp.id_ms  AS agent_ms_id
             FROM order_payment_receipt r
             LEFT JOIN customerorder co ON co.id = r.customerorder_id
             LEFT JOIN counterparty   cp ON cp.id = co.counterparty_id
             WHERE r.provider = 'liqpay'
               AND r.payment_id = '{$pidSql}'
               AND r.status = 'success'
             LIMIT 1");
        if (!$r['ok'] || empty($r['row'])) return null;
        return $r['row'];
    }
}