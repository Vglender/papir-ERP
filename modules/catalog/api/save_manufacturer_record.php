<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../catalog_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id          = isset($_POST['manufacturer_id']) ? (int)$_POST['manufacturer_id']  : 0;
$name        = isset($_POST['name'])            ? trim($_POST['name'])             : '';
$description = isset($_POST['description'])     ? trim($_POST['description'])      : '';
$image       = isset($_POST['image'])           ? trim($_POST['image'])            : '';
$offId       = isset($_POST['off_id'])          ? (int)$_POST['off_id']            : 0;

if ($name === '') {
    echo json_encode(array('ok' => false, 'error' => 'Назва обов\'язкова'));
    exit;
}

// off.oc_manufacturer.name is varchar(64)
$nameOff = mb_substr($name, 0, 64);

$data = array(
    'name'        => $name,
    'description' => $description !== '' ? $description : null,
    'image'       => $image !== '' ? $image : null,
    'off_id'      => $offId > 0 ? $offId : null,
);

// ── Save to Papir.manufacturers ───────────────────────────────────────────
if ($id > 0) {
    $exists = Database::exists('Papir', 'manufacturers', array('manufacturer_id' => $id));
    if (!$exists['ok'] || !$exists['exists']) {
        echo json_encode(array('ok' => false, 'error' => 'Виробника не знайдено'));
        exit;
    }
    $r = Database::update('Papir', 'manufacturers', $data, array('manufacturer_id' => $id));
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка збереження'));
        exit;
    }
    // Reload to get current off_id / mff_id
    $mfrRow = Database::fetchRow('Papir',
        "SELECT manufacturer_id, off_id, mff_id FROM manufacturers WHERE manufacturer_id = {$id}"
    );
} else {
    $r = Database::insert('Papir', 'manufacturers', $data);
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка: можливо виробник з такою назвою вже існує'));
        exit;
    }
    $id = isset($r['insert_id']) ? (int)$r['insert_id'] : 0;
    $mfrRow = Database::fetchRow('Papir',
        "SELECT manufacturer_id, off_id, mff_id FROM manufacturers WHERE manufacturer_id = {$id}"
    );
}

$currentOffId = ($mfrRow['ok'] && $mfrRow['row']) ? (int)$mfrRow['row']['off_id'] : 0;
$currentMffId = ($mfrRow['ok'] && $mfrRow['row']) ? (int)$mfrRow['row']['mff_id'] : 0;

$imageOff = $image !== '' ? mb_substr($image, 0, 255) : null;

// ── Sync to sites via SiteSyncService ────────────────────────────────────
require_once __DIR__ . '/../../integrations/opencart2/SiteSyncService.php';
$sync = new SiteSyncService();

// off (site_id=1)
$mfrData = array('name' => $nameOff, 'image' => $imageOff);
if ($currentOffId > 0) {
    $mfrData['manufacturer_id'] = $currentOffId;
    $sync->manufacturerSave(1, $mfrData);
} else {
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    $mfrData['noindex'] = 1;
    $mfrData['uuid'] = $uuid;
    $r = $sync->manufacturerSave(1, $mfrData);
    if ($r['ok'] && isset($r['manufacturer_id']) && $r['manufacturer_id'] > 0) {
        $currentOffId = (int)$r['manufacturer_id'];
        Database::update('Papir', 'manufacturers',
            array('off_id' => $currentOffId),
            array('manufacturer_id' => $id));
    }
}

// mff (site_id=2)
if ($currentMffId > 0) {
    $sync->manufacturerSave(2, array(
        'manufacturer_id' => $currentMffId,
        'name'            => $nameOff,
        'image'           => $imageOff,
    ));
}


echo json_encode(array(
    'ok'              => true,
    'manufacturer_id' => $id,
    'name'            => $name,
    'off_id'          => $currentOffId > 0 ? $currentOffId : null,
    'mff_id'          => $currentMffId > 0 ? $currentMffId : null,
));
