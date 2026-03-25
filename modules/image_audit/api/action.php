<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$action  = isset($_POST['action']) ? trim($_POST['action']) : '';
$logFile = '/var/log/papir/image_audit.log';
$pidFile = '/tmp/image_audit.pid';
$scripts = __DIR__ . '/../../../scripts';

$allowed = array('audit', 'delete_orphans', 'fix_broken', 'recompress', 'warm_cache');
if (!in_array($action, $allowed)) {
    echo json_encode(array('ok' => false, 'error' => 'Unknown action'));
    exit;
}

// Check if already running
if (file_exists($pidFile)) {
    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid > 0 && file_exists('/proc/' . $pid)) {
        echo json_encode(array('ok' => false, 'error' => 'Already running (PID ' . $pid . ')'));
        exit;
    }
    // Only delete if PID is confirmed dead — not if file was just empty (race condition)
    if ($pid > 0) {
        @unlink($pidFile);
    }
}

$label = array(
    'audit'          => 'Аудит зображень',
    'delete_orphans' => 'Аудит + видалення orphans',
    'fix_broken'     => 'Аудит + видалення broken з БД',
    'recompress'     => 'Стиснення oversized',
    'warm_cache'     => 'Прогрів cache',
);

switch ($action) {
    case 'audit':
        $cmd = "{$scripts}/image_audit.php";
        break;
    case 'delete_orphans':
        $cmd = "{$scripts}/image_audit.php --delete-orphans";
        break;
    case 'fix_broken':
        $cmd = "{$scripts}/image_audit.php --fix-broken";
        break;
    case 'recompress':
        $cmd = "{$scripts}/recompress_images.php";
        break;
    case 'warm_cache':
        $cmd = "{$scripts}/warm_image_cache.php";
        break;
}

// Write log header
file_put_contents($logFile, "=== " . $label[$action] . " === " . date('Y-m-d H:i:s') . "\n");

// output_buffering=0 + implicit_flush=On ensures lines reach the log file in real time
$fullCmd = "nohup php -d output_buffering=0 -d implicit_flush=On {$cmd} >> " . escapeshellarg($logFile) . " 2>&1 & echo \$!";
$pid = (int)trim(shell_exec($fullCmd));

if ($pid > 0) {
    file_put_contents($pidFile, $pid);
    echo json_encode(array('ok' => true, 'pid' => $pid, 'action' => $action));
} else {
    echo json_encode(array('ok' => false, 'error' => 'Failed to start process'));
}
