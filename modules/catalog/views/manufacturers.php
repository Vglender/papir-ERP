<?php
$title = 'Виробники';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
/* ── Page layout ───────────────────────────────────────────────────────────── */
.mfr-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    align-items: start;
}
@media (max-width: 1000px) {
    .mfr-layout { grid-template-columns: 1fr; }
    .mfr-panel  { position: static !important; }
}

/* ── Sticky panel ──────────────────────────────────────────────────────────── */
.mfr-panel {
    position: sticky;
    top: 16px;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #cfd8e3 transparent;
}
.mfr-panel::-webkit-scrollbar { width: 6px; }
.mfr-panel::-webkit-scrollbar-thumb { background: #cfd8e3; border-radius: 6px; }

/* ── Panel content ─────────────────────────────────────────────────────────── */
.panel-head {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.panel-head h2 { margin: 0; font-size: 16px; font-weight: 600; }
.panel-close {
    background: none; border: none; cursor: pointer;
    color: var(--text-faint); font-size: 20px; line-height: 1; padding: 0;
}
.panel-close:hover { color: var(--text); }

.panel-stats {
    display: flex; gap: 16px;
    padding: 10px 0 14px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
}
.stat-item { text-align: center; }
.stat-num  { font-size: 22px; font-weight: 600; color: var(--blue); line-height: 1; }
.stat-lbl  { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

.panel-actions {
    display: flex; gap: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
    margin-top: 4px;
}

/* ── Empty panel ───────────────────────────────────────────────────────────── */
.panel-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    min-height: 220px; color: var(--text-faint);
    text-align: center; padding: 32px;
}
.panel-empty-icon { font-size: 40px; margin-bottom: 12px; opacity: .4; }
.panel-empty p    { margin: 0; font-size: 13px; }

/* ── Table row selected ────────────────────────────────────────────────────── */
.crm-table tbody tr.row-selected td { background: var(--blue-bg) !important; }

/* ── Thumbnail ─────────────────────────────────────────────────────────────── */
.mfr-thumb {
    width: 32px; height: 32px; object-fit: contain;
    border-radius: 4px; border: 1px solid var(--border);
    vertical-align: middle;
}
.mfr-thumb-empty {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; background: #f0f4f8;
    border-radius: 4px; color: #ccc; font-size: 14px;
}

/* ── Image preview in panel ────────────────────────────────────────────────── */
.img-preview {
    width: 100%; max-height: 160px; object-fit: contain;
    border-radius: var(--radius); border: 1px solid var(--border);
    margin-bottom: 12px; display: block;
}
</style>

<div class="page-wrap-sm">

    <div class="breadcrumb">
        <a href="/catalog">Каталог</a> / Виробники
    </div>

    <div class="page-head">
        <h1>Виробники <span class="page-title-count">(<?php echo $total; ?>)</span></h1>
        <a href="<?php echo ViewHelper::h(mfrUrl($page, $search, 'new')); ?>"
           class="btn btn-primary">+ Додати</a>
    </div>

    <div class="toolbar">
        <form method="get" action="/manufacturers" style="display:flex;gap:8px;align-items:center">
            <?php if ($selected !== null) { ?>
                <input type="hidden" name="selected" value="<?php echo ViewHelper::h($selected); ?>">
            <?php } ?>
            <input class="search-input" type="text" name="q"
                   value="<?php echo ViewHelper::h($search); ?>"
                   placeholder="Пошук по назві..." autocomplete="off">
            <?php if ($search !== '') { ?>
                <a href="/manufacturers<?php echo $selected !== null ? '?selected=' . urlencode($selected) : ''; ?>"
                   style="font-size:13px;color:var(--text-faint);text-decoration:none">✕</a>
            <?php } ?>
        </form>
    </div>

    <div class="mfr-layout">

        <!-- ── Список ────────────────────────────────────────────── -->
        <div>
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width:40px"></th>
                        <th>Назва</th>
                        <th style="width:80px">Активних</th>
                        <th style="width:70px">Всього</th>
                        <th style="width:70px">off_id</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($manufacturers as $m) {
                    $isSelected = ($selected !== null && (int)$selected === (int)$m['manufacturer_id']);
                    $rowUrl     = mfrUrl($page, $search, (int)$m['manufacturer_id']);
                ?>
                    <tr class="clickable <?php echo $isSelected ? 'row-selected' : ''; ?>"
                        onclick="window.location='<?php echo ViewHelper::h($rowUrl); ?>'">
                        <td>
                            <?php if (!empty($m['image'])) { ?>
                                <img class="mfr-thumb" src="<?php echo ViewHelper::h($m['image']); ?>" alt="">
                            <?php } else { ?>
                                <span class="mfr-thumb-empty">&#128250;</span>
                            <?php } ?>
                        </td>
                        <td><strong><?php echo ViewHelper::h($m['name']); ?></strong></td>
                        <td>
                            <?php if ((int)$m['active_products'] > 0) { ?>
                                <span class="text-green fw-600"><?php echo (int)$m['active_products']; ?></span>
                            <?php } else { ?>
                                <span class="text-faint">0</span>
                            <?php } ?>
                        </td>
                        <td class="text-muted"><?php echo (int)$m['total_products']; ?></td>
                        <td class="text-faint fs-12"><?php echo $m['off_id'] ? (int)$m['off_id'] : '—'; ?></td>
                    </tr>
                <?php } ?>
                <?php if (empty($manufacturers)) { ?>
                    <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-faint)">Нічого не знайдено</td></tr>
                <?php } ?>
                </tbody>
            </table>

            <?php if ($pages > 1) { ?>
            <div class="pagination">
                <?php
                $qParam = $search !== '' ? '&q=' . urlencode($search) : '';
                $selParam = $selected !== null ? '&selected=' . urlencode($selected) : '';

                echo $page > 1
                    ? '<a href="/manufacturers?page=' . ($page-1) . $qParam . $selParam . '">&#8592;</a>'
                    : '<span style="opacity:.3">&#8592;</span>';

                for ($i = 1; $i <= $pages; $i++) {
                    if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
                        echo $i === $page
                            ? '<span class="cur">' . $i . '</span>'
                            : '<a href="/manufacturers?page=' . $i . $qParam . $selParam . '">' . $i . '</a>';
                    } elseif (abs($i - $page) === 3) {
                        echo '<span class="dots">…</span>';
                    }
                }

                echo $page < $pages
                    ? '<a href="/manufacturers?page=' . ($page+1) . $qParam . $selParam . '">&#8594;</a>'
                    : '<span style="opacity:.3">&#8594;</span>';
                ?>
            </div>
            <?php } ?>
        </div>

        <!-- ── Панель ─────────────────────────────────────────────── -->
        <div class="mfr-panel">
            <div class="card">
            <?php if ($panel !== null) {
                $isNew = (int)$panel['manufacturer_id'] === 0;
            ?>
                <div class="panel-head">
                    <h2><?php echo $isNew ? 'Новий виробник' : 'Редагувати'; ?></h2>
                    <a href="<?php echo ViewHelper::h(mfrUrl($page, $search, null)); ?>"
                       class="panel-close" title="Закрити">&#10005;</a>
                </div>

                <?php if (!$isNew) { ?>
                <div class="panel-stats">
                    <div class="stat-item">
                        <div class="stat-num"><?php echo (int)$panel['active_products']; ?></div>
                        <div class="stat-lbl">активних товарів</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num text-muted"><?php echo (int)$panel['total_products']; ?></div>
                        <div class="stat-lbl">всього товарів</div>
                    </div>
                </div>
                <?php } ?>

                <form id="mfrForm">
                    <input type="hidden" id="fId" value="<?php echo (int)$panel['manufacturer_id']; ?>">

                    <?php if (!empty($panel['image'])) { ?>
                        <img class="img-preview" id="imgPreview" src="<?php echo ViewHelper::h($panel['image']); ?>" alt="">
                    <?php } else { ?>
                        <img class="img-preview" id="imgPreview" src="" alt="" style="display:none">
                    <?php } ?>

                    <div class="form-row">
                        <label>Назва *</label>
                        <input type="text" id="fName" maxlength="128"
                               value="<?php echo ViewHelper::h($panel['name']); ?>"
                               placeholder="Назва виробника">
                    </div>
                    <div class="form-row">
                        <label>Зображення (URL)</label>
                        <input type="text" id="fImage" maxlength="512"
                               value="<?php echo ViewHelper::h($panel['image'] ? $panel['image'] : ''); ?>"
                               placeholder="https://...">
                    </div>
                    <div class="form-row">
                        <label>Опис</label>
                        <textarea id="fDesc" maxlength="2000"
                                  placeholder="Опис (необов'язково)"><?php echo ViewHelper::h($panel['description'] ? $panel['description'] : ''); ?></textarea>
                    </div>
                    <div class="form-row">
                        <label>off_id <span class="text-faint">(oc_manufacturer в off)</span></label>
                        <input type="text" id="fOffId" maxlength="10"
                               value="<?php echo $panel['off_id'] ? (int)$panel['off_id'] : ''; ?>"
                               placeholder="">
                    </div>

                    <div id="fError" class="modal-error" style="display:none"></div>

                    <div class="panel-actions">
                        <button type="button" class="btn btn-primary" id="btnSave" style="flex:1">Зберегти</button>
                        <?php if (!$isNew && (int)$panel['total_products'] === 0) { ?>
                            <button type="button" class="btn btn-danger" id="btnDelete">Видалити</button>
                        <?php } elseif (!$isNew) { ?>
                            <button type="button" class="btn btn-danger" disabled
                                    title="Є прив'язані товари (<?php echo (int)$panel['total_products']; ?>)">Видалити</button>
                        <?php } ?>
                    </div>
                </form>

            <?php } else { ?>
                <div class="panel-empty">
                    <div class="panel-empty-icon">&#128190;</div>
                    <p>Оберіть виробника<br>або натисніть <strong>+ Додати</strong></p>
                </div>
            <?php } ?>
            </div>
        </div>

    </div><!-- /.mfr-layout -->

