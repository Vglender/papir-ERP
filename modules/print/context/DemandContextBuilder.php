<?php
require_once __DIR__ . '/../../shared/MoneyWords.php';

/**
 * Builds Mustache context for demand (shipment/відвантаження) entity.
 * Variables available in templates:
 *   {{invoice.number}}, {{invoice.date}}
 *   {{seller.name}}, {{seller.short_name}}, {{seller.okpo}}, {{seller.inn}}, {{seller.vat_number}},
 *   {{seller.iban}}, {{seller.bank_name}}, {{seller.mfo}},
 *   {{seller.address}}, {{seller.phone}}, {{seller.email}},
 *   {{seller.director_name}}, {{seller.director_title}}, {{seller.signatory_name}},
 *   {{seller.logo_path}}, {{seller.stamp_path}}, {{seller.signature_path}}
 *   {{buyer.name}}, {{buyer.okpo}}, {{buyer.iban}}, {{buyer.bank}}, {{buyer.mfo}}, {{buyer.address}}, {{buyer.phone}}
 *   {{#lines}} {{num}} {{description}} {{sku}} {{unit}} {{qty}} {{price}} {{total}} {{vat_rate}} {{vat_amount}} {{/lines}}
 *   {{total}}, {{sum_net}}, {{vat_rate}}, {{vat_amount}}, {{total_with_vat}}, {{sum_total}}
 *   {{total_text}} — сума прописом (з ПДВ)
 *   {{vat_text}}   — ПДВ прописом
 *   {{items_count}}, {{total_qty}}
 *   {{doc.number}}, {{doc.date}}, {{doc.currency}}
 *   {{order.number}}, {{order.date}} — linked customerorder
 *   {{manager.name}}, {{manager.phone}}
 *   {{location}} — місце складання
 */
