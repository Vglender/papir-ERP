<?php

require_once __DIR__ . '/../prices_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$saleMarkup       = isset($_POST['sale_markup_percent'])      ? trim($_POST['sale_markup_percent'])      : '';
$wholesaleMarkup  = isset($_POST['wholesale_markup_percent']) ? trim($_POST['wholesale_markup_percent']) : '';
$dealerMarkup     = isset($_POST['dealer_markup_percent'])    ? trim($_POST['dealer_markup_percent'])    : '';
$discountStrategy = isset($_POST['discount_strategy_id'])     ? trim($_POST['discount_strategy_id'])     : '';
$quantityStrategy = isset($_POST['quantity_strategy_id'])     ? trim($_POST['quantity_strategy_id'])     : '';
$useTiered        = isset($_POST['use_tiered_markup'])        ? (int)$_POST['use_tiered_markup']         : 0;

if ($wholesaleMarkup === '' || $dealerMarkup === '') {
    echo json_encode(array('ok' => false, 'error' => 'Wholesale and dealer markup are required'));
    exit;
}

$data = array(
    'wholesale_markup_percent' => (float)$wholesaleMarkup,
    'dealer_markup_percent'    => (float)$dealerMarkup,
    'discount_strategy_id'     => $discountStrategy !== '' ? (int)$discountStrategy : null,
    'quantity_strategy_id'     => $quantityStrategy !== '' ? (int)$quantityStrategy : null,
    'use_tiered_markup'        => $useTiered ? 1 : 0,
);

// Простая наценка — только если не ступенчатая
if (!$useTiered && $saleMarkup !== '' && is_numeric($saleMarkup)) {
    $data['sale_markup_percent'] = (float)$saleMarkup;
}

$repo   = new GlobalSettingsRepository();
$result = $repo->save($data);

if (!$result['ok']) {
    $errMsg = isset($result['error']) ? $result['error'] : 'Save failed';
    echo json_encode(array('ok' => false, 'error' => $errMsg));
    exit;
}

// Сохраняем тиры
$tiers = array();
for ($i = 1; $i <= 5; $i++) {
    $fromKey = 'tier_from_' . $i;
    $pctKey  = 'tier_pct_' . $i;
    $from    = isset($_POST[$fromKey]) ? trim($_POST[$fromKey]) : '';
    $pct     = isset($_POST[$pctKey])  ? trim($_POST[$pctKey])  : '';
    if ($pct !== '' && is_numeric($pct) && (float)$pct > 0) {
        $tiers[] = array(
            'price_from'     => ($from !== '' && is_numeric($from)) ? (float)$from : 0,
            'markup_percent' => (float)$pct,
        );
    }
}

$repo->saveTiers($tiers);

echo json_encode(array('ok' => true));
