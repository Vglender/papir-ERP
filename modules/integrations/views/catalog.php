<?php
require_once __DIR__ . '/../IntegrationSettingsService.php';

$registry    = IntegrationSettingsService::getRegistry();
$categories  = IntegrationSettingsService::getCategories();
$statuses    = IntegrationSettingsService::getConnectionStatuses();
$activeCat   = isset($_GET['cat']) ? trim($_GET['cat']) : 'all';

// Load is_active per app
$_activeApps = array();
$_db = Database::connection('Papir');
$_res = $_db->query("SELECT app_key, setting_value FROM integration_settings WHERE setting_key = 'is_active'");
if ($_res) { while ($_r = $_res->fetch_assoc()) { $_activeApps[$_r['app_key']] = $_r['setting_value']; } $_res->free(); }
?>

<style>
/* ── Category filter tabs ─────────────────────────────────────────────── */
.integr-cats {
    display: flex; flex-wrap: wrap; gap: 6px;
    margin-bottom: 24px;
}
.integr-cat-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--bg-card); color: var(--text-secondary);
    font-size: 13px; font-weight: 500;
    cursor: pointer; transition: all .15s;
    text-decoration: none;
}
.integr-cat-btn:hover {
    border-color: #475569; color: var(--text);
}
.integr-cat-btn.active {
    background: #475569; color: #fff;
    border-color: #475569;
}
.integr-cat-btn .cat-count {
    font-size: 11px; opacity: .7;
}

/* ── App grid ─────────────────────────────────────────────────────────── */
.integr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

/* ── App card ─────────────────────────────────────────────────────────── */
.integr-card {
    display: flex; flex-direction: column;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    transition: box-shadow .15s, border-color .15s;
    cursor: pointer;
    text-decoration: none; color: inherit;
    position: relative;
}
.integr-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    border-color: #cbd5e1;
}
.integr-card-head {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 12px;
}
.integr-card-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    overflow: hidden;
}
.integr-card-icon img {
    width: 100%; height: 100%; object-fit: contain;
}
.integr-card-icon.placeholder {
    background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
    color: #64748b; font-weight: 700; font-size: 18px;
}
.integr-card-title {
    font-size: 15px; font-weight: 600; color: var(--text);
    line-height: 1.3;
}
.integr-card-cat {
    font-size: 11px; color: var(--text-muted); margin-top: 2px;
}
.integr-card-desc {
    font-size: 13px; color: var(--text-secondary);
    line-height: 1.5;
    flex: 1;
}

/* Status badge */
.integr-status {
    position: absolute; top: 14px; right: 14px;
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600;
    padding: 3px 8px; border-radius: 10px;
}
.integr-status.connected {
    background: #dcfce7; color: #15803d;
}
.integr-status.not-connected {
    background: #f1f5f9; color: #94a3b8;
}
.integr-status.coming-soon {
    background: #fef3c7; color: #92400e;
}

.integr-card.inactive { opacity: .5; }

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 700px) {
    .integr-grid { grid-template-columns: 1fr; }
}
</style>

<div class="page-wrap-lg">

    <div class="page-head">
        <h1>Додатки</h1>
    </div>

    <!-- Category filter -->
    <div class="integr-cats" id="integrCats">
        <?php
        // Count apps per category
        $counts = array('all' => count($registry));
        foreach ($registry as $app) {
            $cat = $app['category'];
            if (!isset($counts[$cat])) $counts[$cat] = 0;
            $counts[$cat]++;
        }
        foreach ($categories as $catKey => $catInfo):
            $cnt = isset($counts[$catKey]) ? $counts[$catKey] : 0;
            if ($catKey !== 'all' && $cnt === 0) continue;
        ?>
        <button class="integr-cat-btn<?php echo $catKey === $activeCat ? ' active' : ''; ?>"
                data-category="<?php echo $catKey; ?>">
            <?php echo htmlspecialchars($catInfo['label']); ?>
            <span class="cat-count"><?php echo $cnt; ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- App grid -->
    <div class="integr-grid" id="integrGrid">
        <?php foreach ($registry as $appKey => $app):
            $isComingSoon = isset($app['enabled']) && $app['enabled'] === false;
            $isConnected  = !$isComingSoon && !empty($statuses[$appKey]);
            $isAppActive  = !isset($_activeApps[$appKey]) || $_activeApps[$appKey] === '1'; // default active

            // Determine link
            if ($isComingSoon) {
                $href = 'javascript:void(0)';
            } elseif (!empty($app['settings_url']) && empty($app['settings'])) {
                $href = $app['settings_url'];
            } else {
                $href = '/integrations/app?key=' . urlencode($appKey);
            }

            $catLabel = isset($categories[$app['category']]['label']) ? $categories[$app['category']]['label'] : '';

            $iconFile = '/assets/images/integr/' . $app['icon'];
            $hasIcon  = file_exists($_SERVER['DOCUMENT_ROOT'] . $iconFile);
            $initial  = mb_substr($app['name'], 0, 1, 'UTF-8');
        ?>
        <a class="integr-card<?php echo (!$isAppActive && $isConnected) ? ' inactive' : ''; ?>" href="<?php echo $href; ?>"
           data-category="<?php echo htmlspecialchars($app['category']); ?>"
           data-app="<?php echo htmlspecialchars($appKey); ?>">

            <?php if ($isComingSoon): ?>
                <span class="integr-status coming-soon">Скоро</span>
            <?php elseif ($isConnected && $isAppActive): ?>
                <span class="integr-status connected">Активний</span>
            <?php elseif ($isConnected && !$isAppActive): ?>
                <span class="integr-status not-connected">Вимкнено</span>
            <?php else: ?>
                <span class="integr-status not-connected">Не підключено</span>
            <?php endif; ?>

            <div class="integr-card-head">
                <?php if ($hasIcon): ?>
                <div class="integr-card-icon"><img src="<?php echo $iconFile; ?>" alt="<?php echo htmlspecialchars($app['name']); ?>"></div>
                <?php else: ?>
                <div class="integr-card-icon placeholder"><?php echo $initial; ?></div>
                <?php endif; ?>
                <div>
                    <div class="integr-card-title"><?php echo htmlspecialchars($app['name']); ?></div>
                    <div class="integr-card-cat"><?php echo htmlspecialchars($catLabel); ?></div>
                </div>
            </div>
            <div class="integr-card-desc"><?php echo htmlspecialchars($app['description']); ?></div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function() {
    var cats = document.getElementById('integrCats');
    var grid = document.getElementById('integrGrid');
    if (!cats || !grid) return;

    // Apply initial category filter from URL
    var initialCat = '<?php echo htmlspecialchars($activeCat); ?>';
    if (initialCat !== 'all') {
        grid.querySelectorAll('.integr-card').forEach(function(card) {
            card.style.display = card.dataset.category === initialCat ? '' : 'none';
        });
    }

    cats.addEventListener('click', function(e) {
        var btn = e.target.closest('.integr-cat-btn');
        if (!btn) return;

        // Toggle active
        cats.querySelectorAll('.integr-cat-btn').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');

        var cat = btn.dataset.category;
        grid.querySelectorAll('.integr-card').forEach(function(card) {
            if (cat === 'all' || card.dataset.category === cat) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
}());
</script>
