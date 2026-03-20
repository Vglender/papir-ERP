<?php

require_once __DIR__ . '/../database/src/Database.php';
require_once __DIR__ . '/../moysklad/moysklad_api.php';

require_once __DIR__ . '/../bank_privat/privat_api.php';
require_once __DIR__ . '/../bank_monobank/monobank_api.php';
require_once __DIR__ . '/../bank_ukrsib/ukrsib_api.php';

require_once __DIR__ . '/services/BankPaymentCollector.php';
require_once __DIR__ . '/services/PaymentDuplicateChecker.php';
require_once __DIR__ . '/services/PaymentMatcher.php';
require_once __DIR__ . '/services/PaymentMsMapper.php';
require_once __DIR__ . '/services/PaymentsSyncService.php';

require_once __DIR__ . '/tools/lock.php';

$lock = new ProcessLock(__DIR__ . '/storage/payments_sync.lock', 250); // 

$lockResult = $lock->acquire();

if (!$lockResult['ok']) {
    echo "LOCKED: previous process still running ({$lockResult['age']} sec)\n";
    exit;
}

$dbConfigs = require __DIR__ . '/../database/config/databases.php';
Database::init($dbConfigs);

try {

	function getCliOption($name, $default = null)
	{
		global $argv;

		if (!isset($argv) || !is_array($argv)) {
			return $default;
		}

		foreach ($argv as $arg) {
			if (strpos($arg, '--' . $name . '=') === 0) {
				return substr($arg, strlen('--' . $name . '='));
			}
		}

		return $default;
	}

	function resolveDateFrom($rawValue = null)
	{
		if ($rawValue === null || $rawValue === '') {
			return date('Y-m-d', strtotime('-1 day'));
		}

		$value = trim($rawValue);

		if ($value === 'today') {
			return date('Y-m-d');
		}

		if ($value === 'yesterday') {
			return date('Y-m-d', strtotime('-1 day'));
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			throw new InvalidArgumentException('Invalid date_from value: ' . $value);
		}

		return date('Y-m-d', $timestamp);
	}

	function request_pb_ur($dateFrom)
	{
		$api = new PrivatApi([
			'default_user_agent' => 'Papir',
			'default_limit' => 100,
		]);
		$now = date('Y-m-d', strtotime('now'));

		$api->loadAccountsFromFile(__DIR__ . '/../bank_privat/storage/privat_accounts.php');

		return $api->getTransactions($dateFrom,$now);
	}

	function request_mono($dateFrom)
	{
		$mono = new MonobankApi(__DIR__ . '/../bank_monobank/storage');
		return $mono->getAllStatements(strtotime($dateFrom));
	}

	function request_ukrsib_sync($dateFrom)
	{
		$ukrsib = new UkrsibApi();
		return $ukrsib->getStatements($dateFrom);
	}

	try {
		$rawDateFrom = getCliOption('date_from', '');
		$dateFrom = resolveDateFrom($rawDateFrom);

		$service = new PaymentsSyncService();
		$result = $service->sync($dateFrom);

		print_r($result);
	} catch (Throwable $e) {
		fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
		exit(1);
	}

} catch (Throwable $e) {

    echo "ERROR: " . $e->getMessage() . "\n";

} finally {
    $lock->release();
}

$lock->release();