/**
 * chat-modal.js — Floating iframe chat panel.
 *
 * Usage:
 *   ChatModal.open(cpId)           // open for counterparty
 *   ChatModal.open(cpId, 'sms')    // open on specific channel
 *   ChatModal.close()
 *
 * The panel slides in from the bottom-right corner.
 * Listens to postMessage 'chat-popup-close' from the iframe (close button inside popup).
 */
var ChatModal = (function() {
    var PANEL_ID      = '_chatModalPanel';
    var IFRAME_ID     = '_chatModalFrame';
    var TOGGLE_ID     = '_chatModalToggle';
    var _cpId           = null;
    var _visible        = false;
    var _prefillPending = null;
    var _attachPending  = null; // { url, name }

    function _ensurePanel() {
        if (document.getElementById(PANEL_ID)) return;

        // Inject styles once
        var style = document.createElement('style');
        style.textContent = [
            '#' + PANEL_ID + ' {',
            '    position: fixed; bottom: 20px; right: 20px;',
            '    width: 400px; height: 560px;',
            '    border: 1px solid #e5e7eb; border-radius: 14px;',
            '    box-shadow: 0 8px 40px rgba(0,0,0,.18);',
            '    background: #fff; overflow: hidden;',
            '    z-index: 9000;',
            '    display: flex; flex-direction: column;',
            '    transition: transform .25s ease, opacity .25s ease;',
            '    transform: translateY(20px); opacity: 0; pointer-events: none;',
            '}',
            '#' + PANEL_ID + '.cm-open {',
            '    transform: translateY(0); opacity: 1; pointer-events: auto;',
            '}',
            '#' + IFRAME_ID + ' {',
            '    width: 100%; flex: 1; border: none; display: block;',
            '}',
            '#' + TOGGLE_ID + ' {',
            '    position: fixed; bottom: 20px; right: 20px;',
            '    width: 48px; height: 48px; border-radius: 50%;',
            '    background: #7c3aed; color: #fff; border: none;',
            '    box-shadow: 0 4px 16px rgba(124,58,237,.45);',
            '    font-size: 22px; cursor: pointer; z-index: 8999;',
            '    display: none; align-items: center; justify-content: center;',
            '    transition: background .15s;',
            '}',
            '#' + TOGGLE_ID + ':hover { background: #6d28d9; }',
            '#' + TOGGLE_ID + '.cm-has-cp { display: flex; }',
        ].join('\n');
        document.head.appendChild(style);

        // Panel container
        var panel = document.createElement('div');
        panel.id = PANEL_ID;

        // iframe
        var iframe = document.createElement('iframe');
        iframe.id = IFRAME_ID;
        iframe.setAttribute('frameborder', '0');
        panel.appendChild(iframe);

        document.body.appendChild(panel);

        // Toggle button (reopen when panel is closed)
        var btn = document.createElement('button');
        btn.id = TOGGLE_ID;
        btn.title = 'Відкрити чат';
        btn.innerHTML = '💬';
        btn.onclick = function() { _show(); };
        document.body.appendChild(btn);

        // Listen for close message from iframe
        window.addEventListener('message', function(e) {
            if (e.data === 'chat-popup-close') {
                _hide();
            }
        });
    }

    function _buildUrl(cpId, ch) {
        var url = '/counterparties/chat-popup?id=' + cpId;
        if (ch) url += '&ch=' + encodeURIComponent(ch);
        return url;
    }

    function _show() {
        var panel = document.getElementById(PANEL_ID);
        if (!panel) return;
        panel.classList.add('cm-open');
        _visible = true;
        // Hide toggle button when panel is open
        var btn = document.getElementById(TOGGLE_ID);
        if (btn) btn.style.display = 'none';
    }

    function _hide() {
        var panel = document.getElementById(PANEL_ID);
        if (!panel) return;
        panel.classList.remove('cm-open');
        _visible = false;
        // Show toggle button so user can reopen
        if (_cpId) {
            var btn = document.getElementById(TOGGLE_ID);
            if (btn) { btn.style.display = 'flex'; btn.classList.add('cm-has-cp'); }
        }
    }

    return {
        /**
         * Open the chat panel for a given counterparty.
         * @param {number} cpId
         * @param {string} [ch]         channel: viber|sms|email|telegram|tasks
         * @param {string} [prefillText] optional text to pre-fill in the message input
         */
        open: function(cpId, ch, prefillText, attachUrl, attachName) {
            _ensurePanel();
            var iframe = document.getElementById(IFRAME_ID);
            if (!iframe) return;

            var newUrl = _buildUrl(cpId, ch || 'viber');

            if (_cpId !== cpId || !_visible) {
                // Reload iframe; send prefill/attach after it finishes loading
                _cpId = cpId;
                _prefillPending = prefillText || null;
                _attachPending  = attachUrl ? { url: attachUrl, name: attachName || '' } : null;
                if (_prefillPending || _attachPending) {
                    iframe.onload = function() {
                        try {
                            if (_prefillPending) {
                                iframe.contentWindow.postMessage(
                                    { action: 'setPrefill', text: _prefillPending }, '*');
                                _prefillPending = null;
                            }
                            if (_attachPending) {
                                iframe.contentWindow.postMessage(
                                    { action: 'setAttach', url: _attachPending.url, name: _attachPending.name }, '*');
                                _attachPending = null;
                            }
                        } catch(e) {}
                        iframe.onload = null;
                    };
                }
                iframe.src = newUrl;
            } else {
                // Panel already open with same cp — inject directly
                try {
                    if (prefillText) {
                        iframe.contentWindow.postMessage(
                            { action: 'setPrefill', text: prefillText }, '*');
                    }
                    if (attachUrl) {
                        iframe.contentWindow.postMessage(
                            { action: 'setAttach', url: attachUrl, name: attachName || '' }, '*');
                    }
                } catch(e) {}
            }

            // Show toggle button scaffold (hidden until panel is closed)
            var btn = document.getElementById(TOGGLE_ID);
            if (btn) btn.classList.add('cm-has-cp');

            _show();
        },

        /** Close (hide) the panel. */
        close: function() {
            _hide();
        },

        /** Switch channel inside already-open panel without full reload. */
        switchChannel: function(ch) {
            if (!_cpId) return;
            var iframe = document.getElementById(IFRAME_ID);
            if (iframe && iframe.contentWindow) {
                try {
                    iframe.contentWindow.postMessage({ action: 'switchChannel', ch: ch }, '*');
                } catch(e) {
                    // fallback: reload with new channel
                    iframe.src = _buildUrl(_cpId, ch);
                }
            }
        }
    };
}());