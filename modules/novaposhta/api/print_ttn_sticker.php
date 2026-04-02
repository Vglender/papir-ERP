<?php
/**
 * GET /novaposhta/api/print_ttn_sticker?ttn_id=X&format=100x100|a4_6
 *
 * Будує прямий URL my.novaposhta.ua і редіректить браузер.
 * Формат: /orders/printMarking100x100/orders/{int_doc_number}/type/pdf/apiKey/{key}
 */
require_once __DIR__ . '/../novaposhta_bootstrap.php';

$ttnId  = isset($_GET['ttn_id']) ? (int)$_GET['ttn_id'] : 0;
$format = isset($_GET['format']) ? trim($_GET['format']) : '100x100';

function npStickerError($msg) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Помилка</title>'
        . '<style>body{font-family:sans-serif;padding:32px;color:#374151}h2{color:#dc2626}p{margin-top:8px}</style></head><body>'
        . '<h2>Помилка друку наклейки</h2><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><button onclick="window.close()">Закрити</button></p>'
        . '</body></html>';
    exit;
}

if ($ttnId <= 0) npStickerError('ttn_id не вказано');

$ttn = \Papir\Crm\TtnRepository::getById($ttnId);
if (!$ttn)                  npStickerError('ТТН не знайдено');
if (!$ttn['sender_api'])    npStickerError('API ключ відправника не знайдено');
if (!$ttn['int_doc_number']) npStickerError('ТТН не має номера (int_doc_number). Можливо, ще не отримала номер від НП.');

// Два різних URL-формати залежно від формату друку:
//   100x100 → /orders/{int_doc_number}/type/pdf
//   85x85   → /orders[]/{ref}/type/pdf8   (6 наклейок на A4)
if ($format === 'a4_6') {
    // 85×85 мм, 6 на аркуші A4. Використовує Ref (UUID) і тип pdf8.
    // Дужки [] у шляху — літеральні, не кодуємо.
    $url = 'https://my.novaposhta.ua/orders/printMarking85x85'
         . '/orders[]/' . $ttn['ref']
         . '/type/pdf8'
         . '/apiKey/' . $ttn['sender_api'];
} else {
    // 100×100 мм термо. Використовує номер ЕН і тип pdf.
    $url = 'https://my.novaposhta.ua/orders/printMarking100x100'
         . '/orders/' . urlencode($ttn['int_doc_number'])
         . '/type/pdf'
         . '/apiKey/' . $ttn['sender_api'];
}

header('Location: ' . $url);