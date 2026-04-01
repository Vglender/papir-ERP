<?php
// Quick layout test - delete after debugging
require_once __DIR__ . '/modules/database/database.php';
require_once __DIR__ . '/src/ViewHelper.php';
$title = 'TEST'; $activeNav = 'docs'; $subNav = 'templates'; $bodyClass = 'ws-body';
require_once __DIR__ . '/modules/shared/layout.php';
?>
<style>
.test-page { display: flex; flex: 1; min-height: 0; overflow: hidden; background: red; }
.test-left  { width: 200px; background: blue; }
.test-right { flex: 1; background: green; }
</style>
<div class="test-page">
  <div class="test-left">LEFT</div>
  <div class="test-right">RIGHT</div>
</div>
<?php require_once __DIR__ . '/modules/shared/layout_end.php'; ?>
