<?php
$title     = 'Категорії';
$activeNav = 'catalog';
$subNav    = 'categories';
require_once __DIR__ . '/../../shared/layout.php';
?>
<style>
.cats-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 20px;
    align-items: start;
}
.cats-tree-panel {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: #fff;
}
.cats-form-panel {
    position: sticky;
    top: var(--sticky-top);
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.form-lang-tabs { display: flex; gap: 0; margin-bottom: 14px; border-bottom: 2px solid var(--border); }
.form-lang-tab {
    padding: 6px 18px; font-size: 13px; font-weight: 500; cursor: pointer;
    color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -2px;
}
.form-lang-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
.form-lang-pane { display: none; }
.form-lang-pane.active { display: block; }

/* Site + Lang tabs for SEO card */
.seo-site-tabs { display: flex; gap: 0; margin-bottom: 12px; border-bottom: 2px solid var(--border); }
.seo-site-tab {
    padding: 6px 18px; font-size: 13px; font-weight: 500; cursor: pointer;
    color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -2px;
}
.seo-site-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
.seo-site-tab.unmapped { color: var(--text-faint); }
.seo-site-tab.unmapped.active { color: var(--text-muted); border-bottom-color: var(--border); }
.seo-site-pane { display: none; }
.seo-site-pane.active { display: block; }
.seo-add-panel { padding: 20px 0 10px; display: flex; flex-direction: column; gap: 12px; align-items: flex-start; }
.seo-add-msg { font-size: 13px; color: var(--text-muted); }
.seo-lang-tabs { display: flex; gap: 0; margin-bottom: 12px; border-bottom: 1px solid var(--border); }
.seo-lang-tab {
    padding: 5px 14px; font-size: 12px; font-weight: 500; cursor: pointer;
    color: var(--text-muted); border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.seo-lang-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
.seo-lang-pane { display: none; }
.seo-lang-pane.active { display: block; }

.form-row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.form-row label { font-size: 12px; color: var(--text-muted); font-weight: 500; }
.form-row input[type=text],
.form-row input[type=number],
.form-row textarea {
    padding: 7px 10px; border: 1px solid var(--border-input);
    border-radius: var(--radius); font-size: 13px; font-family: var(--font);
    outline: none; width: 100%; box-sizing: border-box;
}
.form-row input:focus, .form-row textarea:focus { border-color: var(--blue-light); }
.form-row input[readonly] { background: #f8fafc; color: var(--text-muted); cursor: default; }
.form-row textarea { resize: vertical; min-height: 60px; }
.desc-preview {
    padding: 10px 12px; border: 1px solid var(--border-input); border-radius: var(--radius);
    min-height: 60px; font-size: 13px; line-height: 1.6; background: #fafcff; cursor: pointer;
    word-break: break-word;
}
.desc-preview h1,.desc-preview h2,.desc-preview h3 { font-size: 14px; font-weight: 600; margin: 6px 0 4px; }
.desc-preview p  { margin: 0 0 6px; }
.desc-preview ul,.desc-preview ol { margin: 0 0 6px; padding-left: 20px; }
.desc-preview li { margin-bottom: 2px; }
.desc-preview strong { font-weight: 600; }
.desc-preview em    { font-style: italic; }
.desc-toggle { font-size: 11px; color: var(--blue); cursor: pointer; margin-left: 6px; text-decoration: underline; }
.desc-empty  { color: var(--text-faint); font-size: 12px; font-style: italic; }
.form-row-inline { display: flex; gap: 10px; align-items: center; }
.cat-info-row { display: flex; gap: 6px; align-items: center; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
.cat-info-row strong { color: var(--text); }
.status-toggle { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
.status-toggle input { width: 16px; height: 16px; cursor: pointer; }
.panel-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-height: 200px; color: var(--text-faint); text-align: center; padding: 32px;
}
.panel-empty-icon { font-size: 40px; margin-bottom: 12px; opacity: .3; }
.form-error { color: var(--red); font-size: 12px; margin-top: 4px; display: none; }
.cat-url-link { font-size: 12px; color: var(--blue); word-break: break-all; }
.cat-url-link:empty { display: none; }
.cat-url-empty { font-size: 12px; color: var(--text-faint); font-style: italic; }
#cardAi textarea { min-height: 68px; }
/* AI generation */
.btn-icon-ai { background:none; border:none; padding:4px; cursor:pointer; color:#f59e0b; line-height:0; border-radius:4px; transition:color .15s; }
.btn-icon-ai:hover { color:#d97706; }
.ai-gen-opts { display:flex; gap:16px; margin-bottom:14px; }
.ai-gen-opts-group { flex:1; }
.ai-gen-opts-group > b { display:block; font-size:12px; color:var(--text-muted); font-weight:500; margin-bottom:6px; }
.ai-gen-opt-row { display:flex; align-items:center; gap:6px; font-size:13px; margin-bottom:5px; }
.ai-gen-opt-row label { cursor:pointer; }
.ai-gen-prompt-toggle { font-size:12px; color:var(--blue-light); cursor:pointer; user-select:none; margin-bottom:6px; }
.ai-gen-prompt-box { font-size:11px; font-family:monospace; background:#f9fafb; border:1px solid var(--border); border-radius:var(--radius); padding:8px 10px; white-space:pre-wrap; max-height:180px; overflow-y:auto; margin-bottom:10px; display:none; }
.ai-gen-status { font-size:12px; color:var(--text-muted); margin-top:8px; min-height:18px; }
.ai-gen-status.ok  { color:#16a34a; }
.ai-gen-status.err { color:#dc2626; }
.seo-site-divider { border: none; border-top: 1px solid var(--border); margin: 14px 0; }
.seo-no-sync { font-size: 11px; color: var(--text-faint); font-style: italic; padding: 3px 0 10px; }
.seo-top-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.seo-site-url { font-size: 12px; color: var(--blue-light); white-space: nowrap; }
.seo-site-url-empty { font-size: 12px; color: var(--text-faint); font-style: italic; }
.sort-row { display: flex; align-items: center; gap: 8px; margin-top: 14px; }
.sort-order-input { width: 54px; padding: 6px 16px 6px 7px; border: 1px solid var(--border-input); border-radius: var(--radius); font-size: 13px; outline: none; box-sizing: border-box; }
.sort-row label { font-size: 13px; color: var(--text-muted); white-space: nowrap; }
.card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 14px; }
.card-head-left { flex: 1; min-width: 0; }
.card-head-title { font-size: 15px; font-weight: 600; color: var(--text); }
.btn-icon {
    width: 30px; height: 30px; flex-shrink: 0;
    border: 1px solid var(--blue); border-radius: var(--radius);
    background: var(--blue); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: #fff; transition: background .15s, border-color .15s;
    padding: 0;
}
.btn-icon:hover { background: var(--blue-dark); border-color: var(--blue-dark); }
.btn-icon:disabled { opacity: .4; cursor: default; pointer-events: none; }

/* Image slider */
.cat-basic-layout { display: grid; grid-template-columns: 320px 1fr; gap: 16px; align-items: start; margin-bottom: 12px; }
.cat-img-section { margin-bottom: 0; }
.cat-img-slider { position: relative; width: 100%; }
.cat-img-track { display: flex; overflow: hidden; border-radius: var(--radius); }
.cat-img-slide { flex: 0 0 100%; position: relative; }
.cat-img-slide img { width: 100%; height: 240px; object-fit: contain; display: block; cursor: zoom-in; }
.cat-img-del, .cat-img-rep {
    position: absolute; top: 6px;
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(0,0,0,.5); color: #fff; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-size: 14px; line-height: 1;
    opacity: 0; transition: opacity .15s;
}
.cat-img-del { right: 6px; }
.cat-img-rep { right: 36px; font-size: 12px; }
.cat-img-slide:hover .cat-img-del,
.cat-img-slide:hover .cat-img-rep { opacity: 1; }
.cat-img-del:hover { background: var(--red); }
.cat-img-rep:hover { background: var(--blue); }
.cat-img-nav { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; }
.cat-img-btn {
    width: 26px; height: 26px; border: 1px solid var(--border); border-radius: var(--radius);
    background: #fff; cursor: pointer; font-size: 16px; line-height: 1;
    display: flex; align-items: center; justify-content: center; color: var(--text-muted);
}
.cat-img-btn:hover { border-color: var(--blue); color: var(--blue); }
.cat-img-dots { display: flex; gap: 4px; }
.cat-img-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--border); cursor: pointer; }
.cat-img-dot.active { background: var(--blue); }
.cat-img-empty { width: 100%; height: 180px; border: 2px dashed var(--border); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: var(--text-faint); font-size: 13px; box-sizing: border-box; }
.cat-img-upload { margin-top: 8px; }
.cat-img-upload input[type=file] { display: none; }
.cat-img-uploading { font-size: 12px; color: var(--text-muted); margin-top: 4px; display: none; }

/* ── Toolbar ── */
.cats-toolbar {
    display: flex; align-items: center;
    gap: 8px; margin-bottom: 10px;
}
.cats-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; }
/* ── Filter-bar chip-search sizing ── */
.cats-chip-wrap { width: 240px; flex-shrink: 0; }
.filter-bar .chip-input { min-height: 30px; max-height: 30px; overflow: hidden; font-size: 12px; }
.filter-bar .chip-typer { font-size: 12px; }
.filter-bar .chip { font-size: 11px; padding: 1px 4px; }
</style>

<div class="page-wrap">

    <!-- ── Toolbar ── -->
    <div class="cats-toolbar">
        <h1>Категорії</h1>
    </div>

    <!-- ── Filter bar ── -->
    <div class="filter-bar">
        <div class="filter-bar-group">
            <span class="filter-bar-label">Категорія</span>
            <div class="cats-chip-wrap">
                <div class="chip-input" id="catChipBox">
                    <input type="text" class="chip-typer" id="catChipTyper" placeholder="Пошук категорії…" autocomplete="off">
                    <div class="chip-actions">
                        <button type="button" class="chip-act-btn chip-act-clear hidden" id="catChipClear" title="Очистити">&#x2715;</button>
                        <button type="button" class="chip-act-btn chip-act-submit" id="catChipSubmit" title="Пошук">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M10 10l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
                <input type="hidden" id="catSearchHidden" value="">
            </div>
        </div>
        <div class="filter-bar-sep"></div>
        <div class="filter-bar-group">
            <span class="filter-bar-label">Статус</span>
            <label class="filter-pill active" id="pillAll">
                <input type="radio" name="catStatusFilter" value="all" checked> Всі
            </label>
            <label class="filter-pill" id="pillActive">
                <input type="radio" name="catStatusFilter" value="active"> Активні
            </label>
            <label class="filter-pill" id="pillInactive">
                <input type="radio" name="catStatusFilter" value="inactive"> Неактивні
            </label>
        </div>
        <button type="button" class="filter-bar-gear" title="Налаштувати фільтри" id="catsFilterGear">
            <svg viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                <path d="M8 1.5v1M8 13.5v1M1.5 8h1M13.5 8h1M3.4 3.4l.7.7M11.9 11.9l.7.7M11.9 3.4l-.7.7M4.1 11.9l-.7.7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div class="cats-layout">

        <!-- ── Tree panel ── -->
        <div class="cats-tree-panel" id="catsTreePanel"></div>

        <!-- ── Form panel (two cards) ── -->
        <div class="cats-form-panel" id="catsFormOuter">

            <!-- ── Empty state (no selection) ── -->
            <div class="card" id="panelEmpty">
                <div class="panel-empty">
                    <div class="panel-empty-icon">&#128193;</div>
                    <p>Оберіть категорію в дереві</p>
                </div>
            </div>

            <!-- ── Card 1: Basic info ── -->
            <div class="card" id="cardBasic" style="display:none">

                <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
                    <button type="button" class="btn-icon" id="btnSave" title="Зберегти">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                    </button>
                </div>

                <div class="cat-basic-layout">

                    <!-- Left: image -->
                    <div class="cat-img-section" id="catImgSection">
                        <div class="cat-img-slider" id="catImgSlider" style="display:none">
                            <div class="cat-img-track" id="catImgTrack"></div>
                            <div class="cat-img-nav" id="catImgNav" style="display:none">
                                <button type="button" class="cat-img-btn" id="catImgPrev">&#8249;</button>
                                <div class="cat-img-dots" id="catImgDots"></div>
                                <button type="button" class="cat-img-btn" id="catImgNext">&#8250;</button>
                            </div>
                        </div>
                        <div class="cat-img-empty" id="catImgEmpty">Фото відсутнє</div>
                        <div class="cat-img-upload" style="margin-top:8px">
                            <button type="button" class="btn btn-ghost btn-sm" id="catImgUploadBtn">+ Фото</button>
                            <input type="file" id="catImgFile" accept="image/jpeg,image/png,image/webp,image/gif">
                            <input type="file" id="catImgReplaceFile" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
                            <div class="cat-img-uploading" id="catImgUploading">Завантаження...</div>
                        </div>
                    </div>

                    <!-- Right: fields -->
                    <div>
                        <div class="cat-info-row">
                            <span>ID:</span> <strong id="infoId">—</strong>
                            <span style="margin-left:12px">Батьківська:</span> <strong id="infoParent">—</strong>
                        </div>
                        <div class="cat-info-row" style="margin-bottom:8px">
                            <span>Off:</span> <strong id="infoOff">—</strong>
                            <span style="margin-left:12px">MFF:</span> <strong id="infoMff">—</strong>
                        </div>

                        <div style="margin-bottom:10px">
                            <label class="status-toggle">
                                <input type="checkbox" id="catStatus"> Активна
                            </label>
                        </div>

                        <!-- Language tabs for names -->
                        <div class="form-lang-tabs">
                            <div class="form-lang-tab active" data-pane="paneUa">UA</div>
                            <div class="form-lang-tab" data-pane="paneRu">RU</div>
                        </div>

                        <!-- UA pane -->
                        <div class="form-lang-pane active" id="paneUa">
                            <div class="form-row">
                                <label>Назва (UA) *</label>
                                <textarea id="nameUa" rows="2" autocomplete="off" style="resize:vertical;min-height:32px;height:32px"></textarea>
                            </div>
                            <div class="form-row">
                                <label>Опис (UA) <span class="desc-toggle" onclick="toggleDescEdit('Ua')">редагувати</span></label>
                                <div id="descPreviewUa" class="desc-preview" onclick="toggleDescEdit('Ua')"><span class="desc-empty">Опис відсутній</span></div>
                                <textarea id="descUa" rows="6" style="display:none" placeholder="HTML-опис категорії (UA)…" onblur="updateDescPreview('Ua')"></textarea>
                            </div>
                        </div>

                        <!-- RU pane -->
                        <div class="form-lang-pane" id="paneRu">
                            <div class="form-row">
                                <label>Назва (RU)</label>
                                <textarea id="nameRu" rows="2" autocomplete="off" style="resize:vertical;min-height:32px;height:32px"></textarea>
                            </div>
                            <div class="form-row">
                                <label>Опис (RU) <span class="desc-toggle" onclick="toggleDescEdit('Ru')">редагувати</span></label>
                                <div id="descPreviewRu" class="desc-preview" onclick="toggleDescEdit('Ru')"><span class="desc-empty">Опис відсутній</span></div>
                                <textarea id="descRu" rows="6" style="display:none" placeholder="HTML-опис категорії (RU)…" onblur="updateDescPreview('Ru')"></textarea>
                            </div>
                        </div>

                        <!-- Sort order -->
                        <div class="sort-row">
                            <label>Порядок:</label>
                            <input type="number" id="sortOrder" class="sort-order-input">
                        </div>
                    </div>

                </div>

                <div id="formError" class="form-error"></div>
            </div>

            <!-- ── Card 2: SEO ── -->
            <div class="card" id="cardSeo" style="display:none">
                <div class="card-head">
                    <div style="display:flex;align-items:center;gap:8px">
                        <div class="card-head-title">SEO</div>
                        <button type="button" class="btn-icon-ai" id="btnAiGenSeo" title="Генерувати SEO через AI">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </button>
                    </div>
                    <button type="button" class="btn-icon" id="btnSaveSeo" title="Зберегти SEO">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                    </button>
                </div>

                <!-- Site tabs (built by JS) -->
                <div class="seo-site-tabs" id="seoSiteTabs"></div>

                <!-- Site panes (built by JS) -->
                <div id="seoSitePanes"></div>

                <div id="seoFormError" class="form-error"></div>
            </div>

            <!-- ── Card 3: AI instructions ── -->
            <div class="card" id="cardAi" style="display:none">
                <div class="card-head">
                    <div class="card-head-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px;color:#f59e0b"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>ШІ — Інструкції
                    </div>
                    <button type="button" class="btn-icon" id="btnSaveAi" title="Зберегти AI інструкції">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                    </button>
                </div>
                <div class="form-row">
                    <label>Контекст категорії</label>
                    <textarea id="aiContext" rows="3" placeholder="Опишіть категорію для ШІ — що це за товари, особливості…"></textarea>
                </div>
                <div class="form-row">
                    <label>Інструкція для ШІ</label>
                    <textarea id="aiInstruction" rows="3" placeholder="Як саме генерувати контент — стиль, акценти, що підкреслити…"></textarea>
                </div>
                <div id="aiFormError" class="form-error"></div>
            </div>

        </div><!-- /cats-form-panel -->

    </div>
</div>

<!-- ── AI Generation Modal ──────────────────────────────────────────────── -->
<div class="modal-overlay" id="aiGenModal" style="display:none">
    <div class="modal-box" style="max-width:500px">
        <div class="modal-head">
            <span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                Генерація SEO-контенту
            </span>
            <button type="button" class="modal-close" id="aiGenModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <div class="ai-gen-opts">
                <div class="ai-gen-opts-group">
                    <b>Сайти</b>
                    <div id="aiGenSitesCont"></div>
                </div>
                <div class="ai-gen-opts-group">
                    <b>Мови</b>
                    <div id="aiGenLangsCont"></div>
                </div>
            </div>
            <div class="form-row">
                <label style="font-size:12px;color:var(--text-muted);font-weight:500;display:block;margin-bottom:4px">Нотатка (опціонально)</label>
                <textarea id="aiGenNote" rows="2" placeholder="Додаткові акценти для цього запуску…" style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid var(--border-input);border-radius:var(--radius);font-size:13px;font-family:var(--font);resize:vertical;outline:none"></textarea>
            </div>
            <div class="ai-gen-prompt-toggle" id="aiGenPromptToggle">&#9654; Переглянути промт</div>
            <div class="ai-gen-prompt-box" id="aiGenPromptBox"></div>
            <div class="ai-gen-status" id="aiGenStatus"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary btn-sm" id="aiGenRun">Згенерувати</button>
            <button type="button" class="btn btn-ghost btn-sm" id="aiGenClose2">Закрити</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<div class="crm-lightbox" id="catLightbox">
    <img src="" id="catLightboxImg" class="crm-lightbox-img" alt="">
</div>

<script src="/modules/shared/category-tree.js?v=<?php echo filemtime(__DIR__ . '/../../shared/category-tree.js'); ?>"></script>
<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script>
var CATS = <?php
    $jsArr = array();
    foreach ($treeCats as $c) {
        $jsArr[] = array(
            'id'        => (int)$c['id'],
            'parent_id' => (int)$c['parent_id'],
            'name'      => (string)($c['name'] ? $c['name'] : ('(id:'.(int)$c['id'].')')),
            'status'    => (int)$c['status'],
        );
    }
    echo json_encode($jsArr);
?>;

var SELECTED_ID  = <?php echo (int)$selected; ?>;
var INITIAL_DATA = <?php echo $initialCat ? json_encode($initialCat) : 'null'; ?>;
var ALL_SITES    = <?php echo json_encode($allSites); ?>;
var ALL_LANGS    = <?php echo json_encode($allLanguages); ?>;

var currentCatId  = 0;
var currentSiteId = 0;
var tree          = null;

// ── Sync tree panel height to form panel ─────────────────────────────────────
(function() {
    var tree = document.getElementById('catsTreePanel');
    var form = document.getElementById('catsFormOuter');
    if (!tree || !form) return;
    function sync() {
        var h = form.offsetHeight;
        tree.style.height = (h > 0 ? h : 400) + 'px';
    }
    if (window.ResizeObserver) {
        new ResizeObserver(sync).observe(form);
    } else {
        sync();
        window.addEventListener('resize', sync);
    }
})();

// ── Lightbox ──────────────────────────────────────────────────────────────────
(function() {
    var lb = document.getElementById('catLightbox');
    lb.addEventListener('click', function() { lb.classList.remove('open'); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') lb.classList.remove('open');
    });
})();

function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function() { t.classList.remove('show'); }, 2000);
}

// ── Basic lang tabs ──────────────────────────────────────────────────────────
(function() {
    var tabs = document.querySelectorAll('.form-lang-tab');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].addEventListener('click', (function(tab) {
            return function() {
                var allTabs  = document.querySelectorAll('.form-lang-tab');
                var allPanes = document.querySelectorAll('.form-lang-pane');
                for (var j = 0; j < allTabs.length; j++)  allTabs[j].classList.remove('active');
                for (var j = 0; j < allPanes.length; j++) allPanes[j].classList.remove('active');
                tab.classList.add('active');
                var paneId = tab.getAttribute('data-pane');
                document.getElementById(paneId).classList.add('active');
            };
        })(tabs[i]));
    }
})();

// ── Build SEO card tabs/panes ────────────────────────────────────────────────
function buildSeoCard(sites, langs) {
    var siteTabs  = document.getElementById('seoSiteTabs');
    var sitePanes = document.getElementById('seoSitePanes');
    siteTabs.innerHTML  = '';
    sitePanes.innerHTML = '';

    for (var si = 0; si < sites.length; si++) {
        var site    = sites[si];
        var sid     = parseInt(site.site_id, 10);
        var isMapped = (site.mapped == 1 || site.mapped === true);

        // Site tab
        var stab = document.createElement('div');
        stab.className = 'seo-site-tab' + (si === 0 ? ' active' : '') + (isMapped ? '' : ' unmapped');
        stab.textContent = isMapped ? site.name : site.name + ' +';
        stab.setAttribute('data-site', sid);
        siteTabs.appendChild(stab);

        // Site pane
        var spane = document.createElement('div');
        spane.className = 'seo-site-pane' + (si === 0 ? ' active' : '');
        spane.setAttribute('id', 'seoSitePane_' + sid);

        if (!isMapped) {
            // Unmapped: show "add to site" panel
            spane.innerHTML =
                '<div class="seo-add-panel">' +
                    '<div class="seo-add-msg">Категорія відсутня на сайті <b>' + site.name + '</b>.</div>' +
                    '<div class="form-row">' +
                        '<label>Розмістити всередині</label>' +
                        '<select id="addParentSel_' + sid + '" style="width:100%;padding:7px 10px;border:1px solid var(--border-input);border-radius:var(--radius);font-size:13px;font-family:var(--font);outline:none;background:#fff;box-sizing:border-box">' +
                            '<option value="0">— Завантаження\u2026</option>' +
                        '</select>' +
                    '</div>' +
                    '<button type="button" class="btn btn-sm btn-primary" id="btnAddToSite_' + sid + '" disabled>Додати на сайт</button>' +
                    '<span id="addToSiteErr_' + sid + '" class="form-error"></span>' +
                '</div>';
        } else {
            // Mapped: normal SEO fields

            // ── Status + URL in one row at TOP of site pane ──
            var topRow = document.createElement('div');
            topRow.className = 'seo-top-row';
            topRow.innerHTML =
                '<label class="status-toggle">' +
                    '<input type="checkbox" id="seoStatus_' + sid + '"> Активна' +
                '</label>' +
                '<span id="catUrlSite_' + sid + '"></span>';
            spane.appendChild(topRow);

            // ── Lang tabs inside site pane ──
            var langTabsDiv = document.createElement('div');
            langTabsDiv.className = 'seo-lang-tabs';
            langTabsDiv.setAttribute('id', 'seoLangTabs_' + sid);

            var langPanesDiv = document.createElement('div');

            for (var li = 0; li < langs.length; li++) {
                var lang  = langs[li];
                var lid   = parseInt(lang.language_id, 10);
                var lCode = lang.code.toUpperCase();
                var isRu  = (lang.code === 'ru');

                // Lang tab
                var ltab = document.createElement('div');
                ltab.className = 'seo-lang-tab' + (li === 0 ? ' active' : '');
                ltab.textContent = lCode;
                ltab.setAttribute('data-site', sid);
                ltab.setAttribute('data-lang', lid);
                langTabsDiv.appendChild(ltab);

                // Lang pane
                var lpane = document.createElement('div');
                lpane.className = 'seo-lang-pane' + (li === 0 ? ' active' : '');
                lpane.setAttribute('id', 'seoLangPane_' + sid + '_' + lid);

                var noSyncNote = isRu
                    ? '<div class="seo-no-sync">Не синхронізується з сайтом</div>'
                    : '';

                lpane.innerHTML = noSyncNote +
                    '<div class="form-row">' +
                        '<label>Назва на сайті</label>' +
                        '<input type="text" id="catName_' + sid + '_' + lid + '" autocomplete="off">' +
                    '</div>' +
                    '<div class="form-row">' +
                        '<label>Опис <span class="desc-toggle" id="seoDescToggle_' + sid + '_' + lid + '" onclick="toggleSeoDesc(' + sid + ',' + lid + ')">редагувати</span></label>' +
                        '<div class="desc-preview" id="seoDescPrev_' + sid + '_' + lid + '" onclick="toggleSeoDesc(' + sid + ',' + lid + ')"><span class="desc-empty">Опис відсутній</span></div>' +
                        '<textarea id="seoDesc_' + sid + '_' + lid + '" rows="4" style="display:none" onblur="updateSeoDescPrev(' + sid + ',' + lid + ')"></textarea>' +
                    '</div>' +
                    '<div class="form-row">' +
                        '<label>SEO H1</label>' +
                        '<input type="text" id="seoH1_' + sid + '_' + lid + '" autocomplete="off">' +
                    '</div>' +
                    '<div class="form-row">' +
                        '<label>Meta title</label>' +
                        '<input type="text" id="metaTitle_' + sid + '_' + lid + '" autocomplete="off">' +
                    '</div>' +
                    '<div class="form-row">' +
                        '<label>Meta description</label>' +
                        '<textarea id="metaDesc_' + sid + '_' + lid + '" rows="2"></textarea>' +
                    '</div>' +
                    '<div class="form-row">' +
                        '<label>SEO URL (slug)</label>' +
                        '<input type="text" id="seoUrl_' + sid + '_' + lid + '" readonly>' +
                    '</div>';

                langPanesDiv.appendChild(lpane);
            }

            spane.appendChild(langTabsDiv);
            spane.appendChild(langPanesDiv);

            // ── Sort order at BOTTOM of site pane ──
            var sortWrap = document.createElement('div');
            sortWrap.className = 'sort-row';
            sortWrap.innerHTML =
                '<label>Порядок:</label>' +
                '<input type="number" id="seoSortOrder_' + sid + '" class="sort-order-input">';
            spane.appendChild(sortWrap);
        }

        sitePanes.appendChild(spane);
    }

    // Attach site tab click handlers
    var allSiteTabs = siteTabs.querySelectorAll('.seo-site-tab');
    for (var i = 0; i < allSiteTabs.length; i++) {
        allSiteTabs[i].addEventListener('click', (function(tab) {
            return function() {
                var tabs  = document.querySelectorAll('.seo-site-tab');
                var panes = document.querySelectorAll('.seo-site-pane');
                for (var j = 0; j < tabs.length; j++)  tabs[j].classList.remove('active');
                for (var j = 0; j < panes.length; j++) panes[j].classList.remove('active');
                tab.classList.add('active');
                var sid = parseInt(tab.getAttribute('data-site'), 10);
                if (!tab.classList.contains('unmapped')) {
                    currentSiteId = sid;
                }
                var pane = document.getElementById('seoSitePane_' + sid);
                if (pane) pane.classList.add('active');

                // Lazy-load site categories for unmapped pane
                if (tab.classList.contains('unmapped') && pane && !pane.getAttribute('data-cats-loaded')) {
                    pane.setAttribute('data-cats-loaded', '1');
                    fetch('/catalog/api/get_site_categories?site_id=' + sid)
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            var sel = document.getElementById('addParentSel_' + sid);
                            var btn = document.getElementById('btnAddToSite_' + sid);
                            if (!sel) return;
                            sel.innerHTML = '';
                            var opt0 = document.createElement('option');
                            opt0.value = '0';
                            opt0.textContent = '\u2014 Головна (корінь) \u2014';
                            sel.appendChild(opt0);
                            if (d.ok && d.categories && d.categories.length) {
                                var cats = d.categories;
                                function buildSelTree(parentId, depth) {
                                    var prefix = '';
                                    for (var di = 0; di < depth; di++) prefix += '\u00a0\u00a0\u00a0';
                                    for (var ci = 0; ci < cats.length; ci++) {
                                        if (parseInt(cats[ci].parent_id, 10) === parentId) {
                                            var opt = document.createElement('option');
                                            opt.value = cats[ci].category_id;
                                            opt.textContent = prefix + (cats[ci].name || '(без назви)');
                                            sel.appendChild(opt);
                                            buildSelTree(parseInt(cats[ci].category_id, 10), depth + 1);
                                        }
                                    }
                                }
                                buildSelTree(0, 0);
                            }
                            if (btn) btn.disabled = false;
                        })
                        .catch(function() {
                            var sel = document.getElementById('addParentSel_' + sid);
                            if (sel) sel.options[0].textContent = 'Помилка завантаження категорій';
                        });
                }
            };
        })(allSiteTabs[i]));
    }

    // Attach "add to site" button handlers
    var addBtns = sitePanes.querySelectorAll('[id^="btnAddToSite_"]');
    for (var ai = 0; ai < addBtns.length; ai++) {
        addBtns[ai].addEventListener('click', (function(btn) {
            return function() {
                var sid = parseInt(btn.id.replace('btnAddToSite_', ''), 10);
                var errEl = document.getElementById('addToSiteErr_' + sid);
                btn.disabled = true;
                btn.textContent = 'Додаю\u2026';
                if (errEl) errEl.style.display = 'none';
                var sel = document.getElementById('addParentSel_' + sid);
                var parentSiteCatId = sel ? parseInt(sel.value, 10) : 0;
                fetch('/categories/api/add_to_site', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'category_id=' + currentCatId + '&site_id=' + sid + '&parent_site_cat_id=' + parentSiteCatId
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        showToast('Категорію додано на сайт');
                        loadCategory(currentCatId);
                    } else {
                        if (errEl) { errEl.textContent = d.error || 'Помилка'; errEl.style.display = 'block'; }
                        btn.disabled = false;
                        btn.textContent = 'Додати на сайт';
                    }
                })
                .catch(function() {
                    if (errEl) { errEl.textContent = 'Помилка мережі'; errEl.style.display = 'block'; }
                    btn.disabled = false;
                    btn.textContent = 'Додати на сайт';
                });
            };
        })(addBtns[ai]));
    }

    // Attach lang tab click handlers
    var allLangTabs = sitePanes.querySelectorAll('.seo-lang-tab');
    for (var i = 0; i < allLangTabs.length; i++) {
        allLangTabs[i].addEventListener('click', (function(tab) {
            return function() {
                var sid   = parseInt(tab.getAttribute('data-site'), 10);
                var pane  = document.getElementById('seoSitePane_' + sid);
                if (!pane) return;
                var ltabs  = pane.querySelectorAll('.seo-lang-tab');
                var lpanes = pane.querySelectorAll('.seo-lang-pane');
                for (var j = 0; j < ltabs.length; j++)  ltabs[j].classList.remove('active');
                for (var j = 0; j < lpanes.length; j++) lpanes[j].classList.remove('active');
                tab.classList.add('active');
                var lid = parseInt(tab.getAttribute('data-lang'), 10);
                var lp  = document.getElementById('seoLangPane_' + sid + '_' + lid);
                if (lp) lp.classList.add('active');
            };
        })(allLangTabs[i]));
    }

    // Set initial active site
    if (sites.length > 0) {
        currentSiteId = parseInt(sites[0].site_id, 10);
    }
}

