<?php
header('Content-Type: application/json; charset=utf-8');

// ── Whitelist of log files ────────────────────────────────────────────────────
$logDir = '/var/www/menufold/data/www/officetorg.com.ua/system/storage/logs/';

$logFiles = array(
    'off_error'    => array('label' => 'off — error.log',    'path' => $logDir . 'error.log'),
    'off_debug'    => array('label' => 'off — debug.log',    'path' => $logDir . 'debug.log'),
    'off_ocmod'    => array('label' => 'off — ocmod.log',    'path' => $logDir . 'ocmod.log'),
    'off_ocfilter' => array('label' => 'off — ocfilter.log', 'path' => $logDir . 'ocfilter.log'),
    'off_cart'     => array('label' => 'off — cart.log',     'path' => $logDir . 'cart.log'),
);

// Filter: only files that exist and are readable
$available = array();
foreach ($logFiles as $key => $info) {
    if (is_readable($info['path'])) {
        $available[$key] = array(
            'label' => $info['label'],
            'path'  => $info['path'],
            'size'  => filesize($info['path']),
            'mtime' => filemtime($info['path']),
        );
    }
}

// ── If just listing ───────────────────────────────────────────────────────────
$file  = isset($_GET['file'])  ? trim($_GET['file'])  : '';
$lines = isset($_GET['lines']) ? (int)$_GET['lines']  : 200;
$lines = max(50, min(2000, $lines));

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
    'mtime'   => $available[$file]['mtime'],
));
