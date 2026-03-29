<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$fileKey = isset($_POST['file']) ? trim($_POST['file']) : '';

// Resolve path — same logic as get_log.php
$path = null;

if (preg_match('/^papir_/', $fileKey)) {
    foreach (glob('/var/log/papir/*.log') as $f) {
        $k = 'papir_' . preg_replace('/[^a-z0-9_]/i', '_', basename($f, '.log'));
        if ($k === $fileKey) { $path = $f; break; }
    }
} elseif (preg_match('/^tmp_/', $fileKey)) {
    foreach (glob('/tmp/*.log') as $f) {
        $k = 'tmp_' . preg_replace('/[^a-z0-9_]/i', '_', basename($f, '.log'));
        if ($k === $fileKey) { $path = $f; break; }
    }
} elseif (preg_match('/^phpfpm_(.+)$/', $fileKey, $m)) {
    $map = array(
        'error' => '/var/log/php-fpm/error.log',
        'www'   => '/var/log/php-fpm/www-error.log',
        'slow'  => '/var/log/php-fpm/www-slow.log',
    );
    $path = isset($map[$m[1]]) ? $map[$m[1]] : null;
}
// Nginx logs — не дозволяємо очищати, вони ротуються системою

if (!$path) {
    echo json_encode(array('ok' => false, 'error' => 'Цей файл не можна очищати через інтерфейс'));
    exit;
}

if (!is_writable($path)) {
    echo json_encode(array('ok' => false, 'error' => 'Файл недоступний для запису'));
    exit;
}

$fh = fopen($path, 'w');
if ($fh === false) {
    echo json_encode(array('ok' => false, 'error' => 'Не вдалося відкрити файл'));
    exit;
}
fclose($fh);

echo json_encode(array('ok' => true, 'path' => $path));
