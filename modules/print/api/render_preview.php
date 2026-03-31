<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$htmlBody   = isset($_POST['html_body'])   ? $_POST['html_body']   : '';
$orgId      = isset($_POST['org_id'])      ? (int)$_POST['org_id'] : 0;
$testValRaw = isset($_POST['test_values']) ? $_POST['test_values'] : '';

// Parse custom test values from editor panel
$testValues = array();
if ($testValRaw) {
    $parsed = json_decode($testValRaw, true);
    if (is_array($parsed)) {
        $testValues = $parsed;
    }
}

// Fetch org data if given
$orgData = array();
if ($orgId > 0) {
    $orgRepo  = new OrganizationRepository();
    $orgRow   = $orgRepo->getById($orgId);
    if ($orgRow) {
        $accounts = !empty($orgRow['bank_accounts']) ? $orgRow['bank_accounts'] : array();
        $defAcc   = array();
        foreach ($accounts as $acc) {
            if ($acc['is_default'] || empty($defAcc)) { $defAcc = $acc; }
        }
        $orgData = array(
            'name'           => isset($orgRow['name'])           ? $orgRow['name']           : '',
            'short_name'     => isset($orgRow['short_name'])     ? $orgRow['short_name']     : '',
            'okpo'           => isset($orgRow['okpo'])           ? $orgRow['okpo']           : '',
            'inn'            => isset($orgRow['inn'])            ? $orgRow['inn']            : '',
            'vat_number'     => isset($orgRow['vat_number'])     ? $orgRow['vat_number']     : '',
            'address'        => isset($orgRow['legal_address'])  ? $orgRow['legal_address']  : '',
            'phone'          => isset($orgRow['phone'])          ? $orgRow['phone']          : '',
            'email'          => isset($orgRow['email'])          ? $orgRow['email']          : '',
            'director_name'  => isset($orgRow['director_name'])  ? $orgRow['director_name']  : '',
            'director_title' => isset($orgRow['director_title']) ? $orgRow['director_title'] : 'Директор',
            'iban'           => isset($defAcc['iban'])           ? $defAcc['iban']           : '',
            'bank_name'      => isset($defAcc['bank_name'])      ? $defAcc['bank_name']      : '',
            'mfo'            => isset($defAcc['mfo'])            ? $defAcc['mfo']            : '',
        );
    }
}

// ── Base sample context ───────────────────────────────────────────────────
$baseContext = array(
    'invoice' => array(
        'number' => 'ТОВ-РАХ-2026-0001',
        'date'   => date('d.m.Y'),
    ),
    'seller' => !empty($orgData) ? $orgData : array(
        'name'           => 'ТОВ "Назва організації"',
        'short_name'     => 'Назва',
        'okpo'           => '12345678',
        'vat_number'     => '123456789',
        'address'        => 'м. Київ, вул. Прикладна, 1',
        'phone'          => '+380 44 000-00-00',
        'iban'           => 'UA123456789012345678901234567',
        'bank_name'      => 'АТ "Приватбанк"',
        'mfo'            => '305299',
        'director_name'  => 'Іванов Іван Іванович',
        'director_title' => 'Директор',
    ),
    'buyer' => array(
        'name'    => 'ТОВ "Покупець"',
        'okpo'    => '87654321',
        'address' => 'м. Харків, вул. Тестова, 5',
        'phone'   => '+380 57 000-00-00',
        'iban'    => 'UA987654321098765432109876543',
    ),
    'lines' => array(
        array('num'=>1,'code'=>'ART-001','description'=>'Товар 1 — назва позиції','unit'=>'шт','qty'=>5, 'price'=>'250,00','total'=>'1 250,00'),
        array('num'=>2,'code'=>'ART-002','description'=>'Товар 2 — інша позиція', 'unit'=>'шт','qty'=>10,'price'=>'180,00','total'=>'1 800,00'),
        array('num'=>3,'code'=>'ART-003','description'=>'Послуга — доставка',     'unit'=>'пос','qty'=>1,'price'=>'300,00','total'=>'300,00'),
    ),
    'total'          => '3 350,00',
    'total_text'     => 'Три тисячі триста п\'ятдесят гривень 00 коп.',
    'vat_rate'       => '20',
    'vat_amount'     => '558,33',
    'total_with_vat' => '3 350,00',
    'doc_date'       => date('d.m.Y'),
    'doc_year'       => date('Y'),
);

// ── Merge test values (flat and nested) ───────────────────────────────────
// testValues can contain dot-notation keys ("seller.name") or nested arrays
function mergeDeep($base, $override)
{
    if (!is_array($override)) { return $base; }
    foreach ($override as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = mergeDeep($base[$k], $v);
        } elseif ($v !== '' && $v !== null) {
            $base[$k] = $v;
        }
    }
    return $base;
}

$context = mergeDeep($baseContext, $testValues);

// ── Render ────────────────────────────────────────────────────────────────
try {
    $mustache = new Mustache_Engine(array(
        'escape' => function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); },
    ));
    $rendered = $mustache->render($htmlBody, $context);
    echo json_encode(array('ok' => true, 'html' => $rendered));
} catch (Exception $e) {
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
}