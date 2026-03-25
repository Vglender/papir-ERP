<?php
$reportFile = __DIR__ . '/../../scripts/image_audit_results.json';
$report = array();
if (file_exists($reportFile)) {
    $report = json_decode(file_get_contents($reportFile), true);
}
$summary = isset($report['summary']) ? $report['summary'] : array();
$generatedAt = isset($report['generated_at']) ? $report['generated_at'] : null;

require_once __DIR__ . '/views/index.php';
