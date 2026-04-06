/**
 * TeamChat — модуль командного чата.
 *
 * Два режима использования:
 *  1. Глобальный чат в шапке (mode='general' + dm)
 *     Инициализация: TeamChat.initGlobal({ onUnreadChange: fn })
 *     Использует DOM-элементы с id: tcMsgs, tcInput, tcSendBtn, tcFwdStrip, tcTabs, tcTitle
 *
 *  2. В workspace (cp context, mode='cp')
 *     Инициализация: WsCpChat.init(cpId) — тонкая обёртка над TeamChat
 *     Использует DOM-элементы: wsTcMsgs, wsTcInput, wsTcSendBtn, wsTcFwdStrip
 *
 * TeamChat поддерживает конфигурацию ID элементов через _ids:
 *   TeamChat._ids = { msgs: 'tcMsgs', input: 'tcInput', ... }
 */
var TeamChat = {

  // ── State ──────────────────────────────────────────────────────────────────
  myEmpId:        null,
  mode:           'general',  // 'general' | 'dm' | 'cp'
  cpId:           null,
  dmWithId:       null,
  dmWithName:     null,
  employees:      [],
  lastId:         0,
  pollTimer:      null,
  onUnreadChange: null,
  _pendingFwd:    null,

  // DOM element IDs — override for different containers
  _ids: {
    msgs:     'tcMsgs',
    input:    'tcInput',
    sendBtn:  'tcSendBtn',
    fwdStrip: 'tcFwdStrip',
    tabs:     'tcTabs',
    title:    'tcTitle',
  },

  _el: function(key) {
    return document.getElementById(this._ids[key] || key);
  },

  // ── Init: global panel ────────────────────────────────────────────────────
  initGlobal: function(opts) {
    opts = opts || {};
    this.mode           = 'general';
    this.cpId           = null;
    this.lastId         = 0;
    this._ids = { msgs: 'tcMsgs', input: 'tcInput', sendBtn: 'tcSendBtn', fwdStrip: 'tcFwdStrip', tabs: 'tcTabs', title: 'tcTitle' };
    if (opts.onUnreadChange) this.onUnreadChange = opts.onUnreadChange;
    var self = this;
    this._loadState(function(d) {
      self.renderGlobalTabs(d);
      self.loadMessages(true);
      self.startPolling();
      // bind input
      var inp = self._el('input');
      var btn = self._el('sendBtn');
      if (inp) inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); self.send(); }
      });
    });
  },

  // ── Init: cp context (called via WsCpChat) ────────────────────────────────
  initCp: function(opts) {
    opts = opts || {};
    this.mode           = 'cp';
    this.cpId           = opts.cpId || null;
    this.lastId         = 0;
    this._ids = opts.ids || { msgs: 'tcMsgs', input: 'tcInput', sendBtn: 'tcSendBtn', fwdStrip: 'tcFwdStrip', tabs: 'tcTabs', title: 'tcTitle' };
    if (opts.onUnreadChange) this.onUnreadChange = opts.onUnreadChange;
    var self = this;
    this._loadState(function() {
      if (self.cpId) {
        self.loadMessages(true);
        self.startPolling();
      }
      // bind input
      var inp = self._el('input');
      var btn = self._el('sendBtn');
      if (inp) inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); self.send(); }
      });
    });
  },

  // Switch counterparty context (called when user selects different cp in ws)
  setCp: function(cpId) {
    if (this.cpId === cpId) return;
    this.cpId   = cpId;
    this.lastId = 0;
    if (cpId) {
      this.loadMessages(true);
      if (!this.pollTimer) this.startPolling();
    }
  },

  _loadState: function(cb) {
    var self = this;
    fetch('/counterparties/api/get_team_state')
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        self.myEmpId   = d.my_emp_id;
        self.employees = d.employees || [];
        var total = (d.general_unread || 0)
          + Object.keys(d.dm_unread || {}).reduce(function(s,k){ return s+(d.dm_unread[k]||0); }, 0)
          + (d.cp_unread || 0);
        if (self.onUnreadChange) self.onUnreadChange(total);
        if (cb) cb(d);
      });
  },

  // ── Global panel sidebar nav (general + DMs) ─────────────────────────────
  renderGlobalTabs: function(d) {
    var self = this;
    var wrap = this._el('tabs');
    if (!wrap) return;

    var dmUnread  = d.dm_unread || {};
    var genUnread = d.general_unread || 0;

    // General — team icon
    var html = '<button class="gc-nav-item' + (self.mode === 'general' ? ' active' : '') + '" data-mode="general" title="Загальний чат">'
             + '<svg width="18" height="18" viewBox="0 0 20 20" fill="none">'
             + '<path d="M3 4C3 3.45 3.45 3 4 3h12c.55 0 1 .45 1 1v8c0 .55-.45 1-1 1H7l-4 3V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>'
             + '</svg>'
             + (genUnread > 0 ? '<span class="gc-nav-badge">' + genUnread + '</span>' : '')
             + '</button>';

    if (self.employees.length) {
      html += '<div class="gc-nav-sep"></div>';
      self.employees.forEach(function(e) {
        var unread   = dmUnread[e.id] || 0;
        var isActive = self.mode === 'dm' && self.dmWithId === e.id;
        var parts    = (e.name || '').trim().split(/\s+/);
        var initials = parts.slice(0,2).map(function(w){ return w.charAt(0).toUpperCase(); }).join('');
        html += '<button class="gc-nav-item' + (isActive ? ' active' : '') + '" data-mode="dm" data-emp-id="' + e.id + '" data-emp-name="' + self.esc(e.name) + '" title="' + self.esc(e.name) + '">'
              + '<span class="gc-nav-avatar">' + initials + '</span>'
              + (unread > 0 ? '<span class="gc-nav-badge">' + unread + '</span>' : '')
              + '</button>';
      });
    }

    wrap.innerHTML = html;
    wrap.querySelectorAll('.gc-nav-item').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var mode = btn.getAttribute('data-mode');
        if (mode === 'general') {
          self._switchGlobalMode('general', null, null);
        } else {
          self._switchGlobalMode('dm', parseInt(btn.getAttribute('data-emp-id'),10), btn.getAttribute('data-emp-name'));
        }
      });
    });
  },

  _switchGlobalMode: function(mode, empId, empName) {
    this.mode       = mode;
    this.dmWithId   = empId || null;
    this.dmWithName = empName || null;
    this.lastId     = 0;
    var title = this._el('title');
    if (title) title.textContent = mode === 'general' ? 'Загальний чат' : (empName || '');
    var wrap = this._el('tabs');
    if (wrap) wrap.querySelectorAll('.gc-nav-item').forEach(function(b) {
      var bMode = b.getAttribute('data-mode');
      var bEmp  = parseInt(b.getAttribute('data-emp-id') || '0', 10);
      b.classList.toggle('active', bMode === mode && (mode !== 'dm' || bEmp === empId));
    });
    this.loadMessages(true);
  },

  // ── Messages ───────────────────────────────────────────────────────────────
  loadMessages: function(full) {
    var self = this;
    var url = '/counterparties/api/get_team_messages?limit=60';
    url += '&mode=' + self.mode;
    if (self.mode === 'cp')  url += '&cp_id=' + (self.cpId || 0);
    if (self.mode === 'dm')  url += '&with=' + self.dmWithId;
    if (!full && self.lastId > 0) url += '&after_id=' + self.lastId;

    fetch(url)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        if (full) {
          self.renderMessages(d.messages);
        } else if (d.messages && d.messages.length) {
          self.appendMessages(d.messages);
        }
        if (d.messages && d.messages.length) {
          self.lastId = d.messages[d.messages.length - 1].id;
        }
      });
  },

  renderMessages: function(msgs) {
    var self = this;
    var container = this._el('msgs');
    if (!container) return;
    if (!msgs || !msgs.length) {
      container.innerHTML = '<div class="tc-empty">Повідомлень поки немає</div>';
      return;
    }
    container.innerHTML = '';
    msgs.forEach(function(m) { container.appendChild(self.buildRow(m)); });
    container.scrollTop = container.scrollHeight;
    if (msgs.length) self.lastId = msgs[msgs.length - 1].id;
  },

  appendMessages: function(msgs) {
    var self = this;
    var container = this._el('msgs');
    if (!container) return;
    var wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;
    var empty = container.querySelector('.tc-empty');
    if (empty) empty.remove();
    msgs.forEach(function(m) { container.appendChild(self.buildRow(m)); });
    if (wasAtBottom) container.scrollTop = container.scrollHeight;
  },

  buildRow: function(m) {
    var self = this;
    var isMe = m.is_mine;
    var row  = document.createElement('div');
    row.className = 'tc-msg-row ' + (isMe ? 'out' : 'in');
    row.setAttribute('data-id', m.id);

    var inner = '';

    if (m.fwd_msg_id) {
      var cpLink = m.counterparty_id
        ? '<a class="tc-fwd-cp" href="/counterparties?select=' + m.counterparty_id + '">' + self.esc(m.cp_name || 'Клієнт') + ' →</a>'
        : '';
      var fwdText = m.fwd_body ? self.esc(m.fwd_body).replace(/\n/g, '<br>') : '…';
      inner += (cpLink ? '<div class="tc-fwd-header">↩ ' + cpLink + '</div>' : '<div class="tc-fwd-header">↩ Клієнт</div>')
             + '<div class="tc-quote">'
             + (m.fwd_author ? '<div class="tc-quote-meta">' + self.esc(m.fwd_author) + '</div>' : '')
             + '<div class="tc-quote-body">' + fwdText + '</div>'
             + '</div>';
      var comment = (m.body || '').trim();
      if (comment && comment !== '+') {
        inner += '<div class="tc-bubble tc-comment">' + self.linkify(self.esc(comment).replace(/\n/g, '<br>')) + '</div>';
      }
    } else {
      inner += '<div class="tc-bubble">' + self.linkify(self.esc(m.body).replace(/\n/g, '<br>')) + '</div>';
    }

    inner += '<div class="tc-meta">'
           + (!isMe ? '<span class="tc-from">' + self.esc(m.from_name || '') + '</span>' : '')
           + '<span class="tc-time">' + self.formatTime(m.created_at) + '</span>'
           + '</div>';

    row.innerHTML = inner;
    return row;
  },

  // ── Send ───────────────────────────────────────────────────────────────────
  send: function() {
    var self = this;
    var inp  = this._el('input');
    if (!inp) return;
    var body = inp.value.trim();
    if (!body && !self._pendingFwd) return;

    var fd = new FormData();
    fd.append('body', body || (self._pendingFwd ? self._pendingFwd.body : ''));
    if (self.mode === 'dm' && self.dmWithId) fd.append('to_employee_id', self.dmWithId);
    if (self.mode === 'cp' && self.cpId)     fd.append('counterparty_id', self.cpId);
    if (self._pendingFwd) {
      fd.append('fwd_msg_id',  self._pendingFwd.fwd_msg_id);
      fd.append('fwd_author',  self._pendingFwd.fwd_author || '');
      if (!fd.get('counterparty_id') && self._pendingFwd.cp_id) {
        fd.append('counterparty_id', self._pendingFwd.cp_id);
      }
    }

    var btn = this._el('sendBtn');
    if (btn) btn.disabled = true;
    fetch('/counterparties/api/send_team_message', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (btn) btn.disabled = false;
        if (d.ok) {
          inp.value = '';
          self.clearFwd();
          self.loadMessages(false);
        } else {
          if (typeof showToast === 'function') showToast('Помилка: ' + (d.error || ''), true);
        }
      })
      .catch(function() { if (btn) btn.disabled = false; });
  },

  // ── Forward from client chat ───────────────────────────────────────────────
  openFwdToTeam: function(opts) {
    this._pendingFwd = opts;
    if (this.mode === 'cp' && opts.cp_id) this.cpId = opts.cp_id;
    var strip = this._el('fwdStrip');
    if (strip) {
      var txt = strip.querySelector('.tc-fwd-strip-text');
      if (txt) txt.textContent = (opts.fwd_author ? opts.fwd_author + ': ' : '') + (opts.body || '').substring(0, 100);
      strip.style.display = 'flex';
    }
    var inp = this._el('input');
    if (inp) inp.focus();
  },

  clearFwd: function() {
    this._pendingFwd = null;
    var strip = this._el('fwdStrip');
    if (strip) strip.style.display = 'none';
  },

  // ── Polling ────────────────────────────────────────────────────────────────
  startPolling: function() {
    var self = this;
    clearInterval(self.pollTimer);
    self.pollTimer = setInterval(function() { self.loadMessages(false); }, 5000);
  },

  stopPolling: function() { clearInterval(this.pollTimer); },

  // ── Helpers ────────────────────────────────────────────────────────────────
  shortName: function(name) {
    if (!name) return '?';
    var parts = name.trim().split(/\s+/);
    return parts.length === 1 ? parts[0] : parts[0] + ' ' + parts[1].charAt(0) + '.';
  },

  formatTime: function(dt) {
    if (!dt) return '';
    var d = new Date((dt || '').replace(' ', 'T'));
    var now = new Date();
    var isToday = d.toDateString() === now.toDateString();
    var time = d.toLocaleTimeString('uk', { hour: '2-digit', minute: '2-digit' });
    return isToday ? time : d.toLocaleDateString('uk', { day: '2-digit', month: '2-digit' }) + ' ' + time;
  },

  esc: function(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },

  linkify: function(s) {
    return s.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener" class="tc-link">$1</a>');
  },
};

