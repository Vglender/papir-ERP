<?php
header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────

function sysReadFile($path) {
    if (!is_readable($path)) return '';
    return file_get_contents($path);
}

function formatBytes($kb) {
    if ($kb >= 1048576) return round($kb / 1048576, 1) . ' GB';
    if ($kb >= 1024)    return round($kb / 1024, 1)    . ' MB';
    return $kb . ' KB';
}

function formatUptime($seconds) {
    $seconds = (int)$seconds;
    $d = (int)($seconds / 86400);
    $h = (int)(($seconds % 86400) / 3600);
    $m = (int)(($seconds % 3600) / 60);
    $parts = array();
    if ($d > 0) $parts[] = $d . 'д';
    if ($h > 0) $parts[] = $h . 'г';
    $parts[] = $m . 'хв';
    return implode(' ', $parts);
}

function isServiceRunning($name) {
    // Check by process name via /proc
    $result = shell_exec('pgrep -x ' . escapeshellarg($name) . ' 2>/dev/null');
    return trim($result) !== '';
}

function getServiceStatus($name) {
    $out = shell_exec('systemctl is-active ' . escapeshellarg($name) . ' 2>/dev/null');
    $status = trim($out);
    if ($status === 'active')   return 'running';
    if ($status === 'inactive') return 'stopped';
    if ($status === 'failed')   return 'failed';
    // fallback: pgrep
    return isServiceRunning($name) ? 'running' : 'unknown';
}

function getNginxVersion() {
    $out = shell_exec('nginx -v 2>&1');
    if (preg_match('/nginx\/([0-9.]+)/', $out, $m)) return $m[1];
    return 'unknown';
}

function getPhpFpmVersion() {
    $out = shell_exec('php-fpm -v 2>&1');
    if (preg_match('/PHP ([0-9.]+)/', $out, $m)) return $m[1];
    return PHP_VERSION;
}

// ── OS / System ───────────────────────────────────────────────────────────────

$osRelease = array();
$osContent = sysReadFile('/etc/os-release');
foreach (explode("\n", $osContent) as $line) {
    if (strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        $osRelease[trim($k)] = trim($v, '"');
    }
}

$uptimeRaw = (float)explode(' ', sysReadFile('/proc/uptime'))[0];
$hostname  = trim(sysReadFile('/proc/sys/kernel/hostname'));
$kernelVer = php_uname('r');
$arch      = php_uname('m');

// CPU
$cpuInfo   = sysReadFile('/proc/cpuinfo');
$cpuCores  = substr_count($cpuInfo, 'processor');
$cpuModel  = 'unknown';
if (preg_match('/model name\s*:\s*(.+)/i', $cpuInfo, $m)) {
    $cpuModel = trim($m[1]);
}

$loadRaw   = explode(' ', sysReadFile('/proc/loadavg'));
$load1     = isset($loadRaw[0]) ? (float)$loadRaw[0] : 0;
$load5     = isset($loadRaw[1]) ? (float)$loadRaw[1] : 0;
$load15    = isset($loadRaw[2]) ? (float)$loadRaw[2] : 0;

// Load percentage relative to core count (for progress bar)
$loadPct = $cpuCores > 0 ? min(100, round($load1 / $cpuCores * 100)) : 0;

// ── Memory ────────────────────────────────────────────────────────────────────

$memRaw   = array();
$memLines = explode("\n", sysReadFile('/proc/meminfo'));
foreach ($memLines as $line) {
    if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
        $memRaw[$m[1]] = (int)$m[2];
    }
}

$memTotal     = isset($memRaw['MemTotal'])     ? $memRaw['MemTotal']     : 0;
$memFree      = isset($memRaw['MemFree'])      ? $memRaw['MemFree']      : 0;
$memAvail     = isset($memRaw['MemAvailable']) ? $memRaw['MemAvailable'] : 0;
$memBuffers   = isset($memRaw['Buffers'])      ? $memRaw['Buffers']      : 0;
$memCached    = isset($memRaw['Cached'])       ? $memRaw['Cached']       : 0;
$memUsed      = $memTotal - $memAvail;
$memPct       = $memTotal > 0 ? round($memUsed / $memTotal * 100) : 0;

$swapTotal    = isset($memRaw['SwapTotal']) ? $memRaw['SwapTotal'] : 0;
$swapFree     = isset($memRaw['SwapFree'])  ? $memRaw['SwapFree']  : 0;
$swapUsed     = $swapTotal - $swapFree;
$swapPct      = $swapTotal > 0 ? round($swapUsed / $swapTotal * 100) : 0;

// ── Disk ──────────────────────────────────────────────────────────────────────

$disks = array();
$dfOut = shell_exec('df -kP 2>/dev/null');
foreach (explode("\n", $dfOut) as $line) {
    if (preg_match('/^\/dev\/(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%\s+(\S+)/', $line, $m)) {
        $disks[] = array(
            'dev'    => '/dev/' . $m[1],
            'total'  => (int)$m[2],
            'used'   => (int)$m[3],
            'avail'  => (int)$m[4],
            'pct'    => (int)$m[5],
            'mount'  => $m[6],
        );
    }
}

