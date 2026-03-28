<?php
/**
 * Finance stub page — підключається з кожного розділу фінансів.
 * Змінні що мають бути встановлені до include:
 *   $title, $activeNav='finance', $subNav, $stubTitle, $stubDesc (опціонально)
 */
require_once __DIR__ . '/../shared/layout.php';
?>
<div class="page-wrap-sm">
    <div class="finance-stub">
        <div class="finance-stub-icon">
            <svg viewBox="0 0 48 48" fill="none">
                <rect x="4" y="10" width="40" height="28" rx="5" stroke="currentColor" stroke-width="2.5"/>
                <path d="M4 18h40" stroke="currentColor" stroke-width="2.5"/>
                <rect x="10" y="26" width="10" height="4" rx="2" fill="currentColor" opacity=".4"/>
                <rect x="24" y="26" width="6" height="4" rx="2" fill="currentColor" opacity=".2"/>
            </svg>
        </div>
        <h1><?php echo htmlspecialchars($stubTitle); ?></h1>
        <p><?php echo htmlspecialchars(isset($stubDesc) ? $stubDesc : 'Цей розділ ще в розробці.'); ?></p>
        <span class="badge badge-orange">В розробці</span>
    </div>
</div>
<style>
.finance-stub {
    display: flex; flex-direction: column; align-items: center;
    text-align: center; padding: 80px 20px;
}
.finance-stub-icon {
    width: 72px; height: 72px; margin-bottom: 20px;
    color: var(--orange); opacity: .6;
}
.finance-stub-icon svg { width: 100%; height: 100%; }
.finance-stub h1 { margin: 0 0 10px; font-size: 24px; font-weight: 700; }
.finance-stub p  { margin: 0 0 20px; color: var(--text-muted); font-size: 14px; max-width: 360px; }
</style>
<?php require_once __DIR__ . '/../shared/layout_end.php'; ?>
