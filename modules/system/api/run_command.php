<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$cmd = isset($_POST['cmd']) ? trim($_POST['cmd']) : '';

$whitelist = array(
    // Nginx
    'nginx_test'     => array('label' => 'nginx -t',            'exec' => 'nginx -t 2>&1'),
    'nginx_reload'   => array('label' => 'reload nginx',        'exec' => 'systemctl reload nginx 2>&1 && echo "OK: nginx reloaded"'),
    'nginx_status'   => array('label' => 'status nginx',        'exec' => 'systemctl status nginx --no-pager -l 2>&1'),
    // PHP-FPM
    'phpfpm_reload'  => array('label' => 'reload php-fpm',      'exec' => 'systemctl reload php-fpm 2>&1 && echo "OK: php-fpm reloaded"'),
    'phpfpm_status'  => array('label' => 'status php-fpm',      'exec' => 'systemctl status php-fpm --no-pager -l 2>&1'),
    // MySQL
    'mysql_status'   => array('label' => 'status mysqld',       'exec' => 'systemctl status mysqld --no-pager -l 2>&1'),
    // System
    'df'             => array('label' => 'df -h',               'exec' => 'df -h 2>&1'),
    'free'           => array('label' => 'free -h',             'exec' => 'free -h 2>&1'),
    'uptime'         => array('label' => 'uptime',              'exec' => 'uptime 2>&1'),
    'top_cpu'        => array('label' => 'top processes (CPU)', 'exec' => 'ps aux --sort=-%cpu --no-headers | head -15 2>&1'),
    'top_mem'        => array('label' => 'top processes (RAM)', 'exec' => 'ps aux --sort=-%mem --no-headers | head -15 2>&1'),
    'ports'          => array('label' => 'listening ports',     'exec' => 'ss -tlnp 2>&1'),
    // PHP
    'php_version'    => array('label' => 'php -v',              'exec' => 'php -v 2>&1'),
    'php_modules'    => array('label' => 'php -m',              'exec' => 'php -m 2>&1'),
    // Cron scripts (manual run)
    'cron_sync_stock'    => array('label' => 'run: sync_stock',    'exec' => 'nohup php /var/www/papir/cron/sync_stock.php > /tmp/sync_stock.log 2>&1 & echo "Started PID $!"'),
    'cron_sync_quantity' => array('label' => 'run: sync_quantity', 'exec' => 'nohup php /var/www/papir/cron/sync_quantity.php > /tmp/sync_quantity.log 2>&1 & echo "Started PID $!"'),
    'cron_sync_prices'   => array('label' => 'run: sync_prices',   'exec' => 'nohup php /var/www/papir/cron/sync_prices.php > /tmp/sync_prices.log 2>&1 & echo "Started PID $!"'),
    'cron_sync_action'   => array('label' => 'run: sync_action',   'exec' => 'nohup php /var/www/papir/cron/sync_action.php > /tmp/sync_action.log 2>&1 & echo "Started PID $!"'),
);

if (!array_key_exists($cmd, $whitelist)) {
    echo json_encode(array('ok' => false, 'error' => 'Unknown command: ' . htmlspecialchars($cmd)));
    exit;
}

$output = shell_exec($whitelist[$cmd]['exec']);

echo json_encode(array(
    'ok'     => true,
    'cmd'    => $cmd,
    'label'  => $whitelist[$cmd]['label'],
    'output' => $output,
    'time'   => date('H:i:s'),
));