<?php
require_once __DIR__ . '/print_bootstrap.php';

$repo      = new PrintTemplateRepository();
$types     = $repo->getTypes();
$templates = $repo->getList();
$selected  = isset($_GET['selected']) ? (int)$_GET['selected'] : 0;
// Auto-select first template if none specified
if ($selected === 0 && !empty($templates)) {
    $selected = (int)$templates[0]['id'];
}
$tpl       = $selected > 0 ? $repo->getById($selected) : null;

$title     = 'Шаблони документів';
$activeNav = 'docs';
$subNav    = 'templates';
$bodyClass = 'ws-body';
require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/templates.php';
require_once __DIR__ . '/../shared/layout_end.php';