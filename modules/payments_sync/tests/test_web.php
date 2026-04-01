<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

define('PAYMENTS_SYNC_ROOT', realpath(__DIR__ . '/..'));
define('MODULES_ROOT', realpath(PAYMENTS_SYNC_ROOT . '/..'));

function bootRequire($path)
{
    if (!file_exists($path)) {
        throw new RuntimeException('File not found: ' . $path);
    }
    require_once $path;
}

bootRequire(MODULES_ROOT . '/database/src/Database.php');
bootRequire(MODULES_ROOT . '/moysklad/moysklad_api.php');
bootRequire(MODULES_ROOT . '/bank_privat/privat_api.php');
bootRequire(MODULES_ROOT . '/bank_monobank/monobank_api.php');
bootRequire(MODULES_ROOT . '/bank_ukrsib/ukrsib_api.php');

bootRequire(PAYMENTS_SYNC_ROOT . '/services/BankPaymentCollector.php');
bootRequire(PAYMENTS_SYNC_ROOT . '/services/PaymentDuplicateChecker.php');
bootRequire(PAYMENTS_SYNC_ROOT . '/services/PaymentMatcher.php');
bootRequire(PAYMENTS_SYNC_ROOT . '/services/PaymentMsMapper.php');
bootRequire(PAYMENTS_SYNC_ROOT . '/services/PaymentsSyncService.php');

$config = require PAYMENTS_SYNC_ROOT . '/config/payments_sync_config.php';
$accountsMap = require PAYMENTS_SYNC_ROOT . '/config/accounts_map.php';

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
        if (function_exists('request_ukrsib_statement')) {
            return request_ukrsib_statement($dateFrom);
        }

        if (function_exists('ukrsib_request_statement')) {
            return ukrsib_request_statement($dateFrom);
        }

        if (function_exists('request_ukrsib_api')) {
            return request_ukrsib_api($dateFrom);
        }

        throw new RuntimeException('Не найдена функция запроса выписки в модуле bank_ukrsib');
    }
}

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : date('Y-m-d', strtotime('-5 days'));
$stage = isset($_GET['stage']) ? trim($_GET['stage']) : 'prepare';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'in';
$externalCode = isset($_GET['external_code']) ? trim($_GET['external_code']) : '';
$sample = isset($_GET['sample']) ? (int)$_GET['sample'] : 0;
$real = isset($_GET['real']) && $_GET['real'] == '1';

$ms = new MoySkladApi();
$collector = new BankPaymentCollector($config, $accountsMap);
$duplicateChecker = new PaymentDuplicateChecker();
$matcher = new PaymentMatcher($config, $ms);
$mapper = new PaymentMsMapper($config, $ms);
$syncService = new PaymentsSyncService();

$result = null;
$error = null;

try {
    switch ($stage) {
        case 'collector':
            $result = $collector->collect($dateFrom);
            break;

        case 'duplicate':
            $result = [
                'exists' => $duplicateChecker->exists($type, $externalCode),
                'id' => $duplicateChecker->findId($type, $externalCode),
                'type' => $type,
                'external_code' => $externalCode,
            ];
            break;

        case 'matcher':
            $payments = $collector->collect($dateFrom);
            $payment = isset($payments[$sample]) ? $payments[$sample] : null;
            $result = $payment ? $matcher->enrich($payment) : ['error' => 'Sample not found'];
            break;

        case 'mapper':
            $payments = $collector->collect($dateFrom);
            $payment = isset($payments[$sample]) ? $payments[$sample] : null;
            if ($payment) {
                $payment = $matcher->enrich($payment);
                $result = $mapper->map($payment);
            } else {
                $result = ['error' => 'Sample not found'];
            }
            break;

        case 'prepare':
            $result = $syncService->prepareOnly($dateFrom);
            break;

        case 'sync':
            $result = $real ? $syncService->sync($dateFrom) : ['warning' => 'Real sync disabled'];
            break;

        default:
            $result = ['error' => 'Unknown stage'];
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Payments Sync Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background:#f5f6f8; color:#222; }
        h1 { margin-top:0; }
        .box { background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; margin-bottom:16px; }
        label { display:block; margin:8px 0 4px; font-weight:bold; }
        input, select { width:100%; max-width:420px; padding:8px; }
        button { padding:10px 16px; cursor:pointer; }
        pre { background:#111; color:#eee; padding:16px; border-radius:8px; overflow:auto; }
        .error { color:#b00020; font-weight:bold; }
        .ok { color:#0a7a2f; font-weight:bold; }
        .row { display:flex; gap:20px; flex-wrap:wrap; }
        .col { min-width:280px; flex:1; }
    </style>
</head>
<body>
    <h1>Payments Sync Test</h1>

    <div class="box">
        <form method="get">
            <div class="row">
                <div class="col">
                    <label>Stage</label>
                    <select name="stage">
                        <option value="collector" <?= $stage === 'collector' ? 'selected' : '' ?>>collector</option>
                        <option value="duplicate" <?= $stage === 'duplicate' ? 'selected' : '' ?>>duplicate</option>
                        <option value="matcher" <?= $stage === 'matcher' ? 'selected' : '' ?>>matcher</option>
                        <option value="mapper" <?= $stage === 'mapper' ? 'selected' : '' ?>>mapper</option>
                        <option value="prepare" <?= $stage === 'prepare' ? 'selected' : '' ?>>prepare</option>
                        <option value="sync" <?= $stage === 'sync' ? 'selected' : '' ?>>sync</option>
                    </select>
                </div>

                <div class="col">
                    <label>Date from</label>
                    <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
                </div>

                <div class="col">
                    <label>Type</label>
                    <select name="type">
                        <option value="in" <?= $type === 'in' ? 'selected' : '' ?>>in</option>
                        <option value="out" <?= $type === 'out' ? 'selected' : '' ?>>out</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <label>External code</label>
                    <input type="text" name="external_code" value="<?= h($externalCode) ?>">
                </div>

                <div class="col">
                    <label>Sample index</label>
                    <input type="number" name="sample" value="<?= h($sample) ?>">
                </div>

                <div class="col">
                    <label>Real sync</label>
                    <select name="real">
                        <option value="0" <?= !$real ? 'selected' : '' ?>>0</option>
                        <option value="1" <?= $real ? 'selected' : '' ?>>1</option>
                    </select>
                </div>
            </div>

            <p><button type="submit">Run</button></p>
        </form>
    </div>

    <div class="box">
        <div><strong>Module root:</strong> <?= h(PAYMENTS_SYNC_ROOT) ?></div>
        <div><strong>Modules root:</strong> <?= h(MODULES_ROOT) ?></div>
    </div>

    <?php if ($error): ?>
        <div class="box error">Error: <?= h($error) ?></div>
    <?php else: ?>
        <div class="box ok">Stage executed: <?= h($stage) ?></div>
    <?php endif; ?>

    <div class="box">
        <h2>Result</h2>
        <pre><?= h(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></pre>
    </div>
</body>
</html>