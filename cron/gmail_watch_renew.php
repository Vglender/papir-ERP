<?php
/**
 * Renew Gmail watch (expires every 7 days).
 * Run daily via cron: 0 8 * * * php /var/www/papir/cron/gmail_watch_renew.php
 */
require_once __DIR__ . '/../modules/database/database.php';
require '/var/sqript/vendor/autoload.php';

$tokenPath     = __DIR__ . '/../modules/counterparties/storage/gmail_token.json';
$historyIdPath = __DIR__ . '/../modules/counterparties/storage/gmail_history_id.txt';

if (!file_exists($tokenPath)) {
    echo "No token found — run OAuth setup first\n";
    exit(1);
}

$tokenData = json_decode(file_get_contents($tokenPath), true);
$client = new Google\Client();
$client->setAuthConfig('/var/sqript/Merchant/credentials.json');
$client->setScopes(['https://www.googleapis.com/auth/gmail.readonly']);
$client->setAccessType('offline');
$client->setAccessToken($tokenData);

if ($client->isAccessTokenExpired()) {
    if (!empty($tokenData['refresh_token'])) {
        $newToken = $client->fetchAccessTokenWithRefreshToken($tokenData['refresh_token']);
        if (!isset($newToken['error'])) {
            $newToken['refresh_token'] = $tokenData['refresh_token'];
            file_put_contents($tokenPath, json_encode($newToken));
            $client->setAccessToken($newToken);
        }
    }
}

$gmail   = new Google\Service\Gmail($client);
$request = new Google\Service\Gmail\WatchRequest();
$request->setTopicName('projects/totemic-fact-340421/topics/gmail-papir-inbox');
$request->setLabelIds(array('INBOX'));

try {
    $watch = $gmail->users->watch('me', $request);
    $historyId = $watch->getHistoryId();
    // Update historyId only if file doesn't exist (don't reset existing tracking)
    if (!file_exists($historyIdPath)) {
        file_put_contents($historyIdPath, $historyId);
    }
    echo date('Y-m-d H:i:s') . " Watch renewed. historyId: {$historyId}\n";
} catch (Exception $e) {
    echo date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n";
    exit(1);
}
