<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../openai_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$entityType  = isset($_POST['entity_type'])  ? trim($_POST['entity_type'])  : '';
$entityId    = isset($_POST['entity_id'])    ? (int)$_POST['entity_id']     : 0;
$siteId      = isset($_POST['site_id'])      ? (int)$_POST['site_id']       : 0;
$languageId  = isset($_POST['language_id'])  ? (int)$_POST['language_id']   : 2;
$useCase     = isset($_POST['use_case'])     ? trim($_POST['use_case'])      : 'content';
$customNote  = isset($_POST['custom_note'])  ? trim($_POST['custom_note'])   : '';
$returnPrompt = !empty($_POST['return_prompt']);
$previewOnly  = !empty($_POST['preview_only']);

$allowedTypes = array('product', 'category');
$allowedUc    = array('content', 'seo');

if (!in_array($entityType, $allowedTypes) || $entityId <= 0 || $siteId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'entity_type, entity_id, site_id required'));
    exit;
}
if (!in_array($useCase, $allowedUc)) {
    $useCase = 'content';
}

// Назва мови для промту
$langR = Database::fetchRow('Papir', "SELECT name FROM languages WHERE language_id = {$languageId}");
$languageName = ($langR['ok'] && !empty($langR['row'])) ? (string)$langR['row']['name'] : 'Українська';

// Визначаємо category_id (для хлібних крихт та інструкції категорії)
$categoryId = 0;
if ($entityType === 'category') {
    $categoryId = $entityId;
} else {
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    if ($categoryId === 0) {
        // Автовизначення категорії через сайт
        $psR = Database::fetchRow('Papir',
            "SELECT site_product_id FROM product_site WHERE product_id = {$entityId} AND site_id = {$siteId}"
        );
        if ($psR['ok'] && !empty($psR['row'])) {
            $siteProdId = (int)$psR['row']['site_product_id'];
            $siteR = Database::fetchRow('Papir', "SELECT db_alias FROM sites WHERE site_id = {$siteId}");
            if ($siteR['ok'] && !empty($siteR['row'])) {
                $dbAlias = (string)$siteR['row']['db_alias'];
                $catR = Database::fetchRow($dbAlias,
                    "SELECT category_id FROM oc_product_to_category WHERE product_id = {$siteProdId} LIMIT 1"
                );
                if ($catR['ok'] && !empty($catR['row'])) {
                    $ocCatId = (int)$catR['row']['category_id'];
                    $mapR = Database::fetchRow('Papir',
                        "SELECT category_id FROM category_site_mapping WHERE site_id = {$siteId} AND site_category_id = {$ocCatId}"
                    );
                    if ($mapR['ok'] && !empty($mapR['row'])) {
                        $categoryId = (int)$mapR['row']['category_id'];
                    }
                }
            }
        }
    }
}

// Збираємо system prompt
$systemPrompt = \Papir\Crm\AiPromptBuilder::buildSystemPrompt(array(
    'site_id'       => $siteId,
    'entity_type'   => $entityType,
    'category_id'   => $categoryId,
    'product_id'    => ($entityType === 'product') ? $entityId : 0,
    'use_case'      => $useCase,
    'language_name' => $languageName,
));

// Збираємо user prompt
if ($entityType === 'product') {
    $userPrompt = \Papir\Crm\AiPromptBuilder::buildProductUserPrompt(array(
        'product_id'  => $entityId,
        'site_id'     => $siteId,
        'language_id' => $languageId,
        'category_id' => $categoryId,
        'custom_note' => $customNote,
    ));
} else {
    $userPrompt = \Papir\Crm\AiPromptBuilder::buildCategoryUserPrompt(array(
        'category_id' => $categoryId,
        'site_id'     => $siteId,
        'language_id' => $languageId,
        'custom_note' => $customNote,
    ));
}

if (trim($userPrompt) === '') {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалось зібрати дані сутності'));
    exit;
}

// Режим попереднього перегляду — повертаємо промт без виклику API
if ($previewOnly) {
    echo json_encode(array(
        'ok'            => true,
        'system_prompt' => $systemPrompt,
        'user_prompt'   => $userPrompt,
        'fields'        => array(),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

// Налаштування моделі
$modelSettings = \Papir\Crm\AiPromptBuilder::getSiteModelSettings($siteId, $useCase);

// Виклик OpenAI
$ai     = openai_client();
$result = $ai->chatWithSystem(
    $systemPrompt,
    $userPrompt,
    $modelSettings['model'],
    $modelSettings['max_tokens'],
    $modelSettings['temperature']
);

$status      = $result['ok'] ? 'generated' : 'rejected';
$responseRaw = $result['ok'] ? $result['text'] : (isset($result['error']) ? $result['error'] : '');
$resultJson  = '';
$parsed      = array();

if ($result['ok']) {
    $text = trim($result['text']);
    // Видаляємо markdown code block якщо є
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/', '',          $text);
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        $parsed     = $decoded;
        $resultJson = json_encode($parsed, JSON_UNESCAPED_UNICODE);
    }
}

// Зберігаємо в лог
$fullPrompt = "=== SYSTEM ===\n\n" . $systemPrompt . "\n\n=== USER ===\n\n" . $userPrompt;
Database::insert('Papir', 'ai_generation_log', array(
    'use_case'     => $useCase,
    'entity_type'  => $entityType,
    'entity_id'    => $entityId,
    'site_id'      => $siteId,
    'language_id'  => $languageId,
    'prompt'       => $fullPrompt,
    'response_raw' => $responseRaw,
    'result_json'  => $resultJson,
    'status'       => $status,
    'tokens_used'  => 0,
));

$logIdR = Database::fetchRow('Papir', 'SELECT LAST_INSERT_ID() AS id');
$logId  = ($logIdR['ok'] && !empty($logIdR['row'])) ? (int)$logIdR['row']['id'] : 0;

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => $result['error']));
    exit;
}

$response = array(
    'ok'     => true,
    'log_id' => $logId,
    'fields' => $parsed,
);

if ($returnPrompt) {
    $response['system_prompt'] = $systemPrompt;
    $response['user_prompt']   = $userPrompt;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
