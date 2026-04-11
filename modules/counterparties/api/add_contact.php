<?php
/**
 * POST /counterparties/api/add_contact
 *
 * Атомарно створює нову контактну особу (counterparty type=person)
 * та зв'язок counterparty_relation з юрособою-батьком.
 *
 * Поля POST:
 *   parent_id     — ID юрособи (counterparty.id, type ∈ {company, fop})  [required]
 *   last_name     — Прізвище   [required]
 *   first_name    — Імʼя
 *   middle_name   — По батькові
 *   phone         — Телефон
 *   email         — Email
 *   position      — Посада (для counterparty_person.position_name та job_title relation)
 *
 * Відповідь: { ok, contact: { id, name, phone, position }, relation_id }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
if ($parentId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'parent_id обовʼязковий'));
    exit;
}

$lastName  = isset($_POST['last_name'])   ? trim($_POST['last_name'])   : '';
$firstName = isset($_POST['first_name'])  ? trim($_POST['first_name'])  : '';
$midName   = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
if ($lastName === '' && $firstName === '') {
    echo json_encode(array('ok' => false, 'error' => 'Вкажіть прізвище або імʼя'));
    exit;
}

$phone    = isset($_POST['phone'])    ? $_POST['phone']    : '';
$email    = isset($_POST['email'])    ? trim($_POST['email']) : '';
$position = isset($_POST['position']) ? trim($_POST['position']) : '';

// Перевірити що батько існує і є юрособою
$rParent = Database::fetchRow('Papir',
    "SELECT id, type FROM counterparty WHERE id = {$parentId} LIMIT 1");
if (!$rParent['ok'] || empty($rParent['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Юрособу не знайдено'));
    exit;
}
if (!in_array($rParent['row']['type'], array('company', 'fop'))) {
    echo json_encode(array('ok' => false, 'error' => 'Контактну особу можна додати тільки до юрособи'));
    exit;
}

$repo = new CounterpartyRepository();

$fullName = trim($lastName . ' ' . $firstName . ' ' . $midName);

Database::begin('Papir');
try {
    // 1) створити особу
    $personId = $repo->create(array(
        'type'          => 'person',
        'name'          => $fullName,
        'last_name'     => $lastName,
        'first_name'    => $firstName,
        'middle_name'   => $midName,
        'phone'         => AlphaSmsService::normalizePhoneLoose($phone),
        'email'         => $email,
        'position_name' => $position,
    ));
    if (!$personId) {
        throw new Exception('Не вдалося створити контактну особу');
    }

    // 2) звʼязок: parent (юрособа) → child (особа), тип = contact_person
    $relId = $repo->addRelation(array(
        'parent_id'     => $parentId,
        'child_id'      => $personId,
        'relation_type' => 'contact_person',
        'job_title'     => $position,
        'is_primary'    => 0,
    ));
    if (!$relId) {
        throw new Exception('Не вдалося створити звʼязок');
    }

    Database::commit('Papir');
} catch (Exception $e) {
    Database::rollback('Papir');
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
    exit;
}

echo json_encode(array(
    'ok' => true,
    'contact' => array(
        'id'       => (int)$personId,
        'name'     => $fullName,
        'phone'    => AlphaSmsService::normalizePhoneLoose($phone),
        'position' => $position,
    ),
    'relation_id' => (int)$relId,
), JSON_UNESCAPED_UNICODE);
