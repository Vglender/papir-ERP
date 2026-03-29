<?php
require_once __DIR__ . '/auth_bootstrap.php';

use Papir\Crm\AuthService;
use Papir\Crm\UserRepository;

$me = AuthService::getCurrentUser();
$settings = $me ? UserRepository::getSettings($me['user_id']) : array('home_screen' => '/catalog', 'theme' => 'light');
$loginMethods = $me ? UserRepository::getLoginMethods($me['user_id']) : array();

$title     = 'Мій профіль';
$activeNav = '';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/profile.php';
require_once __DIR__ . '/../shared/layout_end.php';
