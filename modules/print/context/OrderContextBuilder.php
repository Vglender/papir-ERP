<?php
require_once __DIR__ . '/../../shared/MoneyWords.php';

/**
 * Builds Mustache context for customerorder entity.
 * Variables available in templates:
 *   {{invoice.number}}, {{invoice.date}}
 *   {{seller.name}}, {{seller.okpo}}, {{seller.iban}}, {{seller.bank_name}}, {{seller.mfo}},
 *   {{seller.address}}, {{seller.phone}}, {{seller.director_name}}, {{seller.director_title}},
 *   {{seller.signatory_name}}
 *   {{buyer.name}}, {{buyer.okpo}}, {{buyer.iban}}, {{buyer.bank}}, {{buyer.mfo}}, {{buyer.address}}, {{buyer.phone}}
 *   {{#lines}} {{num}} {{description}} {{sku}} {{unit}} {{qty}} {{price}} {{total}} {{vat_rate}} {{vat_amount}} {{/lines}}
 *   {{total}}, {{vat_rate}}, {{vat_amount}}, {{total_with_vat}}, {{sum_total}}
 *   {{total_text}} — сума прописом (з ПДВ)
 *   {{vat_text}}   — ПДВ прописом
 *   {{items_count}}, {{total_qty}}
 *   {{doc.number}}, {{doc.date}}, {{doc.currency}}
 */
