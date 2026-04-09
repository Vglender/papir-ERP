<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../print_bootstrap.php';

$r = Database::fetchRow('Papir',
    "SELECT COUNT(*) AS cnt FROM print_pack_jobs WHERE queued = 1 AND status = 'ready'");

$count = ($r['ok'] && !empty($r['row'])) ? (int)$r['row']['cnt'] : 0;

echo json_encode(array('ok' => true, 'count' => $count));
