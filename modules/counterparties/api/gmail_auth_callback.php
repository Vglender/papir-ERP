<?php
/**
 * Step 2: OAuth callback — exchanges code for token and saves it.
 */
require '/var/sqript/vendor/autoload.php';

$tokenPath = '/var/www/papir/modules/counterparties/storage/gmail_token.json';

$client = new Google\Client();
$client->setAuthConfig('/var/sqript/Merchant/credentials.json');
$client->setRedirectUri('https://papir.officetorg.com.ua/counterparties/api/gmail_auth_callback');
$client->setScopes(['https://www.googleapis.com/auth/gmail.readonly']);
$client->setAccessType('offline');

if (isset($_GET['error'])) {
    die('OAuth error: ' . htmlspecialchars($_GET['error']));
}
if (!isset($_GET['code'])) {
    die('No code received');
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) {
    die('Token error: ' . htmlspecialchars($token['error_description'] ?: $token['error']));
}

// Save token
if (!is_dir(dirname($tokenPath))) {
    mkdir(dirname($tokenPath), 0700, true);
}
file_put_contents($tokenPath, json_encode($token));

// Now call gmail.users.watch() to start listening
$client->setAccessToken($token);
$gmail   = new Google\Service\Gmail($client);
$request = new Google\Service\Gmail\WatchRequest();
$request->setTopicName('projects/totemic-fact-340421/topics/gmail-papir-inbox');
$request->setLabelIds(array('INBOX'));
try {
    $watch = $gmail->users->watch('me', $request);
    $historyId = $watch->getHistoryId();
    // Save historyId as starting point
    file_put_contents(dirname($tokenPath) . '/gmail_history_id.txt', $historyId);
    echo '<h2>Gmail авторизация успешна!</h2>';
    echo '<p>historyId: ' . htmlspecialchars($historyId) . '</p>';
    echo '<p>Входящие письма будут появляться в CRM чате.</p>';
    echo '<p><a href="/counterparties">Перейти в контрагенты</a></p>';
} catch (Exception $e) {
    echo '<h2>Токен сохранён, но watch не установлен:</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p>Возможно нужно включить Gmail API в проекте.</p>';
}
