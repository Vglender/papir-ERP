<?php
// index.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/modules/auth/auth_bootstrap.php';

// Завантажити сесію поточного користувача (якщо є)
\Papir\Crm\AuthService::load();

// ── Auth middleware ───────────────────────────────────────────────────────────
// Публічні маршрути — доступні без входу
$_publicPaths = array(
    '/login',
    '/auth/api/check_phone',
    '/auth/api/send_otp',
    '/auth/api/verify_otp',
    '/auth/api/login_password',
    '/auth/api/set_password',
    '/counterparties/webhook/',   // prefix
    '/finance/webhook/',          // prefix
    '/customerorder/webhook/',    // prefix
    '/demand/webhook/',           // prefix
);
$_requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_requestPath = rtrim($_requestPath, '/') ?: '/';

$_isPublic = false;
foreach ($_publicPaths as $_p) {
    if (substr($_p, -1) === '/') {
        // prefix match
        if (strpos($_requestPath, $_p) === 0) { $_isPublic = true; break; }
    } else {
        if ($_requestPath === $_p) { $_isPublic = true; break; }
    }
}

if (!$_isPublic && !\Papir\Crm\AuthService::isLoggedIn()) {
    header('Location: /login');
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

use Papir\Crm\Router;

$router = new Router();
$router->handleRequest($_SERVER['REQUEST_URI']);

