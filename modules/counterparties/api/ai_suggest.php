<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';
require_once __DIR__ . '/../../openai/openai_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$counterpartyId = isset($_POST['id'])      ? (int)$_POST['id']      : 0;
$channel        = isset($_POST['channel']) ? trim($_POST['channel']) : 'viber';

if ($counterpartyId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id required'));
    exit;
}

// Load counterparty
$repo = new CounterpartyRepository();
$cp   = $repo->getById($counterpartyId);
if (!$cp) {
    echo json_encode(array('ok' => false, 'error' => 'Контрагента не знайдено'));
    exit;
}

// Load last messages for context
$chatRepo = new ChatRepository();
$messages = $chatRepo->getMessages($counterpartyId, $channel, 20);

// Load recent orders
$ordersR = Database::fetchAll('Papir',
    "SELECT co.number, co.status, co.sum_total, co.moment
     FROM customerorder co
     WHERE co.counterparty_id = {$counterpartyId}
     ORDER BY co.moment DESC LIMIT 5"
);
$orders = ($ordersR['ok'] && !empty($ordersR['rows'])) ? $ordersR['rows'] : array();

// Load AI instruction for chat use_case (entity_type=site, entity_id=1 = global)
$instrR = Database::fetchRow('Papir',
    "SELECT instruction, context FROM ai_instructions
     WHERE entity_type='site' AND use_case='chat'
     ORDER BY entity_id ASC LIMIT 1"
);
$instrRow    = ($instrR['ok'] && !empty($instrR['row'])) ? $instrR['row'] : array();
$sysInstruct = isset($instrRow['instruction']) ? trim((string)$instrRow['instruction']) : '';
$ctx         = array();
if (!empty($instrRow['context'])) {
    $d = json_decode($instrRow['context'], true);
    if (is_array($d)) { $ctx = $d; }
}
$model      = isset($ctx['model'])       ? $ctx['model']              : 'gpt-4o-mini';
$temp       = isset($ctx['temperature']) ? (float)$ctx['temperature'] : 0.7;
$maxTokens  = isset($ctx['max_tokens'])  ? (int)$ctx['max_tokens']    : 400;

// Build system prompt
if (!$sysInstruct) {
    $sysInstruct = 'Ти — AI-асистент менеджера компанії Papir. Допомагай формулювати відповіді клієнтам — коротко, ввічливо, по суті.';
}

// Build context block
$ctxBlock = '';

// Counterparty info
$cpName = trim((string)$cp['name']);
$ctxBlock .= "Контрагент: {$cpName}";
$phone = $cp['company_phone'] ? $cp['company_phone'] : $cp['person_phone'];
if ($phone) { $ctxBlock .= " (тел: {$phone})"; }
$ctxBlock .= "\n";

// Orders
if (!empty($orders)) {
    $ctxBlock .= "Останні замовлення:\n";
    foreach ($orders as $ord) {
        $sum   = number_format((float)$ord['sum_total'], 0, '.', ' ');
        $date  = substr((string)$ord['moment'], 0, 10);
        $ctxBlock .= "  - #{$ord['number']} ({$ord['status']}) ₴{$sum} від {$date}\n";
    }
}

// Recent conversation
if (!empty($messages)) {
    $ctxBlock .= "\nІсторія діалогу (канал: {$channel}):\n";
    $last = array_slice($messages, -10);
    foreach ($last as $m) {
        $who  = ($m['direction'] === 'in') ? 'Клієнт' : 'Менеджер';
        $body = mb_substr(trim((string)$m['body']), 0, 300, 'UTF-8');
        $ctxBlock .= "{$who}: {$body}\n";
    }
}

$userMessage  = $ctxBlock . "\nСформулюй наступну відповідь менеджера клієнту (лише текст відповіді, без пояснень).";

// Call OpenAI
$ai     = openai_client();
$result = $ai->chatWithSystem($sysInstruct, $userMessage, $model, $maxTokens, $temp);

if (!$result['ok']) {
    echo json_encode(array('ok' => false, 'error' => $result['error']));
    exit;
}

echo json_encode(array('ok' => true, 'text' => $result['text']));
