<?php
/**
 * Shared page layout — app shell (header + subnav) + head + body open.
 *
 * Використання на початку кожного view:
 *
 *   <?php
 *   $title      = 'Виробники';
 *   $activeNav  = 'catalog';      // ключ модуля верхнього меню
 *   $subNav     = 'manufacturers'; // ключ активного підпункту
 *   require_once __DIR__ . '/../../shared/layout.php';
 *   ?>
 *   ... html сторінки ...
 *   <?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
 *
 * Змінні (всі необов'язкові):
 *   $title      — заголовок вкладки (без суфіксу "— Papir ERP")
 *   $activeNav  — активний модуль: catalog|prices|sales|finance|integr|tools|system
 *   $subNav     — активний підпункт меню (key з конфігу нижче)
 *   $extraCss   — рядок або масив додаткових <link> тегів
 *   $bodyClass  — додатковий CSS клас для <body>
 */

// ── Navigation config ────────────────────────────────────────────────────────
$_nav = array(
    array('key' => 'catalog', 'label' => 'Каталог', 'color' => '#4f7ef8',
          'items' => array(
              array('key' => 'products',      'label' => 'Товари',            'url' => '/catalog'),
              array('key' => 'categories',    'label' => 'Категорії',         'url' => '/categories'),
              array('key' => 'manufacturers', 'label' => 'Виробники',         'url' => '/manufacturers'),
              array('key' => 'attributes',    'label' => 'Атрибути',          'url' => '/attributes'),
              array('key' => 'cat-mapping',   'label' => 'Маппінг категорій', 'url' => '/category-mapping'),
          )),
    array('key' => 'prices',  'label' => 'Ціни',       'color' => '#16a34a',
          'items' => array(
              array('key' => 'pricelists', 'label' => 'Прайси',        'url' => '/prices'),
              array('key' => 'suppliers',  'label' => 'Постачальники', 'url' => '/prices/suppliers'),
              array('key' => 'actions',    'label' => 'Акції',         'url' => '/action'),
          )),
    array('key' => 'sales',   'label' => 'Продажі',    'color' => '#9333ea',
          'items' => array(
              array('key' => 'counterparties', 'label' => 'Контрагенти', 'url' => '/counterparties'),
              array('key' => 'orders',         'label' => 'Замовлення',  'url' => '/customerorder'),
          )),
    array('key' => 'finance', 'label' => 'Фінанси',    'color' => '#ea580c',
          'items' => array(
              array('key' => 'bank',        'label' => 'Банк',             'url' => '/finance/bank'),
              array('key' => 'cash',        'label' => 'Каса',             'url' => '/finance/cash'),
              array('key' => 'mutual',      'label' => 'Взаєморозрахунки', 'url' => '/finance/mutual'),
              array('key' => 'salary',      'label' => 'Зарплата',         'url' => '/finance/salary'),
              array('key' => 'adjustments', 'label' => 'Коригування',      'url' => '/finance/adjustments'),
          )),
    array('key' => 'integr',  'label' => 'Інтеграції', 'color' => '#475569',
          'items' => array(
              array('key' => 'moysklad', 'label' => 'МойСклад',        'url' => '#'),
              array('key' => 'merchant', 'label' => 'Google Merchant', 'url' => '/integr/merchant'),
              array('key' => 'ai',       'label' => 'AI',              'url' => '/ai'),
          )),
    array('key' => 'tools',   'label' => 'Інструменти', 'color' => '#b45309',
          'items' => array(
              array('key' => 'payments',   'label' => 'Платежі',     'url' => '/payments'),
              array('key' => 'ms-attrs',   'label' => 'МС атрибути', 'url' => '/docum/attr'),
              array('key' => 'image-audit','label' => 'Фото аудит',  'url' => '/image-audit'),
          )),
    array('key' => 'system',  'label' => 'Система',    'color' => '#0d9488',
          'items' => array(
              array('key' => 'jobs', 'label' => 'Фонові процеси', 'url' => '/jobs'),
          )),
);

$_activeNav  = isset($activeNav) ? $activeNav : '';
$_subNav     = isset($subNav)    ? $subNav    : '';
$_pageTitle  = isset($title)     ? htmlspecialchars($title) . ' — Papir ERP' : 'Papir ERP';

// Find active module color for subnav
$_subnavColor = '#4f7ef8';
foreach ($_nav as $_nm) {
    if ($_nm['key'] === $_activeNav) { $_subnavColor = $_nm['color']; break; }
}

// Body class
$_bodyClass = 'has-shell';
if (!empty($bodyClass)) { $_bodyClass .= ' ' . htmlspecialchars($bodyClass); }

