/**
 * HistoryModal — универсальная модаль истории изменений документа.
 *
 * Использование:
 *   HistoryModal.open('customerorder', 12345);
 *   HistoryModal.open('demand', 67);
 *
 * Требует: ui.css (переменные, .modal-overlay, .modal-box, .badge, .pagination)
 */
var HistoryModal = (function () {

    var ACTOR_LABELS = {
        'user':    'Користувач',
        'webhook': 'Webhook',
        'cron':    'Cron',
        'api':     'API',
        'ai':      'AI',
        'system':  'Система'
    };

    var ACTOR_BADGE = {
        'user':    'badge-blue',
        'webhook': 'badge-orange',
        'cron':    'badge-gray',
        'api':     'badge-gray',
        'ai':      'badge-indigo',
        'system':  'badge-gray'
    };

    var _overlay = null;
    var _box     = null;
    var _docType = '';
    var _docId   = 0;
    var _page    = 1;

    function _build() {
        if (_overlay) return;

        _overlay = document.createElement('div');
        _overlay.className = 'modal-overlay';
        _overlay.style.display = 'none';

        _box = document.createElement('div');
        _box.className = 'modal-box history-modal-box';
        _box.innerHTML =
            '<div class="modal-head">' +
                '<span class="history-modal-title">Історія змін</span>' +
                '<button type="button" class="modal-close" id="histModalClose">&#x2715;</button>' +
            '</div>' +
            '<div class="modal-body history-modal-body">' +
                '<div class="history-modal-loading">Завантаження...</div>' +
            '</div>' +
            '<div class="modal-footer history-modal-footer" style="display:none;">' +
                '<div class="pagination" id="histModalPagination"></div>' +
            '</div>';

        _overlay.appendChild(_box);
        document.body.appendChild(_overlay);

        document.getElementById('histModalClose').addEventListener('click', close);
        _overlay.addEventListener('click', function (e) {
            if (e.target === _overlay) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    }

    function open(docType, docId) {
        _build();
        _docType = docType;
        _docId   = docId;
        _page    = 1;
        _overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        _load();
    }

    function close() {
        if (_overlay) _overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    function _load() {
        var body = _box.querySelector('.history-modal-body');
        body.innerHTML = '<div class="history-modal-loading">Завантаження...</div>';

        var url = '/history/api/get?type=' + encodeURIComponent(_docType) +
                  '&id=' + _docId + '&page=' + _page;

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    body.innerHTML = '<div class="history-modal-error">Помилка завантаження.</div>';
                    return;
                }
                _render(data);
            })
            .catch(function () {
                body.innerHTML = '<div class="history-modal-error">Помилка з\'єднання.</div>';
            });
    }

    function _render(data) {
        var body   = _box.querySelector('.history-modal-body');
        var footer = _box.querySelector('.history-modal-footer');
        var pag    = document.getElementById('histModalPagination');

        if (!data.rows || data.rows.length === 0) {
            body.innerHTML = '<div class="history-modal-empty">Історія поки порожня.</div>';
            footer.style.display = 'none';
            return;
        }

        // colgroup для фиксированных ширин
        var html = '<table class="crm-table history-modal-table">' +
            '<colgroup>' +
            '<col style="width:105px">' +   // дата
            '<col style="width:150px">' +   // актор
            '<col style="width:115px">' +   // дія
            '<col style="width:100px">' +   // поле
            '<col style="width:160px">' +   // позиція
            '<col>' +                        // було
            '<col>' +                        // стало
            '</colgroup>' +
            '<thead><tr>' +
            '<th>Дата / час</th>' +
            '<th>Актор</th>' +
            '<th>Дія</th>' +
            '<th>Поле</th>' +
            '<th>Позиція</th>' +
            '<th>Було</th>' +
            '<th>Стало</th>' +
            '</tr></thead><tbody>';

        for (var i = 0; i < data.rows.length; i++) {
            var r = data.rows[i];
            var actorBadge = ACTOR_BADGE[r.actor_type] || 'badge-gray';
            var actorTypeLabel = ACTOR_LABELS[r.actor_type] || r.actor_type;

            var actorHtml =
                '<span class="hist-cell-clip" title="' + _esc(r.actor_label || '') + '">' + _esc(r.actor_label || '—') + '</span>' +
                '<br><span class="badge ' + actorBadge + ' fs-12">' + actorTypeLabel + '</span>';

            var fieldHtml = r.field_label
                ? _esc(r.field_label)
                : (r.field_name ? '<span class="text-muted fs-12">' + _esc(r.field_name) + '</span>' : '—');

            var itemHtml = r.item_label
                ? '<span class="hist-cell-clip" title="' + _esc(r.item_label) + '">' + _esc(r.item_label) + '</span>'
                : '—';

            var oldHtml = _valHtml(r.old_value);
            var newHtml = _valHtml(r.new_value);

            html +=
                '<tr>' +
                '<td class="nowrap fs-12">' + _esc(r.created_at_fmt || r.created_at) + '</td>' +
                '<td class="hist-td-actor">' + actorHtml + '</td>' +
                '<td><span class="badge badge-gray fs-12">' + _esc(r.action_label || r.action) + '</span></td>' +
                '<td class="fs-12">' + fieldHtml + '</td>' +
                '<td class="hist-td-item">' + itemHtml + '</td>' +
                '<td class="hist-td-val">' + oldHtml + '</td>' +
                '<td class="hist-td-val">' + newHtml + '</td>' +
                '</tr>';
        }

        html += '</tbody></table>';
        body.innerHTML = html;

        // Пагинация
        if (data.pages > 1) {
            footer.style.display = '';
            pag.innerHTML = _buildPagination(data.page, data.pages);
            pag.querySelectorAll('[data-p]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    _page = parseInt(this.dataset.p);
                    _load();
                });
            });
        } else {
            footer.style.display = 'none';
        }
    }

    function _valHtml(val) {
        if (val === null || val === undefined || val === '') return '<span class="text-muted">—</span>';
        var s = String(val);
        // JSON-объекты показываем свёрнуто с тултипом
        if ((s.charAt(0) === '{' || s.charAt(0) === '[') && s.length > 60) {
            return '<span class="text-muted fs-12" title="' + _esc(s) + '">[JSON]</span>';
        }
        // Короткие значения — inline, длинные — с переносом и подсказкой
        if (s.length <= 40) {
            return '<span class="fs-12">' + _esc(s) + '</span>';
        }
        return '<span class="hist-val-long fs-12" title="' + _esc(s) + '">' + _esc(s) + '</span>';
    }

    function _buildPagination(current, total) {
        var html = '';
        var from = Math.max(1, current - 2);
        var to   = Math.min(total, current + 2);

        if (current > 1) {
            html += '<button class="page-btn" data-p="' + (current - 1) + '">&#8249;</button>';
        }
        if (from > 1) {
            html += '<button class="page-btn" data-p="1">1</button>';
            if (from > 2) html += '<span class="page-dots">…</span>';
        }
        for (var p = from; p <= to; p++) {
            html += '<button class="page-btn' + (p === current ? ' page-btn-active' : '') +
                    '" data-p="' + p + '">' + p + '</button>';
        }
        if (to < total) {
            if (to < total - 1) html += '<span class="page-dots">…</span>';
            html += '<button class="page-btn" data-p="' + total + '">' + total + '</button>';
        }
        if (current < total) {
            html += '<button class="page-btn" data-p="' + (current + 1) + '">&#8250;</button>';
        }
        return html;
    }

    function _esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    return { open: open, close: close };
}());