<?php
require_once __DIR__ . '/../../modules/database/database.php';

if (!\Papir\Crm\AuthService::can('backlog', 'read')) {
    http_response_code(403);
    $title = 'Доступ заборонено'; $activeNav = 'system';
    require_once __DIR__ . '/../shared/layout.php';
    echo '<div class="page-wrap"><div class="card" style="padding:32px;text-align:center;color:#9ca3af">У вас немає доступу до бэклогу.</div></div>';
    require_once __DIR__ . '/../shared/layout_end.php';
    exit;
}

$title     = 'Бэклог';
$activeNav = 'system';
$subNav    = 'backlog';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/backlog.php';
require_once __DIR__ . '/../shared/layout_end.php';
