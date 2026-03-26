<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../attributes_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$nameUk = isset($_POST['name_uk']) ? trim($_POST['name_uk']) : '';
$nameRu = isset($_POST['name_ru']) ? trim($_POST['name_ru']) : '';

if ($nameUk === '') {
    echo json_encode(array('ok' => false, 'error' => 'Назва (UK) обов\'язкова'));
    exit;
}

// Вставляємо групу
$r = Database::insert('Papir', 'attribute_group', array(
    'sort_order' => 0,
    'status'     => 1
));
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => 'Помилка створення групи'));
    exit;
}
$groupId = $r['insert_id'];

// Описи
Database::insert('Papir', 'attribute_group_description', array(
    'group_id'    => $groupId,
    'language_id' => 2,
    'name'        => $nameUk
));
if ($nameRu !== '') {
    Database::insert('Papir', 'attribute_group_description', array(
        'group_id'    => $groupId,
        'language_id' => 1,
        'name'        => $nameRu
    ));
}

echo json_encode(array(
    'ok'       => true,
    'group_id' => $groupId,
    'name_uk'  => $nameUk,
    'name_ru'  => $nameRu
));
