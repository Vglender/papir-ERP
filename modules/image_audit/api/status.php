<?php
header('Content-Type: application/json; charset=utf-8');

$pidFile    = '/tmp/image_audit.pid';
$logFile    = '/var/log/papir/image_audit.log';
$reportFile = __DIR__ . '/../../../scripts/image_audit_results.json';

$running = false;
$pid     = 0;
if (file_exists($pidFile)) {
    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid > 0 && file_exists('/proc/' . $pid)) {
        $running = true;
    } else {
        @unlink($pidFile);
    }
}

// Last N lines of log
$lines = array();
if (file_exists($logFile)) {
    $all   = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice($all, -60);
}

// Report summary (only if not running — fresh data)
$summary     = null;
$generatedAt = null;
if (!$running && file_exists($reportFile)) {
    $report      = json_decode(file_get_contents($reportFile), true);
    $summary     = isset($report['summary'])      ? $report['summary']      : null;
    $generatedAt = isset($report['generated_at']) ? $report['generated_at'] : null;
}

echo json_encode(array(
    'ok'           => true,
    'running'      => $running,
    'pid'          => $pid,
    'log'          => $lines,
    'summary'      => $summary,
    'generated_at' => $generatedAt,
));
