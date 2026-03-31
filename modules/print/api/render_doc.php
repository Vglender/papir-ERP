<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';
require_once __DIR__ . '/../PrintContextBuilder.php';

$repo = new PrintTemplateRepository();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
$entityType = isset($_POST['entity_type']) ? trim((string)$_POST['entity_type']) : '';
$entityId   = isset($_POST['entity_id'])   ? (int)$_POST['entity_id']           : 0;
$orgId      = isset($_POST['org_id'])      ? (int)$_POST['org_id']              : 0;

if (!$templateId || !$entityType || !$entityId) {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть template_id, entity_type та entity_id'));
    exit;
}

$tpl = $repo->getById($templateId);
if (!$tpl) {
    echo json_encode(array('ok' => false, 'error' => 'Шаблон не знайдено'));
    exit;
}

$context = PrintContextBuilder::build($entityType, $entityId, $orgId);
if (empty($context)) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось зібрати дані для документа'));
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
$mustache = new Mustache_Engine();
$html     = $mustache->render($tpl['html_body'], $context);

echo json_encode(array(
    'ok'            => true,
    'html'          => $html,
    'template_name' => $tpl['name'],
    'type_name'     => $tpl['type_name'],
));