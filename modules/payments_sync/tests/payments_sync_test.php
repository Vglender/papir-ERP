<?php
/**
 * CLI-тестер для модуля payments_sync
 *
 * Примеры:
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=bootstrap
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=collector
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=duplicate-single --type=in --external-code=MN123456
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=duplicate-batch --type=out --codes=MN111111,MN222222,MN333333
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=duplicate-filter-db --type=in
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=duplicate-filter-ms --type=out
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=matcher --sample=0
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=mapper --sample=0
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=prepare --date-from=2026-03-01
 * php /var/www/papir/modules/payments_sync/tests/payments_sync_test.php --stage=sync --date-from=2026-03-01 --real=1
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

define('PAYMENTS_SYNC_TEST_START', microtime(true));
define('PAYMENTS_SYNC_ROOT', realpath(__DIR__ . '/..'));
define('MODULES_ROOT', realpath(PAYMENTS_SYNC_ROOT . '/..'));

if (!PAYMENTS_SYNC_ROOT || !MODULES_ROOT) {
    fwrite(STDERR, "Cannot resolve project paths\n");
    exit(1);
}

$options = getopt('', [
    'stage::',
    'date-from::',
    'sample::',
    'type::',
    'external-code::',
    'codes::',
    'real::',
    'help::',
]);

function getArg($name, $default = null)
{
    global $options;
    return isset($options[$name]) ? $options[$name] : $default;
}

$stage = (string)getArg('stage', 'all');
$dateFrom = (string)getArg('date-from', date('Y-m-d', strtotime('-5 days')));
$sampleIndex = (int)getArg('sample', 0);
$duplicateType = (string)getArg('type', 'in');
$externalCode = (string)getArg('external-code', '');
$isRealSync = (string)getArg('real', '0') === '1';

/* if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php payments_sync_test.php --stage=bootstrap\n";
    echo "  php payments_sync_test.php --stage=collector\n";
    echo "  php payments_sync_test.php --stage=duplicate-single --type=in --external-code=MN123456\n";
    echo "  php payments_sync_test.php --stage=duplicate-batch --type=out --codes=MN111111,MN222222,MN333333\n";
    echo "  php payments_sync_test.php --stage=duplicate-filter-db --type=in\n";
    echo "  php payments_sync_test.php --stage=duplicate-filter-ms --type=out\n";
    echo "  php payments_sync_test.php --stage=matcher --sample=0\n";
    echo "  php payments_sync_test.php --stage=mapper --sample=0\n";
    echo "  php payments_sync_test.php --stage=prepare --date-from=2026-03-01\n";
    echo "  php payments_sync_test.php --stage=sync --date-from=2026-03-01 --real=1\n";
    exit(0);
} */

$paths = [
    'database'      => MODULES_ROOT . '/database/src/Database.php',
    'moysklad'      => MODULES_ROOT . '/moysklad/moysklad_api.php',
    'bank_privat'   => MODULES_ROOT . '/bank_privat/privat_api.php',
    'bank_monobank' => MODULES_ROOT . '/bank_monobank/monobank_api.php',
    'bank_ukrsib'   => MODULES_ROOT . '/bank_ukrsib/ukrsib_api.php',

    'services' => [
        'BankPaymentCollector'   => PAYMENTS_SYNC_ROOT . '/services/BankPaymentCollector.php',
        'PaymentDuplicateChecker'=> PAYMENTS_SYNC_ROOT . '/services/PaymentDuplicateChecker.php',
        'PaymentMatcher'         => PAYMENTS_SYNC_ROOT . '/services/PaymentMatcher.php',
        'PaymentMsMapper'        => PAYMENTS_SYNC_ROOT . '/services/PaymentMsMapper.php',
        'PaymentsSyncService'    => PAYMENTS_SYNC_ROOT . '/services/PaymentsSyncService.php',
    ],

	'config' => [
		'main' => PAYMENTS_SYNC_ROOT . '/config/payments_sync_config.php',
		'accounts_map' => PAYMENTS_SYNC_ROOT . '/config/accounts_map.php',
		'match_rules' => PAYMENTS_SYNC_ROOT . '/config/payment_match_rules.php',
	],
];

function out($title, $data = null)
{
    echo "\n============================================================\n";
    echo $title . "\n";
    echo "============================================================\n";

    if ($data !== null) {
        if (is_scalar($data)) {
            echo $data . "\n";
        } else {
            print_r($data);
        }
    }
}

