<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../jobs_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$title    = isset($_POST['title'])    ? trim($_POST['title'])        : '';
$script   = isset($_POST['script'])   ? trim($_POST['script'])       : '';
$logFile  = isset($_POST['log_file']) ? trim($_POST['log_file'])     : '';
$pid      = isset($_POST['pid'])      ? (int)$_POST['pid']          : null;

if ($title === '') {
    echo json_encode(array('ok' => false, 'error' => 'title required'));
    exit;
}

$data = array(
    'title'    => $title,
    'script'   => $script,
    'log_file' => $logFile,
    'status'   => 'running',
);
if ($pid !== null && $pid > 0) {
    $data['pid'] = $pid;
}

$r = Database::insert('Papir', 'background_jobs', $data);
if (!$r['ok']) {
    echo json_encode(array('ok' => false, 'error' => $r['error']));
    exit;
}

$idR   = Database::fetchRow('Papir', 'SELECT LAST_INSERT_ID() AS id');
$jobId = ($idR['ok'] && $idR['row']) ? (int)$idR['row']['id'] : 0;

echo json_encode(array('ok' => true, 'job_id' => $jobId));