// SVG icons per module key
$_icons = array(
    'catalog' => '<svg viewBox="0 0 22 22" fill="none"><rect x="2" y="2" width="8" height="8" rx="2" fill="currentColor" opacity=".95"/><rect x="12" y="2" width="8" height="8" rx="2" fill="currentColor" opacity=".55"/><rect x="2" y="12" width="8" height="8" rx="2" fill="currentColor" opacity=".55"/><rect x="12" y="12" width="8" height="8" rx="2" fill="currentColor" opacity=".3"/></svg>',
    'prices'  => '<svg viewBox="0 0 22 22" fill="none"><path d="M3 3h7.172a2 2 0 0 1 1.414.586l7 7a2 2 0 0 1 0 2.828l-5.172 5.172a2 2 0 0 1-2.828 0l-7-7A2 2 0 0 1 3 10.172V3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor"/></svg>',
    'sales'   => '<svg viewBox="0 0 22 22" fill="none"><path d="M2 3h2.5l2 8.5h9l2-6H7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="17.5" r="1.5" fill="currentColor"/><circle cx="16" cy="17.5" r="1.5" fill="currentColor"/></svg>',
    'finance' => '<svg viewBox="0 0 22 22" fill="none"><rect x="2" y="5" width="18" height="13" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M2 9.5h18" stroke="currentColor" stroke-width="1.7"/><rect x="5" y="12.5" width="5" height="2" rx="1" fill="currentColor"/></svg>',
    'integr'  => '<svg viewBox="0 0 22 22" fill="none"><circle cx="5.5" cy="11" r="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="16.5" cy="11" r="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M8 11h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M16.5 5v3M16.5 14v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".5"/><path d="M5.5 5v3M5.5 14v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".5"/></svg>',
    'tools'   => '<svg viewBox="0 0 22 22" fill="none"><path d="M14.5 3a4 4 0 0 1 .5 7.5L7 18.5a1.5 1.5 0 0 1-2.1-2.1L12.5 8A4 4 0 0 1 14.5 3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="14.5" cy="5.5" r="1" fill="currentColor"/></svg>',
    'system'  => '<svg viewBox="0 0 22 22" fill="none"><circle cx="11" cy="11" r="3" stroke="currentColor" stroke-width="1.7"/><path d="M11 2v2M11 18v2M2 11h2M18 11h2M4.22 4.22l1.42 1.42M16.36 16.36l1.42 1.42M4.22 17.78l1.42-1.42M16.36 5.64l1.42-1.42" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".6"/></svg>',
);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $_pageTitle; ?></title>
<link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/modules/shared/ui.css?v=<?php echo filemtime(__DIR__ . '/ui.css'); ?>">
<?php if (!empty($extraCss)) { foreach ((array)$extraCss as $_css) { echo $_css . "\n"; } } ?>
</head>
<body class="<?php echo $_bodyClass; ?>">

<!-- ══════════════════════════════════════════════════════ APP HEADER -->
<header class="app-header">

    <a class="app-logo" href="/catalog">
        <div class="app-logo-mark">P</div>
        <div class="app-logo-text">
            <span class="app-logo-name">Papir</span>
            <span class="app-logo-sub">ERP</span>
        </div>
    </a>

    <div class="app-hdr-sep"></div>

    <nav class="app-modules">
<?php foreach ($_nav as $_nm):
    $_isActive = ($_nm['key'] === $_activeNav);
    $_cls = 'app-mod-btn' . ($_isActive ? ' active' : '');
    $_style = $_isActive ? ' style="--mod-color:' . $_nm['color'] . '"' : '';
    // First item with a real URL as module link
    $_url = '#';
    foreach ($_nm['items'] as $_it) {
        if ($_it['url'] !== '#') { $_url = $_it['url']; break; }
    }
    echo '        <a class="' . $_cls . '" href="' . $_url . '"' . $_style . '>';
    echo isset($_icons[$_nm['key']]) ? $_icons[$_nm['key']] : '';
    echo '<span>' . htmlspecialchars($_nm['label']) . '</span>';
    echo '</a>' . "\n";
endforeach; ?>
    </nav>

    <div class="app-hdr-right">

        <button class="app-hdr-btn" title="Чат">
            <svg viewBox="0 0 20 20" fill="none">
                <path d="M3 4C3 3.45 3.45 3 4 3h12c.55 0 1 .45 1 1v8c0 .55-.45 1-1 1H7l-4 3V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M7 7.5h6M7 10.5h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".6"/>
            </svg>
            <span>Чат</span>
        </button>

        <div class="app-hdr-sep"></div>

        <button class="app-lang-btn" title="Мова інтерфейсу">
            <span class="app-lang-code">UA</span>
            <span class="app-lang-lbl">мова</span>
        </button>

        <div class="app-hdr-sep"></div>

        <button class="app-user-btn">
            <div class="app-user-avatar">ВГ</div>
            <div class="app-user-info">
                <span class="app-user-name">Гльондер В.</span>
                <span class="app-user-role">Адмін</span>
            </div>
            <svg class="app-user-arr" viewBox="0 0 13 13" fill="none">
                <path d="M2.5 4.5l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </button>

    </div>
</header>

<!-- ══════════════════════════════════════════════════════ SUBNAV -->
<?php
// Find subnav items for active module
$_subItems = array();
foreach ($_nav as $_nm) {
    if ($_nm['key'] === $_activeNav) { $_subItems = $_nm['items']; break; }
}
if (!empty($_subItems)):
?>
<nav class="app-subnav" style="--subnav-color:<?php echo htmlspecialchars($_subnavColor); ?>">
<?php foreach ($_subItems as $_item):
    $_isActiveSub = ($_item['key'] === $_subNav);
    echo '    <a class="app-sub-lnk' . ($_isActiveSub ? ' active' : '') . '" href="' . htmlspecialchars($_item['url']) . '">'
       . htmlspecialchars($_item['label']) . '</a>' . "\n";
endforeach; ?>
</nav>
<?php endif; ?>

