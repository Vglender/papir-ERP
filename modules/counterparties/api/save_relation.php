<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$repo = new CounterpartyRepository();

$relId   = isset($_POST['id'])        ? (int)$_POST['id']        : 0;
$relType = isset($_POST['relation_type']) ? trim($_POST['relation_type']) : 'contact_person';

$allowed = array(
    // person ↔ company
    'contact_person', 'director', 'accountant', 'manager', 'signer',
    'employee', 'buyer', 'receiver', 'payer', 'department_contact',
    // company ↔ company
    'subsidiary', 'branch', 'partner', 'supplier', 'client',
    'other',
);
if (!in_array($relType, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний тип зв\'язку'));
    exit;
}

$data = array(
    'relation_type'   => $relType,
    'job_title'       => isset($_POST['job_title'])       ? trim($_POST['job_title'])       : '',
    'department_name' => isset($_POST['department_name']) ? trim($_POST['department_name']) : '',
    'is_primary'      => isset($_POST['is_primary'])      ? 1 : 0,
    'comment'         => isset($_POST['comment'])         ? trim($_POST['comment'])         : '',
);

// ── Update existing ──────────────────────────────────────────────────────────
if ($relId > 0) {
    $ok = $repo->updateRelation($relId, $data);
    echo json_encode(array('ok' => $ok));
    exit;
}

// ── Create new ───────────────────────────────────────────────────────────────
$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
$childId  = isset($_POST['child_id'])  ? (int)$_POST['child_id']  : 0;

if (!$parentId || !$childId) {
    echo json_encode(array('ok' => false, 'error' => 'parent_id і child_id обов\'язкові'));
    exit;
}
if ($parentId === $childId) {
    echo json_encode(array('ok' => false, 'error' => 'Не можна зв\'язати контрагента з самим собою'));
    exit;
}

$data['parent_id'] = $parentId;
$data['child_id']  = $childId;

$newId = $repo->addRelation($data);
if (!$newId) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка збереження зв\'язку'));
    exit;
}

echo json_encode(array('ok' => true, 'id' => $newId));