// ── Fill SEO language fields ─────────────────────────────────────────────────
function fillSeoFields(seo, sites, langs) {
    for (var si = 0; si < sites.length; si++) {
        var sid = parseInt(sites[si].site_id, 10);
        for (var li = 0; li < langs.length; li++) {
            var lid  = parseInt(langs[li].language_id, 10);
            var data = (seo && seo[sid] && seo[sid][lid]) ? seo[sid][lid] : {};

            var elName     = document.getElementById('catName_'   + sid + '_' + lid);
            var elSeoDesc  = document.getElementById('seoDesc_'   + sid + '_' + lid);
            var elH1       = document.getElementById('seoH1_'     + sid + '_' + lid);
            var elTitle    = document.getElementById('metaTitle_' + sid + '_' + lid);
            var elDesc     = document.getElementById('metaDesc_'  + sid + '_' + lid);
            var elUrl      = document.getElementById('seoUrl_'    + sid + '_' + lid);

            if (elName)    elName.value    = data.cat_name         || '';
            if (elSeoDesc) { elSeoDesc.value = data.description    || ''; updateSeoDescPrev(sid, lid); }
            if (elH1)      elH1.value      = data.seo_h1           || '';
            if (elTitle)   elTitle.value   = data.meta_title       || '';
            if (elDesc)    elDesc.value    = data.meta_description || '';
            if (elUrl)     elUrl.value     = data.seo_url          || '';

            // Cat URL — show at site level from UK language (lid=1)
            if (lid === 1) {
                var elCatUrlSite = document.getElementById('catUrlSite_' + sid);
                if (elCatUrlSite) {
                    var catUrl = data.cat_url || '';
                    if (catUrl) {
                        elCatUrlSite.innerHTML = '<a href="' + catUrl + '" target="_blank" class="seo-site-url">Категорія на сайті →</a>';
                    } else {
                        elCatUrlSite.innerHTML = '<span class="seo-site-url-empty">URL не сформовано</span>';
                    }
                }
            }
        }
    }
}

