<?php
/**
 * OrderOrgResolver — визначає дефолтну організацію для нового замовлення
 * на основі VAT-статусу клієнта.
 *
 * Правила (в порядку пріоритету):
 *  1. Якщо контрагент існує в БД — читаємо counterparty_company.company_type:
 *     'company' → платник ПДВ, 'fop' → неплатник. Якщо counterparty_company
 *     відсутній (фізособа) — неплатник.
 *  2. Інакше: якщо передано EDRPOU довжиною 8 цифр → платник (юрособа).
 *     Довжина 10 → ІПН фізособи / ФОП → неплатник.
 *  3. Інакше: парсимо опис замовлення на явний маркер ЄДРПОУ/ЭДРПОУ/ЕДРПОУ
 *     + 8 цифр. Знайшли — платник.
 *  4. Fallback → неплатник.
 *
 * Дефолтні organization_id для обох сценаріїв читаються з integration_settings
 * (app_key = 'order_import', ключі default_org_vat / default_org_novat).
 */

require_once __DIR__ . '/../../integrations/IntegrationSettingsService.php';

class OrderOrgResolver
{
    /**
     * @param int|null $counterpartyId
     * @param string   $edrpou          Raw EDRPOU/OKPO from incoming data (may be empty)
     * @param string   $description     Order description / comment (parsed for fallback)
     * @return array ['organization_id' => int|null, 'is_vat' => bool, 'vat_rate' => 0|20]
     */
    public static function resolve($counterpartyId, $edrpou = '', $description = '')
    {
        $isVat = self::isVatPayer($counterpartyId, $edrpou, $description);

        $key   = $isVat ? 'default_org_vat' : 'default_org_novat';
        $defId = $isVat ? '8' : '6'; // legacy fallback: 8=Архкор, 6=Чумаченко
        $orgId = (int) IntegrationSettingsService::get('order_import', $key, $defId);

        return array(
            'organization_id' => $orgId > 0 ? $orgId : null,
            'is_vat'          => $isVat,
            'vat_rate'        => $isVat ? 20 : 0,
        );
    }

    /**
     * Centralized VAT decision logic — shared with consumers that only need
     * the flag without resolving the organization.
     */
    public static function isVatPayer($counterpartyId, $edrpou = '', $description = '')
    {
        // 1) Existing counterparty
        $counterpartyId = (int) $counterpartyId;
        if ($counterpartyId > 0) {
            $r = Database::fetchRow('Papir',
                "SELECT cc.company_type
                   FROM counterparty c
              LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
                  WHERE c.id = {$counterpartyId}
                  LIMIT 1");
            if ($r['ok'] && !empty($r['row'])) {
                $type = isset($r['row']['company_type']) ? (string)$r['row']['company_type'] : '';
                if ($type === 'company') return true;
                if ($type === 'fop')     return false;
                // No counterparty_company row → physical person
                return false;
            }
        }

        // 2) Explicit EDRPOU/OKPO passed in
        $edrpou = preg_replace('/\D+/', '', (string)$edrpou);
        if ($edrpou !== '') {
            $len = strlen($edrpou);
            if ($len === 8) return true;   // ЄДРПОУ юрособи
            if ($len === 10) return false; // ІПН фізособи/ФОП
        }

        // 3) Parse description for explicit marker + 8 digits
        $desc = (string)$description;
        if ($desc !== '' && preg_match('/(ЄДРПОУ|ЭДРПОУ|ЕДРПОУ|EDRPOU)\D{0,5}(\d{8})\b/ui', $desc)) {
            return true;
        }

        // 4) Default: non-VAT
        return false;
    }
}