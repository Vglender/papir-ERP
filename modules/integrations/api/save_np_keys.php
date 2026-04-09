<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/../../auth/AuthService.php';

$user = \Papir\Crm\AuthService::getCurrentUser();
if (!$user || empty($user['is_admin'])) {
    echo json_encode(array('ok' => false, 'error' => 'Доступ заборонено'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['senders']) || !is_array($input['senders'])) {
    echo json_encode(array('ok' => false, 'error' => 'Невірні дані'));
    exit;
}

$db = Database::connection('Papir');

foreach ($input['senders'] as $item) {
    $ref = isset($item['ref']) ? trim($item['ref']) : '';
    $key = isset($item['api_key']) ? trim($item['api_key']) : '';
    if ($ref === '') continue;

    $eRef = $db->real_escape_string($ref);
    $eKey = $db->real_escape_string($key);
    $db->query("UPDATE np_sender SET api = '{$eKey}' WHERE Ref = '{$eRef}'");
}

echo json_encode(array('ok' => true));