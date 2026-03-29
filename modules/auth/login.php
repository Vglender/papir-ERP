<?php
require_once __DIR__ . '/auth_bootstrap.php';

use Papir\Crm\AuthService;

// Якщо вже залогінений — редірект
if (AuthService::isLoggedIn()) {
    $u = AuthService::getCurrentUser();
    $settings = \Papir\Crm\UserRepository::getSettings($u['user_id']);
    $home = isset($settings['home_screen']) ? $settings['home_screen'] : '/catalog';
    header('Location: ' . $home);
    exit;
}

require_once __DIR__ . '/views/login.php';
