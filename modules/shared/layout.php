<?php
/**
 * Shared page layout — head + body open.
 *
 * Використання на початку кожного view:
 *
 *   <?php
 *   $title = 'Виробники';
 *   require_once __DIR__ . '/../../shared/layout.php';
 *   ?>
 *   ... html сторінки ...
 *   <?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
 *
 * Змінні (всі необов'язкові):
 *   $title       — заголовок вкладки (без суфіксу "— Papir CRM")
 *   $extraCss    — рядок або масив додаткових <link> тегів
 *   $bodyClass   — CSS клас для <body>
 */
$_pageTitle = isset($title) ? htmlspecialchars($title) . ' — Papir CRM' : 'Papir CRM';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $_pageTitle; ?></title>
<link rel="stylesheet" href="/modules/shared/ui.css?v=<?php echo filemtime(__DIR__ . '/ui.css'); ?>">
<?php if (!empty($extraCss)) {
    foreach ((array)$extraCss as $_css) { echo $_css . "\n"; }
} ?>
</head>
<body<?php if (!empty($bodyClass)) { echo ' class="' . htmlspecialchars($bodyClass) . '"'; } ?>>
