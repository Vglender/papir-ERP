<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../jobs_bootstrap.php';

$r = Database::fetchAll('Papir',
    "SELECT job_id, title, script, pid, status, started_at, finished_at
     FROM background_jobs
     ORDER BY job_id DESC
     LIMIT 100"
);

$jobs = ($r['ok'] && !empty($r['rows'])) ? $r['rows'] : array();

// Check live PIDs for running jobs
foreach ($jobs as &$job) {
    $pid = (int)$job['pid'];
    if ($job['status'] === 'running' && $pid > 0) {
        $alive = file_exists('/proc/' . $pid);
        if (!$alive) {
            Database::query('Papir',
                "UPDATE background_jobs
                 SET status = 'done', finished_at = NOW()
                 WHERE job_id = " . (int)$job['job_id'] . " AND status = 'running'"
            );
            $job['status'] = 'done';
        }
    }
}
unset($job);

echo json_encode(array('ok' => true, 'jobs' => $jobs), JSON_UNESCAPED_UNICODE);
