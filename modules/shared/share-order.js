/**
 * ShareOrder — модальне вікно «Надіслати замовлення співробітнику».
 *
 * Використання:
 *   ShareOrder.open({ orderId: 123, orderNumber: '00045', orderDate: '2026-04-07', orderSum: '1500.00' });
 *
 * Відправляє DM через /counterparties/api/send_team_message з посиланням на замовлення + коментар.
 */
var ShareOrder = (function() {

  var _overlay    = null;
  var _empList    = null;   // cached employee list
  var _currentCpId = 0;

  function _ensureDOM() {
    if (_overlay) return;

    var html =
      '<div class="modal-box" style="width:440px;max-width:96vw">'
      + '<div class="modal-head">'
      +   '<span>Надіслати співробітнику</span>'
      +   '<button type="button" class="modal-close" id="soClose">&#x2715;</button>'
      + '</div>'
      + '<div class="modal-body" style="padding:16px 20px">'
      +   '<div style="margin-bottom:12px">'
      +     '<label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Співробітник</label>'
      +     '<select id="soEmpSelect" style="width:100%;box-sizing:border-box;padding:7px 10px;font-size:13px;'
      +       'border:1px solid #d1d5db;border-radius:6px;font-family:inherit;outline:none">'
      +       '<option value="">Завантаження…</option>'
      +     '</select>'
      +   '</div>'
      +   '<div style="margin-bottom:12px">'
      +     '<label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Посилання</label>'
      +     '<input type="text" id="soLink" readonly style="width:100%;box-sizing:border-box;padding:7px 10px;'
      +       'font-size:13px;border:1px solid #d1d5db;border-radius:6px;background:#f9fafb;color:#374151;font-family:inherit">'
      +   '</div>'
      +   '<div>'
      +     '<label style="font-size:12px;color:#6b7280;display:block;margin-bottom:4px">Коментар</label>'
      +     '<textarea id="soComment" rows="4" placeholder="Додайте коментар для співробітника…"'
      +       ' style="width:100%;box-sizing:border-box;font-size:13px;font-family:inherit;line-height:1.5;'
      +       'border:1px solid #d1d5db;border-radius:6px;padding:8px 10px;resize:vertical;outline:none"'
      +       ' onfocus="this.style.borderColor=\'#7c3aed\'" onblur="this.style.borderColor=\'#d1d5db\'"></textarea>'
      +   '</div>'
      + '</div>'
      + '<div class="modal-footer">'
      +   '<button type="button" class="btn btn-primary" id="soSendBtn">📨 Надіслати</button>'
      +   '<button type="button" class="btn btn-ghost" id="soCancelBtn">Скасувати</button>'
      + '</div>'
      + '</div>';

    _overlay = document.createElement('div');
    _overlay.className = 'modal-overlay';
    _overlay.id = 'shareOrderModal';
    _overlay.innerHTML = html;
    document.body.appendChild(_overlay);

    // Close handlers
    _overlay.querySelector('#soClose').addEventListener('click', _close);
    _overlay.querySelector('#soCancelBtn').addEventListener('click', _close);
    _overlay.addEventListener('click', function(e) { if (e.target === _overlay) _close(); });

    // Send handler
    _overlay.querySelector('#soSendBtn').addEventListener('click', _send);

    // Enter in comment = send
    _overlay.querySelector('#soComment').addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && e.ctrlKey) { e.preventDefault(); _send(); }
    });
  }

  function _close() {
    if (_overlay) _overlay.classList.remove('open');
  }

  function _loadEmployees(cb) {
    if (_empList) { cb(_empList); return; }
    fetch('/counterparties/api/get_team_state')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok) { cb([]); return; }
        _empList = (d.employees || []).map(function(e) {
          return { id: e.id, name: e.name };
        });
        cb(_empList);
      })
      .catch(function() { cb([]); });
  }

  function _send() {
    var sel     = document.getElementById('soEmpSelect');
    var link    = document.getElementById('soLink');
    var comment = document.getElementById('soComment');
    var btn     = document.getElementById('soSendBtn');

    var empId = sel ? parseInt(sel.value, 10) : 0;
    if (!empId) {
      sel.focus();
      sel.style.borderColor = '#ef4444';
      setTimeout(function() { sel.style.borderColor = '#d1d5db'; }, 1500);
      return;
    }

    var body = (link ? link.value : '') + (comment.value.trim() ? '\n\n' + comment.value.trim() : '');
    if (!body) return;

    if (btn) { btn.disabled = true; btn.textContent = '⏳ Надсилаю…'; }

    var fd = new FormData();
    fd.append('to_employee_id', empId);
    fd.append('body', body);
    if (_currentCpId) fd.append('counterparty_id', _currentCpId);

    fetch('/counterparties/api/send_team_message', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (btn) { btn.disabled = false; btn.textContent = '📨 Надіслати'; }
        if (d.ok) {
          _close();
          if (typeof showToast === 'function') showToast('Надіслано');
        } else {
          if (typeof showToast === 'function') showToast('Помилка: ' + (d.error || ''), true);
        }
      })
      .catch(function() {
        if (btn) { btn.disabled = false; btn.textContent = '📨 Надіслати'; }
        if (typeof showToast === 'function') showToast('Помилка мережі', true);
      });
  }

  return {
    /**
     * @param {Object} opts
     * @param {number} opts.orderId
     * @param {string} opts.orderNumber
     * @param {string} [opts.orderDate]
     * @param {string} [opts.orderSum]
     * @param {number} [opts.counterpartyId] — links message to cp context
     */
    open: function(opts) {
      opts = opts || {};
      _currentCpId = opts.counterpartyId || 0;
      _ensureDOM();

      // Set link
      var link = document.getElementById('soLink');
      var url  = location.origin + '/customerorder/edit?id=' + opts.orderId;
      if (link) link.value = url;

      // Pre-fill comment with order context
      var comment = document.getElementById('soComment');
      var header  = 'Замовлення #' + (opts.orderNumber || opts.orderId);
      if (opts.orderDate) header += ' від ' + opts.orderDate;
      if (opts.orderSum)  header += ', сума: ' + opts.orderSum + ' грн';
      if (comment) comment.value = header;

      // Load employees
      var sel = document.getElementById('soEmpSelect');
      if (sel) sel.innerHTML = '<option value="">Завантаження…</option>';

      _overlay.classList.add('open');

      _loadEmployees(function(emps) {
        if (!sel) return;
        sel.innerHTML = '<option value="">— оберіть співробітника —</option>';
        emps.forEach(function(e) {
          var opt = document.createElement('option');
          opt.value = e.id;
          opt.textContent = e.name;
          sel.appendChild(opt);
        });
        sel.focus();
      });
    }
  };
}());