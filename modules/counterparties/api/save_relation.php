<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../counterparties_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$repo = new CounterpartyRepository();

$parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
$childId  = isset($_POST['child_id'])  ? (int)$_POST['child_id']  : 0;

if (!$parentId || !$childId) {
    echo json_encode(array('ok' => false, 'error' => 'parent_id і child_id обовʼязкові'));
    exit;
}
if ($parentId === $childId) {
    echo json_encode(array('ok' => false, 'error' => 'Не можна звʼязати контрагента з самим собою'));
    exit;
}

$relType = isset($_POST['relation_type']) ? trim($_POST['relation_type']) : 'contact_person';
$allowed = array('contact_person','employee','accountant','director','buyer','receiver',
                 'payer','department_contact','manager','signer','other');
if (!in_array($relType, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Невірний тип звʼязку'));
    exit;
}

$data = array(
    'parent_id'       => $parentId,
    'child_id'        => $childId,
    'relation_type'   => $relType,
    'job_title'       => isset($_POST['job_title'])       ? trim($_POST['job_title'])       : '',
    'department_name' => isset($_POST['department_name']) ? trim($_POST['department_name']) : '',
    'is_primary'      => isset($_POST['is_primary'])      ? 1 : 0,
    'comment'         => isset($_POST['comment'])         ? trim($_POST['comment'])         : '',
);

$newId = $repo->addRelation($data);
if (!$newId) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка збереження звʼязку'));
    exit;
}

echo json_encode(array('ok' => true, 'id' => $newId));