class OrderContextBuilder
{
    public static function build($orderId, $orgId = 0)
    {
        $orderId = (int)$orderId;
        $orgId   = (int)$orgId;

        // Order + buyer (counterparty) + org bank account from order + manager
        $r = Database::fetchRow('Papir',
            "SELECT co.*,
                    c.name   AS cp_name,
                    c.type   AS cp_type,
                    cc.okpo  AS cp_okpo,
                    cc.iban  AS cp_iban,
                    cc.bank_name AS cp_bank_name,
                    cc.mfo   AS cp_mfo,
                    cc.legal_address   AS cp_legal_address,
                    cc.actual_address  AS cp_actual_address,
                    COALESCE(NULLIF(cc.phone,''), NULLIF(cp.phone,''), NULLIF(cp.phone_alt,'')) AS cp_phone,
                    oba.iban      AS order_iban,
                    oba.bank_name AS order_bank_name,
                    oba.mfo       AS order_mfo,
                    e.full_name AS manager_full_name,
                    e.phone  AS manager_phone
             FROM customerorder co
             LEFT JOIN counterparty c ON c.id = co.counterparty_id
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             LEFT JOIN counterparty_person cp ON cp.counterparty_id = c.id
             LEFT JOIN organization_bank_account oba ON oba.id = co.organization_bank_account_id
             LEFT JOIN employee e ON e.id = co.manager_employee_id
             WHERE co.id = {$orderId} AND co.deleted_at IS NULL
             LIMIT 1"
        );

        if (!$r['ok'] || empty($r['row'])) {
            return array();
        }
        $o = $r['row'];

        // Use org from order unless caller overrides
        $resolvedOrgId = ($orgId > 0) ? $orgId : (int)$o['organization_id'];

        // Seller (organization)
        $org = array();
        if ($resolvedOrgId > 0) {
            $rOrg = Database::fetchRow('Papir',
                "SELECT * FROM organization WHERE id = {$resolvedOrgId} AND status = 1 LIMIT 1"
            );
            if ($rOrg['ok'] && !empty($rOrg['row'])) {
                $org = $rOrg['row'];
            }
        }

        // Fallback: if order has no organization_id, use first active org
        if (empty($org)) {
            $rOrg = Database::fetchRow('Papir',
                "SELECT * FROM organization WHERE status = 1 ORDER BY id ASC LIMIT 1"
            );
            if ($rOrg['ok'] && !empty($rOrg['row'])) {
                $org = $rOrg['row'];
                $resolvedOrgId = (int)$org['id'];
            }
        }

        // Bank account: prefer the one linked to the order, fallback to org default
        $sellerIban = isset($o['order_iban']) ? $o['order_iban'] : '';
        $sellerBank = isset($o['order_bank_name']) ? $o['order_bank_name'] : '';
        $sellerMfo  = isset($o['order_mfo']) ? $o['order_mfo'] : '';

        if (empty($sellerIban) && $resolvedOrgId > 0) {
            $rBank = Database::fetchRow('Papir',
                "SELECT iban, bank_name, mfo
                 FROM organization_bank_account
                 WHERE organization_id = {$resolvedOrgId} AND status = 1
                 ORDER BY is_default DESC, id ASC
                 LIMIT 1"
            );
            if ($rBank['ok'] && !empty($rBank['row'])) {
                $sellerIban = $rBank['row']['iban'];
                $sellerBank = $rBank['row']['bank_name'];
                $sellerMfo  = $rBank['row']['mfo'];
            }
        }

        // Final fallback: org-level iban/bank_name/mfo
        if (empty($sellerIban) && !empty($org['iban'])) {
            $sellerIban = $org['iban'];
            $sellerBank = isset($org['bank_name']) ? $org['bank_name'] : '';
            $sellerMfo  = isset($org['mfo'])       ? $org['mfo']       : '';
        }

        // Line items
        $rItems = Database::fetchAll('Papir',
            "SELECT ci.*,
                    COALESCE(NULLIF(pd2.name,''), NULLIF(pd1.name,''), ci.product_name) AS resolved_name,
                    COALESCE(NULLIF(ci.sku,''), pp.product_article, '') AS resolved_sku
             FROM customerorder_item ci
             LEFT JOIN product_papir pp ON pp.product_id = ci.product_id
             LEFT JOIN product_description pd2 ON pd2.product_id = ci.product_id AND pd2.language_id = 2
             LEFT JOIN product_description pd1 ON pd1.product_id = ci.product_id AND pd1.language_id = 1
             WHERE ci.customerorder_id = {$orderId}
             ORDER BY ci.line_no, ci.id"
        );
        $rawItems = ($rItems['ok'] && !empty($rItems['rows'])) ? $rItems['rows'] : array();

        $lines    = array();
        $sumNet   = 0.0;  // total without VAT
        $sumVat   = 0.0;
        $sumGross = 0.0; // total with VAT
        $totalQty = 0.0;

        foreach ($rawItems as $i => $it) {
            $lineTotal = (float)$it['sum_row'];
            $lineVat   = (float)$it['vat_amount'];
            $lineNet   = $lineTotal - $lineVat;
            $sumNet   += $lineNet;
            $sumVat   += $lineVat;
            $sumGross += $lineTotal;
            $totalQty += (float)$it['quantity'];

            $lines[] = array(
                'num'          => $i + 1,
                'description'  => !empty($it['resolved_name']) ? $it['resolved_name'] : $it['product_name'],
                'sku'          => isset($it['resolved_sku']) ? $it['resolved_sku'] : '',
                'unit'         => !empty($it['unit']) ? $it['unit'] : 'шт',
                'qty'          => self::fmtQty((float)$it['quantity']),
                'price'        => self::fmtMoney((float)$it['price']),
                'discount'     => self::fmtQty((float)$it['discount_percent']),
                'vat_rate'     => (float)$it['vat_rate'],
                'vat_amount'   => self::fmtMoney($lineVat),
                'total_no_vat' => self::fmtMoney($lineNet),
                'total'        => self::fmtMoney($lineTotal),
            );
        }

        // Dominant VAT rate (from first line with vat > 0, or first line)
        $vatRate = 0;
        foreach ($rawItems as $it) {
            if ((float)$it['vat_rate'] > 0) {
                $vatRate = (float)$it['vat_rate'];
                break;
            }
        }
        if ($vatRate == 0 && !empty($rawItems)) {
            $vatRate = (float)$rawItems[0]['vat_rate'];
        }

        // Document date
        $moment  = !empty($o['moment']) ? $o['moment'] : $o['created_at'];
        $docDate = date('d.m.Y', strtotime($moment));

        // Buyer address
        $buyerAddress = !empty($o['cp_actual_address']) ? $o['cp_actual_address'] : (string)$o['cp_legal_address'];

        return array(
            'invoice' => array(
                'number' => $o['number'],
                'date'   => $docDate,
            ),
            'doc' => array(
                'number'   => $o['number'],
                'date'     => $docDate,
                'currency' => $o['currency_code'],
            ),
            'seller' => array(
                'name'           => isset($org['name'])           ? $org['name']           : '',
                'short_name'     => isset($org['short_name'])     ? $org['short_name']     : '',
                'okpo'           => isset($org['okpo'])           ? $org['okpo']           : '',
                'inn'            => isset($org['inn'])            ? $org['inn']            : '',
                'vat_number'     => isset($org['vat_number'])     ? $org['vat_number']     : '',
                'iban'           => $sellerIban,
                'bank_name'      => $sellerBank,
                'mfo'            => $sellerMfo,
                'address'        => isset($org['actual_address']) ? $org['actual_address'] : (isset($org['legal_address']) ? $org['legal_address'] : ''),
                'phone'          => isset($org['phone'])          ? $org['phone']          : '',
                'email'          => isset($org['email'])          ? $org['email']          : '',
                'director_name'  => isset($org['director_name'])  ? $org['director_name']  : '',
                'director_title' => isset($org['director_title']) ? $org['director_title'] : 'Директор',
                'signatory_name' => isset($org['director_name'])  ? $org['director_name']  : '',
                'logo_path'      => isset($org['logo_path'])      ? '/' . ltrim($org['logo_path'], '/')      : '',
                'stamp_path'     => isset($org['stamp_path'])     ? '/' . ltrim($org['stamp_path'], '/')     : '',
                'signature_path' => isset($org['signature_path']) ? '/' . ltrim($org['signature_path'], '/') : '',
            ),
            'buyer' => array(
                'name'    => isset($o['cp_name'])      ? $o['cp_name']      : '',
                'okpo'    => isset($o['cp_okpo'])      ? $o['cp_okpo']      : '',
                'iban'    => isset($o['cp_iban'])      ? $o['cp_iban']      : '',
                'bank'    => isset($o['cp_bank_name']) ? $o['cp_bank_name'] : '',
                'mfo'     => isset($o['cp_mfo'])       ? $o['cp_mfo']       : '',
                'address' => $buyerAddress,
                'phone'   => isset($o['cp_phone'])     ? $o['cp_phone']     : '',
            ),
            'manager' => array(
                'name'  => !empty($o['manager_full_name']) ? $o['manager_full_name'] : '',
                'phone' => !empty($o['manager_phone'])     ? $o['manager_phone']     : '',
            ),
            'lines'          => $lines,
            'items_count'    => count($lines),
            'total_qty'      => self::fmtQty($totalQty),
            'total'          => self::fmtMoney($sumNet),
            'sum_net'        => self::fmtMoney($sumNet),
            'vat_rate'       => $vatRate,
            'vat_amount'     => self::fmtMoney($sumVat),
            'total_with_vat' => self::fmtMoney($sumGross),
            'sum_total'      => self::fmtMoney($sumGross),
            'total_text'     => MoneyWords::format($sumGross),
            'vat_text'       => MoneyWords::format($sumVat),
        );
    }

    // ── Formatting helpers ─────────────────────────────────────────────────────

    private static function fmtQty($val)
    {
        if ($val == (int)$val) {
            return (string)(int)$val;
        }
        return rtrim(number_format($val, 3, '.', ''), '0');
    }

    private static function fmtMoney($val)
    {
        return number_format((float)$val, 2, '.', ' ');
    }

}