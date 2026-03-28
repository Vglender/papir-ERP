<?php
/**
 * Step 1: Redirect user to Google OAuth to authorize Gmail read access.
 * Visit: https://papir.officetorg.com.ua/counterparties/api/gmail_auth_setup
 */
require '/var/sqript/vendor/autoload.php';

$client = new Google\Client();
$client->setAuthConfig('/var/sqript/Merchant/credentials.json');
$client->setRedirectUri('https://papir.officetorg.com.ua/counterparties/api/gmail_auth_callback');
$client->setScopes(['https://www.googleapis.com/auth/gmail.readonly']);
$client->setAccessType('offline');
$client->setPrompt('consent');  // force refresh_token

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit;
