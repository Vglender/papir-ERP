<?php
header('Content-Type: application/json; charset=utf-8');

$tokenPath = '/var/sqript/Merchant/token.json';
$credPath  = '/var/sqript/Merchant/credentials.json';

$result = array(
    'ok'              => false,
    'authorized'      => false,
    'has_token'       => false,
    'has_refresh'     => false,
    'access_expires'  => null,   // ISO string
    'refresh_expires' => null,   // ISO string or null
    'created_at'      => null,   // ISO string
    'merchant_id'     => '121039527',
    'token_path'      => $tokenPath,
    'cred_path'       => $credPath,
    'auth_url'        => null,
    'error'           => null,
);

// ── Read token.json ────────────────────────────────────────────────────────
if (!file_exists($tokenPath)) {
    $result['error'] = 'Файл токена не знайдено: ' . $tokenPath;
    echo json_encode($result);
    exit;
}

$raw = file_get_contents($tokenPath);
$token = json_decode($raw, true);

if (!is_array($token)) {
    $result['error'] = 'Невалідний формат token.json';
    echo json_encode($result);
    exit;
}

$result['has_token']   = true;
$result['has_refresh'] = !empty($token['refresh_token']);

$created = isset($token['created'])    ? (int)$token['created']    : 0;
$expiresIn = isset($token['expires_in']) ? (int)$token['expires_in'] : 3599;
$refreshExpiresIn = isset($token['refresh_token_expires_in'])
    ? (int)$token['refresh_token_expires_in']
    : null;

if ($created > 0) {
    $result['created_at']     = date('Y-m-d H:i:s', $created);
    $result['access_expires'] = date('Y-m-d H:i:s', $created + $expiresIn);
    if ($refreshExpiresIn !== null) {
        $result['refresh_expires'] = date('Y-m-d H:i:s', $created + $refreshExpiresIn);
    }
}

// ── Try to authenticate via Google Client ──────────────────────────────────
try {
    require_once '/var/sqript/vendor/autoload.php';

    $client = new Google\Client();
    $client->setAuthConfig($credPath);
    $client->addScope(Google\Service\ShoppingContent::CONTENT);
    $client->setAccessType('offline');
    $client->setRedirectUri('https://officetorg.com.ua/webhooks/oauth2callback.php');
    $client->setPrompt('select_account consent');
    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            if (isset($newToken['error'])) {
                // Refresh token itself is expired — need full reauth
                $result['auth_url'] = $client->createAuthUrl();
                $result['error']    = 'Refresh token протух: ' . $newToken['error'];
                echo json_encode($result);
                exit;
            }
            $client->setAccessToken($newToken);
            // Save refreshed token
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
            $newStored = $client->getAccessToken();
            if (!empty($newStored['created'])) {
                $result['created_at']     = date('Y-m-d H:i:s', $newStored['created']);
                $result['access_expires'] = date('Y-m-d H:i:s', $newStored['created'] + (isset($newStored['expires_in']) ? $newStored['expires_in'] : 3599));
            }
        } else {
            $result['auth_url'] = $client->createAuthUrl();
            $result['error']    = 'Токен протух, refresh token відсутній — потрібна авторизація';
            echo json_encode($result);
            exit;
        }
    }

    $result['ok']         = true;
    $result['authorized'] = true;

} catch (Exception $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'merchant_auth_required::') === 0) {
        $result['auth_url'] = substr($msg, strlen('merchant_auth_required::'));
        $result['error']    = 'Потрібна авторизація Google';
    } else {
        $result['error'] = $msg;
        // Still try to generate auth URL
        try {
            require_once '/var/sqript/vendor/autoload.php';
            $client2 = new Google\Client();
            $client2->setAuthConfig($credPath);
            $client2->addScope(Google\Service\ShoppingContent::CONTENT);
            $client2->setAccessType('offline');
            $client2->setRedirectUri('https://officetorg.com.ua/webhooks/oauth2callback.php');
            $client2->setPrompt('select_account consent');
            $result['auth_url'] = $client2->createAuthUrl();
        } catch (Exception $e2) { /* ignore */ }
    }
}

echo json_encode($result);