function ok($message)
{
    echo "[OK] {$message}\n";
}

function failx($message)
{
    echo "[FAIL] {$message}\n";
}

function warnx($message)
{
    echo "[WARN] {$message}\n";
}

function assertTrue($condition, $message)
{
    if ($condition) {
        ok($message);
        return true;
    }
    failx($message);
    return false;
}

function requireOrFail($label, $path)
{
    if (!file_exists($path)) {
        failx($label . ': file not found -> ' . $path);
        exit(1);
    }

    require_once $path;
    ok($label . ': ' . $path);
}

function runMatcherRulesStage(PaymentMatcher $matcher)
{
    out('STAGE: MATCHER RULES SAMPLE');

    $samples = [
        [
            'type' => 'out',
            'sum' => 10000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'Комісія за переказ на свою картку ПриватБанку',
            'id_agent' => null,
            'inner' => false,
        ],
        [
            'type' => 'out',
            'sum' => 25000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'Оплата Google Ads GOOGLE',
            'id_agent' => null,
            'inner' => false,
        ],
        [
            'type' => 'out',
            'sum' => 15000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'Покупка в АТБ',
            'id_agent' => null,
            'inner' => false,
        ],
        [
            'type' => 'in',
            'sum' => 99000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'Нова пошта переказ',
            'id_agent' => null,
            'inner' => false,
        ],
        [
            'type' => 'out',
            'sum' => 50000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'На свою картку',
            'acc_klient' => 'UA743052990000026003050607356',
            'id_agent' => null,
            'inner' => false,
        ],
    ];

    $result = [];

    foreach ($samples as $i => $payment) {
        $payment['id_paid'] = 'TEST_RULE_' . $i;
        $payment['name'] = 'TEST_RULE_' . $i;
        $result[] = $matcher->enrich($payment);
    }

    out('MATCHER RULES RESULT', $result);
}

function summarizePayment(array $payment)
{
    return [
        'source'         => isset($payment['source']) ? $payment['source'] : null,
        'bank'           => isset($payment['bank']) ? $payment['bank'] : null,
        'id_paid'        => isset($payment['id_paid']) ? $payment['id_paid'] : null,
        'type'           => isset($payment['type']) ? $payment['type'] : null,
        'moment'         => isset($payment['moment']) ? $payment['moment'] : null,
        'sum'            => isset($payment['sum']) ? $payment['sum'] : null,
        'id_org'         => isset($payment['id_org']) ? $payment['id_org'] : null,
        'id_acc'         => isset($payment['id_acc']) ? $payment['id_acc'] : null,
        'name_kl'        => isset($payment['name_kl']) ? $payment['name_kl'] : null,
        'edrpoy_klient'  => isset($payment['edrpoy_klient']) ? $payment['edrpoy_klient'] : null,
        'acc_klient'     => isset($payment['acc_klient']) ? $payment['acc_klient'] : null,
    ];
}

out('BOOTSTRAP');

requireOrFail('Database.php', $paths['database']);
requireOrFail('Database config', MODULES_ROOT . '/database/config/databases.php');
$dbConfigs = require MODULES_ROOT . '/database/config/databases.php';
Database::init($dbConfigs);
ok('Database configs initialized');
requireOrFail('moysklad_api.php', $paths['moysklad']);
requireOrFail('bank_privat/privat_api.php', $paths['bank_privat']);
requireOrFail('bank_monobank/monobank_api.php', $paths['bank_monobank']);
requireOrFail('bank_ukrsib/ukrsib_api.php', $paths['bank_ukrsib']);

foreach ($paths['services'] as $name => $path) {
    requireOrFail('Service ' . $name, $path);
}

$config = require $paths['config']['main'];
$accountsMap = require $paths['config']['accounts_map'];
$matchRules = require $paths['config']['match_rules'];

assertTrue(class_exists('Database'), 'Database class loaded');
assertTrue(class_exists('MoySkladApi'), 'MoySkladApi class loaded');
assertTrue(class_exists('BankPaymentCollector'), 'BankPaymentCollector class loaded');
assertTrue(class_exists('PaymentDuplicateChecker'), 'PaymentDuplicateChecker class loaded');
assertTrue(class_exists('PaymentMatcher'), 'PaymentMatcher class loaded');
assertTrue(class_exists('PaymentMsMapper'), 'PaymentMsMapper class loaded');
assertTrue(class_exists('PaymentsSyncService'), 'PaymentsSyncService class loaded');

