<?php
$selId  = $selected;
$selOrg = $org;
?>
<style>
.org-wrap       { max-width: 1200px; margin: 0 auto; padding: 20px 24px; }
.org-toolbar    { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.org-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex-shrink: 0; }
.org-toolbar .btn { height: 34px; padding: 0 14px; }

.org-layout     { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }

/* List */
.org-list-wrap  { }
.org-list-table { width: 100%; border-collapse: collapse; }
.org-list-table td { padding: 9px 12px; font-size: 13px; border-bottom: 1px solid #f1f3f6; cursor: pointer; }
.org-list-table tr:hover td { background: #f8fafc; }
.org-list-table tr.row-selected td { background: #eef6ff; }
.org-name       { font-weight: 600; color: #1e293b; }
.org-alias      { font-size: 11px; font-weight: 700; letter-spacing: .04em;
                  background: #e2e8f0; color: #475569; border-radius: 4px;
                  padding: 1px 6px; margin-left: 6px; }
.org-sub        { font-size: 12px; color: #64748b; margin-top: 2px; }

/* Panel */
.org-panel      { position: sticky; top: 20px; }
.org-panel .card{ padding: 0; overflow: hidden; }

.org-tabs       { display: flex; border-bottom: 1px solid #e5e7eb; padding: 0 16px; }
.org-tab        { padding: 11px 14px; font-size: 13px; font-weight: 500; color: #64748b;
                  border: none; background: none; cursor: pointer;
                  border-bottom: 2px solid transparent; margin-bottom: -1px; }
.org-tab:hover  { color: #1e293b; }
.org-tab.active { color: #0d9488; border-bottom-color: #0d9488; }

.org-tab-pane   { display: none; padding: 20px 20px 8px; }
.org-tab-pane.active { display: block; }

.org-form-row   { margin-bottom: 14px; }
.org-form-row label { display: block; font-size: 12px; font-weight: 600;
                      color: #64748b; margin-bottom: 4px; }
.org-form-row input,
.org-form-row select,
.org-form-row textarea { width: 100%; font-size: 13px; }
.org-form-row textarea  { resize: vertical; min-height: 60px; }
.org-form-2col  { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.org-form-3col  { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.org-panel-foot { padding: 12px 20px 16px; border-top: 1px solid #f1f3f6;
                  display: flex; gap: 8px; align-items: center; }
.org-panel-foot .btn { height: 32px; padding: 0 16px; font-size: 13px; }

/* Bank accounts */
.bank-list      { margin-bottom: 16px; }
.bank-item      { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px;
                  padding: 10px 12px; margin-bottom: 8px; font-size: 13px; position: relative; }
.bank-item-head { display: flex; align-items: center; gap: 8px; font-weight: 600; color: #1e293b; }
.bank-item-sub  { color: #64748b; font-size: 12px; margin-top: 3px; }
.bank-item-del  { position: absolute; top: 8px; right: 8px; background: none; border: none;
                  cursor: pointer; color: #94a3b8; padding: 2px 4px; }
.bank-item-del:hover { color: #ef4444; }
.bank-add-form  { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                  padding: 14px; margin-top: 8px; display: none; }
.bank-add-form.open { display: block; }

/* Images */
.asset-grid     { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 16px; }
.asset-zone     { border: 2px dashed #d1d5db; border-radius: 10px; padding: 14px 10px;
                  text-align: center; cursor: pointer; transition: border-color .15s; position: relative; }
.asset-zone:hover { border-color: #0d9488; }
.asset-zone.has-img { border-style: solid; border-color: #99f6e4; }
.asset-label    { font-size: 11px; font-weight: 700; color: #64748b;
                  text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
.asset-img      { max-width: 100%; max-height: 80px; border-radius: 4px;
                  object-fit: contain; display: none; }
.asset-zone.has-img .asset-img   { display: block; margin: 0 auto 8px; }
.asset-placeholder { font-size: 22px; margin-bottom: 6px; color: #cbd5e1; }
.asset-zone.has-img .asset-placeholder { display: none; }
.asset-hint     { font-size: 11px; color: #94a3b8; }
.asset-del      { position: absolute; top: 6px; right: 6px; background: #fff; border: 1px solid #e5e7eb;
                  border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 11px;
                  display: none; align-items: center; justify-content: center; color: #ef4444; }
.asset-zone.has-img .asset-del { display: flex; }
.asset-input    { display: none; }

/* Empty state */
.org-empty      { padding: 40px 20px; text-align: center; color: #94a3b8; font-size: 14px; }
</style>

<div class="org-wrap">

    <div class="org-toolbar">
        <h1>Організації</h1>
        <button class="btn btn-primary" id="orgAddBtn" type="button">+ Додати</button>
    </div>

    <div class="org-layout">

        <!-- ── List ─────────────────────────────────── -->
        <div class="org-list-wrap card">
            <?php if (empty($orgs)): ?>
                <div class="org-empty">Ще немає організацій</div>
            <?php else: ?>
            <table class="org-list-table">
                <tbody>
                <?php foreach ($orgs as $o):
                    $cls = ($o['id'] == $selId) ? ' row-selected' : '';
                ?>
                <tr class="<?php echo $cls; ?>"
                    data-id="<?php echo (int)$o['id']; ?>"
                    onclick="location.href='/system/organizations?selected=<?php echo (int)$o['id']; ?>'">
                    <td>
                        <div class="org-name">
                            <?php echo ViewHelper::h($o['name']); ?>
                            <?php if ($o['alias']): ?>
                                <span class="org-alias"><?php echo ViewHelper::h($o['alias']); ?></span>
                            <?php endif; ?>
                            <?php if ($o['is_default']): ?>
                                <span class="badge badge-green" style="margin-left:4px;font-size:10px">default</span>
                            <?php endif; ?>
                            <?php if (!$o['status']): ?>
                                <span class="badge badge-gray" style="margin-left:4px">архів</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($o['director_name']): ?>
                            <div class="org-sub"><?php echo ViewHelper::h($o['director_name']); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ── Panel ─────────────────────────────────── -->
        <div class="org-panel" id="orgPanel">
            <?php if ($selOrg): ?>
            <?php include __DIR__ . '/organizations_panel.php'; ?>
            <?php else: ?>
            <div class="card org-empty" style="padding:60px 20px">
                Оберіть організацію або натисніть «+ Додати»
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /org-layout -->
</div>

<script>
(function () {
    // ── Add new ───────────────────────────────────────────────────────────────
    document.getElementById('orgAddBtn').addEventListener('click', function () {
        window.location.href = '/system/organizations?selected=0&new=1';
    });
}());
</script>

<?php
// New org: show empty panel immediately
if (isset($_GET['new']) && $_GET['new'] == '1' && !$selOrg):
?>
<script>
(function () {
    var panel = document.getElementById('orgPanel');
    panel.innerHTML = <?php
        ob_start();
        $selOrg = array('id'=>0,'name'=>'','short_name'=>'','alias'=>'','code'=>'','okpo'=>'',
                        'inn'=>'','vat_number'=>'','legal_address'=>'','actual_address'=>'',
                        'director_name'=>'','director_title'=>'','phone'=>'','email'=>'',
                        'website'=>'','description'=>'','status'=>1,
                        'logo_path'=>null,'stamp_path'=>null,'signature_path'=>null,
                        'bank_accounts'=>array());
        include __DIR__ . '/organizations_panel.php';
        $html = ob_get_clean();
        echo json_encode($html);
    ?>;
    window.orgPanelInit && window.orgPanelInit();
}());
</script>
<?php endif; ?>