// ── Fill site-level settings (status, sort_order) ───────────────────────────
function fillSiteSettings(siteSettings, sites) {
    for (var si = 0; si < sites.length; si++) {
        var sid      = parseInt(sites[si].site_id, 10);
        var settings = (siteSettings && siteSettings[sid]) ? siteSettings[sid] : {};

        var elStatus    = document.getElementById('seoStatus_'    + sid);
        var elSortOrder = document.getElementById('seoSortOrder_' + sid);

        if (elStatus)    elStatus.checked = parseInt(settings.status    || 0, 10) === 1;
        if (elSortOrder) elSortOrder.value = settings.sort_order || 0;
    }
}

// ── Image slider ─────────────────────────────────────────────────────────────
var _imgList    = [];   // [{image_id, url}, ...]
var _imgCurrent = 0;

function renderImgSlider(images) {
    _imgList    = images || [];
    _imgCurrent = 0;

    var track   = document.getElementById('catImgTrack');
    var nav     = document.getElementById('catImgNav');
    var slider  = document.getElementById('catImgSlider');
    var empty   = document.getElementById('catImgEmpty');
    var dotsEl  = document.getElementById('catImgDots');

    track.innerHTML = '';
    dotsEl.innerHTML = '';

    if (_imgList.length === 0) {
        slider.style.display = 'none';
        empty.style.display  = '';
        return;
    }

    empty.style.display  = 'none';
    slider.style.display = '';

    for (var i = 0; i < _imgList.length; i++) {
        var slide = document.createElement('div');
        slide.className = 'cat-img-slide';

        var img = document.createElement('img');
        img.src = _imgList[i].url;
        img.alt = '';
        img.addEventListener('click', (function(url) {
            return function() {
                document.getElementById('catLightboxImg').src = url;
                document.getElementById('catLightbox').classList.add('open');
            };
        })(_imgList[i].url));
        slide.appendChild(img);

        var rep = document.createElement('button');
        rep.type = 'button';
        rep.className = 'cat-img-rep';
        rep.title = 'Замінити фото';
        rep.innerHTML = '&#9998;';
        rep.setAttribute('data-image-id', _imgList[i].image_id);
        slide.appendChild(rep);

        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'cat-img-del';
        del.title = 'Видалити';
        del.innerHTML = '&#10005;';
        del.setAttribute('data-image-id', _imgList[i].image_id);
        slide.appendChild(del);

        track.appendChild(slide);

        // Dot
        var dot = document.createElement('div');
        dot.className = 'cat-img-dot' + (i === 0 ? ' active' : '');
        dot.setAttribute('data-idx', i);
        dotsEl.appendChild(dot);
    }

    nav.style.display = _imgList.length > 1 ? '' : 'none';
    goToSlide(0);

    // Delete button handlers
    var delBtns = track.querySelectorAll('.cat-img-del');
    for (var di = 0; di < delBtns.length; di++) {
        delBtns[di].addEventListener('click', (function(btn) {
            return function() {
                var imgId = parseInt(btn.getAttribute('data-image-id'), 10);
                deleteImage(imgId);
            };
        })(delBtns[di]));
    }

    // Replace button handlers
    var repBtns = track.querySelectorAll('.cat-img-rep');
    for (var ri = 0; ri < repBtns.length; ri++) {
        repBtns[ri].addEventListener('click', (function(btn) {
            return function() {
                var imgId = parseInt(btn.getAttribute('data-image-id'), 10);
                triggerReplaceImage(imgId);
            };
        })(repBtns[ri]));
    }

    // Dot handlers
    var dotEls = dotsEl.querySelectorAll('.cat-img-dot');
    for (var dj = 0; dj < dotEls.length; dj++) {
        dotEls[dj].addEventListener('click', (function(d) {
            return function() { goToSlide(parseInt(d.getAttribute('data-idx'), 10)); };
        })(dotEls[dj]));
    }
}

