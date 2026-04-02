<?php
/**
 * Cron: кожні 5 хвилин обробляє чергу завдань від тригерів.
 * 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /var/www/papir/cron/run_task_queue.php >> /tmp/task_queue.log 2>&1
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/shared/AlphaSmsService.php';
require_once __DIR__ . '/../modules/counterparties/counterparties_bootstrap.php';

$logFile = '/tmp/task_queue.log';
$pid     = getmypid();

echo date('[Y-m-d H:i:s]') . " Starting task queue runner PID={$pid}\n";

$processed = TaskQueueRunner::runPending();

echo date('[Y-m-d H:i:s]') . " Done. Processed: {$processed}\n";