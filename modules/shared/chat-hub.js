/**
 * ChatHub — автономный модуль чата.
 * Используется в workspace.php (через WS-делегаты) и в chat_popup.php (standalone).
 *
 * Инициализация в workspace.php:
 *   ChatHub.init({ onAfterRender: function(){ WS.loadInbox(); }, onTaskChanged: function(){ WS.refreshInboxCard(); } });
 *
 * Инициализация в chat_popup.php:
 *   ChatHub.init({ cpId: 123, kind: 'counterparty', activeCh: 'viber' });
 */
var ChatHub = {

  // ── State ─────────────────────────────────────────────────────────────────
  cpId:           null,
  kind:           'counterparty',  // 'counterparty' | 'lead'
  activeCh:       'viber',
  activeChatCpId: null,
  attachedFile:   null,
  _tplBodies:     [],
  pollTimer:      null,
  _replyToId:     null,
  _replyToBody:   null,

  // ── Hooks ─────────────────────────────────────────────────────────────────
  onAfterRender: null,  // вызывается после renderMessages (workspace: WS.loadInbox)
  onTaskChanged: null,  // вызывается после изменения задач (workspace: WS.refreshInboxCard)

  // ── Init ──────────────────────────────────────────────────────────────────
  init: function(opts) {
    opts = opts || {};
    if (opts.cpId)           this.cpId           = opts.cpId;
    if (opts.kind)           this.kind           = opts.kind;
    if (opts.activeCh)       this.activeCh       = opts.activeCh;
    if (opts.activeChatCpId) this.activeChatCpId = opts.activeChatCpId;
    if (opts.onAfterRender)  this.onAfterRender  = opts.onAfterRender;
    if (opts.onTaskChanged)  this.onTaskChanged  = opts.onTaskChanged;

    this._bindTextarea();
    this.initEmoji();

    // Popup mode: если cpId передан при init — сразу загружаем нужную панель
    if (opts.cpId) {
      this.activeChatCpId = opts.cpId;
      this.applyChPanel();
    }
  },

  // Устанавливает контекст текущего контрагента/лида
  setContext: function(cpId, kind, activeChatCpId, cpName) {
    this.cpId              = cpId;
    this.kind              = kind;
    this.activeChatCpId    = activeChatCpId || cpId;
    this.activeChatCpName  = cpName || '';
  },

  // ── Channel tabs ──────────────────────────────────────────────────────────
  // Вызвать один раз для привязки вкладок каналов (.ws-ch-tab)
  bindChannelTabs: function() {
    var self = this;
    document.querySelectorAll('.ws-ch-tab').forEach(function(btn) {
      btn.addEventListener('click', function() {
        if (btn.classList.contains('ch-unavailable')) return; // недоступний — ігнор
        document.querySelectorAll('.ws-ch-tab').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        self.activeCh = btn.dataset.ch;
        self.clearChannelDot(self.activeCh);
        self.applyChPanel();
      });
    });
  },

  // Оновлює доступність вкладок каналів на основі контактних даних контрагента.
  // availableChannels — масив рядків: ['viber','sms','email','telegram'] тощо.
  // Вкладка 'tasks' завжди доступна. 'note' завжди доступна.
  updateChannelTabs: function(availableChannels) {
    var avail = availableChannels || [];
    document.querySelectorAll('.ws-ch-tab').forEach(function(btn) {
      var ch = btn.dataset.ch;
      if (ch === 'tasks' || ch === 'note' || avail.indexOf(ch) !== -1) {
        btn.classList.remove('ch-unavailable');
        btn.removeAttribute('title');
      } else {
        btn.classList.add('ch-unavailable');
        btn.setAttribute('title', 'Немає контактних даних');
      }
    });
  },

  // ── Textarea bindings ─────────────────────────────────────────────────────
  _bindTextarea: function() {
    var self = this;
    var inp  = document.getElementById('wsMsgInput');
    if (!inp) return;
    inp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        self.sendMessage();
      }
    });
  },

  // ── Channel panel ─────────────────────────────────────────────────────────
  applyChPanel: function() {
    var isTasks = (this.activeCh === 'tasks');
    var msgs    = document.getElementById('wsMsgs');
    var input   = document.querySelector('.ws-input-area');
    var tPane   = document.getElementById('wsTasksPane');
    if (msgs)   msgs.style.display   = isTasks ? 'none' : '';
    if (input)  input.style.display  = isTasks ? 'none' : '';
    if (tPane)  tPane.style.display  = isTasks ? 'flex' : 'none';
    if (isTasks) {
      this.loadTasks();
    } else {
      this.loadMessages();
    }
  },

  // ── Messages ──────────────────────────────────────────────────────────────
  loadMessages: function() {
    var self   = this;
    var chatId = this.activeChatCpId || this.cpId;
    var url;
    if (this.kind === 'lead') {
      url = '/counterparties/api/get_messages?lead_id=' + this.cpId + '&channel=' + this.activeCh;
    } else {
      url = '/counterparties/api/get_messages?id=' + chatId + '&channel=' + this.activeCh;
    }
    // Для Viber: сначала поллируем Alpha SMS за новыми ответами
    if (this.activeCh === 'viber' && chatId) {
      var pollUrl = this.kind === 'lead'
        ? '/counterparties/api/poll_viber_replies?lead_id=' + this.cpId
        : '/counterparties/api/poll_viber_replies?id=' + chatId;
      fetch(pollUrl)
        .then(function(r){ return r.json(); })
        .then(function() {
          fetch(url)
            .then(function(r){ return r.json(); })
            .then(function(d) { if (d.ok) self.renderMessages(d.messages); });
        })
        .catch(function() {
          fetch(url)
            .then(function(r){ return r.json(); })
            .then(function(d) { if (d.ok) self.renderMessages(d.messages); });
        });
      return;
    }
    fetch(url)
      .then(function(r){ return r.json(); })
      .then(function(d) { if (d.ok) self.renderMessages(d.messages); });
  },

  isImageUrl: function(url) {
    return url && /\.(jpg|jpeg|png|gif|webp)(\?|$)/i.test(url);
  },

  renderMessages: function(msgs) {
    var html = '';
    if (!msgs || msgs.length === 0) {
      html = '<div style="text-align:center;font-size:12px;color:#9ca3af;padding:20px 0">Повідомлень поки немає</div>';
    } else {
      var self = this;
      msgs.forEach(function(m) {
        var isOut = m.direction === 'out';
        var mediaHtml = '';
        var origAttachName = '';
        if (m.media_url && m.body && /^📎\s/u.test(m.body)) {
          origAttachName = m.body.replace(/^📎\s*/u, '').trim();
        }
        if (m.media_url) {
          var bodyIsFilename = m.body && /\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|xls|xlsx|txt|ogg|oga|mp3|m4a|wav|opus)$/i.test(m.body.trim());
          var isImgFromUrl   = self.isImageUrl(m.media_url);
          var isImgFromBody  = bodyIsFilename && /\.(jpg|jpeg|png|gif|webp)$/i.test(m.body.trim());
          var isAudio        = /\.(ogg|oga|mp3|m4a|wav|opus)(\?|$)/i.test(m.media_url)
                             || (bodyIsFilename && /\.(ogg|oga|mp3|m4a|wav|opus)$/i.test(m.body.trim()));
          var hasCaption = m.body && !bodyIsFilename && !origAttachName
                        && m.body !== '[файл]' && m.body !== '[фото]' && m.body !== '[медіа]';
          var mbottom = hasCaption ? '6' : '0';
          if (isImgFromUrl || isImgFromBody) {
            mediaHtml = '<a href="' + self.esc(m.media_url) + '" target="_blank" rel="noopener">'
                      + '<img src="' + self.esc(m.media_url) + '" style="max-width:220px;max-height:180px;border-radius:6px;display:block;margin-bottom:' + mbottom + 'px">'
                      + '</a>';
          } else if (isAudio) {
            mediaHtml = '<audio controls style="max-width:220px;display:block;margin-bottom:' + mbottom + 'px">'
                      + '<source src="' + self.esc(m.media_url) + '">'
                      + '</audio>';
          } else {
            var parts      = m.media_url.split('/');
            var storedName = parts[parts.length - 1].replace(/\.$/, '') || 'файл';
            var fname      = (bodyIsFilename ? m.body.trim() : null) || origAttachName || storedName;
            var extM       = fname.split('.').pop().toLowerCase();
            var icons      = { pdf: '📄', doc: '📝', docx: '📝', xls: '📊', xlsx: '📊', txt: '📃', ogg: '🎵', oga: '🎵', mp3: '🎵', m4a: '🎵', wav: '🎵' };
            var ic         = icons[extM] || '📎';
            var dlUrl      = '/counterparties/api/download_media?url=' + encodeURIComponent(m.media_url) + '&name=' + encodeURIComponent(fname);
            var officeExts = ['doc','docx','xls','xlsx'];
            var viewUrl    = (officeExts.indexOf(extM) !== -1)
                ? 'https://view.officeapps.live.com/op/view.aspx?src=' + encodeURIComponent(m.media_url)
                : dlUrl;
            mediaHtml = '<span style="display:inline-flex;align-items:center;gap:4px;margin-bottom:' + mbottom + 'px">'
                      + '<a href="' + self.esc(viewUrl) + '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:5px;text-decoration:none;color:inherit;background:rgba(0,0,0,.08);border-radius:6px;padding:5px 8px;font-size:12px">'
                      + '<span>' + ic + '</span><span>' + self.esc(fname) + '</span>'
                      + '</a>';
            if (officeExts.indexOf(extM) !== -1) {
              mediaHtml += '<a href="' + self.esc(dlUrl) + '" target="_blank" rel="noopener" title="Завантажити" style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;text-decoration:none;background:rgba(0,0,0,.08);border-radius:6px;font-size:13px">⬇</a>';
            }
            mediaHtml += '</span>';
          }
        }
        var bodyText = (hasCaption || (!m.media_url && m.body && m.body !== '[файл]' && m.body !== '[фото]' && m.body !== '[медіа]' && !origAttachName))
                     ? '<div>' + self.linkify(self.esc(m.body).replace(/\\n/g,'<br>').replace(/\n/g,'<br>')) + '</div>'
                     : '';
        var mediaOnly   = (m.media_url && !bodyText) ? ' media-only' : '';
        var statusIcon  = '';
        if (isOut) {
          var st         = m.status || 'sent';
          var svgTick    = '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="1.5,6 4.5,9 10.5,3"/></svg>';
          var svgDblTick = '<svg viewBox="0 0 16 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="1,6 4,9 10,3"/><polyline points="6,6 9,9 15,3"/></svg>';
          var svgCross   = '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="2" y1="2" x2="10" y2="10"/><line x1="10" y1="2" x2="2" y2="10"/></svg>';
          var svgClock   = '<svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="6" cy="6" r="4.5"/><polyline points="6,3.5 6,6 7.5,7.5"/></svg>';
          var iconHtml   = st === 'pending'   ? svgClock
                         : st === 'sent'      ? svgTick
                         : st === 'delivered' ? svgDblTick
                         : st === 'read'      ? svgDblTick
                         : st === 'failed'    ? svgCross
                         : svgTick;
          statusIcon = '<span class="ws-msg-status ' + st + '" title="' + self.esc(st) + '">' + iconHtml + '</span>';
        }
        var replyQuote = '';
        if (m.reply_to_body) {
          replyQuote = '<div class="ws-bubble-reply">' + self.esc(m.reply_to_body) + '</div>';
        }
        var actBtns = '<div class="ws-msg-actions">'
          + '<button class="ws-msg-act-btn" title="Відповісти клієнту" onclick="ChatHub.startReply(' + m.id + ', this)">↩</button>'
          + '<button class="ws-msg-act-btn" title="Переслати клієнту" onclick="ChatHub.startForward(' + m.id + ', this)">⤷</button>'
          + '<button class="ws-msg-act-btn" title="Переслати в команду" onclick="ChatHub.forwardToTeam(' + m.id + ', this)" style="background:#dbeafe;color:#1d4ed8">👥</button>'
          + '</div>';
        html += '<div class="ws-msg-row ' + (isOut ? 'out' : 'in') + '" data-msg-id="' + m.id + '">'
              + '<div class="ws-msg-outer">'
              + actBtns
              + '<div class="ws-bubble' + mediaOnly + '">' + replyQuote + mediaHtml + bodyText + '</div>'
              + '</div>'
              + '<div class="ws-msg-meta">' + (isOut ? (m.operator_name || 'Оператор') : 'Клієнт') + ' · ' + self.formatTime(m.created_at) + statusIcon + '</div>'
              + '</div>';
      });
    }
    var container = document.getElementById('wsMsgs');
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
    if (this.onAfterRender) this.onAfterRender();
  },

  // ── Send message ──────────────────────────────────────────────────────────
  sendMessage: function() {
    var self = this;
    var inp  = document.getElementById('wsMsgInput');
    var body = inp.value.trim();
    if (!body && !this.attachedFile) return;
    if (this.attachedFile && this.attachedFile.uploading) {
      showToast('Зачекайте, файл ще завантажується…');
      return;
    }
    var chatId = this.activeChatCpId || this.cpId;
    var fd = new FormData();
    fd.append('channel', this.activeCh);
    fd.append('body', body || (this.attachedFile ? '[файл]' : ''));
    if (this.attachedFile && this.attachedFile.url) {
      fd.append('media_url', this.attachedFile.url);
    }
    if (this.kind === 'lead') {
      fd.append('lead_id', this.cpId);
    } else {
      fd.append('id', chatId);
    }
    if (this._replyToId) {
      fd.append('reply_to_id', this._replyToId);
    }
    document.getElementById('wsSendBtn').disabled = true;
    fetch('/counterparties/api/send_message', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        document.getElementById('wsSendBtn').disabled = false;
        if (d.ok) {
          inp.value = '';
          document.getElementById('wsCharC').textContent = '';
          self.removeAttach();
          self.cancelReply();
          self.loadMessages();
        } else {
          showToast('Помилка: ' + (d.error || 'невідома'));
        }
      })
      .catch(function() {
        document.getElementById('wsSendBtn').disabled = false;
        showToast('Помилка відправки');
      });
  },

  // ── Templates dropdown ────────────────────────────────────────────────────
  toggleTemplates: function(e) {
    e.stopPropagation();
    var picker = document.getElementById('wsTplPicker');
    var isOpen = picker.classList.contains('open');
    this.closeAllPickers();
    if (!isOpen) {
      picker.classList.add('open');
      this.loadTplDropdown();
    }
  },

  loadTplDropdown: function() {
    var self = this;
    var list = document.getElementById('wsTplList');
    list.innerHTML = '<div class="ws-tpl-empty">Завантаження…</div>';
    fetch('/counterparties/api/get_templates?channel=' + encodeURIComponent(this.activeCh))
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.templates.length) {
          list.innerHTML = '<div class="ws-tpl-empty">Немає шаблонів для цього каналу</div>';
          return;
        }
        self._tplBodies = [];
        var html = '';
        d.templates.forEach(function(t, i) {
          self._tplBodies.push(t.body);
          html += '<div class="ws-tpl-item" data-tpl-idx="' + i + '">'
                + '<div class="ws-tpl-item-title">' + self.esc(t.title) + '</div>'
                + '<div class="ws-tpl-item-body">'  + self.esc(t.body)  + '</div>'
                + '</div>';
        });
        list.innerHTML = html;
        list.querySelectorAll('.ws-tpl-item').forEach(function(el) {
          el.addEventListener('mousedown', function(ev) {
            ev.preventDefault();
            var idx = parseInt(el.getAttribute('data-tpl-idx'), 10);
            self.insertTemplate(self._tplBodies[idx]);
          });
        });
      });
  },

  insertTemplate: function(body) {
    var inp = document.getElementById('wsMsgInput');
    var cur = inp.value;
    inp.value = cur ? cur + '\n' + body : body;
    inp.dispatchEvent(new Event('input'));
    inp.focus();
    this.closeAllPickers();
  },

  // ── Template manager modal ────────────────────────────────────────────────
  openTplManager: function() {
    this.closeAllPickers();
    document.getElementById('wsTplModal').style.display = 'flex';
    this.cancelTmEdit();
    this.loadTmList();
  },

  closeTplManager: function() {
    document.getElementById('wsTplModal').style.display = 'none';
  },

  loadTmList: function() {
    var self = this;
    var wrap = document.getElementById('wsTmList');
    wrap.innerHTML = '';
    fetch('/counterparties/api/get_templates')
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.templates.length) {
          wrap.innerHTML = '<div style="font-size:12px;color:#9ca3af;padding:4px 0">Шаблонів ще немає</div>';
          return;
        }
        var html = '';
        d.templates.forEach(function(t) {
          var safeData = self.esc(JSON.stringify({id: t.id, title: t.title, body: t.body, channels: t.channels}));
          html += '<div class="ws-tm-row">'
                + '<div class="ws-tm-info">'
                + '<div class="ws-tm-title">' + self.esc(t.title) + '</div>'
                + '<div class="ws-tm-channels">' + self.esc(t.channels) + '</div>'
                + '<div class="ws-tm-body-preview">' + self.esc(t.body) + '</div>'
                + '</div>'
                + '<div class="ws-tm-actions">'
                + '<button class="ws-tm-btn ws-tm-edit-btn" data-tpl="' + safeData + '">✏️</button>'
                + '<button class="ws-tm-btn del" onclick="WS.deleteTmTemplate(' + t.id + ')">🗑</button>'
                + '</div>'
                + '</div>';
        });
        wrap.innerHTML = html;
        wrap.querySelectorAll('.ws-tm-edit-btn').forEach(function(btn) {
          btn.addEventListener('click', function() {
            var t = JSON.parse(this.getAttribute('data-tpl'));
            WS.editTmTemplate(t.id, t.title, t.body, t.channels);
          });
        });
      });
  },

  newTmTemplate: function() {
    document.getElementById('wsTmId').value    = '0';
    document.getElementById('wsTmTitle').value = '';
    document.getElementById('wsTmBody').value  = '';
    document.getElementById('wsTmFormTitle').textContent = 'Новий шаблон';
    document.querySelectorAll('[name="tmch"]').forEach(function(cb){ cb.checked = cb.value === 'viber'; });
    document.getElementById('wsTmErr').style.display = 'none';
    document.getElementById('wsTmForm').style.display = 'block';
    document.getElementById('wsTmAddBtn').style.display = 'none';
    document.getElementById('wsTmTitle').focus();
  },

  editTmTemplate: function(id, title, body, channels) {
    document.getElementById('wsTmId').value    = id;
    document.getElementById('wsTmTitle').value = title;
    document.getElementById('wsTmBody').value  = body;
    document.getElementById('wsTmFormTitle').textContent = 'Редагувати шаблон';
    var chs = channels ? channels.split(',') : [];
    document.querySelectorAll('[name="tmch"]').forEach(function(cb){ cb.checked = chs.indexOf(cb.value) !== -1; });
    document.getElementById('wsTmErr').style.display = 'none';
    document.getElementById('wsTmForm').style.display = 'block';
    document.getElementById('wsTmAddBtn').style.display = 'none';
  },

  cancelTmEdit: function() {
    document.getElementById('wsTmForm').style.display = 'none';
    document.getElementById('wsTmAddBtn').style.display = 'inline-flex';
    document.getElementById('wsTmErr').style.display = 'none';
  },

  saveTmTemplate: function() {
    var self  = this;
    var id    = document.getElementById('wsTmId').value;
    var title = document.getElementById('wsTmTitle').value.trim();
    var body  = document.getElementById('wsTmBody').value.trim();
    var errEl = document.getElementById('wsTmErr');
    if (!title || !body) {
      errEl.textContent = 'Введіть назву і текст шаблону';
      errEl.style.display = 'block';
      return;
    }
    var fd = new FormData();
    fd.append('id', id);
    fd.append('title', title);
    fd.append('body', body);
    document.querySelectorAll('[name="tmch"]').forEach(function(cb){ if (cb.checked) fd.append('channels[]', cb.value); });
    fetch('/counterparties/api/save_template', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) { errEl.textContent = d.error || 'Помилка'; errEl.style.display = 'block'; return; }
        self.cancelTmEdit();
        self.loadTmList();
      });
  },

  deleteTmTemplate: function(id) {
    if (!confirm('Видалити шаблон?')) return;
    var self = this;
    var fd = new FormData();
    fd.append('id', id);
    fetch('/counterparties/api/delete_template', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) self.loadTmList();
        else showToast('Помилка видалення');
      });
  },

  // ── Emoji picker ──────────────────────────────────────────────────────────
  EMOJIS: ['😊','😂','🙏','👍','👌','🔥','❤️','✅','⚡','📦','🚀','💬',
            '😅','🤝','💪','🎉','✨','⚠️','📞','✉️','🕐','💰','📄','🔎',
            '😍','😎','🤔','😴','😤','🙌','👋','💡','📌','🎯','✔️','❌',
            '💯','🌟','📱','🖥️','📊','🗓️','🔑','🏠','🚗','✈️','🌍','💎'],

  initEmoji: function() {
    var grid = document.getElementById('wsEmojiGrid');
    if (!grid) return;
    var self = this;
    this.EMOJIS.forEach(function(em) {
      var btn = document.createElement('button');
      btn.className = 'ws-emoji-btn';
      btn.textContent = em;
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        self.insertEmoji(em);
      });
      grid.appendChild(btn);
    });
    document.addEventListener('click', function(e) {
      if (!e.target.closest('#wsEmojiPicker') && e.target.id !== 'wsEmojiBtn') {
        var ep = document.getElementById('wsEmojiPicker');
        if (ep) ep.classList.remove('open');
      }
      if (!e.target.closest('#wsTplPicker') && e.target.id !== 'wsTplBtn') {
        var tp = document.getElementById('wsTplPicker');
        if (tp) tp.classList.remove('open');
      }
    });
  },

  closeAllPickers: function() {
    var ep = document.getElementById('wsEmojiPicker');
    var tp = document.getElementById('wsTplPicker');
    if (ep) ep.classList.remove('open');
    if (tp) tp.classList.remove('open');
  },

  toggleEmoji: function(e) {
    e.stopPropagation();
    var picker = document.getElementById('wsEmojiPicker');
    var isOpen = picker && picker.classList.contains('open');
    this.closeAllPickers();
    if (!isOpen && picker) picker.classList.add('open');
  },

  insertEmoji: function(em) {
    var inp = document.getElementById('wsMsgInput');
    var pos = inp.selectionStart || inp.value.length;
    inp.value = inp.value.slice(0, pos) + em + inp.value.slice(pos);
    inp.selectionStart = inp.selectionEnd = pos + em.length;
    inp.focus();
    var ep = document.getElementById('wsEmojiPicker');
    if (ep) ep.classList.remove('open');
  },

  // ── File attachment ───────────────────────────────────────────────────────
  openFilePicker: function() {
    document.getElementById('wsFileInput').value = '';
    document.getElementById('wsFileInput').click();
  },

  onFileSelected: function(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var self = this;
    this.attachedFile = { url: null, name: file.name, is_image: false, uploading: true };
    this.renderAttachPreview(file);
    var fd = new FormData();
    fd.append('file', file);
    fetch('/counterparties/api/upload_message_file', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          self.attachedFile = { url: d.url, name: d.name, is_image: d.is_image, uploading: false };
          self.renderAttachPreview(null);
        } else {
          showToast('Помилка завантаження: ' + (d.error || ''));
          self.removeAttach();
        }
      })
      .catch(function() {
        showToast('Помилка завантаження файлу');
        self.removeAttach();
      });
  },

  renderAttachPreview: function(originalFile) {
    var af      = this.attachedFile;
    var preview = document.getElementById('wsAttachPreview');
    var wrap    = document.getElementById('wsAttachThumbWrap');
    var nameEl  = document.getElementById('wsAttachName');
    var sizeEl  = document.getElementById('wsAttachSize');
    nameEl.textContent = af.name;
    if (af.uploading) {
      sizeEl.innerHTML = '<span class="ws-attach-uploading">Завантаження…</span>';
      wrap.innerHTML = '<div class="ws-attach-icon">📎</div>';
    } else {
      sizeEl.textContent = af.is_image ? 'Зображення готове' : 'Файл готовий';
      if (af.is_image) {
        wrap.innerHTML = '<img class="ws-attach-thumb" src="' + this.esc(af.url) + '" alt="">';
      } else {
        var ext   = af.name.split('.').pop().toUpperCase();
        var icons = { PDF: '📄', DOC: '📝', DOCX: '📝', XLS: '📊', XLSX: '📊', TXT: '📋' };
        var ic    = icons[ext] || '📎';
        wrap.innerHTML = '<div class="ws-attach-icon">' + ic + '</div>';
      }
    }
    preview.classList.add('visible');
  },

  removeAttach: function() {
    this.attachedFile = null;
    document.getElementById('wsAttachPreview').classList.remove('visible');
    document.getElementById('wsAttachThumbWrap').innerHTML = '';
    document.getElementById('wsFileInput').value = '';
  },

  /** Pre-attach a server-side file by URL (e.g. generated PDF invoice). */
  setAttachFromUrl: function(url, name) {
    this.removeAttach();
    var fname = name || decodeURIComponent(url.split('/').pop().split('?')[0]);
    this.attachedFile = { url: url, name: fname, is_image: false, uploading: false };
    this.renderAttachPreview(null);
  },

  // ── Channel unread dots ───────────────────────────────────────────────────
  updateChannelDots: function(unreadByChannel) {
    document.querySelectorAll('.ws-ch-tab').forEach(function(btn) {
      var ch  = btn.dataset.ch;
      var cnt = unreadByChannel[ch] || 0;
      if (cnt > 0) { btn.classList.add('has-unread'); }
      else          { btn.classList.remove('has-unread'); }
    });
  },

  clearChannelDot: function(ch) {
    var btn = document.querySelector('.ws-ch-tab[data-ch="' + ch + '"]');
    if (btn) btn.classList.remove('has-unread');
  },

  // ── Polling (messages only) ───────────────────────────────────────────────
  startPolling: function() {
    var self = this;
    this.stopPolling();
    this.pollTimer = setInterval(function() {
      if (self.activeCh === 'tasks') {
        self.loadTasks();
      } else {
        self.loadMessages();
      }
    }, 10000);
  },

  stopPolling: function() {
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
  },

  // ── Tasks ─────────────────────────────────────────────────────────────────
  loadTasks: function() {
    var self   = this;
    var chatId = this.activeChatCpId || this.cpId;
    var url    = this.kind === 'lead'
      ? '/counterparties/api/get_tasks?lead_id=' + this.cpId
      : '/counterparties/api/get_tasks?id=' + chatId;
    fetch(url)
      .then(function(r){ return r.json(); })
      .then(function(d) { if (d.ok) self.renderTasks(d.tasks); });
  },

  renderTasks: function(tasks) {
    var self = this;
    var list = document.getElementById('wsTasksList');
    if (!list) return;
    if (!tasks || tasks.length === 0) {
      list.innerHTML = '<div class="ws-tasks-empty">Задач поки немає<br><span style="font-size:11px">Додайте першу задачу нижче</span></div>';
      return;
    }
    var html = '';
    tasks.forEach(function(t) {
      var priClass  = 'ws-task-pri-' + Math.max(1, Math.min(5, parseInt(t.priority) || 3));
      var icon      = self.taskTypeIcon(t.task_type);
      var dueHtml   = self.taskDueHtml(t.due_at, t.status);
      var isDone    = t.status === 'done';
      var isSnoozed = t.status === 'snoozed';
      var cardClass = 'ws-task-card' + (isDone ? ' done-card' : '') + (isSnoozed ? ' snoozed-card' : '');
      var actsHtml  = '';
      if (!isDone) {
        actsHtml += '<button class="ws-task-act done-btn" onclick="WS.doneTask(' + t.id + ')" title="Виконано">✓</button>';
        if (!isSnoozed) {
          actsHtml += '<button class="ws-task-act snooze-btn" onclick="WS.toggleSnoozeMenu(event,' + t.id + ')" title="Відкласти">💤</button>';
        } else {
          actsHtml += '<button class="ws-task-act snooze-btn" onclick="WS.wakeTask(' + t.id + ')" title="Зняти відкладення" style="color:#7c3aed">▶</button>';
        }
      }
      var snoozedLbl = isSnoozed && t.snoozed_until
        ? '<span class="ws-task-snoozed-lbl">💤 до ' + self.formatSnoozedUntil(t.snoozed_until) + '</span>' : '';
      html += '<div class="' + cardClass + '">'
        + '<div class="ws-task-pri-bar ' + priClass + '"></div>'
        + '<div class="ws-task-body">'
        + '<div class="ws-task-icon">' + icon + '</div>'
        + '<div class="ws-task-content">'
        + '<div class="ws-task-title">' + self.esc(t.title) + '</div>'
        + '<div class="ws-task-meta">'
        + '<span class="ws-task-type-lbl">' + self.taskTypeLabel(t.task_type) + '</span>'
        + dueHtml + snoozedLbl
        + '</div>'
        + '</div>'
        + '</div>'
        + '<div class="ws-task-acts">' + actsHtml + '</div>'
        + '</div>';
    });
    list.innerHTML = html;
  },

  taskDueHtml: function(dueAt, status) {
    if (status === 'done') return '';
    if (!dueAt) return '<span class="ws-task-due no-due">без дедлайну</span>';
    var due       = new Date(dueAt).getTime();
    var hoursLeft = (due - Date.now()) / 3600000;
    var label     = this.formatDueAt(dueAt);
    if (hoursLeft <= 0)  return '<span class="ws-task-due overdue">🔴 ' + label + '</span>';
    if (hoursLeft < 4)   return '<span class="ws-task-due due-soon">🟠 ' + label + '</span>';
    if (hoursLeft < 24)  return '<span class="ws-task-due due-today">🟡 ' + label + '</span>';
    return '<span class="ws-task-due due-later">🟢 ' + label + '</span>';
  },

  formatDueAt: function(dueAt) {
    var d          = new Date(dueAt);
    var now        = new Date();
    var isToday    = d.toDateString() === now.toDateString();
    var tomorrow   = new Date(now); tomorrow.setDate(tomorrow.getDate() + 1);
    var isTomorrow = d.toDateString() === tomorrow.toDateString();
    var hhmm       = ('0'+d.getHours()).slice(-2) + ':' + ('0'+d.getMinutes()).slice(-2);
    if (isToday)    return 'Сьогодні ' + hhmm;
    if (isTomorrow) return 'Завтра '   + hhmm;
    var hoursAgo = (Date.now() - d.getTime()) / 3600000;
    if (hoursAgo > 0) {
      if (hoursAgo < 24) return 'Прострочено ' + Math.round(hoursAgo) + 'г';
      return 'Прострочено ' + Math.round(hoursAgo / 24) + 'д';
    }
    var months = ['Січ','Лют','Бер','Кві','Тра','Чер','Лип','Сер','Вер','Жов','Лис','Гру'];
    return d.getDate() + ' ' + months[d.getMonth()] + ' ' + hhmm;
  },

  formatSnoozedUntil: function(until) {
    var d       = new Date(until);
    var now     = new Date();
    var isToday = d.toDateString() === now.toDateString();
    var hhmm    = ('0'+d.getHours()).slice(-2) + ':' + ('0'+d.getMinutes()).slice(-2);
    return isToday ? hhmm : (d.getDate() + '.' + ('0'+(d.getMonth()+1)).slice(-2) + ' ' + hhmm);
  },

  taskTypeIcon: function(type) {
    var map = { call_back:'📞', follow_up:'💬', send_docs:'📄', payment:'💰', meeting:'📅', other:'✔' };
    return map[type] || '✔';
  },

  taskTypeLabel: function(type) {
    var map = { call_back:'Передзвонити', follow_up:'Нагадати', send_docs:'Надіслати документи', payment:'Платіж', meeting:'Зустріч', other:'Інше' };
    return map[type] || 'Інше';
  },

  addTask: function() {
    var self  = this;
    var title = document.getElementById('wsTaskTitle').value.trim();
    if (!title) { document.getElementById('wsTaskTitle').focus(); return; }
    var btn    = document.getElementById('wsTaskAddBtn');
    btn.disabled = true;
    var chatId = this.activeChatCpId || this.cpId;
    var fd     = new FormData();
    if (this.kind === 'lead') fd.append('lead_id', this.cpId);
    else                      fd.append('id', chatId);
    fd.append('title',     title);
    fd.append('task_type', document.getElementById('wsTaskType').value);
    fd.append('priority',  document.getElementById('wsTaskPriority').value);
    var dueVal = document.getElementById('wsTaskDue').value;
    if (dueVal) fd.append('due_at', dueVal);
    fetch('/counterparties/api/save_task', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        btn.disabled = false;
        if (d.ok) {
          document.getElementById('wsTaskTitle').value = '';
          document.getElementById('wsTaskDue').value   = '';
          self.renderTasks(d.tasks);
          if (self.onTaskChanged) self.onTaskChanged();
        } else {
          showToast('Помилка: ' + (d.error || ''), true);
        }
      });
  },

  doneTask: function(taskId) {
    var self   = this;
    var chatId = this.activeChatCpId || this.cpId;
    var fd     = new FormData();
    fd.append('task_id', taskId);
    if (this.kind === 'lead') fd.append('lead_id', this.cpId);
    else                      fd.append('id', chatId);
    fetch('/counterparties/api/done_task', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          self.renderTasks(d.tasks);
          if (self.onTaskChanged) self.onTaskChanged();
        }
      });
  },

  toggleSnoozeMenu: function(e, taskId) {
    e.stopPropagation();
    document.querySelectorAll('.ws-snooze-menu').forEach(function(m){ m.remove(); });
    var btn  = e.currentTarget;
    var self = this;
    var opts = [
      { label: '1 година',      minutes: 60 },
      { label: '4 години',      minutes: 240 },
      { label: 'Завтра 9:00',   minutes: this.minutesUntilTomorrow9() },
      { label: 'Через тиждень', minutes: 60 * 24 * 7 },
    ];
    var menu = document.createElement('div');
    menu.className = 'ws-snooze-menu';
    opts.forEach(function(o) {
      var b = document.createElement('button');
      b.className = 'ws-snooze-item';
      b.textContent = o.label;
      b.addEventListener('click', function(ev) {
        ev.stopPropagation();
        menu.remove();
        self._snoozeTask(taskId, o.minutes);
      });
      menu.appendChild(b);
    });
    btn.style.position = 'relative';
    btn.appendChild(menu);
    var close = function() { menu.remove(); document.removeEventListener('click', close); };
    setTimeout(function(){ document.addEventListener('click', close); }, 0);
  },

  _snoozeTask: function(taskId, minutes) {
    var self   = this;
    var chatId = this.activeChatCpId || this.cpId;
    var fd     = new FormData();
    fd.append('task_id', taskId);
    fd.append('minutes', minutes);
    if (this.kind === 'lead') fd.append('lead_id', this.cpId);
    else                      fd.append('id', chatId);
    fetch('/counterparties/api/snooze_task', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          self.renderTasks(d.tasks);
          if (self.onTaskChanged) self.onTaskChanged();
        }
      });
  },

  wakeTask: function(taskId) {
    var self   = this;
    var chatId = this.activeChatCpId || this.cpId;
    var fd     = new FormData();
    fd.append('task_id', taskId);
    fd.append('wake', 1);
    if (this.kind === 'lead') fd.append('lead_id', this.cpId);
    else                      fd.append('id', chatId);
    fetch('/counterparties/api/snooze_task', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          self.renderTasks(d.tasks);
          if (self.onTaskChanged) self.onTaskChanged();
        }
      });
  },

  minutesUntilTomorrow9: function() {
    var now = new Date();
    var tom = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 9, 0, 0);
    return Math.max(60, Math.round((tom.getTime() - now.getTime()) / 60000));
  },

  // ── Utilities ─────────────────────────────────────────────────────────────
  esc: function(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },

  linkify: function(html) {
    return html.replace(/(https?:\/\/[^\s<>"']+)/g, function(url) {
      return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" class="chat-link">' + url + '</a>';
    });
  },

  // ── Forward to team ───────────────────────────────────────────────────────
  forwardToTeam: function(msgId, btn) {
    var row    = btn.closest('[data-msg-id]');
    var bubble = row ? row.querySelector('.tc-bubble, .ws-bubble') : null;
    var body   = bubble ? (bubble.innerText || bubble.textContent || '').trim() : '';
    var isOut  = row && row.classList.contains('out');
    var author = isOut
      ? (row.querySelector('.ws-msg-meta') ? (row.querySelector('.ws-msg-meta').textContent || '').split('·')[0].trim() : 'Оператор')
      : (this.activeChatCpName || 'Клієнт');

    var fwdData = {
      fwd_msg_id: msgId,
      fwd_author:  author,
      cp_id:       this.activeChatCpId || this.cpId,
      cp_name:     this.activeChatCpName || '',
      body:        body.substring(0, 100),
    };

    this._showFwdPicker(btn, fwdData);
  },

  _showFwdPicker: function(anchorBtn, fwdData) {
    var existing = document.getElementById('fwdTargetPicker');
    if (existing) existing.remove();

    var employees = (typeof TeamChat !== 'undefined') ? (TeamChat.employees || []) : [];
    var self = this;
    var hasCp = !!(fwdData.cp_id);

    var picker = document.createElement('div');
    picker.id = 'fwdTargetPicker';
    picker.className = 'fwd-picker';

    var html = '<div class="fwd-picker-title">Переслати до:</div>';

    if (hasCp) {
      html += '<button class="fwd-picker-item" data-dest="cp">'
            + '<span class="fwd-picker-icon" style="background:#ede9fe;color:#7c3aed">💬</span>'
            + '<div class="fwd-picker-text"><span class="fwd-picker-name">Команда</span>'
            + '<span class="fwd-picker-hint">по цьому клієнту</span></div>'
            + '</button>';
    }

    html += '<button class="fwd-picker-item" data-dest="general">'
          + '<span class="fwd-picker-icon" style="background:#dbeafe;color:#2563eb">'
          + '<svg width="14" height="14" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="3.5" stroke="currentColor" stroke-width="1.6"/><circle cx="13" cy="7" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M2 16c0-2.2 2.24-4 5-4M11 16c0-2.2 2.24-4 5-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>'
          + '</span>'
          + '<div class="fwd-picker-text"><span class="fwd-picker-name">Загальний чат</span>'
          + '<span class="fwd-picker-hint">команда без контексту</span></div>'
          + '</button>';

    if (employees.length) {
      html += '<div class="fwd-picker-sep"></div>';
      html += '<div class="fwd-picker-subtitle">Особисто</div>';
      employees.forEach(function(e) {
        var parts = (e.name || '').trim().split(/\s+/);
        var initials = parts.slice(0,2).map(function(w){ return w.charAt(0).toUpperCase(); }).join('');
        var shortName = parts.length >= 2 ? parts[0] + ' ' + parts[1].charAt(0) + '.' : parts[0];
        html += '<button class="fwd-picker-item" data-dest="dm:' + e.id + '">'
              + '<span class="fwd-picker-avatar">' + initials + '</span>'
              + '<span class="fwd-picker-name">' + self.esc(shortName) + '</span>'
              + '</button>';
      });
    }

    picker.innerHTML = html;
    document.body.appendChild(picker);

    var rect = anchorBtn.getBoundingClientRect();
    var pickerW = 210;
    var left = Math.min(rect.left, window.innerWidth - pickerW - 8);
    var top  = rect.bottom + 4;
    if (top + 260 > window.innerHeight) top = rect.top - 4 - Math.min(260, picker.offsetHeight || 200);
    picker.style.left = Math.max(8, left) + 'px';
    picker.style.top  = top + 'px';

    picker.querySelectorAll('.fwd-picker-item').forEach(function(item) {
      item.addEventListener('mousedown', function(e) {
        e.preventDefault();
        var dest = item.getAttribute('data-dest');
        picker.remove();
        self._executeFwd(dest, fwdData);
      });
    });

    function closePicker(e) {
      if (!picker.contains(e.target)) {
        picker.remove();
        document.removeEventListener('mousedown', closePicker);
      }
    }
    setTimeout(function() { document.addEventListener('mousedown', closePicker); }, 10);
  },

  _executeFwd: function(dest, fwdData) {
    if (dest === 'cp') {
      if (typeof WS !== 'undefined' && WS.switchTab) WS.switchTab('internal');
      if (typeof WsCpChat !== 'undefined') {
        if (!WsCpChat._inited) WsCpChat.init(fwdData.cp_id);
        WsCpChat.openFwdToTeam(fwdData);
      }
    } else if (dest === 'general') {
      if (typeof GlobalChat !== 'undefined') GlobalChat.openWith('general', null, fwdData);
    } else if (dest.indexOf('dm:') === 0) {
      var empId = parseInt(dest.slice(3), 10);
      if (typeof GlobalChat !== 'undefined') GlobalChat.openWith('dm', empId, fwdData);
    }
  },

  // ── Reply ─────────────────────────────────────────────────────────────────
  startReply: function(msgId, btn) {
    var row = btn.closest('[data-msg-id]');
    var bubble = row ? row.querySelector('.ws-bubble') : null;
    var bodyText = bubble ? (bubble.innerText || bubble.textContent || '').trim() : '';
    bodyText = bodyText.substring(0, 120);
    this._replyToId   = msgId;
    this._replyToBody = bodyText;
    var strip = document.getElementById('wsReplyStrip');
    if (strip) {
      var txt = strip.querySelector('.ws-reply-strip-text');
      if (txt) txt.textContent = bodyText || '…';
      strip.style.display = 'flex';
    }
    var inp = document.getElementById('wsMsgInput');
    if (inp) inp.focus();
  },

  cancelReply: function() {
    this._replyToId   = null;
    this._replyToBody = null;
    var strip = document.getElementById('wsReplyStrip');
    if (strip) strip.style.display = 'none';
  },

  // ── Forward ───────────────────────────────────────────────────────────────
  startForward: function(msgId, btn) {
    var row = btn.closest('[data-msg-id]');
    var bubble = row ? row.querySelector('.ws-bubble') : null;
    var preview = (bubble ? (bubble.innerText || bubble.textContent || '').trim() : '').substring(0, 120);
    this._fwdMsgId      = msgId;
    this._fwdMsgPreview = preview;
    var modal = document.getElementById('wsFwdModal');
    if (!modal) return;
    var prev = modal.querySelector('.ws-fwd-preview');
    if (prev) prev.textContent = preview || '…';
    var inp = modal.querySelector('.ws-fwd-search');
    if (inp) { inp.value = ''; }
    var res = modal.querySelector('.ws-fwd-results');
    if (res) res.innerHTML = '';
    modal.style.display = 'flex';
    if (inp) inp.focus();
  },

  closeFwdModal: function() {
    var modal = document.getElementById('wsFwdModal');
    if (modal) modal.style.display = 'none';
    this._fwdMsgId      = null;
    this._fwdMsgPreview = null;
  },

  searchFwdCounterparty: function(q) {
    var self = this;
    var res  = document.getElementById('wsFwdResults');
    if (!res) return;
    q = q.trim();
    if (q.length < 2) { res.innerHTML = ''; return; }
    res.style.display = 'block';
    res.innerHTML = '<div style="font-size:12px;color:#9ca3af;padding:6px 10px">Пошук…</div>';
    fetch('/counterparties/api/search?q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.items || !d.items.length) {
          res.innerHTML = '<div style="font-size:12px;color:#9ca3af;padding:6px 10px">Нічого не знайдено</div>';
          return;
        }
        var html = '';
        d.items.forEach(function(it) {
          html += '<div class="ws-fwd-result" data-cp-id="' + it.id + '">' + self.esc(it.name) + '</div>';
        });
        res.innerHTML = html;
        res.querySelectorAll('.ws-fwd-result').forEach(function(el) {
          el.addEventListener('mousedown', function(e) {
            e.preventDefault();
            self.doForward(parseInt(el.getAttribute('data-cp-id'), 10), el.textContent.trim());
          });
        });
      });
  },

  doForward: function(targetCpId, targetName) {
    var self    = this;
    var msgId   = this._fwdMsgId;
    if (!msgId || !targetCpId) return;
    var fd = new FormData();
    fd.append('id', targetCpId);
    fd.append('channel', this.activeCh);
    fd.append('forward_msg_id', msgId);
    fetch('/counterparties/api/forward_message', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          showToast('Переслано до: ' + targetName);
          self.closeFwdModal();
        } else {
          showToast('Помилка: ' + (d.error || 'невідома'), true);
        }
      })
      .catch(function() { showToast('Помилка пересилання', true); });
  },

  formatTime: function(dt) {
    if (!dt) return '';
    var d = new Date((dt || '').replace(' ', 'T'));
    return d.toLocaleTimeString('uk', { hour: '2-digit', minute: '2-digit' });
  },

};