function goToSlide(idx) {
    var slides = document.getElementById('catImgTrack').querySelectorAll('.cat-img-slide');
    var dots   = document.getElementById('catImgDots').querySelectorAll('.cat-img-dot');
    if (!slides.length) return;
    _imgCurrent = (idx + slides.length) % slides.length;
    for (var i = 0; i < slides.length; i++) {
        slides[i].style.display = i === _imgCurrent ? 'block' : 'none';
    }
    for (var j = 0; j < dots.length; j++) {
        dots[j].classList.toggle('active', j === _imgCurrent);
    }
}

function deleteImage(imageId) {
    if (!confirm('Видалити фото?')) return;
    fetch('/categories/api/delete_image', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: 'image_id=' + imageId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.ok) { showToast(d.error || 'Помилка'); return; }
        _imgList = _imgList.filter(function(im) { return im.image_id !== imageId; });
        renderImgSlider(_imgList);
        showToast('Фото видалено');
    })
    .catch(function() { showToast('Помилка мережі'); });
}

var _replaceImageId = 0;
function triggerReplaceImage(imageId) {
    _replaceImageId = imageId;
    document.getElementById('catImgReplaceFile').value = '';
    document.getElementById('catImgReplaceFile').click();
}
document.getElementById('catImgReplaceFile').addEventListener('change', function() {
    var file = this.files[0];
    if (!file || !_replaceImageId) return;
    var uploading = document.getElementById('catImgUploading');
    uploading.style.display = 'block';
    var fd = new FormData();
    fd.append('entity_type', 'category');
    fd.append('image_id', _replaceImageId);
    fd.append('image', file);
    var fileInput = this;
    fetch('/shared/api/replace_image', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            uploading.style.display = 'none';
            fileInput.value = '';
            if (!d.ok) { showToast(d.error || 'Помилка заміни'); return; }
            for (var i = 0; i < _imgList.length; i++) {
                if (_imgList[i].image_id === _replaceImageId) {
                    _imgList[i].image = d.data.image;
                    _imgList[i].url   = d.data.url;
                    break;
                }
            }
            var cur = _imgCurrent;
            renderImgSlider(_imgList);
            goToSlide(cur);
            showToast('Фото замінено');
        })
        .catch(function() {
            uploading.style.display = 'none';
            showToast('Помилка мережі');
        });
});

