<?php
header('Content-Type: application/json; charset=utf-8');

// ── Whitelist of log files ────────────────────────────────────────────────────

$logFiles = array();

// --- Крони Papir ---
$papirLogs = glob('/var/log/papir/*.log');
if ($papirLogs) {
    sort($papirLogs);
    foreach ($papirLogs as $f) {
        $name = basename($f, '.log');
        $key  = 'papir_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $logFiles[$key] = array(
            'label' => basename($f),
            'path'  => $f,
            'group' => 'papir',
        );
    }
}

// --- Система ---
$logFiles['nginx_error']  = array('label' => 'nginx error.log',   'path' => '/var/log/nginx/error.log',       'group' => 'system');
$logFiles['phpfpm_error'] = array('label' => 'php-fpm error',     'path' => '/var/log/php-fpm/error.log',     'group' => 'system');
$logFiles['phpfpm_www']   = array('label' => 'php-fpm www-error', 'path' => '/var/log/php-fpm/www-error.log', 'group' => 'system');
$logFiles['phpfpm_slow']  = array('label' => 'php-fpm slow',      'path' => '/var/log/php-fpm/www-slow.log',  'group' => 'system');

// --- Фонові скрипти /tmp/*.log ---
$tmpLogs = glob('/tmp/*.log');
if ($tmpLogs) {
    sort($tmpLogs);
    foreach ($tmpLogs as $f) {
        $name = basename($f, '.log');
        $key  = 'tmp_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $logFiles[$key] = array(
            'label' => basename($f),
            'path'  => $f,
            'group' => 'tmp',
        );
    }
}

// ── Залишити лише існуючі та читабельні файли ─────────────────────────────────

$available = array();
foreach ($logFiles as $key => $info) {
    if (is_readable($info['path'])) {
        $available[$key] = array(
            'label' => $info['label'],
            'path'  => $info['path'],
            'group' => $info['group'],
            'size'  => filesize($info['path']),
            'mtime' => filemtime($info['path']),
        );
    }
}

// ── Якщо тільки список ───────────────────────────────────────────────────────

$file  = isset($_GET['file'])  ? trim($_GET['file'])  : '';
$lines = isset($_GET['lines']) ? (int)$_GET['lines']  : 200;
$lines = max(50, min(5000, $lines));

if ($file === '' || $file === 'list') {
    echo json_encode(array('ok' => true, 'files' => $available));
    exit;
}

if (!array_key_exists($file, $available)) {
    echo json_encode(array('ok' => false, 'error' => 'Unknown log: ' . htmlspecialchars($file)));
    exit;
}

$path   = $available[$file]['path'];
$output = shell_exec('tail -n ' . $lines . ' ' . escapeshellarg($path) . ' 2>&1');

echo json_encode(array(
    'ok'      => true,
    'file'    => $file,
    'label'   => $available[$file]['label'],
    'path'    => $path,
    'lines'   => $lines,
    'content' => $output,
    'size'    => $available[$file]['size'],
    'mtime'   => $available[$file]['mtime'],
));
