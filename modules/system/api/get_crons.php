<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../monitor_bootstrap.php';

// ── Всі відомі крони Papir (еталонний список) ────────────────────────────────
// max_silence — максимально допустимий час мовчання в хвилинах.
// Якщо лог не оновлювався довше — крон вважається неактивним.
//
// sync_stock/quantity: нічна пауза 22:00→07:00 = 9 год, тому 620 хв.
// sync_prices/action/gmail: раз на добу, тому 26 год = 1560 хв.
// payments_sync: кожні 5 хв, допуск 15 хв.
// image_audit: раз на тиждень, допуск 8 днів = 11520 хв.

$knownCrons = array(
    array(
        'name'        => 'Оновлення залишків (МС → Papir)',
        'script'      => '/var/www/papir/cron/sync_stock.php',
        'schedule'    => '0 7-22 * * *',
        'label'       => 'Щогодини :00, 7–22',
        'log_file'    => '/var/log/papir/sync_stock.log',
        'max_silence' => 620,
    ),
    array(
        'name'        => 'Вигрузка кількості на сайти',
        'script'      => '/var/www/papir/cron/sync_quantity.php',
        'schedule'    => '5 7-22 * * *',
        'label'       => 'Щогодини :05, 7–22',
        'log_file'    => '/var/log/papir/sync_quantity.log',
        'max_silence' => 620,
    ),
    array(
        'name'        => 'Перерахунок цін + вигрузка',
        'script'      => '/var/www/papir/cron/sync_prices.php',
        'schedule'    => '0 1 * * *',
        'label'       => 'Щодня о 01:00',
        'log_file'    => '/var/log/papir/sync_prices.log',
        'max_silence' => 1560,
    ),
    array(
        'name'        => 'Акційні ціни',
        'script'      => '/var/www/papir/cron/sync_action.php',
        'schedule'    => '5 21 * * *',
        'label'       => 'Щодня о 00:05 Київ',
        'log_file'    => '/var/log/papir/sync_action.log',
        'max_silence' => 1560,
    ),
    array(
        'name'        => 'Імпорт платежів',
        'script'      => '/var/www/papir/modules/payments_sync/payments_sync.php',
        'schedule'    => '*/5 * * * *',
        'label'       => 'Кожні 5 хв',
        'log_file'    => '/var/log/papir/payments_sync.log',
        'max_silence' => 15,
    ),
    array(
        'name'        => 'Gmail watch renew',
        'script'      => '/var/www/papir/cron/gmail_watch_renew.php',
        'schedule'    => '0 8 * * *',
        'label'       => 'Щодня о 08:00',
        'log_file'    => '/tmp/gmail_watch.log',
        'max_silence' => 1560,
    ),
    array(
        'name'        => 'Image audit',
        'script'      => '/var/www/papir/cron/image_audit.php',
        'schedule'    => '0 5 * * 0',
        'label'       => 'Щонеділі о 05:00',
        'log_file'    => '/var/log/papir/image_audit.log',
        'max_silence' => 11520,
    ),
    array(
        'name'        => 'Трекінг ТТН НП',
        'script'      => '/var/www/papir/cron/track_ttn.php',
        'schedule'    => '0 * * * *',
        'label'       => 'Щогодини',
        'log_file'    => '/tmp/track_ttn.log',
        'max_silence' => 120,
    ),
    array(
        'name'        => 'НП: синхронізація відділень',
        'script'      => '/var/www/papir/scripts/np_sync_warehouses.php',
        'schedule'    => '0 3 1 * *',
        'label'       => '1-го числа о 03:00',
        'log_file'    => '/tmp/np_sync_warehouses.log',
        'max_silence' => 44640, // 31 день
    ),
    array(
        'name'        => 'НП: синхронізація вулиць',
        'script'      => '/var/www/papir/scripts/np_sync_streets.php',
        'schedule'    => '0 6 1 * *',
        'label'       => '1-го числа о 06:00',
        'log_file'    => '/tmp/np_sync_streets.log',
        'max_silence' => 44640, // 31 день
    ),
);

// ── Визначити активність по часу останнього запуску ───────────────────────────

$now = time();

foreach ($knownCrons as &$cron) {
    $cron['last_run']      = null;
    $cron['last_run_ts']   = null;
    $cron['active']        = false;

    if (!empty($cron['log_file']) && file_exists($cron['log_file'])) {
        $mtime = filemtime($cron['log_file']);
        if ($mtime) {
            $cron['last_run']    = date('Y-m-d H:i', $mtime);
            $cron['last_run_ts'] = $mtime;
            $gapMinutes = ($now - $mtime) / 60;
            $cron['active'] = ($gapMinutes <= $cron['max_silence']);
        }
    }

    // gap_minutes — для відображення "X хв тому" в UI
    $cron['gap_min'] = $cron['last_run_ts'] ? (int)(($now - $cron['last_run_ts']) / 60) : null;

    unset($cron['last_run_ts']); // не потрібен на клієнті
}
unset($cron);

// ── Background jobs (recent) ──────────────────────────────────────────────────

$jobsResult = Database::fetchAll('Papir',
    "SELECT job_id, title, script, log_file, pid, status, started_at, finished_at
     FROM background_jobs
     ORDER BY started_at DESC
     LIMIT 50"
);
$jobs = ($jobsResult['ok'] && !empty($jobsResult['rows'])) ? $jobsResult['rows'] : array();

foreach ($jobs as &$job) {
    if ($job['status'] === 'running' && !empty($job['pid'])) {
        if (!file_exists('/proc/' . (int)$job['pid'])) {
            Database::query('Papir',
                "UPDATE background_jobs SET status='done', finished_at=NOW()
                 WHERE job_id=" . (int)$job['job_id'] . " AND status='running'"
            );
            $job['status'] = 'done';
        }
    }
}
unset($job);

echo json_encode(array(
    'ok'          => true,
    'known_crons' => $knownCrons,
    'jobs'        => $jobs,
));