assertTrue(is_array($config), 'Main config loaded');
assertTrue(is_array($accountsMap), 'Accounts map loaded');

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
        $candidates = [
            'request_ukrsib_statement',
            'ukrsib_request_statement',
            'request_ukrsib_api',
            'request_ukrsib',
            'ukrsib_statement',
            'get_ukrsib_statement',
            'ukrsib_get_statement',
        ];

        foreach ($candidates as $fn) {
            if (function_exists($fn)) {
                return $fn($dateFrom);
            }
        }

        $classCandidates = [
            'UkrsibApi',
            'UKRSIBApi',
            'UkrsibBankApi',
        ];

        foreach ($classCandidates as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $instance = null;

            try {
                if ($className === 'UkrsibApi' || $className === 'UKRSIBApi' || $className === 'UkrsibBankApi') {
                    $configPath = MODULES_ROOT . '/bank_ukrsib/ukrsib_config.php';

                    if (file_exists($configPath)) {
                        $instance = new $className($configPath);
                    } else {
                        $instance = new $className();
                    }
                }
            } catch (Throwable $e) {
                continue;
            }

            if (!$instance) {
                continue;
            }

            $methods = [
                'requestStatement',
                'getStatement',
                'getStatements',
                'requestTransactions',
                'getTransactions',
            ];

            foreach ($methods as $method) {
                if (method_exists($instance, $method)) {
                    return $instance->$method($dateFrom);
                }
            }
        }

        $definedFunctions = get_defined_functions();
        $userFunctions = isset($definedFunctions['user']) ? $definedFunctions['user'] : [];

        $ukrsibFunctions = [];
        foreach ($userFunctions as $fn) {
            if (stripos($fn, 'ukrsib') !== false || stripos($fn, 'sib') !== false) {
                $ukrsibFunctions[] = $fn;
            }
        }

        $declaredClasses = get_declared_classes();
        $ukrsibClasses = [];
        foreach ($declaredClasses as $className) {
            if (stripos($className, 'ukrsib') !== false || stripos($className, 'sib') !== false) {
                $ukrsibClasses[] = $className;
            }
        }

        throw new RuntimeException(
            'Не найдена функция/метод запроса выписки в модуле bank_ukrsib. '
            . 'Functions: ' . implode(', ', $ukrsibFunctions)
            . '; Classes: ' . implode(', ', $ukrsibClasses)
        );
    }
}

$ms = new MoySkladApi();
$collector = new BankPaymentCollector($config, $accountsMap);
$duplicateChecker = new PaymentDuplicateChecker(isset($config['db_name']) ? $config['db_name'] : 'ms', $ms);
$matcher = new PaymentMatcher($config, $ms, $matchRules);
$mapper = new PaymentMsMapper($config, $ms);
$syncService = new PaymentsSyncService();

function runCollectorStage(BankPaymentCollector $collector, $dateFrom)
{
    out('STAGE: COLLECTOR', 'date-from=' . $dateFrom);

    try {
        $payments = $collector->collect($dateFrom);
    } catch (Throwable $e) {
        failx('Collector crashed: ' . $e->getMessage());
        return [];
    }

    assertTrue(is_array($payments), 'Collector returns array');
    ok('Collected count: ' . count($payments));

    if (!empty($payments[0])) {
        out('COLLECTOR SAMPLE', summarizePayment($payments[0]));
    } else {
        warnx('Collector returned empty list');
    }

    return $payments;
}

function runMatcherStage(PaymentMatcher $matcher, array $payments, $sampleIndex)
{
    out('STAGE: MATCHER', 'sample=' . $sampleIndex);

    if (!isset($payments[$sampleIndex])) {
        warnx('No payment sample for matcher');
        return null;
    }

    $payment = $payments[$sampleIndex];
    out('MATCHER INPUT', summarizePayment($payment));

    try {
        $enriched = $matcher->enrich($payment);
    } catch (Throwable $e) {
        failx('Matcher crashed: ' . $e->getMessage());
        return null;
    }

    out('MATCHER RESULT', [
        'id_paid'   => isset($enriched['id_paid']) ? $enriched['id_paid'] : null,
        'id_agent'  => isset($enriched['id_agent']) ? $enriched['id_agent'] : null,
        'inner'     => isset($enriched['inner']) ? $enriched['inner'] : null,
        'id_order'  => isset($enriched['id_order']) ? $enriched['id_order'] : null,
        'id_exp'    => isset($enriched['id_exp']) ? $enriched['id_exp'] : null,
    ]);

    return $enriched;
}

