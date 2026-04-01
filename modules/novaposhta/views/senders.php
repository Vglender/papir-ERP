<?php
$csVer = filemtime('/var/www/papir/modules/shared/ui.css');
?>
<style>
.senders-wrap    { max-width: 1100px; margin: 0 auto; }
.senders-toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.senders-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; }

.sender-grid     { display: grid; grid-template-columns: repeat(auto-fill, minmax(480px, 1fr)); gap: 20px; }

.sender-card     { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 0; overflow: hidden; }
.sender-card-head{ display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px 14px;
                   border-bottom: 1px solid #f1f5f9; }
.sender-card-name{ font-size: 15px; font-weight: 700; color: #0f172a; }
.sender-card-meta{ font-size: 12px; color: #64748b; margin-top: 2px; }
.sender-card-actions { margin-left: auto; display: flex; gap: 6px; align-items: center; flex-shrink: 0; }

.sender-section  { padding: 12px 18px; border-bottom: 1px solid #f1f5f9; }
.sender-section:last-child { border-bottom: none; }
.sender-section-title { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;
                         letter-spacing: .05em; margin-bottom: 8px; }

/* Contacts */
.contact-list    { display: flex; flex-direction: column; gap: 4px; }
.contact-row     { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.contact-name    { font-weight: 500; color: #1e293b; }
.contact-phone   { color: #64748b; font-family: monospace; font-size: 12px; }

/* Addresses */
.addr-list       { display: flex; flex-direction: column; gap: 5px; }
.addr-row        { display: flex; align-items: center; gap: 8px; font-size: 12px; padding: 5px 8px;
                   border: 1px solid #e5e7eb; border-radius: 6px; background: #f8fafc; }
.addr-row.is-default { background: #f0fdf4; border-color: #bbf7d0; }
.addr-city       { font-size: 11px; color: #64748b; white-space: nowrap; }
.addr-desc       { flex: 1; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.addr-set-btn    { font-size: 11px; color: #0d9488; background: none; border: none;
                   cursor: pointer; padding: 2px 6px; border-radius: 4px; white-space: nowrap; flex-shrink: 0; }
.addr-set-btn:hover { background: #f0fdfa; }

/* Contact delete btn */
.contact-row        { position: relative; }
.contact-del-btn    { margin-left: auto; background: none; border: none; cursor: pointer;
                      color: #cbd5e1; font-size: 12px; padding: 2px 4px; flex-shrink: 0; }
.contact-del-btn:hover { color: #ef4444; }

/* Address delete btn */
.addr-del-btn       { background: none; border: none; cursor: pointer; color: #cbd5e1;
                      font-size: 12px; padding: 2px 4px; flex-shrink: 0; }
.addr-del-btn:hover { color: #ef4444; }

/* Add forms */
.add-contact-form input,
.add-addr-form input { padding: 5px 8px; border: 1px solid #d1d5db; border-radius: 5px; }
.add-contact-form input:focus,
.add-addr-form input:focus { outline: none; border-color: #0d9488; }

/* Dropdown options */
.af-city-dd .dd-opt,
.af-street-dd .dd-opt { padding: 6px 10px; cursor: pointer; }
.af-city-dd .dd-opt:hover,
.af-street-dd .dd-opt:hover { background: #f0fdfa; }

/* Refresh state */
.sender-card.refreshing .sender-card-head { opacity: .6; }
.refresh-log     { font-size: 11px; color: #64748b; margin-top: 6px; padding: 6px 10px;
                   background: #f8fafc; border-radius: 6px; display: none; }
.refresh-log.visible { display: block; }
</style>

<div class="senders-wrap">

  <div class="senders-toolbar">
    <h1>НП · Відправники</h1>
    <span class="text-muted fs-12" style="margin-left:4px"><?php echo count($senders); ?> відправника</span>
  </div>

  <div class="sender-grid">
  <?php foreach ($senders as $s):
    $sRef      = $s['Ref'];
    $contacts  = $s['contacts'];
    $addresses = $s['addresses'];
    $apiMasked = $s['api'] ? substr($s['api'], 0, 6) . '••••••••••••••••••••••••••' : '—';
  ?>
    <div class="sender-card" id="senderCard_<?php echo ViewHelper::h($sRef); ?>" data-ref="<?php echo ViewHelper::h($sRef); ?>">

      <!-- Head -->
      <div class="sender-card-head">
        <div style="flex:1;min-width:0">
          <div class="sender-card-name">
            <?php echo ViewHelper::h($s['Description']); ?>
            <?php if ($s['is_default']): ?>
              <span class="badge badge-green" style="font-size:10px;vertical-align:middle;margin-left:4px">default</span>
            <?php endif; ?>
          </div>
          <div class="sender-card-meta">
            <?php if ($s['CounterpartyFullName']): ?>
              <?php echo ViewHelper::h($s['CounterpartyFullName']); ?> &nbsp;·&nbsp;
            <?php endif; ?>
            <?php if ($s['EDRPOU']): ?>
              ЄДРПОУ: <?php echo ViewHelper::h($s['EDRPOU']); ?> &nbsp;·&nbsp;
            <?php endif; ?>
            <?php if ($s['organization_name']): ?>
              <?php echo ViewHelper::h($s['organization_name']); ?>
            <?php endif; ?>
          </div>
          <div class="sender-card-meta" style="margin-top:3px;font-family:monospace;font-size:11px;color:#94a3b8">
            API: <?php echo ViewHelper::h($apiMasked); ?>
          </div>
        </div>
        <div class="sender-card-actions">
          <?php if (!$s['is_default']): ?>
          <button class="btn btn-ghost btn-sm set-default-sender-btn" data-ref="<?php echo ViewHelper::h($sRef); ?>">Зробити default</button>
          <?php endif; ?>
          <button class="btn btn-ghost btn-sm refresh-btn-sender" data-ref="<?php echo ViewHelper::h($sRef); ?>"
                  title="Оновити з НП API">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="margin-right:4px">
              <path d="M13.5 2.5A6.5 6.5 0 1 1 8 1.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              <path d="M8 1.5V4.5L11 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>Оновити
          </button>
        </div>
      </div>

      <!-- Refresh log -->
      <div class="refresh-log" id="refreshLog_<?php echo ViewHelper::h($sRef); ?>"></div>

      <!-- Contacts -->
      <div class="sender-section">
        <div class="sender-section-title" style="display:flex;align-items:center;justify-content:space-between">
          <span>Контактні особи (<span class="contact-count-<?php echo ViewHelper::h($sRef); ?>"><?php echo count($contacts); ?></span>)</span>
          <button class="btn btn-ghost btn-xs add-contact-btn" data-ref="<?php echo ViewHelper::h($sRef); ?>">+ Додати</button>
        </div>
        <div class="contact-list" id="contactList_<?php echo ViewHelper::h($sRef); ?>">
          <?php if (empty($contacts)): ?>
            <span class="text-muted fs-12">Немає</span>
          <?php else: ?>
            <?php foreach ($contacts as $c): ?>
              <div class="contact-row" data-ref="<?php echo ViewHelper::h($c['Ref']); ?>">
                <span class="contact-name"><?php echo ViewHelper::h($c['full_name']); ?></span>
                <?php if ($c['phone']): ?>
                  <span class="contact-phone"><?php echo ViewHelper::h($c['phone']); ?></span>
                <?php endif; ?>
                <?php if ($c['ttn_count'] > 0): ?>
                  <span class="badge badge-blue contact-ttn-badge" title="Використовується в ТТН"
                        style="font-size:10px;margin-left:4px"><?php echo (int)$c['ttn_count']; ?> ТТН</span>
                <?php endif; ?>
                <button class="contact-del-btn" data-sender="<?php echo ViewHelper::h($sRef); ?>"
                        data-ref="<?php echo ViewHelper::h($c['Ref']); ?>" title="Видалити">&#x2715;</button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <!-- Add contact form -->
        <div class="add-contact-form" id="addContactForm_<?php echo ViewHelper::h($sRef); ?>" style="display:none;margin-top:8px">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:6px">
            <input type="text" class="cf-last"  placeholder="Прізвище *" style="font-size:12px">
            <input type="text" class="cf-first" placeholder="Ім'я *"     style="font-size:12px">
            <input type="text" class="cf-mid"   placeholder="По батькові" style="font-size:12px">
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" class="cf-phone" placeholder="Телефон * (0XXXXXXXXX)" style="font-size:12px;flex:1">
            <button class="btn btn-primary btn-xs cf-save-btn" data-ref="<?php echo ViewHelper::h($sRef); ?>">Зберегти</button>
            <button class="btn btn-ghost btn-xs cf-cancel-btn" data-ref="<?php echo ViewHelper::h($sRef); ?>">Скасувати</button>
          </div>
          <div class="cf-error" style="font-size:11px;color:#ef4444;margin-top:4px;display:none"></div>
        </div>
      </div>

      <!-- Addresses -->
      <div class="sender-section">
        <div class="sender-section-title" style="display:flex;align-items:center;justify-content:space-between">
          <span>Адреси відправки (<span class="addr-count-<?php echo ViewHelper::h($sRef); ?>"><?php echo count($addresses); ?></span>)</span>
          <button class="btn btn-ghost btn-xs add-addr-btn" data-ref="<?php echo ViewHelper::h($sRef); ?>">+ Додати</button>
        </div>
        <div class="addr-list" id="addrList_<?php echo ViewHelper::h($sRef); ?>">
          <?php foreach ($addresses as $a): ?>
            <div class="addr-row <?php echo $a['is_default'] ? 'is-default' : ''; ?>"
                 id="addr_<?php echo ViewHelper::h($a['Ref']); ?>">
              <?php if ($a['CityDescription']): ?>
                <span class="addr-city"><?php echo ViewHelper::h($a['CityDescription']); ?></span>
                <span style="color:#d1d5db">·</span>
              <?php endif; ?>
              <span class="addr-desc" title="<?php echo ViewHelper::h($a['Description']); ?>">
                <?php echo ViewHelper::h($a['Description']); ?>
              </span>
              <?php if ($a['is_default']): ?>
                <span class="badge badge-green" style="font-size:10px;flex-shrink:0">default</span>
              <?php else: ?>
                <button class="addr-set-btn"
                        data-sender="<?php echo ViewHelper::h($sRef); ?>"
                        data-addr="<?php echo ViewHelper::h($a['Ref']); ?>">
                  Зробити default
                </button>
                <button class="addr-del-btn" data-sender="<?php echo ViewHelper::h($sRef); ?>"
                        data-addr="<?php echo ViewHelper::h($a['Ref']); ?>" title="Видалити">&#x2715;</button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (empty($addresses)): ?><span class="text-muted fs-12">Немає</span><?php endif; ?>
        </div>
        <!-- Add address form -->
        <div class="add-addr-form" id="addAddrForm_<?php echo ViewHelper::h($sRef); ?>" style="display:none;margin-top:8px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:6px">
            <div style="position:relative">
              <input type="text" class="af-city-input" placeholder="Місто *" style="font-size:12px;width:100%"
                     autocomplete="off">
              <div class="af-city-dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;
                   border:1px solid #d1d5db;border-radius:6px;z-index:100;max-height:160px;overflow-y:auto;font-size:12px"></div>
              <input type="hidden" class="af-city-ref">
            </div>
            <div style="position:relative">
              <input type="text" class="af-street-input" placeholder="Вулиця *" style="font-size:12px;width:100%"
                     autocomplete="off" disabled>
              <div class="af-street-dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;
                   border:1px solid #d1d5db;border-radius:6px;z-index:100;max-height:160px;overflow-y:auto;font-size:12px"></div>
              <input type="hidden" class="af-street-ref">
            </div>
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <input type="text" class="af-building" placeholder="Будинок *" style="font-size:12px;width:80px">
            <input type="text" class="af-flat"     placeholder="Кв./офіс"  style="font-size:12px;width:80px">
            <button class="btn btn-primary btn-xs af-save-btn" data-ref="<?php echo ViewHelper::h($sRef); ?>">Зберегти</button>
            <button class="btn btn-ghost btn-xs af-cancel-btn" data-ref="<?php echo ViewHelper::h($sRef); ?>">Скасувати</button>
          </div>
          <div class="af-error" style="font-size:11px;color:#ef4444;margin-top:4px;display:none"></div>
        </div>
      </div>

    </div>
  <?php endforeach; ?>
  </div>

</div>

<script>
(function () {

    // ── Refresh sender ────────────────────────────────────────────────────
    document.querySelectorAll('.refresh-btn-sender').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ref  = btn.dataset.ref;
            var card = document.getElementById('senderCard_' + ref);
            var log  = document.getElementById('refreshLog_' + ref);

            btn.disabled = true;
            btn.textContent = 'Оновлення…';
            card.classList.add('refreshing');
            log.textContent = '';
            log.classList.remove('visible');

            fetch('/novaposhta/api/refresh_sender', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'sender_ref=' + encodeURIComponent(ref)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                card.classList.remove('refreshing');
                btn.disabled = false;
                btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" style="margin-right:4px"><path d="M13.5 2.5A6.5 6.5 0 1 1 8 1.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M8 1.5V4.5L11 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>Оновити';

                if (!res.ok) {
                    log.textContent = 'Помилка: ' + (res.error || '');
                    log.classList.add('visible');
                    return;
                }

                // Show log
                log.textContent = res.log.join(' · ');
                log.classList.add('visible');
                setTimeout(function () { log.classList.remove('visible'); }, 5000);

                // Re-render contacts
                var cl = document.getElementById('contactList_' + ref);
                if (cl && res.sender.contacts) {
                    if (res.sender.contacts.length === 0) {
                        cl.innerHTML = '<span class="text-muted fs-12">Немає</span>';
                    } else {
                        cl.innerHTML = res.sender.contacts.map(function (c) {
                            var ttnBadge = (c.ttn_count > 0)
                                ? '<span class="badge badge-blue contact-ttn-badge" title="Використовується в ТТН" style="font-size:10px;margin-left:4px">' + esc(String(c.ttn_count)) + ' ТТН</span>'
                                : '';
                            return '<div class="contact-row" data-ref="' + esc(c.Ref) + '">' +
                                '<span class="contact-name">' + esc(c.full_name) + '</span>' +
                                (c.phone ? '<span class="contact-phone">' + esc(c.phone) + '</span>' : '') +
                                ttnBadge +
                                '<button class="contact-del-btn" data-sender="' + esc(ref) + '" data-ref="' + esc(c.Ref) + '" title="Видалити">&#x2715;</button>' +
                                '</div>';
                        }).join('');
                        bindContactDel(cl);
                    }
                }

                // Re-render addresses
                var al = document.getElementById('addrList_' + ref);
                if (al && res.sender.addresses) {
                    if (res.sender.addresses.length === 0) {
                        al.innerHTML = '<span class="text-muted fs-12">Немає</span>';
                    } else {
                        al.innerHTML = res.sender.addresses.map(function (a) {
                            var def = a.is_default == 1 || a.is_default === true;
                            return '<div class="addr-row ' + (def ? 'is-default' : '') + '" id="addr_' + esc(a.Ref) + '">' +
                                (a.CityDescription ? '<span class="addr-city">' + esc(a.CityDescription) + '</span><span style="color:#d1d5db">·</span>' : '') +
                                '<span class="addr-desc" title="' + esc(a.Description) + '">' + esc(a.Description) + '</span>' +
                                (def
                                    ? '<span class="badge badge-green" style="font-size:10px;flex-shrink:0">default</span>'
                                    : '<button class="addr-set-btn" data-sender="' + esc(ref) + '" data-addr="' + esc(a.Ref) + '">Зробити default</button>'
                                ) +
                                '</div>';
                        }).join('');
                        bindSetDefault(al);
                    }
                }
            })
            .catch(function () {
                card.classList.remove('refreshing');
                btn.disabled = false;
                btn.textContent = 'Оновити';
                log.textContent = 'Помилка мережі';
                log.classList.add('visible');
            });
        });
    });

    // ── Set default address ───────────────────────────────────────────────
    function renderAddrList(list, sRef, newDefaultRef) {
        var rows = list.querySelectorAll('.addr-row');
        var html = '';
        rows.forEach(function (row) {
            var rRef   = row.id.replace('addr_', '');
            var isDef  = (rRef === newDefaultRef);
            var city   = row.querySelector('.addr-city');
            var desc   = row.querySelector('.addr-desc');
            var cityHtml = city
                ? '<span class="addr-city">' + city.innerHTML + '</span><span style="color:#d1d5db">·</span>'
                : '';
            var descTitle = desc ? desc.getAttribute('title') : '';
            var descText  = desc ? desc.innerHTML : '';
            html += '<div class="addr-row ' + (isDef ? 'is-default' : '') + '" id="addr_' + esc(rRef) + '">' +
                cityHtml +
                '<span class="addr-desc" title="' + esc(descTitle) + '">' + descText + '</span>' +
                (isDef
                    ? '<span class="badge badge-green" style="font-size:10px;flex-shrink:0">default</span>'
                    : '<button class="addr-set-btn" data-sender="' + esc(sRef) + '" data-addr="' + esc(rRef) + '">Зробити default</button>' +
                      '<button class="addr-del-btn" data-sender="' + esc(sRef) + '" data-addr="' + esc(rRef) + '" title="Видалити">&#x2715;</button>'
                ) +
                '</div>';
        });
        list.innerHTML = html;
        bindSetDefault(list);
        bindAddrDel(list);
    }

    function bindSetDefault(container) {
        (container || document).querySelectorAll('.addr-set-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sRef = btn.dataset.sender;
                var aRef = btn.dataset.addr;
                btn.textContent = '…';
                btn.disabled = true;

                fetch('/novaposhta/api/set_default_address', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'sender_ref=' + encodeURIComponent(sRef) + '&address_ref=' + encodeURIComponent(aRef)
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { btn.textContent = 'Помилка'; return; }
                    var list = document.getElementById('addrList_' + sRef);
                    if (!list) return;
                    renderAddrList(list, sRef, aRef);
                });
            });
        });
    }
    bindSetDefault();

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }


    // ── Add / delete contact person ───────────────────────────────────────
    document.querySelectorAll('.add-contact-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ref  = btn.dataset.ref;
            var form = document.getElementById('addContactForm_' + ref);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    });

    document.querySelectorAll('.cf-cancel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('addContactForm_' + btn.dataset.ref).style.display = 'none';
        });
    });

    document.querySelectorAll('.cf-save-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ref  = btn.dataset.ref;
            var form = document.getElementById('addContactForm_' + ref);
            var last  = form.querySelector('.cf-last').value.trim();
            var first = form.querySelector('.cf-first').value.trim();
            var mid   = form.querySelector('.cf-mid').value.trim();
            var phone = form.querySelector('.cf-phone').value.trim();
            var err   = form.querySelector('.cf-error');
            if (!last || !first || !phone) {
                err.textContent = "Прізвище, ім'я та телефон — обов'язкові";
                err.style.display = 'block'; return;
            }
            err.style.display = 'none';
            btn.disabled = true;
            fetch('/novaposhta/api/save_contact_person', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'sender_ref=' + encodeURIComponent(ref) +
                      '&last_name='  + encodeURIComponent(last) +
                      '&first_name=' + encodeURIComponent(first) +
                      '&middle_name='+ encodeURIComponent(mid) +
                      '&phone='      + encodeURIComponent(phone)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (!res.ok) { err.textContent = res.error || 'Помилка'; err.style.display = 'block'; return; }
                form.style.display = 'none';
                form.querySelector('.cf-last').value  = '';
                form.querySelector('.cf-first').value = '';
                form.querySelector('.cf-mid').value   = '';
                form.querySelector('.cf-phone').value = '';
                // Append new contact row
                var list = document.getElementById('contactList_' + ref);
                var placeholder = list.querySelector('.text-muted');
                if (placeholder) placeholder.remove();
                var row = document.createElement('div');
                row.className = 'contact-row';
                row.dataset.ref = esc(res.contact.Ref);
                row.innerHTML = '<span class="contact-name">' + esc(res.contact.full_name) + '</span>' +
                    (res.contact.phone ? '<span class="contact-phone">' + esc(res.contact.phone) + '</span>' : '') +
                    '<button class="contact-del-btn" data-sender="' + esc(ref) + '" data-ref="' + esc(res.contact.Ref) + '" title="Видалити">&#x2715;</button>';
                list.appendChild(row);
                bindContactDel(row);
                // Update count
                var countEl = document.querySelector('.contact-count-' + ref);
                if (countEl) countEl.textContent = list.querySelectorAll('.contact-row').length;
            })
            .catch(function () { btn.disabled = false; err.textContent = 'Помилка мережі'; err.style.display = 'block'; });
        });
    });

    function bindContactDel(container) {
        (container || document).querySelectorAll('.contact-del-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Видалити контактну особу?')) return;
                var sRef = btn.dataset.sender;
                var cRef = btn.dataset.ref;
                btn.disabled = true;
                fetch('/novaposhta/api/delete_contact_person', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'sender_ref=' + encodeURIComponent(sRef) + '&contact_ref=' + encodeURIComponent(cRef)
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { btn.disabled = false; alert('Помилка: ' + (res.error || '')); return; }
                    var row  = btn.closest('.contact-row');
                    var list = row.parentNode;
                    row.remove();
                    var countEl = document.querySelector('.contact-count-' + sRef);
                    if (countEl) countEl.textContent = list.querySelectorAll('.contact-row').length;
                    if (!list.querySelector('.contact-row')) {
                        list.innerHTML = '<span class="text-muted fs-12">Немає</span>';
                    }
                    if (res.warning) { showToast(res.warning); }
                })
                .catch(function () { btn.disabled = false; });
            });
        });
    }
    document.querySelectorAll('.contact-del-btn').forEach(function (btn) {
        bindContactDel(btn.closest('.contact-row'));
    });

    // ── Add / delete sender address ────────────────────────────────────────
    document.querySelectorAll('.add-addr-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ref  = btn.dataset.ref;
            var form = document.getElementById('addAddrForm_' + ref);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
    });

    document.querySelectorAll('.af-cancel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('addAddrForm_' + btn.dataset.ref).style.display = 'none';
        });
    });

    // City search in add-addr-form
    document.querySelectorAll('.add-addr-form').forEach(function (form) {
        var cityInput  = form.querySelector('.af-city-input');
        var cityDd     = form.querySelector('.af-city-dd');
        var cityRef    = form.querySelector('.af-city-ref');
        var streetInput= form.querySelector('.af-street-input');
        var streetDd   = form.querySelector('.af-street-dd');
        var streetRef  = form.querySelector('.af-street-ref');
        var sRef       = form.closest('.sender-card').dataset.ref;
        var cityTimer, streetTimer;

        cityInput.addEventListener('input', function () {
            clearTimeout(cityTimer);
            cityRef.value = ''; streetRef.value = '';
            streetInput.disabled = true; streetInput.value = '';
            var q = cityInput.value.trim();
            if (q.length < 2) { cityDd.style.display = 'none'; return; }
            cityTimer = setTimeout(function () {
                fetch('/novaposhta/api/search_city?q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(sRef))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    cityDd.innerHTML = '';
                    if (!res.ok || !res.cities || !res.cities.length) { cityDd.style.display = 'none'; return; }
                    res.cities.forEach(function (c) {
                        var opt = document.createElement('div');
                        opt.className = 'dd-opt';
                        opt.textContent = c.Description + (c.SettlementTypeDescription ? ' (' + c.SettlementTypeDescription + ')' : '');
                        opt.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            cityInput.value  = opt.textContent;
                            cityRef.value    = c.Ref;
                            cityDd.style.display = 'none';
                            streetInput.disabled = false;
                            streetInput.focus();
                        });
                        cityDd.appendChild(opt);
                    });
                    cityDd.style.display = 'block';
                });
            }, 300);
        });
        cityInput.addEventListener('blur', function () { setTimeout(function () { cityDd.style.display = 'none'; }, 150); });

        streetInput.addEventListener('input', function () {
            clearTimeout(streetTimer);
            streetRef.value = '';
            var q   = streetInput.value.trim();
            var crf = cityRef.value;
            if (!crf || q.length < 2) { streetDd.style.display = 'none'; return; }
            streetTimer = setTimeout(function () {
                fetch('/novaposhta/api/search_street?city_ref=' + encodeURIComponent(crf) + '&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    streetDd.innerHTML = '';
                    if (!res.ok || !res.streets || !res.streets.length) { streetDd.style.display = 'none'; return; }
                    res.streets.forEach(function (s) {
                        var opt = document.createElement('div');
                        opt.className = 'dd-opt';
                        opt.textContent = (s.StreetsType ? s.StreetsType + ' ' : '') + s.Description;
                        opt.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            streetInput.value = opt.textContent;
                            streetRef.value   = s.Ref;
                            streetDd.style.display = 'none';
                            form.querySelector('.af-building').focus();
                        });
                        streetDd.appendChild(opt);
                    });
                    streetDd.style.display = 'block';
                });
            }, 300);
        });
        streetInput.addEventListener('blur', function () { setTimeout(function () { streetDd.style.display = 'none'; }, 150); });
    });

    document.querySelectorAll('.af-save-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ref    = btn.dataset.ref;
            var form   = document.getElementById('addAddrForm_' + ref);
            var cRef   = form.querySelector('.af-city-ref').value;
            var cName  = form.querySelector('.af-city-input').value.trim();
            var sRef2  = form.querySelector('.af-street-ref').value;
            var bld    = form.querySelector('.af-building').value.trim();
            var flat   = form.querySelector('.af-flat').value.trim();
            var err    = form.querySelector('.af-error');
            if (!cRef || !sRef2 || !bld) {
                err.textContent = 'Оберіть місто, вулицю і вкажіть будинок';
                err.style.display = 'block'; return;
            }
            err.style.display = 'none';
            btn.disabled = true;
            fetch('/novaposhta/api/save_sender_address', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'sender_ref='  + encodeURIComponent(ref) +
                      '&city_ref='   + encodeURIComponent(cRef) +
                      '&city_name='  + encodeURIComponent(cName) +
                      '&street_ref=' + encodeURIComponent(sRef2) +
                      '&building='   + encodeURIComponent(bld) +
                      '&flat='       + encodeURIComponent(flat)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false;
                if (!res.ok) { err.textContent = res.error || 'Помилка'; err.style.display = 'block'; return; }
                form.style.display = 'none';
                form.querySelector('.af-city-input').value = ''; form.querySelector('.af-city-ref').value = '';
                form.querySelector('.af-street-input').value = ''; form.querySelector('.af-street-ref').value = '';
                form.querySelector('.af-building').value = ''; form.querySelector('.af-flat').value = '';
                // Re-render address list
                var list = document.getElementById('addrList_' + ref);
                list.innerHTML = res.addresses.map(function (a) {
                    var def = a.is_default == 1 || a.is_default === true;
                    return '<div class="addr-row ' + (def ? 'is-default' : '') + '" id="addr_' + esc(a.Ref) + '">' +
                        (a.CityDescription ? '<span class="addr-city">' + esc(a.CityDescription) + '</span><span style="color:#d1d5db">·</span>' : '') +
                        '<span class="addr-desc" title="' + esc(a.Description) + '">' + esc(a.Description) + '</span>' +
                        (def
                            ? '<span class="badge badge-green" style="font-size:10px;flex-shrink:0">default</span>'
                            : '<button class="addr-set-btn" data-sender="' + esc(ref) + '" data-addr="' + esc(a.Ref) + '">Зробити default</button>' +
                              '<button class="addr-del-btn" data-sender="' + esc(ref) + '" data-addr="' + esc(a.Ref) + '" title="Видалити">&#x2715;</button>'
                        ) +
                        '</div>';
                }).join('');
                bindSetDefault(list);
                bindAddrDel(list);
                var countEl = document.querySelector('.addr-count-' + ref);
                if (countEl) countEl.textContent = res.addresses.length;
            })
            .catch(function () { btn.disabled = false; err.textContent = 'Помилка мережі'; err.style.display = 'block'; });
        });
    });

    function bindAddrDel(container) {
        (container || document).querySelectorAll('.addr-del-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Видалити адресу?')) return;
                var sRef = btn.dataset.sender;
                var aRef = btn.dataset.addr;
                btn.disabled = true;
                fetch('/novaposhta/api/delete_sender_address', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'sender_ref=' + encodeURIComponent(sRef) + '&address_ref=' + encodeURIComponent(aRef)
                })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok) { btn.disabled = false; alert('Помилка: ' + (res.error || '')); return; }
                    var list = document.getElementById('addrList_' + sRef);
                    list.innerHTML = res.addresses.map(function (a) {
                        var def = a.is_default == 1 || a.is_default === true;
                        return '<div class="addr-row ' + (def ? 'is-default' : '') + '" id="addr_' + esc(a.Ref) + '">' +
                            (a.CityDescription ? '<span class="addr-city">' + esc(a.CityDescription) + '</span><span style="color:#d1d5db">·</span>' : '') +
                            '<span class="addr-desc" title="' + esc(a.Description) + '">' + esc(a.Description) + '</span>' +
                            (def
                                ? '<span class="badge badge-green" style="font-size:10px;flex-shrink:0">default</span>'
                                : '<button class="addr-set-btn" data-sender="' + esc(sRef) + '" data-addr="' + esc(a.Ref) + '">Зробити default</button>' +
                                  '<button class="addr-del-btn" data-sender="' + esc(sRef) + '" data-addr="' + esc(a.Ref) + '" title="Видалити">&#x2715;</button>'
                            ) +
                            '</div>';
                    }).join('') || '<span class="text-muted fs-12">Немає</span>';
                    bindSetDefault(list);
                    bindAddrDel(list);
                    var countEl = document.querySelector('.addr-count-' + sRef);
                    if (countEl) countEl.textContent = res.addresses.length;
                })
                .catch(function () { btn.disabled = false; });
            });
        });
    }
    bindAddrDel();

    // ── Set default sender ───────────────────────────────────────────────
    document.querySelectorAll('.set-default-sender-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ref = btn.dataset.ref;
            if (!confirm('Зробити цього відправника дефолтним?')) return;
            btn.disabled = true;
            fetch('/novaposhta/api/set_default_sender', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'sender_ref=' + encodeURIComponent(ref)
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { btn.disabled = false; alert('Помилка: ' + (res.error || '')); return; }
                window.location.reload();
            })
            .catch(function () { btn.disabled = false; });
        });
    });
}());
</script>