// ── PHP ───────────────────────────────────────────────────────────────────────

$phpSettings = array(
    'version'             => PHP_VERSION,
    'memory_limit'        => ini_get('memory_limit'),
    'max_execution_time'  => ini_get('max_execution_time') . 's',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size'       => ini_get('post_max_size'),
    'max_input_vars'      => ini_get('max_input_vars'),
    'display_errors'      => ini_get('display_errors') ? 'On' : 'Off',
    'error_reporting'     => ini_get('error_reporting'),
    'date_timezone'       => ini_get('date.timezone'),
    'opcache_enabled'     => function_exists('opcache_get_status') ? 'On' : 'Off',
);

if (function_exists('opcache_get_status')) {
    $oc = @opcache_get_status(false);
    if ($oc) {
        $phpSettings['opcache_hit_rate'] = isset($oc['opcache_statistics']['opcache_hit_rate'])
            ? round($oc['opcache_statistics']['opcache_hit_rate'], 1) . '%' : 'n/a';
        $phpSettings['opcache_memory_used'] = isset($oc['memory_usage']['used_memory'])
            ? formatBytes((int)($oc['memory_usage']['used_memory'] / 1024)) : 'n/a';
    }
}

// Loaded extensions (key ones)
$keyExts = array('pdo', 'pdo_mysql', 'mysqli', 'gd', 'curl', 'json', 'mbstring', 'openssl', 'zip', 'ftp');
$extStatus = array();
foreach ($keyExts as $ext) {
    $extStatus[$ext] = extension_loaded($ext);
}

// ── Services ──────────────────────────────────────────────────────────────────

$services = array(
    array('name' => 'nginx',    'label' => 'Nginx',    'status' => getServiceStatus('nginx'),    'version' => getNginxVersion()),
    array('name' => 'php-fpm',  'label' => 'PHP-FPM',  'status' => getServiceStatus('php-fpm'),  'version' => getPhpFpmVersion()),
    array('name' => 'mysqld',   'label' => 'MySQL',    'status' => getServiceStatus('mysqld'),   'version' => ''),
);

// MySQL version
$mysqlVer = shell_exec('mysql --version 2>/dev/null');
if (preg_match('/Distrib ([0-9.]+)/', $mysqlVer, $m)) {
    $services[2]['version'] = $m[1];
}

// ── Alerts ────────────────────────────────────────────────────────────────────

$alerts = array();

if ($loadPct > 80) {
    $alerts[] = array('level' => 'error', 'msg' => 'Високе навантаження CPU: ' . $load1 . ' (ядер: ' . $cpuCores . ')');
} elseif ($loadPct > 60) {
    $alerts[] = array('level' => 'warn', 'msg' => 'Підвищене навантаження CPU: ' . $load1);
}

foreach ($disks as $disk) {
    if ($disk['pct'] >= 90) {
        $alerts[] = array('level' => 'error', 'msg' => 'Критично мало місця на диску ' . $disk['mount'] . ': ' . $disk['pct'] . '%');
    } elseif ($disk['pct'] >= 80) {
        $alerts[] = array('level' => 'warn', 'msg' => 'Мало місця на диску ' . $disk['mount'] . ': ' . $disk['pct'] . '%');
    }
}

if ($memPct >= 90) {
    $alerts[] = array('level' => 'error', 'msg' => 'Критично мало RAM: використано ' . $memPct . '%');
} elseif ($memPct >= 80) {
    $alerts[] = array('level' => 'warn', 'msg' => 'Високе використання RAM: ' . $memPct . '%');
}

foreach ($services as $svc) {
    if ($svc['status'] === 'stopped' || $svc['status'] === 'failed') {
        $alerts[] = array('level' => 'error', 'msg' => 'Сервіс ' . $svc['label'] . ' не працює!');
    }
}

// ── Result ────────────────────────────────────────────────────────────────────

echo json_encode(array(
    'ok'        => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'system' => array(
        'hostname'   => $hostname,
        'os'         => isset($osRelease['PRETTY_NAME']) ? $osRelease['PRETTY_NAME'] : 'unknown',
        'kernel'     => $kernelVer,
        'arch'       => $arch,
        'uptime'     => formatUptime($uptimeRaw),
        'uptime_sec' => (int)$uptimeRaw,
    ),
    'cpu' => array(
        'model'    => $cpuModel,
        'cores'    => $cpuCores,
        'load1'    => $load1,
        'load5'    => $load5,
        'load15'   => $load15,
        'load_pct' => $loadPct,
    ),
    'memory' => array(
        'total'     => formatBytes($memTotal),
        'used'      => formatBytes($memUsed),
        'available' => formatBytes($memAvail),
        'pct'       => $memPct,
        'swap_total'=> formatBytes($swapTotal),
        'swap_used' => formatBytes($swapUsed),
        'swap_pct'  => $swapPct,
    ),
    'disks'    => $disks,
    'php'      => $phpSettings,
    'php_ext'  => $extStatus,
    'services' => $services,
    'alerts'   => $alerts,
));