class DemandContextBuilder
{
    public static function build($demandId, $orgId = 0)
    {
        $demandId = (int)$demandId;
        $orgId    = (int)$orgId;

        $r = Database::fetchRow('Papir',
            "SELECT d.*,
                    c.name   AS cp_name,
                    c.type   AS cp_type,
                    cc.okpo  AS cp_okpo,
                    cc.iban  AS cp_iban,
                    cc.bank_name AS cp_bank_name,
                    cc.mfo   AS cp_mfo,
                    cc.legal_address   AS cp_legal_address,
                    cc.actual_address  AS cp_actual_address,
                    COALESCE(NULLIF(cc.phone,''), NULLIF(cp2.phone,''), NULLIF(cp2.phone_alt,'')) AS cp_phone,
                    e.full_name AS manager_full_name,
                    e.phone  AS manager_phone,
                    co.number AS order_number,
                    co.moment AS order_moment,
                    co.created_at AS order_created_at,
                    co.currency_code AS order_currency,
                    co.organization_id AS order_organization_id
             FROM demand d
             LEFT JOIN counterparty c ON c.id = d.counterparty_id
             LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
             LEFT JOIN counterparty_person cp2 ON cp2.counterparty_id = c.id
             LEFT JOIN employee e ON e.id = d.manager_employee_id
             LEFT JOIN customerorder co ON co.id = d.customerorder_id
             WHERE d.id = {$demandId} AND d.deleted_at IS NULL
             LIMIT 1"
        );

        if (!$r['ok'] || empty($r['row'])) {
            return array();
        }
        $d = $r['row'];

        // Organization priority: caller override → demand → linked order
        $resolvedOrgId = ($orgId > 0) ? $orgId : (int)$d['organization_id'];
        if ($resolvedOrgId <= 0 && !empty($d['order_organization_id'])) {
            $resolvedOrgId = (int)$d['order_organization_id'];
        }

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

        if (empty($org)) {
            $rOrg = Database::fetchRow('Papir',
                "SELECT * FROM organization WHERE status = 1 ORDER BY id ASC LIMIT 1"
            );
            if ($rOrg['ok'] && !empty($rOrg['row'])) {
                $org = $rOrg['row'];
                $resolvedOrgId = (int)$org['id'];
            }
        }

        // Bank account: org default
        $sellerIban = '';
        $sellerBank = '';
        $sellerMfo  = '';

        if ($resolvedOrgId > 0) {
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

        if (empty($sellerIban) && !empty($org['iban'])) {
            $sellerIban = $org['iban'];
            $sellerBank = isset($org['bank_name']) ? $org['bank_name'] : '';
            $sellerMfo  = isset($org['mfo'])       ? $org['mfo']       : '';
        }

        // Line items
        $rItems = Database::fetchAll('Papir',
            "SELECT di.*,
                    COALESCE(NULLIF(pd2.name,''), NULLIF(pd1.name,''), di.product_name) AS resolved_name,
                    COALESCE(NULLIF(di.sku,''), pp.product_article, '') AS resolved_sku,
                    COALESCE(NULLIF(pp.unit,''), 'шт') AS resolved_unit
             FROM demand_item di
             LEFT JOIN product_papir pp ON pp.product_id = di.product_id
             LEFT JOIN product_description pd2 ON pd2.product_id = di.product_id AND pd2.language_id = 2
             LEFT JOIN product_description pd1 ON pd1.product_id = di.product_id AND pd1.language_id = 1
             WHERE di.demand_id = {$demandId}
             ORDER BY di.line_no, di.id"
        );
        $rawItems = ($rItems['ok'] && !empty($rItems['rows'])) ? $rItems['rows'] : array();

        $lines    = array();
        $sumNet   = 0.0;
        $sumVat   = 0.0;
        $sumGross = 0.0;
        $totalQty = 0.0;

        foreach ($rawItems as $i => $it) {
            $lineTotal = (float)$it['sum_row'];
            $lineVat   = 0.0;
            $vatRate   = (float)$it['vat_rate'];
            if ($vatRate > 0) {
                $lineNet = $lineTotal / (1 + $vatRate / 100);
                $lineVat = $lineTotal - $lineNet;
            } else {
                $lineNet = $lineTotal;
            }
            $sumNet   += $lineNet;
            $sumVat   += $lineVat;
            $sumGross += $lineTotal;
            $totalQty += (float)$it['quantity'];

            $lines[] = array(
                'num'          => $i + 1,
                'description'  => !empty($it['resolved_name']) ? $it['resolved_name'] : $it['product_name'],
                'sku'          => isset($it['resolved_sku']) ? $it['resolved_sku'] : '',
                'unit'         => !empty($it['resolved_unit']) ? $it['resolved_unit'] : 'шт',
                'qty'          => self::fmtQty((float)$it['quantity']),
                'price'        => self::fmtMoney((float)$it['price']),
                'discount'     => self::fmtQty((float)$it['discount_percent']),
                'vat_rate'     => $vatRate,
                'vat_amount'   => self::fmtMoney($lineVat),
                'total_no_vat' => self::fmtMoney($lineNet),
                'total'        => self::fmtMoney($lineTotal),
            );
        }

        // Dominant VAT rate
        $vatRate = 0;
        foreach ($rawItems as $it) {
            if ((float)$it['vat_rate'] > 0) {
                $vatRate = (float)$it['vat_rate'];
                break;
            }
        }

        // Document date
        $moment  = !empty($d['moment']) ? $d['moment'] : $d['created_at'];
        $docDate = date('d.m.Y', strtotime($moment));

        // Linked order
        $orderNumber = isset($d['order_number']) ? $d['order_number'] : '';
        $orderMoment = !empty($d['order_moment']) ? $d['order_moment'] : (isset($d['order_created_at']) ? $d['order_created_at'] : '');
        $orderDate   = !empty($orderMoment) ? date('d.m.Y', strtotime($orderMoment)) : '';

        // Buyer address
        $buyerAddress = !empty($d['cp_actual_address']) ? $d['cp_actual_address'] : (string)$d['cp_legal_address'];

        // Location
        $location = isset($org['actual_address']) ? $org['actual_address'] : (isset($org['legal_address']) ? $org['legal_address'] : '');

        return array(
            'invoice' => array(
                'number' => $d['number'],
                'date'   => $docDate,
            ),
            'doc' => array(
                'number'   => $d['number'],
                'date'     => $docDate,
                'currency' => isset($d['order_currency']) ? $d['order_currency'] : 'UAH',
            ),
            'order' => array(
                'number' => $orderNumber,
                'date'   => $orderDate,
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
                'name'    => isset($d['cp_name'])      ? $d['cp_name']      : '',
                'okpo'    => isset($d['cp_okpo'])      ? $d['cp_okpo']      : '',
                'iban'    => isset($d['cp_iban'])      ? $d['cp_iban']      : '',
                'bank'    => isset($d['cp_bank_name']) ? $d['cp_bank_name'] : '',
                'mfo'     => isset($d['cp_mfo'])       ? $d['cp_mfo']       : '',
                'address' => $buyerAddress,
                'phone'   => isset($d['cp_phone'])     ? $d['cp_phone']     : '',
            ),
            'manager' => array(
                'name'  => !empty($d['manager_full_name']) ? $d['manager_full_name'] : '',
                'phone' => !empty($d['manager_phone'])     ? $d['manager_phone']     : '',
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
            'location'       => $location,
            'description'    => isset($d['description']) ? $d['description'] : '',
        );
    }

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