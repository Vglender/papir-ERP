<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

define('PAYMENTS_SYNC_ROOT', realpath(__DIR__ . '/..'));
define('MODULES_ROOT', realpath(PAYMENTS_SYNC_ROOT . '/..'));

require_once MODULES_ROOT . '/database/src/Database.php';
$dbConfigs = require MODULES_ROOT . '/database/config/databases.php';
Database::init($dbConfigs);

require_once MODULES_ROOT . '/moysklad/moysklad_api.php';
require_once MODULES_ROOT . '/bank_privat/privat_api.php';
require_once MODULES_ROOT . '/bank_monobank/monobank_api.php';
require_once MODULES_ROOT . '/bank_ukrsib/ukrsib_api.php';

require_once PAYMENTS_SYNC_ROOT . '/services/BankPaymentCollector.php';
require_once PAYMENTS_SYNC_ROOT . '/services/PaymentDuplicateChecker.php';
require_once PAYMENTS_SYNC_ROOT . '/services/PaymentMatcher.php';
require_once PAYMENTS_SYNC_ROOT . '/services/PaymentMsMapper.php';
require_once PAYMENTS_SYNC_ROOT . '/services/PaymentsSyncService.php';

if (!function_exists('request_pb_ur')) {
    function request_pb_ur($dateFrom)
    {
        $api = new PrivatApi([
            'default_user_agent' => 'Papir',
            'default_limit' => 100,
        ]);

        $api->loadAccountsFromFile(MODULES_ROOT . '/bank_privat/storage/privat_accounts.php');
        return $api->getTransactionsByDate($dateFrom);
    }
}

if (!function_exists('request_mono')) {
    function request_mono($dateFrom)
    {
        $mono = new MonobankApi(MODULES_ROOT . '/bank_monobank/storage');
        return $mono->getAllStatements(strtotime($dateFrom));
    }
}

