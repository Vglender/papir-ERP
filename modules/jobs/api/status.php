<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../jobs_bootstrap.php';

$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$lines = isset($_GET['lines'])  ? max(1, min(200, (int)$_GET['lines'])) : 50;

if ($jobId <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'job_id required'));
    exit;
}

$r = Database::fetchRow('Papir',
    "SELECT * FROM background_jobs WHERE job_id = {$jobId}"
);
if (!$r['ok'] || empty($r['row'])) {
    echo json_encode(array('ok' => false, 'error' => 'Not found'));
    exit;
}

$job = $r['row'];

// Check if PID is still alive
$pid       = (int)$job['pid'];
$isRunning = false;
if ($pid > 0) {
    $isRunning = file_exists('/proc/' . $pid);
}

// Auto-mark as done if PID is gone and status is still running
if (!$isRunning && $job['status'] === 'running') {
    // Detect finish status from last log line
    $logFile  = (string)$job['log_file'];
    $newStatus = 'done';
    if ($logFile !== '' && file_exists($logFile)) {
        $tail = shell_exec('tail -5 ' . escapeshellarg($logFile));
        if ($tail !== null && stripos($tail, 'error') !== false && stripos($tail, 'DONE') === false) {
            $newStatus = 'failed';
        }
    }
    Database::query('Papir',
        "UPDATE background_jobs
         SET status = '{$newStatus}', finished_at = NOW()
         WHERE job_id = {$jobId} AND status = 'running'"
    );
    $job['status'] = $newStatus;
}

// Read log tail
$tail = '';
$logFile = (string)$job['log_file'];
if ($logFile !== '' && file_exists($logFile)) {
    $raw  = shell_exec('tail -n ' . (int)$lines . ' ' . escapeshellarg($logFile));
    $tail = $raw !== null ? $raw : '';
}

// Count stats from log
$okCount  = 0;
$errCount = 0;
if ($logFile !== '' && file_exists($logFile)) {
    $okCount  = (int)shell_exec('grep -c "  OK  " ' . escapeshellarg($logFile) . ' 2>/dev/null || echo 0');
    $errCount = (int)shell_exec('grep -c "  ERR " ' . escapeshellarg($logFile) . ' 2>/dev/null || echo 0');
}

echo json_encode(array(
    'ok'         => true,
    'job'        => $job,
    'is_running' => $isRunning,
    'tail'       => $tail,
    'ok_count'   => $okCount,
    'err_count'  => $errCount,
), JSON_UNESCAPED_UNICODE);
