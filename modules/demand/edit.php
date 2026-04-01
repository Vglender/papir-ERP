<?php
require_once __DIR__ . '/demand_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$activeNav = 'sales';
$subNav    = 'demands';
$title     = 'Відвантаження';

$repo = new DemandRepository();

if ($id) {
    $r = $repo->getById($id);
    if (!$r['ok'] || empty($r['row'])) {
        header('Location: /demand');
        exit;
    }
    $demand = $r['row'];
    $ri     = $repo->getItems($id);
    $items  = ($ri['ok'] && !empty($ri['rows'])) ? $ri['rows'] : array();
    $title  = 'Відвантаження ' . (!empty($demand['number']) ? $demand['number'] : '#' . $id);
} else {
    $demand = array();
    $items  = array();
}

require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/edit.php';
require_once __DIR__ . '/../shared/layout_end.php';