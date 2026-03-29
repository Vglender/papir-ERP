<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../monitor_bootstrap.php';

$search   = isset($_GET['search'])    ? trim((string)$_GET['search'])    : '';
$status   = isset($_GET['status'])    ? trim((string)$_GET['status'])    : '';
$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$dateTo   = isset($_GET['date_to'])   ? trim((string)$_GET['date_to'])   : '';
$page     = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit    = 50;
$offset   = ($page - 1) * $limit;

$where = array('1=1');

if ($search !== '') {
    $s = Database::escape('Papir', $search);
    $where[] = "(title LIKE '%{$s}%' OR script LIKE '%{$s}%')";
}

if (in_array($status, array('running', 'done', 'failed'), true)) {
    $s = Database::escape('Papir', $status);
    $where[] = "status = '{$s}'";
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $s = Database::escape('Papir', $dateFrom);
    $where[] = "DATE(started_at) >= '{$s}'";
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $s = Database::escape('Papir', $dateTo);
    $where[] = "DATE(started_at) <= '{$s}'";
}

$whereStr = implode(' AND ', $where);

$countResult = Database::fetchRow('Papir',
    "SELECT COUNT(*) as cnt FROM background_jobs WHERE {$whereStr}"
);
$total      = ($countResult['ok'] && $countResult['row']) ? (int)$countResult['row']['cnt'] : 0;
$totalPages = max(1, (int)ceil($total / $limit));

$jobsResult = Database::fetchAll('Papir',
    "SELECT job_id, title, script, log_file, pid, status, started_at, finished_at
     FROM background_jobs
     WHERE {$whereStr}
     ORDER BY started_at DESC
     LIMIT {$limit} OFFSET {$offset}"
);
$jobs = ($jobsResult['ok'] && !empty($jobsResult['rows'])) ? $jobsResult['rows'] : array();

// Автоматично помічаємо завершені процеси
foreach ($jobs as &$job) {
    if ($job['status'] === 'running' && !empty($job['pid'])) {
        if (!file_exists('/proc/' . (int)$job['pid'])) {
            Database::query('Papir',
                "UPDATE background_jobs SET status='done', finished_at=NOW()
                 WHERE job_id=" . (int)$job['job_id'] . " AND status='running'"
            );
            $job['status']      = 'done';
            $job['finished_at'] = date('Y-m-d H:i:s');
        }
    }
}
unset($job);

echo json_encode(array(
    'ok'          => true,
    'jobs'        => $jobs,
    'total'       => $total,
    'page'        => $page,
    'total_pages' => $totalPages,
    'limit'       => $limit,
));