// Nav buttons
document.getElementById('catImgPrev').addEventListener('click', function() {
    goToSlide(_imgCurrent - 1);
});
document.getElementById('catImgNext').addEventListener('click', function() {
    goToSlide(_imgCurrent + 1);
});

// Upload
document.getElementById('catImgUploadBtn').addEventListener('click', function() {
    document.getElementById('catImgFile').click();
});
document.getElementById('catImgFile').addEventListener('change', function() {
    var file = this.files[0];
    if (!file || !currentCatId) return;
    var uploading = document.getElementById('catImgUploading');
    uploading.style.display = 'block';
    var fd = new FormData();
    fd.append('category_id', currentCatId);
    fd.append('image', file);
    var fileInput = this;
    fetch('/categories/api/upload_image', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            uploading.style.display = 'none';
            fileInput.value = '';
            if (!d.ok) { showToast(d.error || 'Помилка завантаження'); return; }
            _imgList.push(d.data);
            renderImgSlider(_imgList);
            goToSlide(_imgList.length - 1);
            showToast('Фото додано');
        })
        .catch(function() {
            uploading.style.display = 'none';
            showToast('Помилка мережі');
        });
});

// ── Populate both cards ──────────────────────────────────────────────────────
function populateForm(d) {
    currentCatId = parseInt(d.category_id, 10);

    document.getElementById('infoId').textContent     = d.category_id;
    document.getElementById('infoParent').textContent = d.parent_name || '(корінь)';
    document.getElementById('infoOff').textContent    = parseInt(d.category_off, 10) > 0 ? d.category_off : '—';
    document.getElementById('infoMff').textContent    = parseInt(d.category_mf,  10) > 0 ? d.category_mf  : '—';

    var ua = d.ua || {};
    var ru = d.ru || {};
    document.getElementById('nameUa').value      = ua.name      || '';
    document.getElementById('nameRu').value      = ru.name      || '';
    document.getElementById('catStatus').checked = parseInt(d.status, 10) === 1;
    document.getElementById('sortOrder').value   = d.sort_order || 0;

    // Description full
    document.getElementById('descUa').value = ua.description_full || '';
    document.getElementById('descRu').value = ru.description_full || '';
    updateDescPreview('Ua');
    updateDescPreview('Ru');
    // Reset to preview mode
    setDescMode('Ua', 'preview');
    setDescMode('Ru', 'preview');

    document.getElementById('formError').style.display    = 'none';
    document.getElementById('seoFormError').style.display = 'none';

    var sites = d.sites || ALL_SITES;
    var langs = d.languages || ALL_LANGS;
    var seo   = d.seo || {};

    buildSeoCard(sites, langs);
    fillSeoFields(seo, sites, langs);
    fillSiteSettings(d.site_settings || {}, sites);
    renderImgSlider(d.images || []);

    // Pre-fill "Назва на сайті" with Papir name if empty
    // languages table: language_id=1=uk → d.ua.name, language_id=2=ru → d.ru.name
    var papirNames = {};
    papirNames[1] = (d.ua && d.ua.name) ? d.ua.name : '';
    papirNames[2] = (d.ru && d.ru.name) ? d.ru.name : '';
    for (var _si = 0; _si < sites.length; _si++) {
        var _sid = parseInt(sites[_si].site_id, 10);
        for (var _li = 0; _li < langs.length; _li++) {
            var _lid   = parseInt(langs[_li].language_id, 10);
            var elName = document.getElementById('catName_' + _sid + '_' + _lid);
            if (elName && elName.value === '' && papirNames[_lid]) {
                elName.value = papirNames[_lid];
            }
        }
    }

    document.getElementById('panelEmpty').style.display = 'none';
    document.getElementById('cardBasic').style.display  = 'block';
    document.getElementById('cardSeo').style.display    = 'block';
    document.getElementById('cardAi').style.display     = 'block';

    loadAiInstruction(currentCatId);
}