/**
 * WsCpChat — тонкая обёртка над TeamChat для workspace (контекст контрагента).
 * Использует DOM-элементы с id: wsTcMsgs, wsTcInput, wsTcSendBtn, wsTcFwdStrip
 */
var WsCpChat = {
  _inited: false,

  _wsIds: {
    msgs:     'wsTcMsgs',
    input:    'wsTcInput',
    sendBtn:  'wsTcSendBtn',
    fwdStrip: 'wsTcFwdStrip',
    tabs:     null,
    title:    null,
  },

  init: function(cpId) {
    this._inited = true;
    TeamChat.initCp({
      cpId: cpId || null,
      ids:  this._wsIds,
      onUnreadChange: function(n) {
        var dot = document.querySelector('.ws-hub-tab[data-tab="internal"] .ws-mode-badge');
        if (dot) { dot.textContent = n > 0 ? n : ''; dot.classList.toggle('visible', n > 0); }
      }
    });
  },

  setCp: function(cpId) {
    if (!this._inited) { this.init(cpId); return; }
    TeamChat.setCp(cpId);
  },

  send:     function() { TeamChat.send(); },
  clearFwd: function() { TeamChat.clearFwd(); },

  openFwdToTeam: function(opts) { TeamChat.openFwdToTeam(opts); },
};
