<?php
/**
 * POST /print/api/generate_demand_pdf
 * Generates PDF for a demand using a specific template.
 *
 * Params:
 *   demand_id   — demand.id
 *   template_id — (optional) print_templates.id, defaults to first active waybill template
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../PrintContextBuilder.php';

$demandId   = isset($_GET['demand_id'])   ? (int)$_GET['demand_id']
            : (isset($_POST['demand_id']) ? (int)$_POST['demand_id'] : 0);
$templateId = isset($_GET['template_id'])   ? (int)$_GET['template_id']
            : (isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0);

if (!$demandId) {
    echo json_encode(array('ok' => false, 'error' => 'demand_id required'));
    exit;
}

// Load demand basics
$rDemand = Database::fetchRow('Papir',
    "SELECT number, moment, created_at FROM demand WHERE id = {$demandId} AND deleted_at IS NULL LIMIT 1"
);
if (!$rDemand['ok'] || empty($rDemand['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Відвантаження не знайдено'));
    exit;
}
$demandRow = $rDemand['row'];
$number    = $demandRow['number'];
$moment    = !empty($demandRow['moment']) ? $demandRow['moment'] : $demandRow['created_at'];
$subdir    = date('Y_m', strtotime($moment));

// Get template
$repo = new PrintTemplateRepository();

if ($templateId > 0) {
    $tpl = $repo->getById($templateId);
} else {
    // Default: first active waybill template
    $templates = $repo->getList(0, 'active');
    $tpl = null;
    foreach ($templates as $t) {
        if ($t['type_code'] === 'waybill') {
            $tpl = $repo->getById((int)$t['id']);
            break;
        }
    }
}

if (!$tpl) {
    echo json_encode(array('ok' => false, 'error' => 'Активний шаблон накладної не знайдено'));
    exit;
}

// Build context
$context = PrintContextBuilder::build('demand', $demandId, 0);
if (empty($context)) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось зібрати дані відвантаження'));
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
$baseDir  = '/var/www/menufold/data/www/officetorg.com.ua/docum/demand/';
$dir      = $baseDir . $subdir . '/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Generate safe filename
$safeNumber = preg_replace('/[^A-Za-z0-9_\-]/', '', $number);
$suffix     = $templateId > 0 ? '_t' . $templateId : '';
$filename   = $safeNumber . $suffix . '.pdf';
$filePath   = $dir . $filename;

// Parse page settings
$pageSettings = array(
    'margin_left'   => 15,
    'margin_right'  => 15,
    'margin_top'    => 15,
    'margin_bottom' => 15,
);
if (!empty($tpl['page_settings'])) {
    $ps = json_decode($tpl['page_settings'], true);
    if ($ps) {
        $pageSettings = array_merge($pageSettings, $ps);
    }
}

// Render PDF with mPDF
$mpdf = new \Mpdf\Mpdf(array(
    'mode'          => 'utf-8',
    'format'        => isset($pageSettings['format']) ? $pageSettings['format'] : 'A4',
    'tempDir'       => '/tmp/mpdf',
    'margin_left'   => $pageSettings['margin_left'],
    'margin_right'  => $pageSettings['margin_right'],
    'margin_top'    => $pageSettings['margin_top'],
    'margin_bottom' => $pageSettings['margin_bottom'],
));
$mpdf->SetTitle('Накладна ' . $number);
$mpdf->WriteHTML($html);
$mpdf->Output($filePath, 'F');

$url = 'https://officetorg.com.ua/docum/demand/' . $subdir . '/' . $filename;

echo json_encode(array(
    'ok'            => true,
    'url'           => $url,
    'filename'      => $filename,
    'number'        => $number,
    'template_name' => $tpl['name'],
));