if (!function_exists('request_ukrsib_sync')) {
    function request_ukrsib_sync($dateFrom)
    {
		$ukrsib = new UkrsibApi();
		return $ukrsib->getStatements($dateFrom);
    }
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getCronStatus()
{
    $heartbeatFile = PAYMENTS_SYNC_ROOT . '/storage/cron_heartbeat.json';

    if (!file_exists($heartbeatFile)) {
        return [
            'ok' => false,
            'label' => 'Нет heartbeat',
            'last_run' => null,
            'color' => 'red',
        ];
    }

    $json = json_decode(file_get_contents($heartbeatFile), true);

    if (!$json || empty($json['last_run'])) {
        return [
            'ok' => false,
            'label' => 'Некорректный heartbeat',
            'last_run' => null,
            'color' => 'red',
        ];
    }

    $lastRun = $json['last_run'];
    $timestamp = strtotime($lastRun);

    if (!$timestamp) {
        return [
            'ok' => false,
            'label' => 'Ошибка времени heartbeat',
            'last_run' => $lastRun,$report['raw_payments'] = $rawPayments,  
            'ok' => true,
            'label' => 'Cron активен',
            'last_run' => $lastRun,
            'color' => 'green',
        ];
    }

    return [
        'ok' => false,
        'label' => 'Cron давно не запускался',
        'last_run' => $lastRun,
        'color' => 'red',
    ];
}

$dateFrom = isset($_REQUEST['date_from']) && $_REQUEST['date_from'] !== ''
    ? $_REQUEST['date_from']
    : date('Y-m-d', strtotime('-1 day'));

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$realSend = ($action === 'sync_real');

$report = null;
$error = null;
$duration = null;
$cronStatus = getCronStatus();

if ($action !== '') {
    $service = new PaymentsSyncService();
    $startedAt = microtime(true);

    try {
        $report = $service->syncDetailed($dateFrom, $realSend);

        /**
         * Перегруппировка:
         * даты -> банки
         */
        $grouped = [];
        if (!empty($report['samples']['raw'])) {
            // samples raw не весь список, поэтому строим из отдельной коллекции, если service её вернёт
        }

        if (!empty($report['raw_payments']) && is_array($report['raw_payments'])) {
            foreach ($report['raw_payments'] as $payment) {
                $date = !empty($payment['moment']) ? substr($payment['moment'], 0, 10) : 'unknown';
                $bank = !empty($payment['bank']) ? $payment['bank'] : 'unknown';
                $type = (!empty($payment['type']) && $payment['type'] === 'out') ? 'out' : 'in';

                if (!isset($grouped[$date])) {
                    $grouped[$date] = [
                        'total' => 0,
                        'in' => 0,
                        'out' => 0,
                        'banks' => [],
                    ];
                }

                if (!isset($grouped[$date]['banks'][$bank])) {
                    $grouped[$date]['banks'][$bank] = [
                        'total' => 0,
                        'in' => 0,
                        'out' => 0,
                    ];
                }

                $grouped[$date]['total']++;
                $grouped[$date]['banks'][$bank]['total']++;

                if ($type === 'out') {
                    $grouped[$date]['out']++;
                    $grouped[$date]['banks'][$bank]['out']++;
                } else {
                    $grouped[$date]['in']++;
                    $grouped[$date]['banks'][$bank]['in']++;
                }
            }
        }

        $report['grouped_by_date_bank'] = $grouped;

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $duration = round(microtime(true) - $startedAt, 3);
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Payments Sync Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        h1, h2, h3 { margin-top: 0; }
        .wrap { max-width: 1450px; margin: 0 auto; }
        .card {
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 18px;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }
        .metric {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
        }
        .metric .label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .metric .value {
            font-size: 28px;
            font-weight: bold;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        input[type="date"] {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            min-width: 180px;
        }
        button {
            border: 0;
            border-radius: 10px;
            padding: 11px 16px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-blue { background: #2563eb; color: #fff; }
        .btn-gray { background: #4b5563; color: #fff; }
        .btn-red { background: #dc2626; color: #fff; }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        th { background: #f9fafb; }

        pre {
            background: #111827;
            color: #f9fafb;
            padding: 14px;
            border-radius: 10px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 12px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
        }
        .status-green {
            background: #dcfce7;
            color: #166534;
        }
        .status-red {
            background: #fee2e2;
            color: #991b1b;
        }
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .dot-green { background: #16a34a; }
        .dot-red { background: #dc2626; }

        .date-block {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 14px;
            overflow: hidden;
        }
        .date-head {
            background: #eef2ff;
            padding: 12px 14px;
            font-weight: bold;
        }
        .inner-table {
            margin: 0;
        }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;">
            <div>
                <h1>Payments Sync Dashboard</h1>
            </div>
            <div>
                <span class="status-pill <?= $cronStatus['color'] === 'green' ? 'status-green' : 'status-red' ?>">
                    <span class="dot <?= $cronStatus['color'] === 'green' ? 'dot-green' : 'dot-red' ?>"></span>
                    <?= h($cronStatus['label']) ?>
                    <?php if (!empty($cronStatus['last_run'])): ?>
                        <span class="muted">| Последний запуск: <?= h($cronStatus['last_run']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="card">
        <form method="post" class="toolbar">
            <div class="field">
                <label for="date_from">Дата начала</label>
                <input type="date" id="date_from" name="date_from" value="<?= h($dateFrom) ?>">
            </div>

            <button class="btn-blue" type="submit" name="action" value="prepare">Подготовить</button>
            <button class="btn-gray" type="submit" name="action" value="dry_run">Dry-run отчет</button>
            <button class="btn-red" type="submit" name="action" value="sync_real" onclick="return confirm('Точно выполнить реальную синхронизацию?');">Реальная синхронизация</button>
        </form>
    </div>

    <?php if ($action === ''): ?>
        <div class="card">
            <h2>Панель готова</h2>
            <p class="muted">Выберите дату и нажмите кнопку запуска. До нажатия ничего не выполняется.</p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="card">
            <h2 style="color:#b91c1c;">Ошибка</h2>
            <pre><?= h($error) ?></pre>
        </div>
    <?php endif; ?>

    <?php if ($report && !$error): ?>
        <div class="card">
            <h2>Итоги</h2>
            <div class="grid">
                <div class="metric">
                    <div class="label">Дата начала</div>
                    <div class="value"><?= h($report['date_from']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Собрано всего</div>
                    <div class="value"><?= h($report['collected']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Входящих</div>
                    <div class="value"><?= h($report['in_total']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Исходящих</div>
                    <div class="value"><?= h($report['out_total']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Пропущено дублей</div>
                    <div class="value"><?= h($report['skipped_duplicates']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Новых входящих</div>
                    <div class="value"><?= h($report['prepared_in']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Новых исходящих</div>
                    <div class="value"><?= h($report['prepared_out']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Отправлено</div>
                    <div class="value"><?= h($report['created_in'] + $report['created_out']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Ордера найдены</div>
                    <div class="value"><?= h($report['orders_found_in'] + $report['orders_found_out']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Ордера не найдены</div>
                    <div class="value"><?= h($report['orders_not_found_in'] + $report['orders_not_found_out']) ?></div>
                </div>
                <div class="metric">
                    <div class="label">Время выполнения</div>
                    <div class="value"><?= h($duration) ?>s</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Разбивка по датам и банкам</h2>

            <?php if (!empty($report['grouped_by_date_bank'])): ?>
                <?php foreach ($report['grouped_by_date_bank'] as $date => $dateRow): ?>
                    <div class="date-block">
                        <div class="date-head">
                            <?= h($date) ?> —
                            всего: <?= h($dateRow['total']) ?>,
                            входящих: <?= h($dateRow['in']) ?>,
                            исходящих: <?= h($dateRow['out']) ?>
                        </div>

                        <table class="inner-table">
                            <thead>
                                <tr>
                                    <th>Банк</th>
                                    <th>Всего</th>
                                    <th>Входящих</th>
                                    <th>Исходящих</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($dateRow['banks'] as $bank => $bankRow): ?>
                                <tr>
                                    <td><?= h($bank) ?></td>
                                    <td><?= h($bankRow['total']) ?></td>
                                    <td><?= h($bankRow['in']) ?></td>
                                    <td><?= h($bankRow['out']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted">Нет данных для отображения.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Ошибки</h2>
            <pre><?= h(json_encode($report['errors'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
        </div>
    <?php endif; ?>

</div>
</body>
</html>