// ── Load category via AJAX ───────────────────────────────────────────────────
function loadCategory(id) {
    document.getElementById('panelEmpty').style.display = 'none';
    document.getElementById('cardBasic').style.display  = 'none';
    document.getElementById('cardSeo').style.display    = 'none';
    document.getElementById('cardAi').style.display     = 'none';

    fetch('/categories/api/get?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) { showToast(d.error || 'Помилка'); return; }
            populateForm(d.data);
        })
        .catch(function() { showToast('Помилка мережі'); });
}

// ── Init tree ────────────────────────────────────────────────────────────────
tree = new CategoryTree({
    container:  document.getElementById('catsTreePanel'),
    categories: CATS,
    selectedId: SELECTED_ID || 0,
    searchable: false,
    onSelect: function(id) {
        currentCatId = id;
        history.pushState({id: id}, '', '/categories?selected=' + id);
        loadCategory(id);
    }
});

if (INITIAL_DATA) {
    populateForm(INITIAL_DATA);
} else if (SELECTED_ID) {
    loadCategory(SELECTED_ID);
}

// ── Save basic ───────────────────────────────────────────────────────────────
document.getElementById('btnSave').addEventListener('click', function() {
    if (!currentCatId) return;
    var btn    = this;
    var err    = document.getElementById('formError');
    var nameUa = document.getElementById('nameUa').value.trim();
    if (!nameUa) {
        err.textContent = 'Назва (UA) обов\'язкова';
        err.style.display = 'block';
        return;
    }
    btn.disabled = true;
    err.style.display = 'none';

    var params = 'category_id=' + currentCatId
        + '&name_ua='      + encodeURIComponent(nameUa)
        + '&name_ru='      + encodeURIComponent(document.getElementById('nameRu').value)
        + '&desc_ua='      + encodeURIComponent(document.getElementById('descUa').value)
        + '&desc_ru='      + encodeURIComponent(document.getElementById('descRu').value)
        + '&status='       + (document.getElementById('catStatus').checked ? 1 : 0)
        + '&sort_order='   + encodeURIComponent(document.getElementById('sortOrder').value);

    fetch('/categories/api/save', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: params
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (!d.ok) {
            err.textContent = d.error || 'Помилка';
            err.style.display = 'block';
            return;
        }
        showToast('Збережено');
        if (tree && d.name) {
            var cats = tree._allCats;
            for (var i = 0; i < cats.length; i++) {
                if (cats[i].id === currentCatId) { cats[i].name = d.name; break; }
            }
            tree._renderNodes();
        }
    })
    .catch(function() {
        btn.disabled = false;
        err.textContent = 'Помилка мережі';
        err.style.display = 'block';
    });
});

