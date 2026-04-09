/**
 * PackPrint — document pack printer for demand (shipment).
 * Opens a modal with pack items, allows sequential or individual printing.
 */
var PackPrint = (function () {
    var _demandId = 0;
    var _profiles = [];
    var _currentPack = null;

    function open(demandId) {
        _demandId = demandId;
        _currentPack = null;

        var modal = document.getElementById('packPrintModal');
        modal.style.display = 'flex';
        _showLoading();

        Promise.all([
            fetch('/print/api/get_pack_profiles').then(function (r) { return r.json(); }),
            fetch('/print/api/get_pack?demand_id=' + demandId).then(function (r) { return r.json(); })
        ]).then(function (results) {
            _profiles = (results[0].ok && results[0].profiles) ? results[0].profiles : [];
            var packData = results[1].ok ? results[1].pack : null;

            if (packData && packData.items && packData.items.length > 0) {
                _currentPack = packData;
                _renderPack(packData);
            } else {
                _renderGenForm();
            }
        }).catch(function () {
            _showBody('<div class="pack-loading" style="color:#ef4444">Помилка завантаження</div>');
        });
    }

    function close() {
        document.getElementById('packPrintModal').style.display = 'none';
    }

    function regenerate() {
        var sel = document.getElementById('packProfileSelect');
        var profileId = sel ? parseInt(sel.value, 10) || 0 : 0;
        _generatePack(profileId);
    }

    // ── Rendering ───────────────────────────────────────────────────────

    function _showLoading() {
        _showBody('<div class="pack-loading">Завантаження…</div>');
        document.getElementById('packModalFooter').style.display = 'none';
    }

    function _showBody(html) {
        document.getElementById('packModalBody').innerHTML = html;
    }

    function _renderGenForm() {
        var html = _buildProfileBar(null);
        html += '<div class="pack-gen-area">';
        html += '<p style="color:#6b7280;font-size:13px;margin:0 0 14px">Пакет ще не сформований для цього відвантаження.</p>';
        html += '<button class="btn btn-primary" onclick="PackPrint._doGenerate()">📦 Сформувати пакет</button>';
        html += '</div>';
        _showBody(html);
        document.getElementById('packModalFooter').style.display = 'none';
    }

    function _buildProfileBar(selectedProfileId) {
        var html = '<div class="pack-profile-bar">';
        html += '<label>Профіль:</label>';
        html += '<select id="packProfileSelect">';
        for (var i = 0; i < _profiles.length; i++) {
            var p = _profiles[i];
            var sel = selectedProfileId ? (p.id == selectedProfileId ? ' selected' : '')
                                        : (p.is_default == 1 ? ' selected' : '');
            html += '<option value="' + p.id + '"' + sel + '>' + _esc(p.name) + '</option>';
        }
        html += '</select>';
        html += '</div>';
        return html;
    }

    function _renderPack(pack) {
        var items = pack.items || [];
        var html = _buildProfileBar(pack.profile_id);

        // Items list
        html += '<ul class="pack-items-list">';
        for (var j = 0; j < items.length; j++) {
            var it = items[j];
            var status = it.status || 'error';
            var icon = status === 'ok' ? '✅' : (status === 'skip' ? '⏭' : '❌');
            var isExternal = it.external || false;

            html += '<li class="pack-item" data-idx="' + j + '">';
            html += '<div class="pack-item-icon ' + status + '">' + icon + '</div>';
            html += '<div class="pack-item-info">';
            html += '<div class="pack-item-label">' + _esc(it.label || 'Документ') + '</div>';
            if (it.error) {
                html += '<div class="pack-item-sub" style="color:#ef4444">' + _esc(it.error) + '</div>';
            } else if (it.url) {
                html += '<div class="pack-item-sub">' + (isExternal ? 'Зовнішнє посилання' : 'PDF') + '</div>';
            }
            html += '</div>';

            if (status === 'ok' && it.url) {
                html += '<div class="pack-item-actions">';
                if (isExternal) {
                    html += '<a class="btn" href="' + _esc(it.url) + '" target="_blank">Відкрити ↗</a>';
                } else {
                    html += '<a class="btn" href="' + _esc(it.url) + '" target="_blank">🖨 Друк</a>';
                }
                html += '</div>';
            }

            html += '</li>';
        }
        html += '</ul>';

        // Meta
        if (pack.created_at) {
            html += '<div class="pack-meta">Сформовано: ' + _esc(pack.created_at);
            if (pack.profile_name) html += ' · ' + _esc(pack.profile_name);
            html += '</div>';
        }

        _showBody(html);
        document.getElementById('packModalFooter').style.display = 'flex';

        var hasPrintable = items.some(function (it) { return it.status === 'ok' && it.url; });
        document.getElementById('packPrintAllBtn').disabled = !hasPrintable;
    }

    // ── Actions ─────────────────────────────────────────────────────────

    function _doGenerate() {
        var sel = document.getElementById('packProfileSelect');
        var profileId = sel ? parseInt(sel.value, 10) || 0 : 0;
        _generatePack(profileId);
    }

    function _generatePack(profileId) {
        _showBody('<div class="pack-loading">⏳ Формую пакет…</div>');
        document.getElementById('packModalFooter').style.display = 'none';

        var fd = new FormData();
        fd.append('demand_id', _demandId);
        if (profileId) fd.append('profile_id', profileId);

        fetch('/print/api/generate_pack', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) {
                    _showBody('<div class="pack-loading" style="color:#ef4444">Помилка: ' + _esc(d.error || '') + '</div>');
                    return;
                }
                _currentPack = {
                    id: d.pack_id,
                    status: d.status,
                    items: d.items,
                    created_at: new Date().toLocaleString('uk-UA'),
                    profile_id: profileId || null,
                };
                _renderPack(_currentPack);
            })
            .catch(function () {
                _showBody('<div class="pack-loading" style="color:#ef4444">Помилка мережі</div>');
            });
    }

    /**
     * Add current pack to print queue.
     */
    function addToQueue() {
        if (!_currentPack) {
            // Generate first, then queue
            var sel = document.getElementById('packProfileSelect');
            var profileId = sel ? parseInt(sel.value, 10) || 0 : 0;
            var fd = new FormData();
            fd.append('demand_id', _demandId);
            if (profileId) fd.append('profile_id', profileId);
            fetch('/print/api/queue_add', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { alert('Помилка: ' + (d.error || '')); return; }
                    if (typeof showToast === 'function') showToast('Додано в чергу друку');
                    close();
                });
            return;
        }
        // Queue existing pack
        var fd = new FormData();
        fd.append('pack_id', _currentPack.id);
        fetch('/print/api/queue_add', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { alert('Помилка: ' + (d.error || '')); return; }
                if (typeof showToast === 'function') showToast('Додано в чергу друку');
                close();
            });
    }

    /**
     * Print all — opens each document in a new tab.
     */
    function printAll() {
        if (!_currentPack) return;
        var items = _currentPack.items || [];
        var opened = 0;
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            if (it.status === 'ok' && it.url) {
                window.open(it.url, '_blank');
                opened++;
            }
        }
        if (opened > 0 && typeof showToast === 'function') {
            showToast('Відкрито ' + opened + ' документ(ів)');
        }
    }

    function _esc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Close on backdrop click
    document.addEventListener('click', function (e) {
        var overlay = document.getElementById('packPrintModal');
        if (overlay && e.target === overlay) close();
    });

    return {
        open: open,
        close: close,
        regenerate: regenerate,
        printAll: printAll,
        addToQueue: addToQueue,
        _doGenerate: _doGenerate
    };
}());
