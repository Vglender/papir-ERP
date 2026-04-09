<?php
/**
 * chat_popup.php — автономна сторінка чату для iframe-попапу.
 * URL: /counterparties/chat-popup?id=<cp_id>[&ch=viber|sms|email|telegram|note]
 * Не використовує layout.php, мінімальний HTML.
 */
require_once __DIR__ . '/../../../modules/database/database.php';

$cpId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$initCh = isset($_GET['ch']) ? preg_replace('/[^a-z]/', '', $_GET['ch']) : 'viber';
$allowedCh = array('viber', 'sms', 'email', 'telegram', 'note', 'tasks');
if (!in_array($initCh, $allowedCh)) {
    $initCh = 'viber';
}

if ($cpId <= 0) {
    http_response_code(400);
    echo '<p style="font-family:sans-serif;padding:20px;color:#dc2626">Помилка: id контрагента не вказано</p>';
    exit;
}

// Перевірити що контрагент існує + завантажити контактні дані для визначення доступних каналів
$r = Database::fetchRow('Papir',
    "SELECT c.id, c.name, c.telegram_chat_id, c.viber_unavailable,
            cp.phone AS person_phone, cp.email AS person_email,
            cc.phone AS company_phone, cc.email AS company_email
     FROM counterparty c
     LEFT JOIN counterparty_person  cp ON cp.counterparty_id = c.id
     LEFT JOIN counterparty_company cc ON cc.counterparty_id = c.id
     WHERE c.id = {$cpId} AND c.status = 1 LIMIT 1");
if (!$r['ok'] || empty($r['row'])) {
    http_response_code(404);
    echo '<p style="font-family:sans-serif;padding:20px;color:#dc2626">Контрагент не знайдений</p>';
    exit;
}
$cpName = htmlspecialchars($r['row']['name'], ENT_QUOTES, 'UTF-8');

// Визначаємо доступні канали
$phone = $r['row']['company_phone'] ? $r['row']['company_phone'] : $r['row']['person_phone'];
$email = $r['row']['company_email'] ? $r['row']['company_email'] : $r['row']['person_email'];
$tgChatId = $r['row']['telegram_chat_id'];

$viberUnavailable = !empty($r['row']['viber_unavailable']);
$availableChannels = array('note');
if ($phone) {
    if (!$viberUnavailable) { $availableChannels[] = 'viber'; }
    $availableChannels[] = 'sms';
}
if ($email)   { $availableChannels[] = 'email'; }
if ($tgChatId){ $availableChannels[] = 'telegram'; }

// Якщо запитаний канал недоступний — перемикаємо на перший доступний
if (!in_array($initCh, array_merge($availableChannels, array('tasks')))) {
    $initCh = $phone ? 'viber' : 'note';
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Чат: <?php echo $cpName; ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    height: 100%; overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px; color: #1a1a1a; background: #fff;
}

/* ── Layout ─────────────────────────────────────────────────────────────────── */
.cp-popup {
    display: flex; flex-direction: column; height: 100vh; overflow: hidden;
}

/* ── Popup header ───────────────────────────────────────────────────────────── */
.cp-popup-head {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; border-bottom: 1px solid #e5e7eb;
    background: #fff; flex-shrink: 0;
}
.cp-popup-av {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff; background: #7c3aed;
}
.cp-popup-name {
    font-weight: 700; font-size: 13px; color: #111827; flex: 1; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cp-popup-phone { font-weight: 400; color: #6b7280; font-size: 12px; margin-left: 4px; }
.cp-popup-link {
    font-size: 11px; color: #9ca3af; text-decoration: none; flex-shrink: 0;
}
.cp-popup-link:hover { color: #7c3aed; }
.cp-close-btn {
    width: 26px; height: 26px; border: none; background: transparent;
    cursor: pointer; color: #9ca3af; font-size: 16px; border-radius: 4px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cp-close-btn:hover { background: #fee2e2; color: #dc2626; }

/* ── Channel tabs ───────────────────────────────────────────────────────────── */
.ws-ch-tabs {
    display: flex; padding: 0 14px; border-bottom: 1px solid #f0f0f0;
    background: #fafafa; flex-shrink: 0; overflow-x: auto;
}
.ws-ch-tab {
    padding: 6px 10px; font-size: 11px; font-weight: 500; color: #9ca3af;
    border-bottom: 2px solid transparent; border-top: none; border-left: none;
    border-right: none; background: none; cursor: pointer; font-family: inherit;
    white-space: nowrap; transition: opacity .15s;
}
.ws-ch-tab.ch-unavailable { opacity: 0.35; cursor: default; }
.ws-ch-tab.ch-unavailable:hover { color: #9ca3af; }
.ws-ch-tab.active { color: #7c3aed; border-bottom-color: #7c3aed; }
.ws-ch-tab { position: relative; }
.ws-ch-tab .ch-unread-dot {
    position: absolute; top: 4px; right: 2px;
    width: 7px; height: 7px; border-radius: 50%;
    background: #ef4444; display: none;
}
.ws-ch-tab.has-unread .ch-unread-dot { display: block; }

/* ── Messages ───────────────────────────────────────────────────────────────── */
.ws-msgs {
    flex: 1; overflow-y: auto; padding: 12px 14px;
    display: flex; flex-direction: column; gap: 8px; background: #fafafa;
}
.ws-msg-row { display: flex; flex-direction: column; width: 100%; }
.ws-msg-row.out { align-items: flex-end; }
.ws-msg-row.in  { align-items: flex-start; }
.ws-bubble {
    padding: 8px 11px; border-radius: 10px; font-size: 12px;
    line-height: 1.55; max-width: 80%; white-space: pre-wrap; word-break: break-word;
}
.ws-msg-row.out .ws-bubble { background: #7c3aed; color: #fff; border-radius: 10px 2px 10px 10px; }
.ws-msg-row.in  .ws-bubble { background: #f3f4f6; color: #1a1a1a; border-radius: 2px 10px 10px 10px; }
.ws-bubble.media-only { padding: 0; background: transparent !important; overflow: hidden; border-radius: 10px; }
.ws-bubble.media-only img { display: block; border-radius: 10px; }
.ws-msg-meta { font-size: 10px; color: #9ca3af; margin-top: 2px; padding: 0 2px; display: flex; align-items: center; gap: 3px; }
.ws-msg-status { display: inline-flex; align-items: center; }
.ws-msg-status svg { width: 12px; height: 12px; }
.ws-msg-status.pending svg { color: #9ca3af; }
.ws-msg-status.sent    svg { color: #9ca3af; }
.ws-msg-status.delivered svg { color: #9ca3af; }
.ws-msg-status.read    svg { color: #6d28d9; }
.ws-msg-status.failed  svg { color: #ef4444; }
.ws-bubble .chat-link { color: inherit; text-decoration: underline; word-break: break-all; opacity: .9; }
.ws-bubble .chat-link:hover { opacity: 1; }
.ws-msg-row.reminder .ws-bubble {
    background: #fef9c3; color: #78350f;
    border: 1px solid #fde68a; border-radius: 8px;
}
.ws-msg-row.reminder .ws-bubble::before { content: '🔔 '; font-size: 13px; }

/* Message outer (bubble + action buttons side by side) */
.ws-msg-outer {
    display: inline-flex; align-items: center; gap: 4px; max-width: 85%;
}
.ws-msg-row.out .ws-msg-outer { flex-direction: row-reverse; }
.ws-msg-row.in  .ws-msg-outer { flex-direction: row; }
.ws-msg-actions {
    display: flex; flex-direction: column; gap: 3px; flex-shrink: 0;
    opacity: 0; pointer-events: none; transition: opacity .1s;
}
.ws-msg-outer:hover .ws-msg-actions { opacity: 1; pointer-events: auto; }
.ws-msg-act-btn {
    width: 24px; height: 24px; border: none; border-radius: 5px;
    background: #ede9fe; color: #7c3aed; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-family: inherit; padding: 0; line-height: 1;
}
.ws-msg-act-btn:hover { background: #ddd6fe; }
/* Reply quote inside bubble */
.ws-bubble-reply {
    border-left: 3px solid rgba(255,255,255,.5);
    background: rgba(0,0,0,.12); border-radius: 4px;
    padding: 4px 8px; font-size: 11px; line-height: 1.4;
    margin-bottom: 5px; white-space: pre-wrap; word-break: break-word;
    max-height: 44px; overflow: hidden; opacity: .9;
}
.ws-msg-row.in .ws-bubble-reply { border-left-color: #7c3aed; background: rgba(124,58,237,.08); }
/* Reply strip above textarea */
.ws-reply-strip {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 14px; background: #f5f3ff;
    border-top: 1px solid #ede9fe; flex-shrink: 0;
}
.ws-reply-strip-bar { width: 3px; align-self: stretch; min-height: 28px; background: #7c3aed; border-radius: 2px; flex-shrink: 0; }
.ws-reply-strip-text { flex: 1; font-size: 12px; color: #374151; line-height: 1.4; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.ws-reply-strip-close { width: 22px; height: 22px; border: none; background: transparent; color: #9ca3af; cursor: pointer; font-size: 18px; line-height: 1; padding: 0; border-radius: 4px; flex-shrink: 0; }
.ws-reply-strip-close:hover { background: #ede9fe; color: #7c3aed; }
/* Forward search results */
.ws-fwd-result { padding: 8px 10px; cursor: pointer; font-size: 13px; border-radius: 6px; }
.ws-fwd-result:hover { background: #f5f3ff; }

/* ── Input area ─────────────────────────────────────────────────────────────── */
.ws-input-area { border-top: 1px solid #f0f0f0; flex-shrink: 0; background: #fff; }
.ws-input-toolbar {
    display: flex; align-items: center; gap: 2px;
    padding: 6px 14px 2px; position: relative;
}
.ws-t-btn {
    width: 28px; height: 28px; border-radius: 6px; border: none; background: transparent;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    color: #6b7280; font-family: inherit;
}
.ws-t-btn:hover { background: #f3f0ff; color: #7c3aed; }
.ws-t-hint { font-size: 11px; color: #9ca3af; padding: 0 6px; }
.ws-textarea {
    width: 100%; padding: 6px 14px 2px; border: none; outline: none;
    font-size: 13px; font-family: inherit; resize: vertical; background: transparent;
    line-height: 1.5; min-height: 80px; max-height: 300px; color: #1a1a1a;
}
.ws-textarea::placeholder { color: #9ca3af; }
.ws-textarea:disabled { opacity: .5; cursor: not-allowed; }
.ws-input-row {
    display: flex; align-items: center; gap: 6px; padding: 4px 14px 10px;
}
.ws-char-c { font-size: 11px; color: #9ca3af; }
.ws-send-btn {
    margin-left: auto; display: flex; align-items: center; gap: 5px;
    padding: 7px 14px; background: #7c3aed; color: #fff; border: none;
    border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: inherit;
}
.ws-send-btn:hover { background: #6d28d9; }
.ws-send-btn:disabled { background: #d1d5db; cursor: not-allowed; }

/* ── File attachment preview ────────────────────────────────────────────────── */
.ws-attach-preview {
    display: none; align-items: center; gap: 8px;
    padding: 6px 14px; border-top: 1px solid #f0f0f0; background: #f9fafb;
    flex-shrink: 0;
}
.ws-attach-preview.visible { display: flex; }
.ws-attach-thumb { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; }
.ws-attach-icon { width: 48px; height: 48px; border-radius: 6px; background: #ede9fe; display: flex; align-items: center; justify-content: center; font-size: 20px; border: 1px solid #e5e7eb; }
.ws-attach-info { flex: 1; min-width: 0; }
.ws-attach-name { font-size: 12px; font-weight: 600; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ws-attach-size { font-size: 11px; color: #9ca3af; }
.ws-attach-remove { width: 24px; height: 24px; border: none; background: transparent; cursor: pointer; color: #9ca3af; font-size: 16px; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ws-attach-remove:hover { background: #fee2e2; color: #dc2626; }
.ws-attach-uploading { font-size: 11px; color: #7c3aed; animation: ws-pulse 1s infinite; }
@keyframes ws-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── Templates dropdown ─────────────────────────────────────────────────────── */
.ws-tpl-picker {
    position: absolute; bottom: calc(100% + 6px); left: 0;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12); width: 280px;
    display: none; z-index: 100; overflow: hidden;
}
.ws-tpl-picker.open { display: block; }
.ws-tpl-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 12px; border-bottom: 1px solid #f3f4f6;
    font-size: 12px; font-weight: 600; color: #374151;
}
.ws-tpl-manage { font-size: 11px; color: #7c3aed; background: none; border: none; cursor: pointer; font-family: inherit; }
.ws-tpl-manage:hover { text-decoration: underline; }
.ws-tpl-list { max-height: 220px; overflow-y: auto; }
.ws-tpl-item {
    padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f9fafb;
    transition: background .1s;
}
.ws-tpl-item:last-child { border-bottom: none; }
.ws-tpl-item:hover { background: #f5f3ff; }
.ws-tpl-item-title { font-size: 12px; font-weight: 600; color: #1f2937; margin-bottom: 2px; }
.ws-tpl-item-body  { font-size: 11px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ws-tpl-empty { padding: 16px 12px; font-size: 12px; color: #9ca3af; text-align: center; }

/* ── Emoji picker ───────────────────────────────────────────────────────────── */
.ws-emoji-picker {
    position: absolute; bottom: calc(100% + 6px); left: 0;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12); padding: 8px;
    display: none; z-index: 100; width: 240px;
}
.ws-emoji-picker.open { display: block; }
.ws-emoji-grid { display: flex; flex-wrap: wrap; gap: 2px; }
.ws-emoji-btn {
    width: 28px; height: 28px; border: none; background: transparent;
    font-size: 16px; cursor: pointer; border-radius: 5px; display: flex;
    align-items: center; justify-content: center;
}
.ws-emoji-btn:hover { background: #f3f0ff; }

/* ── Tasks pane ─────────────────────────────────────────────────────────────── */
.ws-tasks-pane {
    display: flex; flex-direction: column; flex: 1; overflow: hidden;
    background: #f9fafb;
}
.ws-tasks-list { flex: 1; overflow-y: auto; padding: 8px 12px; display: flex; flex-direction: column; gap: 6px; }
.ws-tasks-empty { padding: 32px 0; text-align: center; font-size: 13px; color: #9ca3af; }
.ws-task-card {
    display: flex; align-items: flex-start; gap: 0;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    overflow: hidden; transition: border-color .12s;
}
.ws-task-card:hover { border-color: #c4b5fd; }
.ws-task-card.done-card { opacity: .55; }
.ws-task-card.snoozed-card { opacity: .7; border-style: dashed; }
.ws-task-pri-bar { width: 4px; flex-shrink: 0; align-self: stretch; border-radius: 0; }
.ws-task-pri-1 { background: #9ca3af; }
.ws-task-pri-2 { background: #60a5fa; }
.ws-task-pri-3 { background: #f59e0b; }
.ws-task-pri-4 { background: #ef4444; }
.ws-task-pri-5 { background: #7c3aed; }
.ws-task-body { flex: 1; min-width: 0; padding: 8px 10px; display: flex; align-items: flex-start; gap: 8px; }
.ws-task-icon { font-size: 15px; flex-shrink: 0; line-height: 1.4; }
.ws-task-content { flex: 1; min-width: 0; }
.ws-task-title { font-size: 13px; font-weight: 600; color: #111827; line-height: 1.3; word-break: break-word; }
.ws-task-meta { display: flex; align-items: center; gap: 6px; margin-top: 3px; flex-wrap: wrap; }
.ws-task-type-lbl { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: .3px; }
.ws-task-due { font-size: 11px; font-weight: 600; padding: 1px 5px; border-radius: 4px; }
.ws-task-due.overdue   { background: #fee2e2; color: #dc2626; }
.ws-task-due.due-soon  { background: #fff7ed; color: #c2410c; }
.ws-task-due.due-today { background: #fef9c3; color: #854d0e; }
.ws-task-due.due-later { background: #f0fdf4; color: #166534; }
.ws-task-due.no-due    { color: #9ca3af; }
.ws-task-snoozed-lbl { font-size: 11px; color: #7c3aed; background: #ede9fe; padding: 1px 5px; border-radius: 4px; }
.ws-task-acts { display: flex; flex-direction: column; gap: 0; flex-shrink: 0; padding: 4px 4px 4px 0; }
.ws-task-act {
    width: 26px; height: 26px; border: none; background: transparent; cursor: pointer;
    border-radius: 5px; display: flex; align-items: center; justify-content: center;
    font-size: 14px; color: #9ca3af; transition: background .1s, color .1s;
    position: relative;
}
.ws-task-act:hover { background: #f3f4f6; color: #374151; }
.ws-task-act.done-btn:hover  { background: #dcfce7; color: #16a34a; }
.ws-task-act.snooze-btn:hover { background: #ede9fe; color: #7c3aed; }
.ws-task-quick-add {
    border-top: 1px solid #e5e7eb; background: #fff; padding: 10px 12px; flex-shrink: 0;
}
.ws-task-quick-add input[type=text] {
    width: 100%; padding: 6px 9px; border: 1px solid #e5e7eb; border-radius: 6px;
    font-size: 13px; font-family: inherit; outline: none; transition: border-color .12s;
    box-sizing: border-box;
}
.ws-task-quick-add input[type=text]:focus { border-color: #a78bfa; }
.ws-task-quick-row {
    display: flex; gap: 6px; margin-top: 6px; align-items: center; flex-wrap: wrap;
}
.ws-task-quick-row select {
    padding: 4px 6px; border: 1px solid #e5e7eb; border-radius: 5px;
    font-size: 12px; font-family: inherit; background: #fff; outline: none; cursor: pointer;
    flex: 1; min-width: 0;
}
.ws-task-quick-row input[type=datetime-local] {
    padding: 4px 6px; border: 1px solid #e5e7eb; border-radius: 5px;
    font-size: 12px; font-family: inherit; background: #fff; outline: none;
    flex: 1.2; min-width: 0;
}
.ws-task-add-btn {
    padding: 5px 12px; background: #7c3aed; color: #fff; border: none;
    border-radius: 5px; font-size: 12px; font-weight: 600; cursor: pointer;
    white-space: nowrap; transition: background .1s; flex-shrink: 0;
}
.ws-task-add-btn:hover { background: #6d28d9; }
.ws-task-add-btn:disabled { background: #c4b5fd; cursor: not-allowed; }

/* ── Snooze dropdown ────────────────────────────────────────────────────────── */
.ws-snooze-menu {
    position: absolute; right: 0; top: 100%; z-index: 200;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 7px;
    box-shadow: 0 4px 14px rgba(0,0,0,.12); min-width: 160px; overflow: hidden;
}
.ws-snooze-item {
    display: block; width: 100%; text-align: left; padding: 8px 12px;
    font-size: 12px; background: none; border: none; cursor: pointer; color: #374151;
    transition: background .1s;
}
.ws-snooze-item:hover { background: #f3f4f6; }

/* ── Template manager modal ─────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.35);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal-box {
    background: #fff; border-radius: 12px; box-shadow: 0 8px 40px rgba(0,0,0,.18);
    width: 100%; max-width: 500px; max-height: 90vh; overflow: hidden;
    display: flex; flex-direction: column;
}
.modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid #f0f0f0;
    font-size: 14px; font-weight: 700; color: #111827; flex-shrink: 0;
}
.modal-close {
    width: 28px; height: 28px; border: none; background: transparent;
    cursor: pointer; font-size: 18px; color: #9ca3af; border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background: #fee2e2; color: #dc2626; }
.modal-body { padding: 18px; overflow-y: auto; flex: 1; }
.modal-footer {
    padding: 12px 18px; border-top: 1px solid #f0f0f0;
    display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0;
}
.modal-error { color: #dc2626; font-size: 12px; margin-top: 8px; }
.form-row { margin-bottom: 12px; }
.form-row label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
.form-row input[type=text], .form-row textarea, .form-row select {
    width: 100%; padding: 7px 10px; border: 1px solid #e5e7eb; border-radius: 7px;
    font-size: 13px; font-family: inherit; outline: none; transition: border-color .12s;
}
.form-row input[type=text]:focus, .form-row textarea:focus, .form-row select:focus { border-color: #a78bfa; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 0 14px; height: 34px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; color: #374151; font-size: 13px; font-weight: 500; cursor: pointer; font-family: inherit; text-decoration: none; }
.btn-ghost { background: #fff; border-color: #e5e7eb; color: #374151; }
.btn-ghost:hover { background: #f9fafb; }
.btn-primary { background: #7c3aed; border-color: #7c3aed; color: #fff; }
.btn-primary:hover { background: #6d28d9; }
.btn-sm { height: 28px; padding: 0 10px; font-size: 12px; }
.ws-tm-list { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.ws-tm-row {
    display: flex; align-items: flex-start; gap: 8px;
    background: #f9fafb; border-radius: 8px; padding: 8px 10px;
}
.ws-tm-info { flex: 1; min-width: 0; }
.ws-tm-title { font-size: 12px; font-weight: 600; color: #1f2937; }
.ws-tm-channels { font-size: 10px; color: #9ca3af; margin-top: 1px; }
.ws-tm-body-preview { font-size: 11px; color: #6b7280; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ws-tm-actions { display: flex; gap: 4px; flex-shrink: 0; }
.ws-tm-btn { background: none; border: 1px solid #e5e7eb; border-radius: 5px; cursor: pointer; padding: 2px 6px; font-size: 11px; font-family: inherit; color: #6b7280; }
.ws-tm-btn:hover { background: #f3f4f6; }
.ws-tm-btn.del:hover { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }
.ws-tm-form { border-top: 1px solid #f3f4f6; padding-top: 14px; margin-top: 4px; }
.ws-tm-form-title { font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 10px; }
.ws-tm-chs { display: flex; gap: 10px; flex-wrap: wrap; margin: 6px 0 10px; }
.ws-tm-ch { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #4b5563; cursor: pointer; }

/* ── Loading state ──────────────────────────────────────────────────────────── */
.ws-inbox-loading { padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; }

/* ── Toast ──────────────────────────────────────────────────────────────────── */
.toast {
    position: fixed; bottom: 16px; left: 50%;
    transform: translateX(-50%) translateY(10px);
    background: #222; color: #fff;
    padding: 8px 18px; border-radius: 20px; font-size: 13px;
    opacity: 0; transition: opacity .2s, transform .2s;
    z-index: 3000; pointer-events: none; white-space: nowrap;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
</head>
<body>

<div class="cp-popup">

  <!-- ── Popup header ─────────────────────────────────────────────────────── -->
  <div class="cp-popup-head">
    <div class="cp-popup-av" id="cpPopupAv"><?php echo mb_strtoupper(mb_substr($r['row']['name'], 0, 1, 'UTF-8'), 'UTF-8'); ?></div>
    <div class="cp-popup-name"><?php echo $cpName; ?><?php if ($phone): ?> <span class="cp-popup-phone"><?php echo htmlspecialchars($phone); ?></span><?php endif; ?></div>
    <a href="/counterparties/view?id=<?php echo $cpId; ?>" target="_blank" class="cp-popup-link" title="Відкрити картку">↗</a>
    <button class="cp-close-btn" title="Закрити" onclick="window.parent.postMessage('chat-popup-close','*')">×</button>
  </div>

  <!-- ── Channel tabs ─────────────────────────────────────────────────────── -->
  <div class="ws-ch-tabs" id="wsChTabs">
    <button class="ws-ch-tab<?php echo $initCh === 'viber' ? ' active' : ''; ?>" data-ch="viber">Viber<span class="ch-unread-dot"></span></button>
    <button class="ws-ch-tab<?php echo $initCh === 'sms' ? ' active' : ''; ?>" data-ch="sms">SMS<span class="ch-unread-dot"></span></button>
    <button class="ws-ch-tab<?php echo $initCh === 'email' ? ' active' : ''; ?>" data-ch="email">Email<span class="ch-unread-dot"></span></button>
    <button class="ws-ch-tab<?php echo $initCh === 'telegram' ? ' active' : ''; ?>" data-ch="telegram">Telegram<span class="ch-unread-dot"></span></button>
    <button class="ws-ch-tab<?php echo $initCh === 'tasks' ? ' active' : ''; ?>" data-ch="tasks">✅ Завдання<span class="ch-unread-dot"></span></button>
  </div>

  <!-- ── Messages area ────────────────────────────────────────────────────── -->
  <div class="ws-msgs" id="wsMsgs" style="flex:1">
    <div class="ws-inbox-loading">Завантаження…</div>
  </div>

  <!-- ── Tasks pane ───────────────────────────────────────────────────────── -->
  <div class="ws-tasks-pane" id="wsTasksPane" style="display:none; flex:1">
    <div class="ws-tasks-list" id="wsTasksList">
      <div class="ws-tasks-empty">Завантаження…</div>
    </div>
    <div class="ws-task-quick-add" id="wsTaskQuickAdd">
      <input type="text" id="wsTaskTitle" placeholder="Нова задача для цього контрагента…"
             onkeydown="if(event.key==='Enter')ChatHub.addTask()">
      <div class="ws-task-quick-row">
        <select id="wsTaskType">
          <option value="call_back">📞 Передзвонити</option>
          <option value="follow_up">💬 Нагадати</option>
          <option value="send_docs">📄 Надіслати документи</option>
          <option value="payment">💰 Платіж</option>
          <option value="meeting">📅 Зустріч</option>
          <option value="other" selected>✔ Інше</option>
        </select>
        <select id="wsTaskPriority">
          <option value="1">↓ Низький</option>
          <option value="2">→ Нормальний</option>
          <option value="3" selected>↑ Важливий</option>
          <option value="4">⚡ Терміновий</option>
          <option value="5">🔥 Критичний</option>
        </select>
        <input type="datetime-local" id="wsTaskDue" title="Дедлайн (необов'язково)">
        <button class="ws-task-add-btn" id="wsTaskAddBtn" onclick="ChatHub.addTask()">+ Додати</button>
      </div>
    </div>
  </div>

  <!-- ── Input area ───────────────────────────────────────────────────────── -->
  <div class="ws-input-area">
    <!-- File attachment preview -->
    <div class="ws-attach-preview" id="wsAttachPreview">
      <div id="wsAttachThumbWrap"></div>
      <div class="ws-attach-info">
        <div class="ws-attach-name" id="wsAttachName"></div>
        <div class="ws-attach-size" id="wsAttachSize"></div>
      </div>
      <button class="ws-attach-remove" onclick="ChatHub.removeAttach()" title="Видалити">×</button>
    </div>
    <!-- Hidden file input -->
    <input type="file" id="wsFileInput" style="display:none"
           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
           onchange="ChatHub.onFileSelected(this)">
    <div class="ws-input-toolbar">
      <!-- Emoji picker -->
      <div class="ws-emoji-picker" id="wsEmojiPicker">
        <div class="ws-emoji-grid" id="wsEmojiGrid"></div>
      </div>
      <button class="ws-t-btn" title="Емодзі" id="wsEmojiBtn" onclick="ChatHub.toggleEmoji(event)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
      </button>
      <button class="ws-t-btn" title="Прикріпити файл або фото" onclick="ChatHub.openFilePicker()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
      </button>
      <!-- Templates dropdown -->
      <div class="ws-tpl-picker" id="wsTplPicker">
        <div class="ws-tpl-head">
          <span>Шаблони</span>
          <button class="ws-tpl-manage" onclick="ChatHub.openTplManager()">Управляти →</button>
        </div>
        <div class="ws-tpl-list" id="wsTplList"></div>
      </div>
      <button class="ws-t-btn" id="wsTplBtn" title="Шаблони" onclick="ChatHub.toggleTemplates(event)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
      </button>
      <span class="ws-t-hint" id="wsInputHint"></span>
    </div>
    <!-- Reply strip -->
    <div class="ws-reply-strip" id="wsReplyStrip" style="display:none">
      <div class="ws-reply-strip-bar"></div>
      <div class="ws-reply-strip-text"></div>
      <button type="button" class="ws-reply-strip-close" onclick="ChatHub.cancelReply()" title="Скасувати">&#x2715;</button>
    </div>
    <textarea class="ws-textarea" id="wsMsgInput" placeholder="Написати повідомлення…" rows="2" spellcheck="false"></textarea>
    <div class="ws-input-row">
      <span class="ws-char-c" id="wsCharC"></span>
      <button class="ws-send-btn" id="wsSendBtn" onclick="ChatHub.sendMessage()">
        Надіслати
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </button>
    </div>
  </div>

</div><!-- /cp-popup -->

<!-- Forward message modal -->
<div class="modal-overlay" id="wsFwdModal" style="display:none" onclick="if(event.target===this)ChatHub.closeFwdModal()">
  <div class="modal-box" style="max-width:420px">
    <div class="modal-head">
      <span>Переслати повідомлення</span>
      <button class="modal-close" onclick="ChatHub.closeFwdModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="ws-fwd-preview" style="font-size:12px;color:#6b7280;border-left:3px solid #7c3aed;padding:4px 10px;border-radius:3px;background:#f5f3ff;margin-bottom:12px;max-height:44px;overflow:hidden;"></div>
      <input type="text" class="ws-fwd-search" placeholder="Пошук контрагента…" autocomplete="off"
             style="width:100%;height:34px;border:1px solid #e5e7eb;border-radius:8px;padding:0 10px;font-size:13px;font-family:inherit;box-sizing:border-box"
             oninput="ChatHub.searchFwdCounterparty(this.value)">
      <div class="ws-fwd-results" id="wsFwdResults" style="margin-top:6px;max-height:200px;overflow-y:auto;border-radius:8px;border:1px solid #e5e7eb;display:none"></div>
    </div>
  </div>
</div>

<!-- ══ Modal: template manager ════════════════════════════════════════════════ -->
<div class="modal-overlay" id="wsTplModal" style="display:none" onclick="if(event.target===this)ChatHub.closeTplManager()">
  <div class="modal-box">
    <div class="modal-head">
      <span>Шаблони повідомлень</span>
      <button class="modal-close" onclick="ChatHub.closeTplManager()">×</button>
    </div>
    <div class="modal-body" style="max-height:70vh;overflow-y:auto">
      <div id="wsTmList" class="ws-tm-list"></div>
      <div class="ws-tm-form" id="wsTmForm">
        <div class="ws-tm-form-title" id="wsTmFormTitle">Новий шаблон</div>
        <input type="hidden" id="wsTmId" value="0">
        <div class="form-row">
          <label>Назва <span style="color:#ef4444">*</span></label>
          <input type="text" id="wsTmTitle" placeholder="Короткий опис шаблону">
        </div>
        <div class="form-row">
          <label>Текст <span style="color:#ef4444">*</span></label>
          <textarea id="wsTmBody" rows="4" style="resize:vertical" placeholder="Текст повідомлення…"></textarea>
        </div>
        <div class="form-row">
          <label>Канали</label>
          <div class="ws-tm-chs">
            <label class="ws-tm-ch"><input type="checkbox" name="tmch" value="viber" checked> Viber</label>
            <label class="ws-tm-ch"><input type="checkbox" name="tmch" value="sms"> SMS</label>
            <label class="ws-tm-ch"><input type="checkbox" name="tmch" value="email"> Email</label>
            <label class="ws-tm-ch"><input type="checkbox" name="tmch" value="telegram"> Telegram</label>
            <label class="ws-tm-ch"><input type="checkbox" name="tmch" value="note"> Нотатка</label>
          </div>
        </div>
        <div class="modal-error" id="wsTmErr" style="display:none"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px">
          <button class="btn btn-ghost btn-sm" onclick="ChatHub.cancelTmEdit()">Скасувати</button>
          <button class="btn btn-primary btn-sm" onclick="ChatHub.saveTmTemplate()">Зберегти</button>
        </div>
      </div>
      <button class="btn btn-ghost btn-sm" id="wsTmAddBtn" onclick="ChatHub.newTmTemplate()" style="margin-top:4px">+ Новий шаблон</button>
    </div>
  </div>
</div>

<!-- ── Toast ──────────────────────────────────────────────────────────────── -->
<div class="toast" id="_blGlobalToast"></div>

<script src="/modules/shared/chat-hub.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chat-hub.js'); ?>"></script>
<script>
window.showToast = function(msg, isError) {
    var t = document.getElementById('_blGlobalToast');
    if (!t) return;
    t.textContent = msg;
    t.style.background = isError ? '#dc2626' : '';
    t.classList.add('show');
    clearTimeout(t._toastTimer);
    t._toastTimer = setTimeout(function() { t.classList.remove('show'); }, 2500);
};

// loadTmList використовує WS.editTmTemplate — пробриджуємо на ChatHub
var WS = {
    editTmTemplate: function(id, t, b, ch) { ChatHub.editTmTemplate(id, t, b, ch); },
    deleteTmTemplate: function(id)          { ChatHub.deleteTmTemplate(id); }
};

ChatHub.bindChannelTabs();
ChatHub.updateChannelTabs(<?php echo json_encode(array_values($availableChannels)); ?>);

ChatHub.init({
    cpId:    <?php echo $cpId; ?>,
    kind:    'counterparty',
    activeCh: '<?php echo $initCh; ?>'
});

// Запустити polling для popup-режиму
ChatHub.startPolling();

// Слухати команди від батьківського вікна (switchChannel, setPrefill)
window.addEventListener('message', function(e) {
    if (!e.data || typeof e.data !== 'object') return;
    if (e.data.action === 'switchChannel' && e.data.ch) {
        var tabs = document.querySelectorAll('.ws-ch-tab');
        for (var i = 0; i < tabs.length; i++) {
            if (tabs[i].dataset.ch === e.data.ch) {
                tabs[i].click();
                break;
            }
        }
    }
    if (e.data.action === 'setPrefill' && e.data.text) {
        var inp = document.getElementById('wsMsgInput');
        if (inp) {
            inp.value = e.data.text;
            inp.focus();
            inp.setSelectionRange(inp.value.length, inp.value.length);
        }
    }
    if (e.data.action === 'setAttach' && e.data.url) {
        ChatHub.setAttachFromUrl(e.data.url, e.data.name || '');
    }
});
</script>
</body>
</html>