// ── AI instruction ───────────────────────────────────────────────────────────
function loadAiInstruction(catId) {
    document.getElementById('aiContext').value     = '';
    document.getElementById('aiInstruction').value = '';
    document.getElementById('aiFormError').style.display = 'none';

    fetch('/ai/api/get_instruction?entity_type=category&entity_id=' + catId + '&use_case=seo')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) return;
            document.getElementById('aiContext').value     = d.data.context     || '';
            document.getElementById('aiInstruction').value = d.data.instruction || '';
        })
        .catch(function() {});
}

document.getElementById('btnSaveAi').addEventListener('click', function() {
    if (!currentCatId) return;
    var btn = this;
    var err = document.getElementById('aiFormError');
    btn.disabled = true;
    err.style.display = 'none';

    var params = 'entity_type=category'
        + '&entity_id='   + currentCatId
        + '&use_case=seo'
        + '&context='     + encodeURIComponent(document.getElementById('aiContext').value)
        + '&instruction=' + encodeURIComponent(document.getElementById('aiInstruction').value);

    fetch('/ai/api/save_instruction', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: params
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (!d.ok) {
            err.textContent = d.error || 'Помилка';
            err.style.display = 'block';
            return;
        }
        showToast('ШІ інструкції збережено');
    })
    .catch(function() {
        btn.disabled = false;
        err.textContent = 'Помилка мережі';
        err.style.display = 'block';
    });
});

// ── Save SEO ─────────────────────────────────────────────────────────────────
document.getElementById('btnSaveSeo').addEventListener('click', function() {
    if (!currentCatId || !currentSiteId) return;
    var btn = this;
    var err = document.getElementById('seoFormError');
    btn.disabled = true;
    err.style.display = 'none';

    // Site-level fields
    var elStatus    = document.getElementById('seoStatus_'    + currentSiteId);
    var elSortOrder = document.getElementById('seoSortOrder_' + currentSiteId);

    var params = 'category_id=' + currentCatId
        + '&site_id='    + currentSiteId
        + '&status='     + (elStatus    ? (elStatus.checked ? 1 : 0) : 0)
        + '&sort_order=' + (elSortOrder ? encodeURIComponent(elSortOrder.value) : 0);

    // Per-language fields
    for (var li = 0; li < ALL_LANGS.length; li++) {
        var lid     = parseInt(ALL_LANGS[li].language_id, 10);
        var elName    = document.getElementById('catName_'    + currentSiteId + '_' + lid);
        var elSeoDesc = document.getElementById('seoDesc_'    + currentSiteId + '_' + lid);
        var elH1      = document.getElementById('seoH1_'      + currentSiteId + '_' + lid);
        var elTitle   = document.getElementById('metaTitle_'  + currentSiteId + '_' + lid);
        var elDesc    = document.getElementById('metaDesc_'   + currentSiteId + '_' + lid);
        var elUrl     = document.getElementById('seoUrl_'     + currentSiteId + '_' + lid);

        params += '&cat_name_'          + lid + '=' + encodeURIComponent(elName    ? elName.value    : '');
        params += '&description_'       + lid + '=' + encodeURIComponent(elSeoDesc ? elSeoDesc.value : '');
        params += '&seo_h1_'            + lid + '=' + encodeURIComponent(elH1      ? elH1.value      : '');
        params += '&meta_title_'        + lid + '=' + encodeURIComponent(elTitle   ? elTitle.value   : '');
        params += '&meta_description_'  + lid + '=' + encodeURIComponent(elDesc    ? elDesc.value    : '');
        params += '&seo_url_'           + lid + '=' + encodeURIComponent(elUrl     ? elUrl.value     : '');
    }

    fetch('/categories/api/save_seo', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: params
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.disabled = false;
        if (!d.ok) {
            err.textContent = d.error || 'Помилка';
            err.style.display = 'block';
            return;
        }
        showToast('SEO збережено');
        loadCategory(currentCatId);
    })
    .catch(function() {
        btn.disabled = false;
        err.textContent = 'Помилка мережі';
        err.style.display = 'block';
    });
});

// ── AI Generation ─────────────────────────────────────────────────────────────

function aiGenBuildModal() {
    var sitesCont = document.getElementById('aiGenSitesCont');
    var langsCont = document.getElementById('aiGenLangsCont');
    sitesCont.innerHTML = '';
    langsCont.innerHTML = '';

    for (var si = 0; si < ALL_SITES.length; si++) {
        var s   = ALL_SITES[si];
        var row = document.createElement('div');
        row.className = 'ai-gen-opt-row';
        row.innerHTML = '<input type="checkbox" id="aiGs_' + s.site_id + '" value="' + s.site_id + '" checked>'
            + '<label for="aiGs_' + s.site_id + '">' + s.name + '</label>';
        sitesCont.appendChild(row);
    }
    for (var li = 0; li < ALL_LANGS.length; li++) {
        var l   = ALL_LANGS[li];
        var row = document.createElement('div');
        row.className = 'ai-gen-opt-row';
        row.innerHTML = '<input type="checkbox" id="aiGl_' + l.language_id + '" value="' + l.language_id + '" checked>'
            + '<label for="aiGl_' + l.language_id + '">' + l.name + '</label>';
        langsCont.appendChild(row);
    }

    document.getElementById('aiGenNote').value    = '';
    document.getElementById('aiGenStatus').textContent = '';
    document.getElementById('aiGenStatus').className   = 'ai-gen-status';
    document.getElementById('aiGenPromptBox').style.display  = 'none';
    document.getElementById('aiGenPromptBox').textContent    = '';
    document.getElementById('aiGenPromptToggle').textContent = '\u25BA Переглянути промт';
    document.getElementById('aiGenRun').disabled    = false;
    document.getElementById('aiGenRun').textContent = 'Згенерувати';
}

document.getElementById('btnAiGenSeo').addEventListener('click', function() {
    if (!currentCatId) return;
    aiGenBuildModal();
    document.getElementById('aiGenModal').style.display = 'flex';
});

document.getElementById('aiGenModalClose').addEventListener('click', function() {
    document.getElementById('aiGenModal').style.display = 'none';
});
document.getElementById('aiGenClose2').addEventListener('click', function() {
    document.getElementById('aiGenModal').style.display = 'none';
});
document.getElementById('aiGenModal').addEventListener('click', function(e) {
    if (e.target === this) { this.style.display = 'none'; }
});

document.getElementById('aiGenPromptToggle').addEventListener('click', function() {
    var box = document.getElementById('aiGenPromptBox');
    if (box.style.display === 'none') {
        box.style.display = 'block';
        this.textContent = '\u25BC Сховати промт';
        if (!box.textContent.trim()) { aiGenLoadPreview(); }
    } else {
        box.style.display = 'none';
        this.textContent = '\u25BA Переглянути промт';
    }
});
document.getElementById('aiGenNote').addEventListener('input', function() {
    // Reset prompt preview on note change
    var box = document.getElementById('aiGenPromptBox');
    if (box.style.display !== 'none') {
        box.textContent = '';
        aiGenLoadPreview();
    }
});

function aiGenLoadPreview() {
    var box = document.getElementById('aiGenPromptBox');
    box.textContent = 'Завантаження\u2026';
    var siteCbs = document.querySelectorAll('[id^="aiGs_"]:checked');
    var langCbs = document.querySelectorAll('[id^="aiGl_"]:checked');
    if (!siteCbs.length || !langCbs.length) { box.textContent = '\u2014'; return; }
    var sid = parseInt(siteCbs[0].value, 10);
    var lid = parseInt(langCbs[0].value, 10);
    var body = 'entity_type=category'
        + '&entity_id='   + currentCatId
        + '&site_id='     + sid
        + '&language_id=' + lid
        + '&use_case=seo'
        + '&custom_note=' + encodeURIComponent(document.getElementById('aiGenNote').value)
        + '&return_prompt=1&preview_only=1';
    fetch('/ai/api/generate_content', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.ok) {
            box.textContent = '=== SYSTEM ===\n\n' + d.system_prompt + '\n\n=== USER ===\n\n' + d.user_prompt;
        } else {
            box.textContent = d.error || 'Помилка';
        }
    })
    .catch(function() { box.textContent = 'Помилка мережі'; });
}