</div><!-- /.page-wrap-sm -->

<div class="toast" id="toast"></div>

<script>
function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 1800);
}

// Image URL preview
(function() {
    var fImage  = document.getElementById('fImage');
    var preview = document.getElementById('imgPreview');
    if (!fImage || !preview) return;

    fImage.addEventListener('input', function() {
        var url = this.value.trim();
        if (url) {
            preview.src = url;
            preview.style.display = 'block';
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    });
})();

// Save
(function() {
    var btnSave = document.getElementById('btnSave');
    if (!btnSave) return;

    btnSave.addEventListener('click', function() {
        var id    = document.getElementById('fId').value;
        var name  = document.getElementById('fName').value.trim();
        var err   = document.getElementById('fError');

        if (!name) {
            err.textContent = 'Введіть назву виробника';
            err.style.display = 'block';
            document.getElementById('fName').focus();
            return;
        }
        err.style.display = 'none';
        btnSave.disabled = true;

        var body = 'manufacturer_id=' + encodeURIComponent(id)
            + '&name='        + encodeURIComponent(name)
            + '&description=' + encodeURIComponent(document.getElementById('fDesc').value.trim())
            + '&image='       + encodeURIComponent(document.getElementById('fImage').value.trim())
            + '&off_id='      + encodeURIComponent(document.getElementById('fOffId').value.trim());

        fetch('/catalog/api/save_manufacturer_record', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btnSave.disabled = false;
            if (!d.ok) {
                err.textContent = d.error || 'Помилка збереження';
                err.style.display = 'block';
                return;
            }
            showToast('Збережено');
            // Redirect to same page with new selected ID (reloads data)
            setTimeout(function() {
                var url = new URL(window.location.href);
                url.searchParams.set('selected', d.manufacturer_id);
                window.location = url.toString();
            }, 600);
        })
        .catch(function() {
            btnSave.disabled = false;
            err.textContent = 'Помилка мережі';
            err.style.display = 'block';
        });
    });
})();

// Delete
(function() {
    var btnDel = document.getElementById('btnDelete');
    if (!btnDel) return;

    btnDel.addEventListener('click', function() {
        var id   = document.getElementById('fId').value;
        var name = document.getElementById('fName').value;
        if (!confirm('Видалити виробника "' + name + '"?')) return;

        fetch('/catalog/api/delete_manufacturer', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: 'manufacturer_id=' + id
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) { alert(d.error || 'Помилка видалення'); return; }
            showToast('Видалено');
            setTimeout(function() {
                var url = new URL(window.location.href);
                url.searchParams.delete('selected');
                window.location = url.toString();
            }, 600);
        })
        .catch(function() { alert('Помилка мережі'); });
    });
})();
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>

<?php
// ── Helper ──────────────────────────────────────────────────────────────────
function mfrUrl($page, $search, $selected) {
    $params = array();
    if ($page > 1)     $params[] = 'page=' . (int)$page;
    if ($search !== '') $params[] = 'q=' . urlencode($search);
    if ($selected !== null) $params[] = 'selected=' . urlencode($selected);
    return '/manufacturers' . ($params ? '?' . implode('&', $params) : '');
}
?>
