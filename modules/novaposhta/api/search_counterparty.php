<?php
/**
 * GET /novaposhta/api/search_counterparty?mode=person|org&q=...
 *
 * Lightweight live-search for the NP TTN form.
 *   mode=person → search counterparty_person by phone digits (last 7+)
 *   mode=org    → search counterparty_company by EDRPOU (okpo)
 *
 * Returns items with the exact fields the form needs to autofill:
 *   { counterparty_id, type, first_name, last_name, middle_name,
 *     full_name, phone, edrpou, contact_person }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$mode = isset($_GET['mode']) ? trim($_GET['mode']) : '';
$q    = isset($_GET['q'])    ? trim($_GET['q'])    : '';

if ($mode !== 'person' && $mode !== 'org') {
    echo json_encode(array('ok' => false, 'error' => 'mode must be person or org'));
    exit;
}
if (mb_strlen($q, 'UTF-8') < 3) {
    echo json_encode(array('ok' => true, 'items' => array()));
    exit;
}

$items = array();

if ($mode === 'person') {
    // Phone search: keep only digits, match last N digits of stored phone
    $digits = preg_replace('/\D/', '', $q);
    if (strlen($digits) < 3) {
        echo json_encode(array('ok' => true, 'items' => array()));
        exit;
    }
    $digitsEsc = \Database::escape('Papir', $digits);

    $r = \Database::fetchAll('Papir',
        "SELECT c.id AS counterparty_id,
                cp.first_name, cp.last_name, cp.middle_name,
                cp.phone, cp.phone_alt,
                c.name
         FROM counterparty c
         JOIN counterparty_person cp ON cp.counterparty_id = c.id
         WHERE c.status = 1 AND c.type = 'person'
           AND (
                REGEXP_REPLACE(COALESCE(cp.phone,''),     '[^0-9]', '') LIKE '%{$digitsEsc}%'
             OR REGEXP_REPLACE(COALESCE(cp.phone_alt,''), '[^0-9]', '') LIKE '%{$digitsEsc}%'
           )
         ORDER BY c.name ASC
         LIMIT 15");

    if ($r['ok']) {
        foreach ($r['rows'] as $row) {
            $items[] = array(
                'counterparty_id' => (int)$row['counterparty_id'],
                'type'            => 'PrivatePerson',
                'first_name'      => $row['first_name']  ?: '',
                'last_name'       => $row['last_name']   ?: '',
                'middle_name'     => $row['middle_name'] ?: '',
                'full_name'       => trim(($row['last_name'] ?: '') . ' ' . ($row['first_name'] ?: '') . ' ' . ($row['middle_name'] ?: '')) ?: $row['name'],
                'phone'           => $row['phone'] ?: $row['phone_alt'] ?: '',
                'edrpou'          => '',
                'contact_person'  => '',
            );
        }
    }
} else {
    // Org search: EDRPOU (okpo) prefix / contains match
    $digits = preg_replace('/\D/', '', $q);
    if (strlen($digits) < 3) {
        echo json_encode(array('ok' => true, 'items' => array()));
        exit;
    }
    $digitsEsc = \Database::escape('Papir', $digits);

    $r = \Database::fetchAll('Papir',
        "SELECT c.id AS counterparty_id, c.type,
                cc.okpo, cc.short_name, cc.phone AS company_phone, c.name
         FROM counterparty c
         JOIN counterparty_company cc ON cc.counterparty_id = c.id
         WHERE c.status = 1
           AND c.type IN ('company','fop')
           AND cc.okpo LIKE '{$digitsEsc}%'
         ORDER BY cc.okpo ASC
         LIMIT 15");

    if ($r['ok']) {
        foreach ($r['rows'] as $row) {
            $items[] = array(
                'counterparty_id' => (int)$row['counterparty_id'],
                'type'            => 'Organization',
                'first_name'      => '',
                'last_name'       => '',
                'middle_name'     => '',
                'full_name'       => $row['short_name'] ?: $row['name'],
                'phone'           => $row['company_phone'] ?: '',
                'edrpou'          => $row['okpo'] ?: '',
                'contact_person'  => '',
            );
        }
    }
}

echo json_encode(array('ok' => true, 'items' => $items));