document.getElementById('aiGenRun').addEventListener('click', function() {
    if (!currentCatId) return;

    var checkedSites = [];
    var checkedLangs = [];
    var siteCbs = document.querySelectorAll('[id^="aiGs_"]:checked');
    var langCbs = document.querySelectorAll('[id^="aiGl_"]:checked');
    for (var i = 0; i < siteCbs.length; i++) { checkedSites.push(parseInt(siteCbs[i].value, 10)); }
    for (var i = 0; i < langCbs.length;  i++) { checkedLangs.push(parseInt(langCbs[i].value,  10)); }

    var status = document.getElementById('aiGenStatus');
    if (!checkedSites.length || !checkedLangs.length) {
        status.textContent = 'Оберіть хоча б один сайт і мову';
        status.className = 'ai-gen-status err';
        return;
    }

    var note  = document.getElementById('aiGenNote').value;
    var btn   = this;
    btn.disabled    = true;
    btn.textContent = 'Генерація\u2026';
    status.textContent = '';
    status.className = 'ai-gen-status';

    var pairs = [];
    for (var si = 0; si < checkedSites.length; si++) {
        for (var li = 0; li < checkedLangs.length; li++) {
            pairs.push({site_id: checkedSites[si], language_id: checkedLangs[li]});
        }
    }

    var done   = 0;
    var errors = 0;

    function runNext(idx) {
        if (idx >= pairs.length) {
            btn.disabled    = false;
            btn.textContent = 'Ще раз';
            if (errors === 0) {
                status.textContent = '\u2713 Згенеровано ' + done + ' варіантів. Перевірте поля і збережіть SEO для кожного сайту.';
                status.className = 'ai-gen-status ok';
            } else {
                status.textContent = done + ' згенеровано, ' + errors + ' помилок.';
                status.className = 'ai-gen-status err';
            }
            return;
        }
        var pair = pairs[idx];
        status.textContent = 'Генерація ' + (idx + 1) + ' / ' + pairs.length + '\u2026';
        status.className = 'ai-gen-status';

        var body = 'entity_type=category'
            + '&entity_id='   + currentCatId
            + '&site_id='     + pair.site_id
            + '&language_id=' + pair.language_id
            + '&use_case=seo'
            + '&custom_note=' + encodeURIComponent(note);

        fetch('/ai/api/generate_content', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok && d.fields) {
                aiGenApplyFields(pair.site_id, pair.language_id, d.fields);
                done++;
            } else {
                errors++;
            }
            runNext(idx + 1);
        })
        .catch(function() { errors++; runNext(idx + 1); });
    }

    runNext(0);
});

function aiGenApplyFields(siteId, languageId, fields) {
    var map = {
        'description':      'seoDesc_',
        'seo_h1':           'seoH1_',
        'meta_title':       'metaTitle_',
        'meta_description': 'metaDesc_'
    };
    for (var key in map) {
        if (fields[key] !== undefined && fields[key] !== '') {
            var el = document.getElementById(map[key] + siteId + '_' + languageId);
            if (el) { el.value = fields[key]; }
        }
    }
}

// ── Description preview / edit toggle ──────────────────────────────────
function updateDescPreview(lang) {
    var ta  = document.getElementById('desc' + lang);
    var pre = document.getElementById('descPreview' + lang);
    if (!ta || !pre) return;
    var val = ta.value.trim();
    if (val === '') {
        pre.innerHTML = '<span class="desc-empty">Опис відсутній</span>';
    } else {
        pre.innerHTML = val;
    }
}

function setDescMode(lang, mode) {
    var ta      = document.getElementById('desc' + lang);
    var pre     = document.getElementById('descPreview' + lang);
    var toggle  = pre ? pre.previousElementSibling.querySelector('.desc-toggle') : null;
    if (!ta || !pre) return;
    if (mode === 'edit') {
        pre.style.display = 'none';
        ta.style.display  = '';
        ta.focus();
        if (toggle) toggle.textContent = 'переглянути';
    } else {
        updateDescPreview(lang);
        ta.style.display  = 'none';
        pre.style.display = '';
        if (toggle) toggle.textContent = 'редагувати';
    }
}

function toggleDescEdit(lang) {
    var ta = document.getElementById('desc' + lang);
    if (!ta) return;
    if (ta.style.display === 'none') {
        setDescMode(lang, 'edit');
    } else {
        setDescMode(lang, 'preview');
    }
}

function updateSeoDescPrev(sid, lid) {
    var ta   = document.getElementById('seoDesc_' + sid + '_' + lid);
    var prev = document.getElementById('seoDescPrev_' + sid + '_' + lid);
    if (!ta || !prev) return;
    var html = ta.value.trim();
    prev.innerHTML = html !== '' ? html : '<span class="desc-empty">Опис відсутній</span>';
}

function toggleSeoDesc(sid, lid) {
    var ta     = document.getElementById('seoDesc_' + sid + '_' + lid);
    var prev   = document.getElementById('seoDescPrev_' + sid + '_' + lid);
    var toggle = document.getElementById('seoDescToggle_' + sid + '_' + lid);
    if (!ta || !prev) return;
    if (ta.style.display === 'none') {
        prev.style.display = 'none';
        ta.style.display = '';
        if (toggle) toggle.textContent = 'перегляд';
        ta.focus();
    } else {
        updateSeoDescPrev(sid, lid);
        ta.style.display = 'none';
        prev.style.display = '';
        if (toggle) toggle.textContent = 'редагувати';
    }
}

// ── Status filter (drives tree) ──────────────────────────────────────────────
(function() {
    var pills = ['pillAll', 'pillActive', 'pillInactive'];
    document.querySelectorAll('input[name="catStatusFilter"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            // Update pill active class
            pills.forEach(function(id) { var el = document.getElementById(id); if (el) el.classList.remove('active'); });
            var label = radio.closest('label');
            if (label) label.classList.add('active');
            // Apply to tree
            if (tree) {
                tree._statusFilter = radio.value;
                tree._renderNodes();
            }
        });
    });
}());

// ── Cat search (drives tree) ──────────────────────────────────────────────────
function applyCatSearch() {
    if (!tree) return;
    tree._searchVal = document.getElementById('catSearchHidden').value.trim();
    tree._renderNodes();
}

(function() {
    var catFakeForm = {
        _cbs: [],
        addEventListener: function(ev, cb) { if (ev === 'submit') this._cbs.push(cb); },
        submit: function() { this._cbs.forEach(function(cb) { cb(); }); applyCatSearch(); },
        querySelector: function() { return null; }
    };
    ChipSearch.init('catChipBox', 'catChipTyper', 'catSearchHidden', catFakeForm, {noComma: true});

    // Re-apply immediately on chip removal
    document.getElementById('catChipBox').addEventListener('click', function(e) {
        if (e.target.closest('.chip-x')) setTimeout(applyCatSearch, 0);
    });

    // Lens button — apply search
    document.getElementById('catChipSubmit').addEventListener('click', applyCatSearch);

    // Clear button
    var clearBtn = document.getElementById('catChipClear');
    var box      = document.getElementById('catChipBox');
    var typer    = document.getElementById('catChipTyper');
    var hidden   = document.getElementById('catSearchHidden');
    function updateCatClear() {
        var hasChips = box.querySelectorAll('.chip').length > 0;
        if (hasChips || typer.value.trim() !== '') clearBtn.classList.remove('hidden');
        else clearBtn.classList.add('hidden');
    }
    new MutationObserver(updateCatClear).observe(box, {childList: true});
    typer.addEventListener('input', updateCatClear);
    clearBtn.addEventListener('click', function() {
        box.querySelectorAll('.chip').forEach(function(c) { c.remove(); });
        typer.value = '';
        hidden.value = '';
        clearBtn.classList.add('hidden');
        applyCatSearch();
    });
}());
</script>

<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