function runMapperStage(PaymentMsMapper $mapper, array $payment)
{
    out('STAGE: MAPPER');

    try {
        $payload = $mapper->map($payment);
    } catch (Throwable $e) {
        failx('Mapper crashed: ' . $e->getMessage());
        return null;
    }

    out('MAPPER RESULT', $payload);
    return $payload;
}

function runPrepareStage(PaymentsSyncService $syncService, $dateFrom)
{
    out('STAGE: PREPARE', 'date-from=' . $dateFrom);

    try {
        $prepared = $syncService->prepareOnly($dateFrom);
    } catch (Throwable $e) {
        failx('Prepare crashed: ' . $e->getMessage());
        return null;
    }

    out('PREPARE RESULT', [
        'raw_count' => isset($prepared['raw']) ? count($prepared['raw']) : 0,
        'in_duplicates_db' => isset($prepared['in']['duplicates_db']) ? count($prepared['in']['duplicates_db']) : 0,
        'in_duplicates_ms' => isset($prepared['in']['duplicates_ms']) ? count($prepared['in']['duplicates_ms']) : 0,
        'in_new' => isset($prepared['in']['new']) ? count($prepared['in']['new']) : 0,
        'in_payload' => isset($prepared['in']['payload']) ? count($prepared['in']['payload']) : 0,
        'out_duplicates_db' => isset($prepared['out']['duplicates_db']) ? count($prepared['out']['duplicates_db']) : 0,
        'out_duplicates_ms' => isset($prepared['out']['duplicates_ms']) ? count($prepared['out']['duplicates_ms']) : 0,
        'out_new' => isset($prepared['out']['new']) ? count($prepared['out']['new']) : 0,
        'out_payload' => isset($prepared['out']['payload']) ? count($prepared['out']['payload']) : 0,
    ]);

    return $prepared;
}

function runMapperRulesStage(PaymentMatcher $matcher, PaymentMsMapper $mapper)
{
    out('STAGE: MAPPER RULES SAMPLE');

    $samples = [
        [
            'type' => 'out',
            'sum' => 10000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'Комісія за переказ на свою картку ПриватБанку',
            'id_paid' => 'TEST_MAP_RULE_0',
            'name' => 'TEST_MAP_RULE_0',
            'id_org' => '2aac93f7-1edf-11f0-0a80-18810000bd68',
            'id_acc' => '30255067-0fee-11f1-0a80-00c5002270f8',
            'id_agent' => null,
            'inner' => false,
            'id_order' => null,
            'id_exp' => null,
        ],
        [
            'type' => 'out',
            'sum' => 25000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'Оплата Google Ads GOOGLE',
            'id_paid' => 'TEST_MAP_RULE_1',
            'name' => 'TEST_MAP_RULE_1',
            'id_org' => '41b6ac22-d29a-11ea-0a80-0517000f0d25',
            'id_acc' => '19ee251f-9172-11eb-0a80-06e20012d030',
            'id_agent' => null,
            'inner' => false,
            'id_order' => null,
            'id_exp' => null,
        ],
        [
            'type' => 'in',
            'sum' => 99000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'Нова пошта переказ',
            'id_paid' => 'TEST_MAP_RULE_2',
            'name' => 'TEST_MAP_RULE_2',
            'id_org' => '043320a2-1e56-11f1-0a80-1d8800016a44',
            'id_acc' => '043327ad-1e56-11f1-0a80-1d8800016a45',
            'id_agent' => null,
            'inner' => false,
            'id_order' => null,
            'id_exp' => null,
        ],
        [
            'type' => 'out',
            'sum' => 50000,
            'moment' => date('Y-m-d H:i:s'),
            'description' => 'На свою картку',
            'id_paid' => 'TEST_MAP_RULE_3',
            'name' => 'TEST_MAP_RULE_3',
            'id_org' => '41b6ac22-d29a-11ea-0a80-0517000f0d25',
            'id_acc' => '19ee251f-9172-11eb-0a80-06e20012d030',
            'acc_klient' => 'UA743052990000026003050607356',
            'id_agent' => null,
            'inner' => false,
            'id_order' => null,
            'id_exp' => null,
        ],
    ];

    $result = [];

    foreach ($samples as $sample) {
        $enriched = $matcher->enrich($sample);
        $result[] = [
            'enriched' => $enriched,
            'payload' => $mapper->map($enriched),
        ];
    }

    out('MAPPER RULES RESULT', $result);
}

