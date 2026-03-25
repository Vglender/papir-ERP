<?php

require_once __DIR__ . '/../prices_bootstrap.php';
require_once __DIR__ . '/../../moysklad/moysklad_api.php';

header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
$limit  = isset($_POST['limit'])  ? (int)$_POST['limit']  : 50;
// phase: 'sites' — push to off+mff only; 'ms' — push to MoySklad only
$phase  = isset($_POST['phase'])  ? $_POST['phase'] : 'sites';
$limit  = max(1, min(100, $limit));

// Count total active products with a sale price
$countResult = Database::fetchRow('Papir',
    "SELECT COUNT(*) AS cnt FROM product_papir WHERE status = 1 AND price_sale > 0"
);
$total = ($countResult['ok'] && !empty($countResult['row'])) ? (int)$countResult['row']['cnt'] : 0;

// Load batch with discount profile
$batchResult = Database::fetchAll('Papir',
    "SELECT
        p.product_id,
        p.product_article,
        p.id_off,
        p.id_mf,
        p.id_ms,
        p.price_purchase,
        p.price_sale,
        p.price_wholesale,
        p.price_dealer,
        p.quantity,
        IF(ps_off.seo_url != '' AND ps_off.seo_url IS NOT NULL, CONCAT('https://officetorg.com.ua/', ps_off.seo_url), '') AS link_off,
        IF(ps_mff.seo_url != '' AND ps_mff.seo_url IS NOT NULL, CONCAT('https://menufolder.com.ua/', ps_mff.seo_url), '') AS links_mf,
        p.links_prom,
        dp.qty_1,
        dp.price_1,
        dp.qty_2,
        dp.price_2,
        dp.qty_3,
        dp.price_3
     FROM product_papir p
     LEFT JOIN product_discount_profile dp ON dp.product_id = p.product_id
     LEFT JOIN product_seo ps_off ON ps_off.product_id = p.product_id AND ps_off.site_id = 1 AND ps_off.language_id = 1
     LEFT JOIN product_seo ps_mff ON ps_mff.product_id = p.product_id AND ps_mff.site_id = 2 AND ps_mff.language_id = 1
     WHERE p.status = 1
       AND p.price_sale > 0
     ORDER BY p.product_id ASC
     LIMIT " . (int)$limit . " OFFSET " . (int)$offset
);
$rows = ($batchResult['ok'] && !empty($batchResult['rows'])) ? $batchResult['rows'] : array();

if (empty($rows)) {
    echo json_encode(array(
        'ok'          => true,
        'has_errors'  => false,
        'processed'   => 0,
        'errors'      => array(),
        'total'       => $total,
        'next_offset' => null,
        'stats'       => array('pushed' => 0, 'skipped' => 0),
    ));
    exit;
}

$allErrors = array();
$pushed    = 0;
$skipped   = 0;

if ($phase === 'sites') {
    // ── Phase 1: off → если ошибки, mff не трогаем ──
    $exporter  = new OpenCartPriceExport();
    $resultOff = $exporter->pushBatch('off', $rows, 'id_off');
    foreach ($resultOff['errors'] as $e) {
        $allErrors[] = '[offtorg] ' . $e;
    }
    if (empty($allErrors)) {
        $resultMff = $exporter->pushBatch('mff', $rows, 'id_mf');
        foreach ($resultMff['errors'] as $e) {
            $allErrors[] = '[mff] ' . $e;
        }
    }
    $pushed  = $resultOff['pushed'];
    $skipped = $resultOff['skipped'];

} elseif ($phase === 'ms') {
    // ── Phase 2: MoySklad API (rate-limited, ~15 req/s) ──
    $exporter = new MoySkladPriceExport(new MoySkladApi());
    $resultMs = $exporter->pushBatch($rows);
    foreach ($resultMs['errors'] as $e) {
        $allErrors[] = '[МС] ' . $e;
    }
    $pushed  = $resultMs['pushed'];
    $skipped = $resultMs['skipped'];
}

$processed  = count($rows);
$nextOffset = $offset + $processed;
if ($nextOffset >= $total) {
    $nextOffset = null;
}

echo json_encode(array(
    'ok'          => true,
    'has_errors'  => !empty($allErrors),
    'processed'   => $processed,
    'errors'      => $allErrors,
    'total'       => $total,
    'next_offset' => $nextOffset,
    'stats'       => array('pushed' => $pushed, 'skipped' => $skipped),
));
