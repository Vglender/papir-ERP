<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../finance_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id  = isset($_POST['id'])  ? (int)$_POST['id']  : 0;
$ids = isset($_POST['ids']) ? trim($_POST['ids']) : '';

if ($id > 0) {
    $r = Database::query('Papir', "DELETE FROM finance_bank WHERE id = {$id}");
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка видалення'));
        exit;
    }
    echo json_encode(array('ok' => true, 'deleted' => $r['affected_rows']));
    exit;
}

if ($ids !== '') {
    $idList = array_values(array_filter(array_map('intval', explode(',', $ids))));
    if (empty($idList)) {
        echo json_encode(array('ok' => false, 'error' => 'Не вказані ID'));
        exit;
    }
    $inClause = implode(',', $idList);
    $r = Database::query('Papir', "DELETE FROM finance_bank WHERE id IN ({$inClause})");
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка видалення'));
        exit;
    }
    echo json_encode(array('ok' => true, 'deleted' => $r['affected_rows']));
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'Не вказаний ID'));
