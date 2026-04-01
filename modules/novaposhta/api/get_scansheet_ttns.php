<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$scanSheetRef = isset($_GET['scan_sheet_ref']) ? trim($_GET['scan_sheet_ref']) : '';
$senderRef    = isset($_GET['sender_ref'])     ? trim($_GET['sender_ref'])     : '';

if (!$scanSheetRef) {
    echo json_encode(array('ok' => false, 'error' => 'scan_sheet_ref required'));
    exit;
}

$eRef = \Database::escape('Papir', $scanSheetRef);

// First: try exact match by scan_sheet_ref (works for scan sheets created after the update)
$r = \Database::fetchAll('Papir',
    "SELECT ref, int_doc_number, recipient_contact_person, city_recipient_desc,
            cost, backward_delivery_money, afterpayment_on_goods_cost,
            state_name, state_define
     FROM ttn_novaposhta
     WHERE scan_sheet_ref = '{$eRef}' AND deletion_mark = 0
     ORDER BY int_doc_number");

if ($r['ok'] && !empty($r['rows'])) {
    echo json_encode(array('ok' => true, 'ttns' => $r['rows'], 'source' => 'linked'));
    exit;
}

// Fallback for historical scan sheets: match TTNs by sender + scan sheet date (same day)
if ($senderRef) {
    $ss = \Database::fetchRow('Papir',
        "SELECT DateTime, Count FROM np_scan_sheets WHERE Ref = '{$eRef}' LIMIT 1");
    if ($ss['ok'] && $ss['row'] && $ss['row']['DateTime']) {
        $ssDate  = date('Y-m-d', strtotime($ss['row']['DateTime']));
        $ssCount = (int)$ss['row']['Count'];
        $eSender = \Database::escape('Papir', $senderRef);

        $rFallback = \Database::fetchAll('Papir',
            "SELECT ref, int_doc_number, recipient_contact_person, city_recipient_desc,
                    cost, backward_delivery_money, afterpayment_on_goods_cost,
                    state_name, state_define
             FROM ttn_novaposhta
             WHERE sender_ref = '{$eSender}'
               AND scan_sheet_ref IS NULL
               AND deletion_mark = 0
               AND DATE(moment) = '{$ssDate}'
             ORDER BY int_doc_number");

        if ($rFallback['ok'] && !empty($rFallback['rows'])) {
            echo json_encode(array(
                'ok'      => true,
                'ttns'    => $rFallback['rows'],
                'source'  => 'date_fallback',
                'warning' => 'Відображено ТТН за датою реєстру (приблизно)',
            ));
            exit;
        }
    }
}

echo json_encode(array(
    'ok'      => true,
    'ttns'    => array(),
    'source'  => 'none',
    'warning' => 'Список ТТН недоступний для реєстрів, створених до оновлення системи',
));