function runSyncStage(PaymentsSyncService $syncService, $dateFrom, $isRealSync)
{
    out('STAGE: SYNC', [
        'dateFrom' => $dateFrom,
        'real' => $isRealSync,
    ]);

    if (!$isRealSync) {
        warnx('Sync stage is blocked in safe mode. Add --real=1 to run real sync.');
        return null;
    }

    try {
        $result = $syncService->sync($dateFrom);
    } catch (Throwable $e) {
        failx('Sync crashed: ' . $e->getMessage());
        return null;
    }

    out('SYNC RESULT', $result);
    return $result;
}

switch ($stage) {
    case 'bootstrap':
        break;

    case 'collector':
        runCollectorStage($collector, $dateFrom);
        break;

    case 'duplicate-single':
        out('STAGE: DUPLICATE SINGLE', [
            'type' => $duplicateType,
            'externalCode' => $externalCode,
        ]);

        $exists = $duplicateChecker->exists($duplicateType, $externalCode);
        out('DUPLICATE SINGLE RESULT', [
            'type' => $duplicateType,
            'externalCode' => $externalCode,
            'exists' => $exists,
            'id' => $duplicateChecker->findId($duplicateType, $externalCode),
        ]);
        break;

    case 'duplicate-batch':
        $codes = (string)getArg('codes', '');
        $codesArray = array_filter(array_map('trim', explode(',', $codes)));
        $existing = $duplicateChecker->getExistingExternalCodes($duplicateType, $codesArray);

        out('STAGE: DUPLICATE BATCH', [
            'type' => $duplicateType,
            'input' => $codesArray,
            'existing' => $existing,
        ]);
        break;
		case 'mapper-rules':
			runMapperRulesStage($matcher, $mapper);
		break;

    case 'duplicate-filter-db':
        $samplePayments = [
            ['id_paid' => 'MN111111', 'type' => $duplicateType],
            ['id_paid' => 'MN222222', 'type' => $duplicateType],
            ['id_paid' => 'MN333333', 'type' => $duplicateType],
        ];

        out('STAGE: DUPLICATE FILTER DB');
        out('FILTER DB RESULT', $duplicateChecker->filterNotExistingInDb($duplicateType, $samplePayments));
        break;

    case 'duplicate-filter-ms':
        $samplePayments = [
            ['id_paid' => 'MN111111', 'type' => $duplicateType],
            ['id_paid' => 'MN222222', 'type' => $duplicateType],
            ['id_paid' => 'MN333333', 'type' => $duplicateType],
        ];

        out('STAGE: DUPLICATE FILTER MS');
        out('FILTER MS RESULT', $duplicateChecker->filterNotExistingInMs($duplicateType, $samplePayments));
        break;

    case 'matcher':
        $payments = runCollectorStage($collector, $dateFrom);
        if (!empty($payments)) {
            runMatcherStage($matcher, $payments, $sampleIndex);
        }
        break;

    case 'mapper':
        $payments = runCollectorStage($collector, $dateFrom);
        if (!empty($payments)) {
            $matchedPayment = runMatcherStage($matcher, $payments, $sampleIndex);
            if (is_array($matchedPayment)) {
                runMapperStage($mapper, $matchedPayment);
            }
        }
        break;

    case 'prepare':
        runPrepareStage($syncService, $dateFrom);
        break;

    case 'sync':
        runSyncStage($syncService, $dateFrom, $isRealSync);
        break;

    case 'all':
    default:
        runCollectorStage($collector, $dateFrom);
        runPrepareStage($syncService, $dateFrom);

        if ($isRealSync) {
            runSyncStage($syncService, $dateFrom, true);
        } else {
            warnx('Real sync skipped. Add --real=1 if you really want to send documents.');
        }
        break;
		case 'matcher-rules':
		runMatcherRulesStage($matcher);
		break;
}

out('DONE', [
    'stage' => $stage,
    'date_from' => $dateFrom,
    'duration_sec' => round(microtime(true) - PAYMENTS_SYNC_TEST_START, 3),
]);