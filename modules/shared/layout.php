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
    array('key' => 'prostor', 'label' => 'Простір', 'color' => '#7c3aed',
          'items' => array(
              array('key' => 'counterparties', 'label' => 'Контрагенти', 'url' => '/counterparties'),
          )),
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
              array('key' => 'orders',    'label' => 'Замовлення',    'url' => '/customerorder'),
              array('key' => 'demands',   'label' => 'Відвантаження', 'url' => '/demand'),
              array('key' => 'scenarios', 'label' => 'Сценарії ⚡',   'url' => '/sales/scenarios'),
          )),
    array('key' => 'logistics', 'label' => 'Логістика', 'color' => '#0369a1',
          'items' => array(
              array('key' => 'np-ttns',    'label' => 'НП · ТТН',            'url' => '/novaposhta/ttns'),
              array('key' => 'np-scan',    'label' => 'НП · Реєстри',      'url' => '/novaposhta/scansheets'),
              array('key' => 'np-courier', 'label' => 'НП · Виклики кур.',  'url' => '/novaposhta/courier-calls'),
              array('key' => 'np-senders', 'label' => 'НП · Відправники',  'url' => '/novaposhta/senders'),
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
    array('key' => 'analytics', 'label' => 'Аналітика', 'color' => '#db2777',
          'items' => array(
              array('key' => 'google-analytics', 'label' => 'Google Analytics',  'url' => '/analytics/google'),
              array('key' => 'google-shopping',  'label' => 'Google Shopping',   'url' => '/analytics/shopping'),
          )),
    array('key' => 'tools',   'label' => 'Інструменти', 'color' => '#b45309',
          'items' => array(
              array('key' => 'payments',   'label' => 'Платежі',     'url' => '/payments'),
              array('key' => 'ms-attrs',   'label' => 'МС атрибути', 'url' => '/docum/attr'),
              array('key' => 'image-audit','label' => 'Фото аудит',  'url' => '/image-audit'),
              array('key' => 'cp-dedup',   'label' => 'Дублікати контрагентів', 'url' => '/counterparties/dedup'),
          )),
    array('key' => 'docs',    'label' => 'Документи',  'color' => '#6366f1',
          'items' => array(
              array('key' => 'templates', 'label' => 'Шаблони',  'url' => '/print/templates'),
              array('key' => 'documents', 'label' => 'Архів',    'url' => '#'),
          )),
    array('key' => 'system',  'label' => 'Система',    'color' => '#0d9488',
          'items' => array(
              array('key' => 'monitor',       'label' => 'Сервер',        'url' => '/system/monitor'),
              array('key' => 'sites',         'label' => 'Сайти',         'url' => '/system/sites'),
              array('key' => 'logs',          'label' => 'Логи',          'url' => '/system/logs'),
              array('key' => 'organizations', 'label' => 'Організації',   'url' => '/system/organizations'),
              array('key' => 'users',         'label' => 'Користувачі',   'url' => '/auth/users'),
              array('key' => 'roles',         'label' => 'Ролі та права', 'url' => '/auth/roles'),
              array('key' => 'backlog',       'label' => 'Бэклог',        'url' => '/system/backlog'),
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
    'prostor' => '<svg viewBox="0 0 22 22" fill="none"><circle cx="8" cy="7.5" r="2.8" stroke="currentColor" stroke-width="1.7"/><path d="M2 18.5c0-3.31 2.69-6 6-6s6 2.69 6 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M15 6a2.5 2.5 0 0 1 0 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".6"/><path d="M18.5 18.5c0-2.76-1.79-5.1-4.28-5.88" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".6"/></svg>',
    'catalog' => '<svg viewBox="0 0 22 22" fill="none"><rect x="2" y="2" width="8" height="8" rx="2" fill="currentColor" opacity=".95"/><rect x="12" y="2" width="8" height="8" rx="2" fill="currentColor" opacity=".55"/><rect x="2" y="12" width="8" height="8" rx="2" fill="currentColor" opacity=".55"/><rect x="12" y="12" width="8" height="8" rx="2" fill="currentColor" opacity=".3"/></svg>',
    'prices'  => '<svg viewBox="0 0 22 22" fill="none"><path d="M3 3h7.172a2 2 0 0 1 1.414.586l7 7a2 2 0 0 1 0 2.828l-5.172 5.172a2 2 0 0 1-2.828 0l-7-7A2 2 0 0 1 3 10.172V3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor"/></svg>',
    'sales'      => '<svg viewBox="0 0 22 22" fill="none"><path d="M2 3h2.5l2 8.5h9l2-6H7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="17.5" r="1.5" fill="currentColor"/><circle cx="16" cy="17.5" r="1.5" fill="currentColor"/></svg>',
    'logistics'  => '<svg viewBox="0 0 22 22" fill="none"><rect x="1" y="8" width="13" height="9" rx="1.5" stroke="currentColor" stroke-width="1.7"/><path d="M14 11h4l3 3v3h-7V11z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="5"  cy="18.5" r="1.5" fill="currentColor"/><circle cx="16" cy="18.5" r="1.5" fill="currentColor"/></svg>',
    'finance' => '<svg viewBox="0 0 22 22" fill="none"><rect x="2" y="5" width="18" height="13" rx="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M2 9.5h18" stroke="currentColor" stroke-width="1.7"/><rect x="5" y="12.5" width="5" height="2" rx="1" fill="currentColor"/></svg>',
    'integr'  => '<svg viewBox="0 0 22 22" fill="none"><circle cx="5.5" cy="11" r="2.5" stroke="currentColor" stroke-width="1.7"/><circle cx="16.5" cy="11" r="2.5" stroke="currentColor" stroke-width="1.7"/><path d="M8 11h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M16.5 5v3M16.5 14v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".5"/><path d="M5.5 5v3M5.5 14v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity=".5"/></svg>',
    'tools'   => '<svg viewBox="0 0 22 22" fill="none"><path d="M14.5 3a4 4 0 0 1 .5 7.5L7 18.5a1.5 1.5 0 0 1-2.1-2.1L12.5 8A4 4 0 0 1 14.5 3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="14.5" cy="5.5" r="1" fill="currentColor"/></svg>',
    'analytics' => '<svg viewBox="0 0 22 22" fill="none"><rect x="2" y="13" width="4" height="7" rx="1" fill="currentColor" opacity=".9"/><rect x="9" y="8" width="4" height="12" rx="1" fill="currentColor" opacity=".7"/><rect x="16" y="3" width="4" height="17" rx="1" fill="currentColor" opacity=".5"/><path d="M4 10l5-4 5 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/></svg>',
    'docs'    => '<svg viewBox="0 0 22 22" fill="none"><rect x="4" y="2" width="10" height="14" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M7 7h6M7 10h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".7"/><path d="M10 16l2 4 2-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity=".5"/></svg>',
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

        <button class="app-hdr-btn" id="blQuickBtn" title="Бэклог / швидке додавання">
            <svg viewBox="0 0 20 20" fill="none">
                <rect x="4" y="2" width="12" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
                <path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".6"/>
                <circle cx="15" cy="15" r="4" fill="#ef4444"/>
                <path d="M15 13v4M13 15h4" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <span>Бэклог</span>
        </button>

        <div class="app-hdr-sep"></div>

        <button class="app-lang-btn" title="Мова інтерфейсу">
            <span class="app-lang-code">UA</span>
            <span class="app-lang-lbl">мова</span>
        </button>

        <div class="app-hdr-sep"></div>

        <div class="app-user-wrap" id="appUserWrap">
            <?php
            if (!class_exists('\Papir\Crm\AuthService')) {
                require_once __DIR__ . '/../auth/AuthService.php';
            }
            require_once __DIR__ . '/../auth/avatar_helper.php';
            $_authUser     = \Papir\Crm\AuthService::getCurrentUser();
            $_userName     = $_authUser ? htmlspecialchars(isset($_authUser['full_name']) ? $_authUser['full_name'] : $_authUser['display_name']) : 'Гість';
            $_userInitials = $_authUser ? htmlspecialchars($_authUser['initials'])     : '??';
            $_userRole     = $_authUser ? htmlspecialchars($_authUser['role_name'])    : '';
            $_isAdmin      = $_authUser && !empty($_authUser['is_admin']);
            // Avatar
            $_avatarInfo   = array('type'=>'color','style'=>'linear-gradient(135deg,#5b8af8,#7c3aed)','isImage'=>false);
            if ($_authUser) {
                $_userSettings = \Papir\Crm\UserRepository::getSettings($_authUser['user_id']);
                $_avatarVal    = papirAvatarFromSettings($_userSettings);
                $_avatarInfo   = papirAvatarInfo($_avatarVal);
            }
            ?>
            <button class="app-user-btn" id="appUserBtn" type="button">
                <?php if ($_avatarInfo['isImage']): ?>
                <div class="app-user-avatar" style="background:none;padding:0;overflow:hidden"><img src="<?php echo $_avatarInfo['url']; ?>?v=<?php echo time(); ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%"></div>
                <?php elseif ($_avatarInfo['type'] === 'emoji'): ?>
                <div class="app-user-avatar" style="background:<?php echo $_avatarInfo['bg']; ?>;font-size:16px"><?php echo $_avatarInfo['emoji']; ?></div>
                <?php else: ?>
                <div class="app-user-avatar" style="background:<?php echo $_avatarInfo['bg']; ?>"><?php echo $_userInitials; ?></div>
                <?php endif; ?>
                <div class="app-user-info">
                    <span class="app-user-name"><?php echo $_userName; ?></span>
                    <span class="app-user-role"><?php echo $_userRole; ?></span>
                </div>
                <svg class="app-user-arr" viewBox="0 0 13 13" fill="none">
                    <path d="M2.5 4.5l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>

            <div class="app-user-dropdown" id="appUserDropdown">
                <div class="app-user-drop-head">
                    <div class="app-user-drop-name"><?php echo $_userName; ?></div>
                    <?php if ($_userRole): ?><div class="app-user-drop-role"><?php echo $_userRole; ?></div><?php endif; ?>
                </div>

                <a class="app-user-drop-item" href="/auth/profile">
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.5" r="2.5" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 13.5c0-2.76 2.46-5 5.5-5s5.5 2.24 5.5 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Налаштування
                </a>

                <?php if ($_isAdmin): ?>
                <div class="app-user-drop-sep"></div>
                <a class="app-user-drop-item" href="/auth/users">
                    <svg viewBox="0 0 16 16" fill="none"><circle cx="5.5" cy="5" r="2" stroke="currentColor" stroke-width="1.4"/><path d="M1.5 13c0-2.2 1.79-4 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="11" cy="5" r="2" stroke="currentColor" stroke-width="1.4"/><path d="M14.5 13c0-2.2-1.79-4-4-4h-2c-.78 0-1.51.22-2.14.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Користувачі
                </a>
                <a class="app-user-drop-item" href="/auth/roles">
                    <svg viewBox="0 0 16 16" fill="none"><rect x="1.5" y="3.5" width="13" height="9" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 7.5h6M5 10h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".6"/></svg>
                    Ролі та права
                </a>
                <?php endif; ?>

                <div class="app-user-drop-sep"></div>
                <?php if ($_authUser): ?>
                <button class="app-user-drop-item danger" id="appLogoutBtn" type="button">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M10.5 5.5L13.5 8l-3 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.5 8H6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M6.5 3H3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Вийти
                </button>
                <?php else: ?>
                <a class="app-user-drop-item" href="/login">
                    <svg viewBox="0 0 16 16" fill="none"><path d="M5.5 5.5L2.5 8l3 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M2.5 8H9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M9.5 3H13a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Увійти
                </a>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function () {
            var wrap = document.getElementById('appUserWrap');
            var btn  = document.getElementById('appUserBtn');
            if (!wrap || !btn) { return; }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                wrap.classList.toggle('open');
            });
            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) { wrap.classList.remove('open'); }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { wrap.classList.remove('open'); }
            });
            var logoutBtn = document.getElementById('appLogoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function () {
                    fetch('/auth/api/logout', { method: 'POST' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { window.location.href = d.redirect || '/login'; });
                });
            }
        }());
        </script>

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

