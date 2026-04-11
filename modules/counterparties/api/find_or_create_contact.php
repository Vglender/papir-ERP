<?php
/**
 * find_or_create_contact — locate a counterparty by phone or email, or create
 * a minimal "person" counterparty if not found. Used by the Quick Message
 * composer to address arbitrary phone numbers / email addresses without
 * requiring the operator to manually create a card first.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$name  = isset($_POST['name'])  ? trim($_POST['name'])  : '';

if ($phone === '' && $email === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть телефон або email'));
    exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний формат email'));
    exit;
}

$chatRepo = new ChatRepository();
$cpRepo   = new CounterpartyRepository();

// ── Try to find existing counterparty ────────────────────────────────────────
$cpId = 0;
if ($phone !== '') {
    $cpId = $chatRepo->findCounterpartyByPhone($phone);
}
if (!$cpId && $email !== '') {
    $cpId = $chatRepo->findCounterpartyByEmail($email);
}

if ($cpId > 0) {
    echo json_encode(array('ok' => true, 'id' => $cpId, 'created' => false));
    exit;
}

// ── Create minimal person-type counterparty ──────────────────────────────────
if ($name === '') {
    $name = $phone !== '' ? $phone : $email;
}

$normPhone = $phone !== '' ? AlphaSmsService::normalizePhoneLoose($phone) : '';

$newId = $cpRepo->create(array(
    'type'  => 'person',
    'name'  => $name,
    'phone' => $normPhone,
    'email' => $email,
));

if (!$newId) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалося створити контрагента'));
    exit;
}

echo json_encode(array('ok' => true, 'id' => $newId, 'created' => true));