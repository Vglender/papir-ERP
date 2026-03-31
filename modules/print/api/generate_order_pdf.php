<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../PrintContextBuilder.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id']
         : (isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0);

if (!$orderId) {
    echo json_encode(array('ok' => false, 'error' => 'order_id required'));
    exit;
}

// Load order basics (number + moment for path)
$rOrder = Database::fetchRow('Papir',
    "SELECT number, moment, created_at FROM customerorder WHERE id = {$orderId} AND deleted_at IS NULL LIMIT 1"
);
if (!$rOrder['ok'] || empty($rOrder['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Замовлення не знайдено'));
    exit;
}
$orderRow = $rOrder['row'];
$number   = $orderRow['number'];
$moment   = !empty($orderRow['moment']) ? $orderRow['moment'] : $orderRow['created_at'];
$subdir   = date('Y_m', strtotime($moment));   // e.g. 2026_03

// Get active invoice template
$repo = new PrintTemplateRepository();
$templates = $repo->getList(0, 'active');
$tpl = null;
foreach ($templates as $t) {
    if ($t['type_code'] === 'invoice') {
        $tpl = $repo->getById((int)$t['id']);
        break;
    }
}
if (!$tpl) {
    echo json_encode(array('ok' => false, 'error' => 'Активний шаблон рахунку не знайдено'));
    exit;
}

// Build context
$context = PrintContextBuilder::build('order', $orderId, 0);
if (empty($context)) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось зібрати дані замовлення'));
    exit;
}

// Render HTML via Mustache
require_once __DIR__ . '/../../../vendor/autoload.php';
$mustache = new Mustache_Engine();
$html     = $mustache->render($tpl['html_body'], $context);

// Convert web-root image paths to absolute filesystem paths for mPDF
$html = preg_replace_callback(
    '/(<img[^>]+src=")\/storage\/([^"]+)(")/i',
    function ($m) { return $m[1] . '/var/www/papir/storage/' . $m[2] . $m[3]; },
    $html
);

// Ensure output directory exists
$baseDir  = '/var/www/menufold/data/www/officetorg.com.ua/docum/customerorder/';
$dir      = $baseDir . $subdir . '/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Generate safe filename from order number
$safeNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', $number);
$filename   = $safeNumber . '.pdf';
$filePath   = $dir . $filename;

// Render PDF with mPDF
$mpdf = new \Mpdf\Mpdf(array(
    'mode'     => 'utf-8',
    'format'   => 'A4',
    'tempDir'  => '/tmp/mpdf',
    'margin_left'   => 15,
    'margin_right'  => 15,
    'margin_top'    => 15,
    'margin_bottom' => 15,
));
$mpdf->SetTitle('Рахунок ' . $number);
$mpdf->WriteHTML($html);
$mpdf->Output($filePath, 'F');

$url = 'https://officetorg.com.ua/docum/customerorder/' . $subdir . '/' . $filename;

echo json_encode(array(
    'ok'       => true,
    'url'      => $url,
    'filename' => $filename,
    'number'   => $number,
));
