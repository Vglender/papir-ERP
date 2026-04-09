<?php
$title     = 'Контрагенти';
$activeNav = 'prostor';
$subNav    = 'counterparties';
$bodyClass = 'ws-body';
$extraCss  = array(
    '<link rel="stylesheet" href="/modules/shared/chip-search.js?noop=1" style="display:none">',
);
require_once __DIR__ . '/../../shared/layout.php';

// Load organizations and employees for order header selectors
$rOrgs = Database::fetchAll('Papir',
    "SELECT id, name, short_name, vat_number FROM organization WHERE status=1 ORDER BY name ASC");
$wsOrgs = ($rOrgs['ok'] && !empty($rOrgs['rows'])) ? $rOrgs['rows'] : array();

$rEmps = Database::fetchAll('Papir',
    "SELECT id, full_name AS name FROM employee WHERE status = 1 ORDER BY last_name ASC");
$wsEmployees = ($rEmps['ok'] && !empty($rEmps['rows'])) ? $rEmps['rows'] : array();

$rDMs = Database::fetchAll('Papir',
    "SELECT id, code, name_uk, has_ttn FROM delivery_method WHERE status=1 ORDER BY sort_order");
$wsDeliveryMethods = ($rDMs['ok'] && !empty($rDMs['rows'])) ? $rDMs['rows'] : array();

$rPMs = Database::fetchAll('Papir',
    "SELECT id, code, name_uk FROM payment_method WHERE status=1 ORDER BY sort_order");
$wsPaymentMethods = ($rPMs['ok'] && !empty($rPMs['rows'])) ? $rPMs['rows'] : array();
?>
<style>
/* ── Workspace layout ───────────────────────────────────────────────────────── */
.ws-wrap {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.ws-layout {
    display: grid;
    grid-template-columns: 268px minmax(280px, 360px) 1fr;
    grid-template-rows: 1fr;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* ── Inbox (left) ───────────────────────────────────────────────────────────── */
.ws-inbox {
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #fff;
}
.ws-inbox-top {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 12px 8px;
    border-bottom: 1px solid #f3f4f6;
    flex-shrink: 0;
}
.ws-inbox-title { font-size: 13px; font-weight: 700; color: #111827; flex: 1; }
.ws-mode-tabs { display: flex; gap: 2px; }
.ws-mode-tab {
    width: 28px; height: 28px; border-radius: 6px; border: 1px solid #e5e7eb;
    background: #fff; font-size: 14px; cursor: pointer; display: flex;
    align-items: center; justify-content: center; color: #6b7280;
}
.ws-mode-tab.active { background: #ede9fe; border-color: #c4b5fd; color: #7c3aed; }
.ws-mode-badge {
    display: none; min-width: 16px; height: 16px; border-radius: 8px;
    background: #ef4444; color: #fff; font-size: 9px; font-weight: 700;
    padding: 0 4px; line-height: 16px; text-align: center;
    vertical-align: middle; margin-left: 2px;
}
.ws-mode-badge.visible { display: inline-block; }
.ws-inbox-search-wrap { padding: 8px 12px; flex-shrink: 0; }
.ws-search-box { position: relative; }
.ws-inbox-search {
    width: 100%; height: 32px; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 0 26px 0 10px; font-size: 12px; background: #f9fafb; outline: none;
    font-family: inherit;
}
.ws-inbox-search:focus { border-color: #a78bfa; background: #fff; }
.ws-search-clear {
    position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #9ca3af;
    font-size: 15px; padding: 0 3px; line-height: 1; display: flex; align-items: center;
}
.ws-search-clear:hover { color: #374151; }
.ws-search-clear.hidden { display: none; }
.ws-inbox-body { flex: 1; overflow-y: auto; }
.ws-pager {
    display: flex; align-items: center; justify-content: center;
    gap: 8px; padding: 6px 12px;
    border-top: 1px solid #f3f4f6; flex-shrink: 0;
    background: #fff;
}
.ws-pager-btn {
    width: 26px; height: 26px; border-radius: 6px;
    border: 1px solid #e5e7eb; background: #f9fafb;
    cursor: pointer; font-size: 14px; color: #374151;
    display: flex; align-items: center; justify-content: center;
    line-height: 1;
}
.ws-pager-btn:hover:not(:disabled) { background: #ede9fe; border-color: #c4b5fd; color: #7c3aed; }
.ws-pager-btn:disabled { opacity: .35; cursor: default; }
.ws-pager-info { font-size: 11px; color: #6b7280; min-width: 48px; text-align: center; }

/* Tier headers */
.ws-tier {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 12px 4px;
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: #9ca3af; background: #f9fafb;
    border-top: 1px solid #f3f4f6; position: sticky; top: 0; z-index: 1;
}
.ws-tier-dot { width: 7px; height: 7px; border-radius: 50%; }
.ws-tier.urgent .ws-tier-dot { background: #ef4444; }
.ws-tier.attention .ws-tier-dot { background: #f59e0b; }
.ws-tier.active .ws-tier-dot { background: #10b981; }
.ws-tier.processed .ws-tier-dot { background: #d1d5db; }

/* Contact card in inbox */
.ws-card {
    display: flex; align-items: flex-start; gap: 9px;
    padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #f3f4f6;
    transition: background .1s; position: relative;
}
.ws-card:hover { background: #f9fafb; }
.ws-card.selected { background: #f5f3ff; }
.ws-card-av {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; color: #fff;
}
.ws-card-av.company { background: #3b82f6; }
.ws-card-av.fop     { background: #f59e0b; }
.ws-card-av.person  { background: #8b5cf6; }
.ws-card-av.lead    { background: #6b7280; font-size: 16px; }
.ws-card-av.urgent  { background: #ef4444; }
.ws-card-body { flex: 1; min-width: 0; }
.ws-card-row1 { display: flex; align-items: center; gap: 4px; margin-bottom: 2px; }
.ws-card-name { font-size: 12px; font-weight: 600; color: #111827; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ws-card-time { font-size: 10px; color: #9ca3af; flex-shrink: 0; }
.ws-card-sub { font-size: 11px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 1px; }
.ws-card-msg { font-size: 11px; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ws-card-msg.unread { color: #374151; font-weight: 500; }
.ws-unread-badge {
    position: absolute; top: 9px; right: 10px;
    min-width: 18px; height: 18px; border-radius: 9px;
    background: #7c3aed; color: #fff; font-size: 10px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; padding: 0 4px;
}
.ws-urgent-bar {
    position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
    background: #ef4444; border-radius: 0 2px 2px 0;
}

/* ── Hub (center) ───────────────────────────────────────────────────────────── */
.ws-hub {
    display: flex; flex-direction: column; overflow: hidden;
    border-right: 1px solid #e5e7eb; background: #fff;
}
.ws-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 8px;
    color: #9ca3af;
}
.ws-empty-ic { font-size: 40px; opacity: .4; }
.ws-empty-txt { font-size: 14px; }
.ws-hub-inner { display: flex; flex-direction: column; flex: 1; overflow: hidden; }

/* AI/Manual mode banner */
.ws-mode-banner {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 14px; font-size: 12px; font-weight: 600;
    border-bottom: 1px solid transparent; flex-shrink: 0; cursor: default;
    transition: background .25s, border-color .25s;
}
.ws-mode-banner.ai     { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
.ws-mode-banner.manual { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.ws-mode-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ws-mode-banner.ai     .ws-mode-dot { background: #16a34a; }
.ws-mode-banner.manual .ws-mode-dot { background: #d97706; }
.ws-mode-lbl { flex: 1; }
.ws-mode-toggle {
    display: flex; align-items: center; gap: 6px;
    margin-left: auto; cursor: pointer; user-select: none;
}
.ws-toggle-track {
    width: 34px; height: 18px; border-radius: 9px; position: relative;
    flex-shrink: 0; transition: background .2s;
}
.ws-mode-banner.ai     .ws-toggle-track { background: #16a34a; }
.ws-mode-banner.manual .ws-toggle-track { background: #d97706; }
.ws-toggle-thumb {
    width: 14px; height: 14px; border-radius: 50%; background: #fff;
    position: absolute; top: 2px; transition: left .2s;
}
.ws-mode-banner.ai     .ws-toggle-thumb { left: 18px; }
.ws-mode-banner.manual .ws-toggle-thumb { left: 2px; }
.ws-toggle-lbl { font-size: 11px; }

/* Hub header */
.ws-hub-head {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0;
}
.ws-hub-av {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; color: #fff; background: #7c3aed;
}
.ws-hub-name { font-weight: 700; font-size: 14px; color: #111827; }
.ws-hub-card-link { color: #9ca3af; font-size: 12px; line-height: 1; text-decoration: none; flex-shrink: 0; }
.ws-hub-card-link:hover { color: #6b7280; }
.ws-hub-sub { font-size: 11px; color: #6b7280; }
.ws-hub-acts { margin-left: auto; display: flex; gap: 5px; }
.ws-hub-act {
    font-size: 11px; padding: 4px 9px; border-radius: 6px;
    border: 1px solid #e5e7eb; background: #fff; color: #374151;
    cursor: pointer; font-family: inherit;
}
.ws-hub-act:hover { background: #f9fafb; }
.ws-hub-act.primary { background: #7c3aed; color: #fff; border-color: #7c3aed; }
.ws-hub-act.primary:hover { background: #6d28d9; }

/* Hub tabs */
.ws-hub-tabs {
    display: flex; border-bottom: 1px solid #f0f0f0;
    padding: 0 14px; background: #fafafa; flex-shrink: 0;
}
.ws-hub-tab {
    padding: 8px 12px; font-size: 12px; font-weight: 500;
    color: #9ca3af; border-bottom: 2px solid transparent;
    border-top: none; border-left: none; border-right: none;
    background: none; cursor: pointer; font-family: inherit;
    white-space: nowrap;
}
.ws-hub-tab.active { color: #7c3aed; border-bottom-color: #7c3aed; font-weight: 600; }

/* Tab panes */
.ws-tab-pane { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

/* ── Chat tab ───────────────────────────────────────────────────────────────── */
.ws-contact-switcher {
    display: flex; flex-wrap: wrap; gap: 4px; padding: 6px 10px 5px;
    border-bottom: 1px solid #f0f0f0; background: #fff; flex-shrink: 0;
}
.ws-cs-btn {
    padding: 3px 9px; font-size: 11px; font-weight: 600;
    background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 10px;
    cursor: pointer; white-space: nowrap; color: #6b7280;
    transition: background .12s, border-color .12s, color .12s;
    max-width: 140px; overflow: hidden; text-overflow: ellipsis;
    font-family: inherit;
}
.ws-cs-btn:hover { color: #374151; border-color: #c0c8d0; }
.ws-cs-btn.active { background: #eff6ff; border-color: #93c5fd; color: #2563eb; }
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
/* Image-only bubble: no background/padding, just the photo */
.ws-bubble.media-only { padding: 0; background: transparent !important; overflow: hidden; border-radius: 10px; }
.ws-bubble.media-only img { display: block; border-radius: 10px; }
.ws-msg-meta { font-size: 10px; color: #9ca3af; margin-top: 2px; padding: 0 2px; display: flex; align-items: center; gap: 3px; }
/* Message delivery status icons (outgoing only) */
.ws-msg-status { display: inline-flex; align-items: center; }
.ws-msg-status svg { width: 12px; height: 12px; }
.ws-msg-status.pending svg { color: #9ca3af; }
.ws-msg-status.sent    svg { color: #9ca3af; }
.ws-msg-status.delivered svg { color: #9ca3af; }
.ws-msg-status.read    svg { color: #6d28d9; }
.ws-msg-status.failed  svg { color: #ef4444; }

/* System messages (delivery errors) */
.ws-msg-system {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 6px 0; font-size: 12px; color: #dc2626;
}
.ws-msg-system span:first-child {
  background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
  padding: 4px 12px; max-width: 80%; word-break: break-word;
}
.ws-msg-system-time { color: #9ca3af; font-size: 11px; white-space: nowrap; }

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

/* Reply preview strip above textarea */
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

/* Takeover notice */
.ws-takeover {
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
    margin: 0 14px 6px; padding: 8px 12px; display: flex; align-items: center;
    gap: 8px; font-size: 11px; color: #78350f; flex-shrink: 0;
}
.ws-takeover-txt { flex: 1; }
.ws-takeover-btn {
    font-size: 10px; font-weight: 600; background: #fef3c7; border: 1px solid #fde68a;
    color: #92400e; border-radius: 5px; padding: 3px 9px; cursor: pointer; font-family: inherit;
}

/* AI draft */
.ws-ai-draft {
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
    margin: 0 14px 6px; padding: 8px 11px; display: none; flex-shrink: 0;
}
.ws-ai-draft-ic { font-size: 10px; font-weight: 700; color: #15803d; margin-bottom: 3px; }
.ws-ai-draft-txt { font-size: 11px; color: #15803d; line-height: 1.5; }
.ws-ai-draft-btns { display: flex; gap: 5px; margin-top: 5px; }
.ws-ai-use { font-size: 10px; font-weight: 600; background: #dcfce7; color: #15803d; border: none; border-radius: 4px; padding: 2px 8px; cursor: pointer; font-family: inherit; }
.ws-ai-skip { font-size: 10px; color: #6b7280; background: transparent; border: 1px solid #e5e5e5; border-radius: 4px; padding: 2px 8px; cursor: pointer; font-family: inherit; }

/* Input area */
.ws-input-area { border-top: 1px solid #f0f0f0; flex-shrink: 0; background: #fff; }
.ws-input-toolbar {
    display: flex; align-items: center; gap: 2px;
    padding: 6px 14px 2px;
}
.ws-t-btn {
    width: 28px; height: 28px; border-radius: 6px; border: none; background: transparent;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
    color: #6b7280; font-family: inherit;
}
.ws-t-btn:hover { background: #f3f0ff; color: #7c3aed; }
.ws-t-hint { font-size: 11px; color: #9ca3af; padding: 0 6px; }
.ws-t-hint.active-hint { color: #15803d; }
.ws-textarea {
    width: 100%; padding: 6px 14px 2px; border: none; outline: none;
    font-size: 13px; font-family: inherit; resize: vertical; background: transparent;
    line-height: 1.5; min-height: 96px; max-height: 400px; color: #1a1a1a;
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

/* ── Orders tab ─────────────────────────────────────────────────────────────── */
.ws-orders-list { flex: 1; overflow-y: auto; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
.ws-order-card {
    border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; cursor: pointer;
    transition: border-color .15s;
}
.ws-order-card:hover { border-color: #c4b5fd; }
.ws-order-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; }
.ws-order-icon { width: 30px; height: 30px; border-radius: 7px; background: #ede9fe; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ws-order-num { font-weight: 700; font-size: 13px; color: #111827; }
.ws-order-sub { font-size: 11px; color: #6b7280; }
.ws-order-sum { font-size: 13px; font-weight: 700; color: #7c3aed; margin-left: auto; }
.ws-order-date { font-size: 10px; color: #9ca3af; }
.ws-order-open { font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 5px; border: 1px solid #e5e7eb; background: #fff; color: #374151; cursor: pointer; font-family: inherit; margin-left: 6px; text-decoration: none; }
.ws-order-open:hover { background: #f9fafb; }

/* Status badges */
.ws-sbadge { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 20px; }
.ws-traffic-badge { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 10px; white-space: nowrap; }
.wsb-draft     { background: #f0f4f8; color: #6b7280; }
.wsb-new       { background: #e8f0fa; color: #2563c4; }
.wsb-confirmed { background: #ede9fe; color: #5b21b6; }
.wsb-progress  { background: #fae8ff; color: #7e22ce; }
.wsb-waiting   { background: #fff4e5; color: #b26a00; }
.wsb-paid      { background: #ccfbf1; color: #0f766e; }
.wsb-partship  { background: #ede9fe; color: #5b21b6; }
.wsb-shipped   { background: #edfdf3; color: #1a7f3c; }
.wsb-done      { background: #edfdf3; color: #15803d; }
.wsb-cancelled { background: #fff0f0; color: #c0392b; }

/* Placeholder tabs */
.ws-placeholder { flex: 1; display: flex; align-items: center; justify-content: center; color: #d1d5db; font-size: 13px; }

/* ── Context (right) ────────────────────────────────────────────────────────── */
.ws-ctx { overflow: hidden; background: #fff; display: flex; flex-direction: column; min-height: 0; }
.ws-ctx-empty { flex: 1; display: flex; align-items: center; justify-content: center; color: #d1d5db; font-size: 12px; padding: 20px; text-align: center; }
.ws-ctx-section { padding: 14px 14px 0; }
.ws-ctx-section + .ws-ctx-section { padding-top: 12px; border-top: 1px solid #f3f4f6; margin-top: 12px; }
.ws-ctx-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #9ca3af; margin-bottom: 8px; }
.ws-ctx-row { display: flex; align-items: flex-start; gap: 6px; margin-bottom: 5px; font-size: 12px; }
.ws-ctx-icon { font-size: 13px; flex-shrink: 0; margin-top: 1px; }
.ws-ctx-val { color: #374151; word-break: break-all; }
.ws-ctx-val a { color: #7c3aed; text-decoration: none; }
.ws-ctx-val a:hover { text-decoration: underline; }

/* Order summary in context */
.ws-ctx-order { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; }
.ws-ctx-order-top { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
.ws-ctx-order-num { font-weight: 700; font-size: 12px; }
.ws-ctx-order-sum { font-weight: 700; font-size: 13px; color: #7c3aed; margin-left: auto; }
.ws-ctx-order-sub { font-size: 11px; color: #6b7280; }

/* Stats row */
.ws-ctx-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 10px; }
.ws-ctx-stat { background: #f9fafb; border-radius: 7px; padding: 7px 10px; }
.ws-ctx-stat-val { font-size: 13px; font-weight: 700; color: #111827; }
.ws-ctx-stat-lbl { font-size: 10px; color: #9ca3af; }

/* Lead identification panel */
.ws-lead-panel { padding: 14px; }
.ws-lead-badge {
    display: inline-flex; align-items: center; gap: 5px;
    background: #fef3c7; border: 1px solid #fde68a; color: #92400e;
    font-size: 11px; font-weight: 600; border-radius: 20px; padding: 3px 10px;
    margin-bottom: 12px;
}
.ws-lead-info { font-size: 12px; color: #374151; margin-bottom: 14px; }
.ws-lead-info-row { display: flex; gap: 6px; margin-bottom: 4px; }
.ws-lead-info-lbl { color: #9ca3af; min-width: 60px; }
.ws-lead-sep { border: none; border-top: 1px solid #f3f4f6; margin: 12px 0; }
.ws-lead-section-title { font-size: 11px; font-weight: 700; color: #374151; margin-bottom: 8px; }
.ws-cp-search { width: 100%; height: 32px; border: 1px solid #e5e7eb; border-radius: 7px; padding: 0 10px; font-size: 12px; font-family: inherit; outline: none; }
.ws-cp-search:focus { border-color: #a78bfa; }
.ws-match-list { margin-top: 6px; display: flex; flex-direction: column; gap: 4px; }
.ws-match-item {
    display: flex; align-items: center; gap: 8px; padding: 7px 10px;
    border: 1px solid #e5e7eb; border-radius: 7px; cursor: pointer;
    transition: border-color .1s; font-size: 12px;
}
.ws-match-item:hover { border-color: #a78bfa; background: #f9f5ff; }
.ws-match-name { font-weight: 600; flex: 1; }
.ws-match-tag { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 10px; background: #ede9fe; color: #5b21b6; }
.ws-match-select { font-size: 10px; padding: 3px 8px; border-radius: 5px; border: 1px solid #c4b5fd; background: #f5f0ff; color: #7c3aed; cursor: pointer; font-family: inherit; }
.ws-lead-btns { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
.ws-lead-btn {
    width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #e5e7eb;
    background: #fff; font-size: 12px; font-family: inherit; cursor: pointer; text-align: left;
}
.ws-lead-btn:hover { background: #f9fafb; }
.ws-lead-btn.primary { background: #7c3aed; color: #fff; border-color: #7c3aed; }
.ws-lead-btn.primary:hover { background: #6d28d9; }
.ws-lead-btn.danger { color: #dc2626; border-color: #fecaca; }
.ws-lead-btn.danger:hover { background: #fef2f2; }

/* Full card link */
.ws-ctx-fullcard {
    margin: 14px; padding: 8px 12px; border-radius: 8px; border: 1px solid #e5e7eb;
    text-align: center; font-size: 12px; color: #6b7280; text-decoration: none; display: block;
}
.ws-ctx-fullcard:hover { background: #f9fafb; color: #7c3aed; border-color: #c4b5fd; }

/* Add btn */
.ws-add-btn {
    height: 28px; padding: 0 10px; font-size: 12px; font-weight: 600;
    background: #7c3aed; color: #fff; border: none; border-radius: 6px; cursor: pointer;
    font-family: inherit;
}
.ws-add-btn:hover { background: #6d28d9; }

/* Loading / empty states */
.ws-inbox-loading { padding: 20px; text-align: center; font-size: 12px; color: #9ca3af; }
.ws-no-items { padding: 20px; text-align: center; font-size: 12px; color: #d1d5db; }

/* New counterparty modal (reuse .modal-* from ui.css) */

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

/* ── Template manager modal ──────────────────────────────────────────────────── */
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

/* ── Hub header contacts pills ──────────────────────────────────────────────── */
.ws-hub-contact-pill {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 11px; color: #6b7280; text-decoration: none;
    background: #f3f4f6; border-radius: 10px; padding: 1px 8px;
    white-space: nowrap;
}
.ws-hub-contact-pill:hover { color: #7c3aed; background: #f5f3ff; }
#wsHubContacts { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 3px; }

/* ── Right panel sections ────────────────────────────────────────────────────── */
.ws-ctx-head {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .4px; color: #9ca3af; padding: 12px 14px 6px;
}
.ws-ctx-head + .ws-ctx-head { border-top: 1px solid #f3f4f6; padding-top: 10px; }

/* ── Active documents timeline ───────────────────────────────────────────────── */
.ws-tl { padding: 4px 14px 10px; }
.ws-tl-step { display: flex; gap: 10px; padding-bottom: 12px; position: relative; }
.ws-tl-step:not(:last-child)::before {
    content: ''; position: absolute; left: 9px; top: 22px; bottom: 0;
    width: 2px; background: #e5e7eb;
}
.ws-tl-dot {
    width: 20px; height: 20px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; background: #f3f4f6; border: 2px solid #e5e7eb;
    position: relative; z-index: 1;
}
.ws-tl-dot.done    { background: #dcfce7; border-color: #86efac; }
.ws-tl-dot.active  { background: #ede9fe; border-color: #c4b5fd; }
.ws-tl-dot.pending { opacity: .45; }
.ws-tl-body { flex: 1; min-width: 0; padding-top: 1px; }
.ws-tl-name { font-size: 12px; font-weight: 600; color: #374151; }
.ws-tl-name.pending { color: #9ca3af; font-weight: 400; }
.ws-tl-val  { font-size: 11px; color: #6b7280; margin-top: 1px; }
.ws-tl-val a { color: #7c3aed; text-decoration: none; }
.ws-tl-val a:hover { text-decoration: underline; }
.ws-tl-empty { font-size: 12px; color: #d1d5db; padding: 6px 14px 12px; text-align: center; }

/* ── Mini completed lists ────────────────────────────────────────────────────── */
.ws-ctx-mini { padding: 0 14px 10px; }
.ws-ctx-mini-row {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 0; border-bottom: 1px solid #f9fafb;
    font-size: 11px; color: #374151;
}
.ws-ctx-mini-row:last-child { border-bottom: none; }
.ws-ctx-mini-num { font-weight: 600; color: #111827; white-space: nowrap; }
.ws-ctx-mini-sub { color: #9ca3af; font-size: 10px; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ws-ctx-mini-sum { font-size: 11px; font-weight: 600; color: #7c3aed; white-space: nowrap; }
.ws-ctx-mini-empty { font-size: 11px; color: #d1d5db; padding: 4px 0; }

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
.ws-bubble .chat-link { color: inherit; text-decoration: underline; word-break: break-all; opacity: .9; }
.ws-bubble .chat-link:hover { opacity: 1; }

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

.ws-task-pri-bar {
  width: 4px; flex-shrink: 0; align-self: stretch;
  border-radius: 0;
}
.ws-task-pri-1 { background: #9ca3af; }
.ws-task-pri-2 { background: #60a5fa; }
.ws-task-pri-3 { background: #f59e0b; }
.ws-task-pri-4 { background: #ef4444; }
.ws-task-pri-5 { background: #7c3aed; }

.ws-task-body {
  flex: 1; min-width: 0; padding: 8px 10px; display: flex; align-items: flex-start; gap: 8px;
}
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

/* Quick add form */
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

/* ── Urgency dot (ball is with us / client model) ──────────────────────── */
.ws-urg-dot {
  display: inline-block; width: 9px; height: 9px; border-radius: 50%;
  flex-shrink: 0; margin-right: 5px; vertical-align: middle;
}
.ws-urg-critical { background: #ef4444; box-shadow: 0 0 0 2px rgba(239,68,68,.25); animation: ws-pulse-dot 1.5s infinite; }
.ws-urg-high     { background: #f97316; }
.ws-urg-medium   { background: #eab308; }
.ws-urg-low      { background: #22c55e; }
@keyframes ws-pulse-dot { 0%,100%{box-shadow:0 0 0 2px rgba(239,68,68,.25)} 50%{box-shadow:0 0 0 4px rgba(239,68,68,.15)} }

/* Task indicator badge on inbox card */
.ws-task-badge {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 8px;
  background: #ede9fe; color: #7c3aed; white-space: nowrap; flex-shrink: 0;
}
.ws-task-badge.overdue { background: #fee2e2; color: #dc2626; }

/* Snooze dropdown */
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

/* ── Team Chat ──────────────────────────────────────────────────────────────── */
.tc-tabs-wrap { flex-shrink:0; padding: 6px 10px 0; border-bottom: 1px solid #f3f4f6; overflow-x: auto; }
.tc-tabs { display: flex; gap: 4px; white-space: nowrap; }
.tc-tab {
    height: 28px; padding: 0 10px; border: 1px solid #e5e7eb; border-radius: 14px;
    background: #fff; font-size: 11px; font-family: inherit; cursor: pointer;
    color: #6b7280; display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0;
}
.tc-tab.active { background: #ede9fe; border-color: #c4b5fd; color: #7c3aed; font-weight: 600; }
.tc-tab:hover:not(.active) { background: #f9fafb; }
.tc-badge {
    min-width: 16px; height: 16px; border-radius: 8px; background: #ef4444;
    color: #fff; font-size: 9px; font-weight: 700; padding: 0 4px;
    line-height: 16px; text-align: center;
}
.tc-title-bar { flex-shrink:0; padding: 6px 14px 4px; }
.tc-title { font-size: 12px; font-weight: 600; color: #374151; }
.tc-msgs {
    flex: 1; overflow-y: auto; padding: 10px 14px;
    display: flex; flex-direction: column; gap: 6px; background: #fafafa;
}
.tc-empty { font-size: 12px; color: #9ca3af; text-align: center; padding: 20px 0; }
.tc-msg-row { display: flex; flex-direction: column; }
.tc-msg-row.out { align-items: flex-end; }
.tc-msg-row.in  { align-items: flex-start; }
.tc-fwd-badge {
    display: flex; align-items: center; gap: 5px; margin-bottom: 3px;
    font-size: 11px; color: #7c3aed;
}
.tc-fwd-icon { font-size: 13px; }
.tc-fwd-cp { color: #7c3aed; font-weight: 600; text-decoration: none; }
.tc-fwd-cp:hover { text-decoration: underline; }
.tc-fwd-author { color: #6b7280; }
.tc-bubble {
    padding: 7px 10px; border-radius: 10px; font-size: 12px;
    line-height: 1.5; max-width: 78%; white-space: pre-wrap; word-break: break-word;
}
.tc-msg-row.out .tc-bubble { background: #7c3aed; color: #fff; border-radius: 10px 2px 10px 10px; }
.tc-msg-row.in  .tc-bubble { background: #f3f4f6; color: #1a1a1a; border-radius: 2px 10px 10px 10px; }
.tc-bubble .tc-link { color: inherit; opacity: .85; text-decoration: underline; word-break: break-all; }
.tc-meta { font-size: 10px; color: #9ca3af; margin-top: 2px; padding: 0 2px; display: flex; gap: 4px; }
.tc-from { font-weight: 600; color: #6b7280; }
.tc-fwd-strip {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 14px; background: #f5f3ff; border-top: 1px solid #ede9fe; flex-shrink: 0;
}
.tc-fwd-strip-bar { width: 3px; align-self: stretch; min-height: 24px; background: #7c3aed; border-radius: 2px; flex-shrink: 0; }
.tc-fwd-strip-text { flex: 1; font-size: 11px; color: #374151; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.tc-fwd-strip-close { width: 20px; height: 20px; border: none; background: transparent; color: #9ca3af; cursor: pointer; font-size: 16px; padding: 0; border-radius: 4px; flex-shrink: 0; }
.tc-fwd-strip-close:hover { background: #ede9fe; color: #7c3aed; }
/* Forwarded quote block */
.tc-fwd-header { font-size: 11px; color: #7c3aed; font-weight: 600; margin-bottom: 3px; }
.tc-quote {
    border-left: 3px solid #7c3aed; background: rgba(124,58,237,.07);
    border-radius: 4px; padding: 5px 9px; margin-bottom: 4px; max-width: 78%;
}
.tc-quote-meta { font-size: 10px; color: #7c3aed; font-weight: 600; margin-bottom: 2px; }
.tc-quote-body { font-size: 12px; color: #374151; line-height: 1.4; white-space: pre-wrap; word-break: break-word; max-height: 60px; overflow: hidden; }
.tc-comment { margin-top: 4px; }
.tc-input-area { border-top: 1px solid #f0f0f0; flex-shrink: 0; background: #fff; padding: 6px 14px 8px; }
.tc-input {
    width: 100%; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 6px 10px; font-size: 12px; font-family: inherit; resize: none;
    outline: none; box-sizing: border-box; line-height: 1.4;
}
.tc-input:focus { border-color: #c4b5fd; }
.tc-input-row { display: flex; justify-content: flex-end; margin-top: 5px; }
</style>

<div class="ws-wrap">
  <div class="ws-layout">

    <!-- ══ LEFT: INBOX ═══════════════════════════════════════════════════════ -->
    <div class="ws-inbox">
      <div class="ws-inbox-top">
        <span class="ws-inbox-title">Робочий простір</span>
        <div class="ws-mode-tabs">
          <button class="ws-mode-tab active" data-mode="chat" title="За повідомленнями">💬<span class="ws-mode-badge" id="wsModeBadgeChat"></span></button>
          <button class="ws-mode-tab" data-mode="orders" title="За замовленнями">📦<span class="ws-mode-badge" id="wsModeBadgeOrders"></span></button>
        </div>
        <button class="ws-add-btn" id="wsBtnNew">+ Новий</button>
        <button class="ws-mode-tab" id="wsBtnSpam" title="Заблоковані відправники" onclick="WS.openSpamModal()">🚫</button>
      </div>
      <div class="ws-inbox-search-wrap">
        <div class="ws-search-box">
          <input type="text" class="ws-inbox-search" id="wsSearch" placeholder="Пошук контрагента...">
          <button type="button" class="ws-search-clear hidden" id="wsSearchClear" title="Очистити">&#x2715;</button>
        </div>
      </div>
      <div class="ws-inbox-body" id="wsInboxBody">
        <div class="ws-inbox-loading">Завантаження…</div>
      </div>
      <div class="ws-pager" id="wsInboxPager" style="display:none"></div>
    </div>

    <!-- ══ CENTER: HUB ═══════════════════════════════════════════════════════ -->
    <div class="ws-hub" id="wsHub">
      <div class="ws-empty" id="wsEmpty">
        <div class="ws-empty-ic">👥</div>
        <div class="ws-empty-txt">Оберіть контрагента або нове звернення</div>
      </div>
      <div class="ws-hub-inner" id="wsHubInner" style="display:none; flex:1; overflow:hidden; display:none; flex-direction:column;">

        <!-- AI/Manual banner -->
        <div class="ws-mode-banner ai" id="wsModeBanner" style="display:none">
          <span class="ws-mode-dot"></span>
          <span class="ws-mode-lbl" id="wsModeLbl">AI відповідає автоматично</span>
          <label class="ws-mode-toggle" onclick="WS.toggleAi()">
            <span class="ws-toggle-track"><span class="ws-toggle-thumb"></span></span>
            <span class="ws-toggle-lbl" id="wsToggleLbl">Автомат</span>
          </label>
        </div>

        <!-- Hub header -->
        <div class="ws-hub-head">
          <div class="ws-hub-av" id="wsHubAv">?</div>
          <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <div class="ws-hub-name" id="wsHubName">—</div>
            </div>
            <div id="wsHubContacts"></div>
            <div class="ws-hub-sub" id="wsHubSub"></div>
          </div>
          <div class="ws-hub-acts" id="wsHubActs"></div>
        </div>

        <!-- Hub tabs -->
        <div class="ws-hub-tabs">
          <button class="ws-hub-tab active" data-tab="chat">💬 Чат</button>
          <button class="ws-hub-tab" data-tab="internal">👥 Команда</button>
        </div>

        <!-- Chat pane -->
        <div class="ws-tab-pane" id="wsPaneChat">
          <!-- Contact switcher (shown only when company has linked persons) -->
          <div class="ws-contact-switcher" id="wsContactSwitcher" style="display:none"></div>
          <div class="ws-ch-tabs" id="wsChTabs">
            <button class="ws-ch-tab active" data-ch="viber">Viber<span class="ch-unread-dot"></span></button>
            <button class="ws-ch-tab" data-ch="sms">SMS<span class="ch-unread-dot"></span></button>
            <button class="ws-ch-tab" data-ch="email">Email<span class="ch-unread-dot"></span></button>
            <button class="ws-ch-tab" data-ch="telegram">Telegram<span class="ch-unread-dot"></span></button>
            <button class="ws-ch-tab" data-ch="tasks">✅ Завдання<span class="ch-unread-dot"></span></button>
          </div>
          <div class="ws-msgs" id="wsMsgs"><div class="ws-inbox-loading">Завантаження…</div></div>
          <!-- Tasks pane (shown when ch=tasks) -->
          <div class="ws-tasks-pane" id="wsTasksPane" style="display:none">
            <div class="ws-tasks-list" id="wsTasksList">
              <div class="ws-tasks-empty">Завантаження…</div>
            </div>
            <div class="ws-task-quick-add" id="wsTaskQuickAdd">
              <input type="text" id="wsTaskTitle" placeholder="Нова задача для цього контрагента…"
                     onkeydown="if(event.key==='Enter')WS.addTask()">
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
                <button class="ws-task-add-btn" id="wsTaskAddBtn" onclick="WS.addTask()">+ Додати</button>
              </div>
            </div>
          </div>
          <div class="ws-takeover" id="wsTakeover" style="display:none">
            <span class="ws-takeover-txt">⚡ AI обробляє запит. Хочете відповісти самі?</span>
            <button class="ws-takeover-btn" onclick="WS.toggleAi()">Взяти діалог</button>
          </div>
          <div class="ws-ai-draft" id="wsAiDraft">
            <div class="ws-ai-draft-ic">✨ AI підказка</div>
            <div class="ws-ai-draft-txt" id="wsAiDraftTxt"></div>
            <div class="ws-ai-draft-btns">
              <button class="ws-ai-use" onclick="WS.useAiDraft()">Вставити</button>
              <button class="ws-ai-skip" onclick="WS.hideAiDraft()">Відхилити</button>
            </div>
          </div>
          <div class="ws-input-area">
            <!-- File attachment preview -->
            <div class="ws-attach-preview" id="wsAttachPreview">
              <div id="wsAttachThumbWrap"></div>
              <div class="ws-attach-info">
                <div class="ws-attach-name" id="wsAttachName"></div>
                <div class="ws-attach-size" id="wsAttachSize"></div>
              </div>
              <button class="ws-attach-remove" onclick="WS.removeAttach()" title="Видалити">×</button>
            </div>
            <!-- Hidden file input -->
            <input type="file" id="wsFileInput" style="display:none"
                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                   onchange="WS.onFileSelected(this)">
            <div class="ws-input-toolbar" style="position:relative">
              <!-- Emoji picker -->
              <div class="ws-emoji-picker" id="wsEmojiPicker">
                <div class="ws-emoji-grid" id="wsEmojiGrid"></div>
              </div>
              <button class="ws-t-btn" title="Емодзі" id="wsEmojiBtn" onclick="WS.toggleEmoji(event)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
              </button>
              <button class="ws-t-btn" title="Прикріпити файл або фото" onclick="WS.openFilePicker()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
              </button>
              <!-- Templates dropdown -->
              <div class="ws-tpl-picker" id="wsTplPicker">
                <div class="ws-tpl-head">
                  <span>Шаблони</span>
                  <button class="ws-tpl-manage" onclick="WS.openTplManager()">Управляти →</button>
                </div>
                <div class="ws-tpl-list" id="wsTplList"></div>
              </div>
              <button class="ws-t-btn" id="wsTplBtn" title="Шаблони" onclick="WS.toggleTemplates(event)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
              </button>
              <button class="ws-t-btn" title="AI підказка" onclick="WS.requestAiSuggest()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              </button>
              <span class="ws-t-hint" id="wsInputHint"></span>
            </div>
            <!-- Reply strip (hidden by default) -->
            <div class="ws-reply-strip" id="wsReplyStrip" style="display:none">
              <div class="ws-reply-strip-bar"></div>
              <div class="ws-reply-strip-text"></div>
              <button type="button" class="ws-reply-strip-close" onclick="ChatHub.cancelReply()" title="Скасувати">&#x2715;</button>
            </div>
            <textarea class="ws-textarea" id="wsMsgInput" placeholder="Написати повідомлення…" rows="2" spellcheck="false"></textarea>
            <div class="ws-input-row">
              <span class="ws-char-c" id="wsCharC"></span>
              <button class="ws-remind-btn" id="wsRemindBtn" onclick="WS.openReminder()" title="Нагадати пізніше">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Нагадати
              </button>
              <button class="ws-send-btn" id="wsSendBtn" onclick="WS.sendMessage()">
                Надіслати
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Internal team chat pane (cp context) -->
        <div class="ws-tab-pane" id="wsPaneInternal" style="display:none;flex-direction:column;flex:1;min-height:0;overflow:hidden">
          <div class="tc-title-bar">
            <span class="tc-title">💬 Команда про цього клієнта</span>
          </div>
          <div class="tc-msgs" id="wsTcMsgs">
            <div class="tc-empty">Завантаження…</div>
          </div>
          <div class="tc-fwd-strip" id="wsTcFwdStrip" style="display:none">
            <div class="tc-fwd-strip-bar"></div>
            <div class="tc-fwd-strip-text"></div>
            <button type="button" class="tc-fwd-strip-close" onclick="WsCpChat.clearFwd()" title="Скасувати">&#x2715;</button>
          </div>
          <div class="tc-input-area">
            <textarea class="tc-input" id="wsTcInput" placeholder="Повідомлення команді…" rows="2"></textarea>
            <div class="tc-input-row">
              <button class="ws-send-btn" id="wsTcSendBtn" onclick="WsCpChat.send()">
                Надіслати
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </div>
          </div>
        </div>

      </div><!-- /ws-hub-inner -->
    </div><!-- /ws-hub -->

    <!-- ══ RIGHT: CONTEXT ════════════════════════════════════════════════════ -->
    <div class="ws-ctx" id="wsCtx">
      <div class="ws-ctx-empty">Оберіть контакт зі списку зліва</div>
    </div>

  </div>
</div>

<!-- ══ Modal: new counterparty ═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="wsNewModal" style="display:none" onclick="if(event.target===this)WS.closeNew()">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-head">
      <span>Новий контрагент</span>
      <button class="modal-close" onclick="WS.closeNew()">×</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <label>Тип</label>
        <select id="wsNewType" style="width:100%">
          <option value="company">Юрлицо / ТОВ / АТ</option>
          <option value="fop">ФОП</option>
          <option value="person" selected>Фізична особа</option>
        </select>
      </div>
      <div class="form-row">
        <label>Назва / Ім'я <span style="color:#ef4444">*</span></label>
        <input type="text" id="wsNewName" style="width:100%" placeholder="ТОВ Альфа або Іванов Іван">
      </div>
      <div class="form-row">
        <label>Телефон</label>
        <input type="text" id="wsNewPhone" style="width:100%" placeholder="+38 067 123 45 67">
      </div>
      <div class="form-row">
        <label>Email</label>
        <input type="text" id="wsNewEmail" style="width:100%" placeholder="name@company.com">
      </div>
      <div class="modal-error" id="wsNewErr" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="WS.closeNew()">Скасувати</button>
      <button class="btn btn-primary" onclick="WS.createNew()">Створити</button>
    </div>
  </div>
</div>

<!-- ══ Modal: merge conflict resolution ══════════════════════════════════════ -->
<div class="modal-overlay" id="wsMergeModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-head">
      <span>Злиття з контрагентом</span>
      <button class="modal-close" onclick="document.getElementById('wsMergeModal').style.display='none'">×</button>
    </div>
    <div class="modal-body" id="wsMergeBody"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="document.getElementById('wsMergeModal').style.display='none'">Скасувати</button>
      <button class="btn btn-primary" onclick="WS.doMergeWithResolutions()">Прив'язати</button>
    </div>
  </div>
</div>

<!-- ══ Modal: create from lead ═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="wsLeadCreateModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-head">
      <span>Створити контрагента з ліда</span>
      <button class="modal-close" onclick="document.getElementById('wsLeadCreateModal').style.display='none'">×</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <label>Тип</label>
        <select id="wsLcType" style="width:100%">
          <option value="person" selected>Фізична особа</option>
          <option value="company">Юрлицо</option>
          <option value="fop">ФОП</option>
        </select>
      </div>
      <div class="form-row">
        <label>Назва / Ім'я <span style="color:#ef4444">*</span></label>
        <input type="text" id="wsLcName" style="width:100%" placeholder="Ім'я або назва компанії">
      </div>
      <div class="modal-error" id="wsLcErr" style="display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="document.getElementById('wsLeadCreateModal').style.display='none'">Скасувати</button>
      <button class="btn btn-primary" onclick="WS.doCreateFromLead()">Створити і прив'язати</button>
    </div>
  </div>
</div>

<!-- ══ Modal: template manager ════════════════════════════════════════════════ -->
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

<div class="modal-overlay" id="wsTplModal" style="display:none" onclick="if(event.target===this)WS.closeTplManager()">
  <div class="modal-box" style="max-width:500px">
    <div class="modal-head">
      <span>Шаблони повідомлень</span>
      <button class="modal-close" onclick="WS.closeTplManager()">×</button>
    </div>
    <div class="modal-body" style="max-height:70vh;overflow-y:auto">
      <div id="wsTmList" class="ws-tm-list"></div>
      <div class="ws-tm-form" id="wsTmForm">
        <div class="ws-tm-form-title" id="wsTmFormTitle">Новий шаблон</div>
        <input type="hidden" id="wsTmId" value="0">
        <div class="form-row">
          <label>Назва <span style="color:#ef4444">*</span></label>
          <input type="text" id="wsTmTitle" style="width:100%" placeholder="Короткий опис шаблону">
        </div>
        <div class="form-row">
          <label>Текст <span style="color:#ef4444">*</span></label>
          <textarea id="wsTmBody" rows="4" style="width:100%;resize:vertical" placeholder="Текст повідомлення…"></textarea>
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
          <button class="btn btn-ghost btn-sm" onclick="WS.cancelTmEdit()">Скасувати</button>
          <button class="btn btn-primary btn-sm" onclick="WS.saveTmTemplate()">Зберегти</button>
        </div>
      </div>
      <button class="btn btn-ghost btn-sm" id="wsTmAddBtn" onclick="WS.newTmTemplate()" style="margin-top:4px">+ Новий шаблон</button>
    </div>
  </div>
</div>

<script src="/modules/shared/chip-search.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chip-search.js'); ?>"></script>
<script src="/modules/shared/chat-hub.js?v=<?php echo filemtime(__DIR__ . '/../../shared/chat-hub.js'); ?>"></script>
<script src="/modules/shared/share-order.js?v=<?php echo filemtime(__DIR__ . '/../../shared/share-order.js'); ?>"></script>
<script>
var WS = {

  // ── State ──────────────────────────────────────────────────────────────────
  mode:           'chat',
  kind:           null,    // 'counterparty' | 'lead'
  itemId:         null,
  activeChatCpId: null,    // switches when contact switcher is clicked
  activeTab:      'chat',
  activeCh:       'viber',
  aiMode:         {},      // {key: bool}  key = 'cp_5' or 'lead_3'
  inboxData:      null,    // raw {leads, counterparties}
  cpPage:         1,       // current inbox page
  CP_PER_PAGE:    40,
  pollTimer:      null,
  inboxTimer:     null,
  currentCp:      null,
  currentLead:    null,
  _firstRender:   true,     // auto-select top item on first inbox load

  // ── Init ───────────────────────────────────────────────────────────────────
  init: function() {
    var self = this;

    // Auto-select counterparty from ?select=ID (back link from view.php)
    var urlParams = new URLSearchParams(window.location.search);
    var autoSelect = parseInt(urlParams.get('select') || '0', 10);
    if (autoSelect > 0) {
      this.kind         = 'counterparty';
      this.itemId       = autoSelect;
      this._autoSelect  = autoSelect;
      this._firstRender = false;  // explicit selection — don't override with auto-first
      history.replaceState(null, '', '/counterparties');
      // Fire detail fetch in parallel with inbox — don't wait for inbox to render
      this._autoSelectPromise = fetch('/counterparties/api/get_counterparty_detail?id=' + autoSelect)
        .then(function(r){ return r.json(); });
    }

    this.loadInbox();

    // Mode tabs
    document.querySelectorAll('.ws-mode-tab').forEach(function(btn) {
      btn.addEventListener('click', function() {
        document.querySelectorAll('.ws-mode-tab').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        self.mode = btn.dataset.mode;
        self.renderInbox();
      });
    });

    // Hub tabs
    document.querySelectorAll('.ws-hub-tab').forEach(function(btn) {
      btn.addEventListener('click', function() {
        document.querySelectorAll('.ws-hub-tab').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        self.switchTab(btn.dataset.tab);
      });
    });

    // Channel tabs — управляет ChatHub (он же синхронизирует activeCh)
    ChatHub.bindChannelTabs();

    // Search
    var searchInp   = document.getElementById('wsSearch');
    var searchClear = document.getElementById('wsSearchClear');
    var searchTimer;
    searchInp.addEventListener('input', function() {
      searchClear.classList.toggle('hidden', searchInp.value === '');
      clearTimeout(searchTimer);
      var delay = searchInp.value.trim() === '' ? 0 : 300;
      searchTimer = setTimeout(function() {
        self.cpPage = 1;
        var q = searchInp.value.trim();
        if (q === '') {
          self.inboxData = self.inboxDataBase || self.inboxData;
          self.renderInbox();
        } else {
          self.searchInbox(q);
        }
      }, delay);
    });
    searchClear.addEventListener('click', function() {
      searchInp.value = '';
      searchClear.classList.add('hidden');
      self.cpPage = 1;
      self.inboxData = self.inboxDataBase || self.inboxData;
      self.renderInbox();
      searchInp.focus();
    });

    // Textarea: char counter + '/' command menu (Enter→send handled by ChatHub)
    var inp = document.getElementById('wsMsgInput');
    inp.addEventListener('input', function() {
      var len = inp.value.length;
      document.getElementById('wsCharC').textContent = len > 0 ? len + ' симв.' : '';
    });
    inp.addEventListener('keydown', function(e) {
      // Telegram-style / command menu
      if (e.key === '/' && inp.value === '' && self._flowData) {
        e.preventDefault();
        self._showCmdMenu(inp, self._flowData);
      }
    });

    // New btn
    document.getElementById('wsBtnNew').addEventListener('click', function() {
      self.openNew();
    });

    // ChatHub: инициализация чат-модуля
    ChatHub.init({
      onAfterRender: function() { self.loadInbox(); },
      onTaskChanged: function() { self.refreshInboxCard(); }
    });
  },

  // ── Inbox ──────────────────────────────────────────────────────────────────
  loadInbox: function() {
    var self = this;
    this.cpPage = 1;
    fetch('/counterparties/api/get_inbox?mode=' + this.mode)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          self.inboxDataBase = d; // кеш top-300 для восстановления после поиска
          var q = document.getElementById('wsSearch') ? document.getElementById('wsSearch').value.trim() : '';
          if (q) {
            // Активний пошук — не перебиваємо поточні результати, тихо оновлюємо базу
            return;
          }
          self.inboxData = d;
          self.renderInbox();
        }
      });
  },

  searchInbox: function(q) {
    var self = this;
    var body = document.getElementById('wsInboxBody');
    body.innerHTML = '<div class="ws-inbox-loading">Пошук…</div>';
    fetch('/counterparties/api/get_inbox?mode=' + this.mode + '&q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          // не перезаписываем inboxDataBase — только активный набор
          self.inboxData = d;
          self.renderInbox();
        }
      });
  },

  renderInbox: function() {
    if (!this.inboxData) return;
    this.updateModeBadges();
    if (this.mode === 'orders') {
      this.renderInboxOrders();
    } else {
      this.renderInboxChat();
    }
  },

  updateModeBadges: function() {
    if (!this.inboxData) return;
    // Chat badge: total unread messages
    var unread = 0;
    this.inboxData.leads.forEach(function(l) { unread += (l.unread_count || 0); });
    this.inboxData.counterparties.forEach(function(c) { unread += (c.unread_count || 0); });
    var chatBadge = document.getElementById('wsModeBadgeChat');
    if (chatBadge) {
      if (unread > 0) { chatBadge.textContent = unread > 99 ? '99+' : unread; chatBadge.classList.add('visible'); }
      else { chatBadge.textContent = ''; chatBadge.classList.remove('visible'); }
    }
    // Orders badge: CPs with 'new' order status (leads excluded — they only appear in chat)
    var newCount = 0;
    this.inboxData.counterparties.forEach(function(c) {
      if (c.last_order_status === 'new') newCount++;
    });
    var ordBadge = document.getElementById('wsModeBadgeOrders');
    if (ordBadge) {
      if (newCount > 0) { ordBadge.textContent = newCount > 99 ? '99+' : newCount; ordBadge.classList.add('visible'); }
      else { ordBadge.textContent = ''; ordBadge.classList.remove('visible'); }
    }
  },

  // ── Chat mode: leads + flat CP list sorted by last message ─────────────────
  renderInboxChat: function() {
    var self  = this;
    var query = document.getElementById('wsSearch').value.trim().toLowerCase();
    var leads = this.inboxData.leads;
    var cps   = this.inboxData.counterparties;

    // Leads фільтруємо клієнтськи (їх мало); CPs вже відфільтровані сервером
    if (query) {
      leads = leads.filter(function(l) {
        return (l.display_name && l.display_name.toLowerCase().indexOf(query) !== -1)
            || (l.phone && l.phone.indexOf(query) !== -1)
            || (l.email && l.email.toLowerCase().indexOf(query) !== -1);
      });
    }

    // CPs with messages OR open tasks
    var chatCps = cps.filter(function(c) { return c.last_msg_body !== null || c.open_task_count > 0; });
    // Sort: message urgency (ball model) → task decay → last activity
    chatCps.sort(function(a, b) {
      var ma = self.computeMessageScore(a), mb = self.computeMessageScore(b);
      if (mb !== ma) return mb - ma;
      var ta = self.computeTaskScore(a),    tb = self.computeTaskScore(b);
      if (tb !== ta) return tb - ta;
      var at = a.last_msg_at ? new Date(a.last_msg_at).getTime() : 0;
      var bt = b.last_msg_at ? new Date(b.last_msg_at).getTime() : 0;
      return bt - at;
    });

    var html = '';

    if (leads.length > 0) {
      html += '<div class="ws-tier urgent"><span class="ws-tier-dot"></span>Нові звернення (' + leads.length + ')</div>';
      leads.forEach(function(l) { html += self.renderLeadCard(l); });
    }

    if (chatCps.length > 0) {
      var total      = chatCps.length;
      var totalPages = Math.max(1, Math.ceil(total / self.CP_PER_PAGE));
      if (self.cpPage > totalPages) self.cpPage = totalPages;
      var start   = (self.cpPage - 1) * self.CP_PER_PAGE;
      var pageCps = chatCps.slice(start, start + self.CP_PER_PAGE);
      html += '<div class="ws-tier active"><span class="ws-tier-dot"></span>Розмови (' + total + ')</div>';
      pageCps.forEach(function(c) { html += self.renderCpCard(c); });
      self.renderPager(totalPages);
    } else {
      document.getElementById('wsInboxPager').style.display = 'none';
    }

    if (!leads.length && !chatCps.length) {
      html = '<div class="ws-no-items">Нічого не знайдено</div>';
    }

    var inboxEl = document.getElementById('wsInboxBody');
    inboxEl.innerHTML = html;
    self.bindInboxClicks(inboxEl);
    self.restoreSelection(inboxEl);
  },

  // ── Orders mode: CPs with real orders, tiered by status (leads excluded) ───
  renderInboxOrders: function() {
    var self  = this;
    var query = document.getElementById('wsSearch').value.trim().toLowerCase();
    var cps   = this.inboxData.counterparties;

    // Only CPs with real orders (exclude draft and null)
    var orderCps = cps.filter(function(c) {
      if (query) {
        var match = (c.name && c.name.toLowerCase().indexOf(query) !== -1)
                 || (c.phone && c.phone.indexOf(query) !== -1)
                 || (c.email && c.email.toLowerCase().indexOf(query) !== -1);
        if (!match) return false;
      }
      return c.last_order_status && c.last_order_status !== 'draft';
    });

    // Sort within each tier: decay score DESC then last_activity DESC
    var tierSort = function(arr) {
      return arr.slice().sort(function(a, b) {
        var sa = self.computeTaskScore(a), sb = self.computeTaskScore(b);
        if (sb !== sa) return sb - sa;
        var at = a.last_msg_at ? new Date(a.last_msg_at).getTime() : 0;
        var bt = b.last_msg_at ? new Date(b.last_msg_at).getTime() : 0;
        return bt - at;
      });
    };

    var newCps     = tierSort(orderCps.filter(function(c) { return c.last_order_status === 'new'; }));
    var waitingCps = tierSort(orderCps.filter(function(c) { return c.last_order_status === 'waiting_payment'; }));
    var workingCps = tierSort(orderCps.filter(function(c) { return ['confirmed','in_progress'].indexOf(c.last_order_status) !== -1; }));
    var doneCps    = tierSort(orderCps.filter(function(c) { return ['completed','cancelled'].indexOf(c.last_order_status) !== -1; }));

    var html = '';

    if (newCps.length > 0) {
      html += '<div class="ws-tier attention"><span class="ws-tier-dot"></span>Нові замовлення (' + newCps.length + ')</div>';
      newCps.forEach(function(c) { html += self.renderCpCardOrder(c); });
    }
    if (waitingCps.length > 0) {
      html += '<div class="ws-tier attention" style="--tier-dot-color:#f59e0b"><span class="ws-tier-dot" style="background:#f59e0b"></span>Очікують оплати (' + waitingCps.length + ')</div>';
      waitingCps.forEach(function(c) { html += self.renderCpCardOrder(c); });
    }
    if (workingCps.length > 0) {
      html += '<div class="ws-tier active"><span class="ws-tier-dot"></span>В роботі (' + workingCps.length + ')</div>';
      workingCps.forEach(function(c) { html += self.renderCpCardOrder(c); });
    }
    if (doneCps.length > 0) {
      html += '<div class="ws-tier processed"><span class="ws-tier-dot"></span>Завершені (' + doneCps.length + ')</div>';
      doneCps.forEach(function(c) { html += self.renderCpCardOrder(c); });
    }

    if (!orderCps.length) {
      html = '<div class="ws-no-items">Замовлень не знайдено</div>';
    }

    document.getElementById('wsInboxPager').style.display = 'none';
    var inboxEl = document.getElementById('wsInboxBody');
    inboxEl.innerHTML = html;
    self.bindInboxClicks(inboxEl);
    self.restoreSelection(inboxEl);
  },

  // ── Shared inbox helpers ───────────────────────────────────────────────────
  renderPager: function(totalPages) {
    var self    = this;
    var pagerEl = document.getElementById('wsInboxPager');
    if (totalPages > 1) {
      pagerEl.innerHTML =
        '<button class="ws-pager-btn" id="wsPrevPage"' + (self.cpPage <= 1 ? ' disabled' : '') + '>&#8249;</button>' +
        '<span class="ws-pager-info">' + self.cpPage + '\u00a0/\u00a0' + totalPages + '</span>' +
        '<button class="ws-pager-btn" id="wsNextPage"' + (self.cpPage >= totalPages ? ' disabled' : '') + '>&#8250;</button>';
      pagerEl.style.display = 'flex';
      var prevBtn = document.getElementById('wsPrevPage');
      var nextBtn = document.getElementById('wsNextPage');
      if (prevBtn) prevBtn.addEventListener('click', function() { self.cpPage--; self.renderInbox(); });
      if (nextBtn) nextBtn.addEventListener('click', function() { self.cpPage++; self.renderInbox(); });
    } else {
      pagerEl.style.display = 'none';
      pagerEl.innerHTML = '';
    }
  },

  bindInboxClicks: function(inboxEl) {
    var self = this;
    inboxEl.querySelectorAll('.ws-card[data-kind]').forEach(function(card) {
      card.addEventListener('click', function() {
        self.selectItem(card.dataset.kind, parseInt(card.dataset.id), card.dataset.channel || null);
      });
    });
  },

  restoreSelection: function(inboxEl) {
    // Auto-select first card on initial page load (only once, no existing selection)
    if (!this.kind && this._firstRender) {
      this._firstRender = false;
      var first = inboxEl.querySelector('.ws-card[data-kind]');
      if (first) {
        first.click();
      }
      return;
    }
    if (this.kind && this.itemId) {
      var sel = inboxEl.querySelector('.ws-card[data-kind="'+this.kind+'"][data-id="'+this.itemId+'"]');
      if (sel) {
        sel.classList.add('selected');
        if (this._autoSelect) {
          var self = this;
          var id   = this._autoSelect;
          sel.scrollIntoView({ block: 'center' });
          this._autoSelect = 0;
          // Show hub panel immediately
          document.getElementById('wsEmpty').style.display = 'none';
          document.getElementById('wsHubInner').style.display = 'flex';
          // Use already-running parallel fetch if available
          var p = this._autoSelectPromise || fetch('/counterparties/api/get_counterparty_detail?id=' + id).then(function(r){ return r.json(); });
          this._autoSelectPromise = null;
          p.then(function(d) {
            if (!d.ok) return;
            self.currentCp      = d;
            self.currentLead    = null;
            self.activeChatCpId = self.itemId;
            ChatHub.setContext(self.itemId, 'counterparty', self.itemId, d.cp.name);
            self.renderHubHeader(d.cp.initials, d.cp.name, d.cp.type, null, d.stats, d.cp.id);
            self.renderContactSwitcher(d.cp.id, d.contacts || []);
            self.updateChannelTabs(d.cp.available_channels || ['note']);
            self.renderCpCtx(d);
            self.applyAiMode();
            ChatHub.updateChannelDots(d.unread_by_channel || {});
            ChatHub.loadMessages();
            self.startPolling();
          });
        }
      }
    }
  },

  renderLeadCard: function(l) {
    var name = l.display_name || l.source_label;
    var initials = this.initials(name);
    var msgTxt = l.last_msg_body ? this.truncate(l.last_msg_body, 40) : '— немає повідомлень —';
    var timeAgo = l.last_msg_at ? this.timeAgo(l.last_msg_at) : this.timeAgo(l.created_at);
    var badge = l.unread_count > 0 ? '<span class="ws-unread-badge">' + l.unread_count + '</span>' : '';
    var urgDot = this.renderUrgencyDot(l.unread_count > 0 ? 'critical' : 'high');
    var sel = (this.kind === 'lead' && this.itemId === l.id) ? ' selected' : '';
    return '<div class="ws-card' + sel + '" data-kind="lead" data-id="' + l.id + '" data-channel="' + (l.last_msg_channel || l.source || 'viber') + '">'
      + '<span class="ws-urgent-bar"></span>'
      + '<div class="ws-card-av lead">?</div>'
      + '<div class="ws-card-body">'
      + '<div class="ws-card-row1">' + urgDot + '<span class="ws-card-name">' + this.esc(name) + '</span><span class="ws-card-time">' + timeAgo + '</span></div>'
      + '<div class="ws-card-sub">' + this.esc(l.source_label) + (l.phone ? ' · ' + this.esc(l.phone) : '') + '</div>'
      + '<div class="ws-card-msg' + (l.unread_count > 0 ? ' unread' : '') + '">' + this.esc(msgTxt) + '</div>'
      + '</div>' + badge
      + '</div>';
  },

  renderCpCard: function(c) {
    var initials = this.initials(c.name);
    var avClass  = 'ws-card-av ' + (c.type || 'person');
    // Chat mode: no order noise — phone only
    var sub      = c.phone || '';
    var msgTxt   = c.last_msg_body ? this.truncate(c.last_msg_body, 45) : '';
    var timeAgo  = c.last_msg_at ? this.timeAgo(c.last_msg_at) : '';
    var badge    = c.unread_count > 0 ? '<span class="ws-unread-badge">' + c.unread_count + '</span>' : '';
    var taskBadge = this.renderTaskBadge(c);
    var urgDot   = this.renderUrgencyDot(this.getMsgState(c));
    var sel      = (this.kind === 'counterparty' && this.itemId === c.id) ? ' selected' : '';
    return '<div class="ws-card' + sel + '" data-kind="counterparty" data-id="' + c.id + '" data-channel="' + (c.last_msg_channel || 'viber') + '">'
      + '<div class="' + avClass + '">' + this.esc(initials) + '</div>'
      + '<div class="ws-card-body">'
      + '<div class="ws-card-row1">' + urgDot + '<span class="ws-card-name">' + this.esc(c.name) + '</span><span class="ws-card-time">' + timeAgo + '</span></div>'
      + (sub ? '<div class="ws-card-sub">' + this.esc(sub) + '</div>' : '')
      + (msgTxt ? '<div class="ws-card-msg' + (c.unread_count > 0 ? ' unread' : '') + '">' + this.esc(msgTxt) + '</div>' : '')
      + (taskBadge ? '<div style="margin-top:3px">' + taskBadge + '</div>' : '')
      + '</div>' + badge
      + '</div>';
  },

  // Urgency state: ball is with us (critical/high/medium) or client (low)
  getMsgState: function(c) {
    if (!c.last_msg_body) return null;
    if (c.last_msg_dir === 'in') {
      if (c.unread_count > 0) {
        var waitMin = c.last_msg_at ? (Date.now() - new Date(c.last_msg_at).getTime()) / 60000 : 0;
        return waitMin > 30 ? 'critical' : 'high';
      }
      return 'medium';  // read but no reply yet
    }
    return 'low';  // ball is with client
  },

  renderUrgencyDot: function(state) {
    if (!state) return '';
    var cls = { critical: 'ws-urg-critical', high: 'ws-urg-high', medium: 'ws-urg-medium', low: 'ws-urg-low' }[state] || '';
    return '<span class="ws-urg-dot ' + cls + '"></span>';
  },

  // Score for messages tab sorting — clear tiers:
  //   Unread incoming  1000-2000  (always above read)
  //   Read-but-unanswered 100-500
  //   Outgoing             5
  computeMessageScore: function(c) {
    if (!c.last_msg_body || !c.last_msg_at) return 0;
    if (c.last_msg_dir === 'in') {
      var waitMin = (Date.now() - new Date(c.last_msg_at).getTime()) / 60000;
      var chCoeff = { viber: 1.2, sms: 1.0, email: 0.8, telegram: 1.1 }[c.last_msg_channel] || 1.0;
      if (c.unread_count > 0) {
        var timeBonus = Math.floor(waitMin / 15) * 5;
        return 1000 + Math.min(1000, timeBonus * chCoeff);
      }
      var timeBonus = Math.floor(waitMin / 15) * 2;
      return Math.min(500, (100 + timeBonus) * chCoeff);
    }
    return 5;  // ball is with client
  },

  renderTaskBadge: function(c) {
    if (!c.open_task_count) return '';
    var isOverdue = c.next_task_due_at && new Date(c.next_task_due_at).getTime() < Date.now();
    var cls  = 'ws-task-badge' + (isOverdue ? ' overdue' : '');
    var icon = isOverdue ? '🔴' : '✅';
    return '<span class="' + cls + '">' + icon + '\u00a0' + c.open_task_count + (c.open_task_count === 1 ? '\u00a0задача' : '\u00a0задачі') + '</span>';
  },

  renderCpCardOrder: function(c) {
    var initials   = this.initials(c.name);
    var avClass    = 'ws-card-av ' + (c.type || 'person');
    var sum        = c.last_order_sum ? Math.round(c.last_order_sum).toLocaleString('uk') + '\u00a0₴' : '';
    var taskBadge  = this.renderTaskBadge(c);
    var urgState   = this.computeTaskScore(c) > 80 ? 'critical'
                   : this.computeTaskScore(c) > 40 ? 'high'
                   : this.computeTaskScore(c) > 10 ? 'medium'
                   : null;
    var urgDot     = this.renderUrgencyDot(urgState);
    var sel = (this.kind === 'counterparty' && this.itemId === c.id) ? ' selected' : '';
    return '<div class="ws-card' + sel + '" data-kind="counterparty" data-id="' + c.id + '" data-channel="' + (c.last_msg_channel || 'viber') + '">'
      + '<div class="' + avClass + '">' + this.esc(initials) + '</div>'
      + '<div class="ws-card-body">'
      + '<div class="ws-card-row1">'
      + '<span class="ws-card-name">' + this.esc(c.name) + '</span>'
      + (sum ? '<span class="ws-card-time" style="color:#7c3aed;font-weight:700">' + this.esc(sum) + '</span>' : '')
      + '</div>'
      + '<div style="display:flex;gap:5px;align-items:center;margin-top:3px">'
      + urgDot
      + taskBadge
      + '</div>'
      + '</div>'
      + '</div>';
  },

  // ── Select item ────────────────────────────────────────────────────────────
  selectItem: function(kind, id, channel) {
    this.stopPolling();
    // Restore previous item to AI mode before switching
    if (this.kind && this.itemId) {
      this.aiMode[this.aiKey()] = true;
    }
    this.kind   = kind;
    this.itemId = id;
    // Opening a chat → always manual so operator can respond immediately
    this.aiMode[this.aiKey()] = false;

    // Highlight
    document.querySelectorAll('.ws-card').forEach(function(c){ c.classList.remove('selected'); });
    var sel = document.querySelector('.ws-card[data-kind="'+kind+'"][data-id="'+id+'"]');
    if (sel) sel.classList.add('selected');

    // Switch to channel of last message (or keep current if not specified)
    var validChannels = ['viber', 'sms', 'email', 'telegram', 'tasks'];
    if (channel && validChannels.indexOf(channel) !== -1) {
      this.activeCh = channel;
      ChatHub.activeCh = channel;
      document.querySelectorAll('.ws-ch-tab').forEach(function(b){ b.classList.remove('active'); });
      var chBtn = document.querySelector('.ws-ch-tab[data-ch="' + channel + '"]');
      if (chBtn) chBtn.classList.add('active');
    }

    // Reset hub
    document.getElementById('wsEmpty').style.display    = 'none';
    var inner = document.getElementById('wsHubInner');
    inner.style.display = 'flex';

    // Reset tab to chat
    this.switchTab('chat', true);
    document.querySelectorAll('.ws-hub-tab').forEach(function(b){ b.classList.remove('active'); });
    document.querySelector('.ws-hub-tab[data-tab="chat"]').classList.add('active');

    if (kind === 'lead') {
      this.loadLeadDetail(id);
    } else {
      this.loadCpDetail(id);
    }
  },

  // ── Load counterparty ──────────────────────────────────────────────────────
  loadCpDetail: function(id) {
    var self = this;
    fetch('/counterparties/api/get_counterparty_detail?id=' + id)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        self.currentCp      = d;
        self.currentLead    = null;
        self.activeChatCpId = self.itemId; // reset to company on new selection
        ChatHub.setContext(self.itemId, 'counterparty', self.itemId, d.cp.name);
        self.renderHubHeader(d.cp.initials, d.cp.name, d.cp.type, null, d.stats, d.cp.id);
        self.renderContactSwitcher(d.cp.id, d.contacts || []);
        self.updateChannelTabs(d.cp.available_channels || ['note']);
        self.renderCpCtx(d);
        self.applyAiMode();
        ChatHub.updateChannelDots(d.unread_by_channel || {});
        ChatHub.loadMessages();
        self.startPolling();
      });
  },

  // ── Channel tab availability ───────────────────────────────────────────────
  updateChannelTabs: function(availableChannels) { ChatHub.updateChannelTabs(availableChannels); },

  // ── Contact switcher ──────────────────────────────────────────────────────
  renderContactSwitcher: function(cpId, contacts) {
    var self = this;
    var el = document.getElementById('wsContactSwitcher');
    if (!el) return;
    if (!contacts || contacts.length === 0) {
      el.style.display = 'none';
      el.innerHTML = '';
      return;
    }
    var cpName = (this.currentCp && this.currentCp.cp) ? this.currentCp.cp.name : '';
    var cpChannels = (this.currentCp && this.currentCp.cp && this.currentCp.cp.available_channels)
      ? this.currentCp.cp.available_channels : ['note'];

    var html = '<button class="ws-cs-btn active" data-cs-id="' + cpId + '">'
      + '🏢 ' + this.esc(cpName.length > 22 ? cpName.substring(0, 22) + '…' : cpName)
      + '</button>';
    contacts.forEach(function(ct) {
      var n = ct.name.length > 18 ? ct.name.substring(0, 18) + '…' : ct.name;
      html += '<button class="ws-cs-btn" data-cs-id="' + ct.id + '" title="' + self.esc(ct.name) + '">'
        + '👤 ' + self.esc(n) + '</button>';
    });
    el.innerHTML = html;
    el.style.display = 'flex';

    // Store channels per contact for quick access on switch
    var channelMap = {};
    channelMap[cpId] = cpChannels;
    contacts.forEach(function(ct) {
      channelMap[ct.id] = ct.available_channels || ['note'];
    });

    el.querySelectorAll('.ws-cs-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var newId = parseInt(this.dataset.csId, 10);
        if (newId === self.activeChatCpId) return;
        self.activeChatCpId = newId;
        ChatHub.activeChatCpId = newId;
        el.querySelectorAll('.ws-cs-btn').forEach(function(b){ b.classList.remove('active'); });
        this.classList.add('active');
        self.updateChannelTabs(channelMap[newId] || ['note']);
        ChatHub.loadMessages();
        if (WS._teamChatInited) WsCpChat.setCp(newId);
      });
    });
  },

  // ── Load lead ──────────────────────────────────────────────────────────────
  loadLeadDetail: function(id) {
    var self = this;
    fetch('/counterparties/api/get_lead_detail?id=' + id)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        self.currentLead    = d;
        self.currentCp      = null;
        self.activeChatCpId = null;
        ChatHub.setContext(id, 'lead', null);
        var switcher = document.getElementById('wsContactSwitcher');
        if (switcher) { switcher.style.display = 'none'; switcher.innerHTML = ''; }
        self.updateChannelTabs(['viber','sms','email','telegram','note']);
        var lead = d.lead;
        self.renderHubHeader(lead.initials, lead.name, 'lead', lead.source_label);
        self.renderLeadCtx(d);
        // Hide AI banner for leads
        document.getElementById('wsModeBanner').style.display = 'none';
        document.getElementById('wsTakeover').style.display   = 'none';
        ChatHub.updateChannelDots(d.unread_by_channel || {});
        ChatHub.loadMessages();
        self.startPolling();
      });
  },

  // ── Hub header ─────────────────────────────────────────────────────────────
  renderHubHeader: function(initials, name, type, sub, stats, cpId) {
    document.getElementById('wsHubAv').textContent  = initials;
    document.getElementById('wsHubAv').className    = 'ws-hub-av ' + type;
    document.getElementById('wsHubName').textContent = name;

    var nameRow = document.getElementById('wsHubName').parentNode;
    var oldLink = nameRow.querySelector('.ws-hub-card-link');
    if (oldLink) oldLink.parentNode.removeChild(oldLink);
    if (cpId) {
      var a = document.createElement('a');
      a.href = '/counterparties/view?id=' + cpId;
      a.target = '_blank';
      a.className = 'ws-hub-card-link';
      a.title = 'Відкрити картку контрагента';
      a.innerHTML = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M7 3H3a1 1 0 00-1 1v9a1 1 0 001 1h9a1 1 0 001-1V9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M10 2h4v4M14 2L8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      nameRow.appendChild(a);
    }

    // Stats chip under name (only for counterparties)
    var subEl = document.getElementById('wsHubSub');
    if (stats && stats.order_count > 0) {
      subEl.innerHTML = '<span class="ws-hub-stats"><strong>' + stats.order_count + '</strong> зам. / <strong>₴' + this.formatNum(stats.ltv) + '</strong></span>';
    } else {
      subEl.textContent = sub || '';
    }

    document.getElementById('wsHubActs').innerHTML = '';
  },

  // ── Counterparty context panel ─────────────────────────────────────────────
  renderCpCtx: function(d) {
    var self = this;
    var cp   = d.cp;
    self._activeCp = cp || null;
    var ord  = d.active_order;

    // Contacts one-liner
    var contacts = [];
    if (cp.phone) contacts.push('<a href="tel:' + this.esc(cp.phone) + '">' + this.esc(cp.phone) + '</a>');
    if (cp.email) contacts.push('<a href="mailto:' + this.esc(cp.email) + '">' + this.esc(cp.email) + '</a>');
    var ctxHtml = contacts.length
      ? '<div class="ws-ctx-contacts">' + contacts.join(' · ') + '</div>'
      : '';

    // ── Schema (top) + detail zone (bottom, flex:1) — same layout always ───────
    ctxHtml += '<div class="ws-ctx-flow-zone">';
    if (ord) {
      ctxHtml += '<div class="wf-chain" id="wsFlowChain"><div style="font-size:11px;color:#d1d5db">Завантаження…</div></div>';
    } else {
      ctxHtml += '<div style="font-size:11px;color:#d1d5db;padding:4px 0">Немає активного замовлення</div>';
    }
    // Create-bar: shown only when an order is loaded; buttons open creation forms in the detail zone
    var createBar = ord
      ? '<div class="ws-create-bar" id="wsCreateBar">'
          + '<button type="button" class="ws-create-btn" data-create="demand" title="Створити відвантаження"><span class="ws-create-icon">📋</span>Відвантаження</button>'
          + '<button type="button" class="ws-create-btn" data-create="delivery" title="Доставка або ТТН"><span class="ws-create-icon">🚚</span>Доставка / ТТН</button>'
          + '<button type="button" class="ws-create-btn" data-create="payment" title="Внести оплату вручну"><span class="ws-create-icon">💰</span>Оплата</button>'
        + '</div>'
      : '';

    // Status color helper
    var statusColors = {
      draft:'badge-gray', new:'badge-blue', confirmed:'badge-indigo',
      in_progress:'badge-purple', waiting_payment:'badge-orange',
      shipped:'badge-violet', received:'badge-teal', 'return':'badge-rose',
      completed:'badge-green', cancelled:'badge-red'
    };

    // Active order card
    var activeOrdHtml = '';
    if (ord) {
      var ordDate = ord.moment ? ord.moment.substring(0, 10).split('-').reverse().join('.') : '';
      var ordSum  = ord.sum_total > 0 ? '₴' + Math.round(ord.sum_total).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') : '—';
      var ordBc   = statusColors[ord.status] || 'badge-gray';
      activeOrdHtml = '<div class="ws-ctx-active-order" id="wsActiveOrder" data-order-id="' + ord.id + '">'
        + '<div class="ws-ctx-active-order-row">'
        + '<span class="ws-ctx-active-order-num">' + self.esc(ord.number || ('#' + ord.id)) + '</span>'
        + '<span class="ws-ctx-active-order-date">' + ordDate + '</span>'
        + '<span class="ws-ctx-active-order-sum">' + ordSum + '</span>'
        + '<span class="badge ' + ordBc + '" style="font-size:9px;padding:1px 5px">' + self.esc(ord.status_label) + '</span>'
        + '</div>'
        + '</div>';
    }

    // Previous orders (all except active)
    var activeOrdId = ord ? ord.id : 0;
    var otherOrders = (d.recent_orders || []).filter(function(o) { return o.id !== activeOrdId; });

    // 3-column bottom grid
    var bottomGrid = '<div class="ws-ctx-bottom-grid" id="wsBottomGrid">';

    // Col 1: Previous orders
    var ORD_ST_LBL = { draft:'Черн.', new:'Нове', confirmed:'Підтв.', in_progress:'Вик.',
      waiting_payment:'Оч.опл', completed:'Готово', cancelled:'Скас.' };
    var ORD_ST_CSS = { new:'st-new', confirmed:'st-new', in_progress:'st-new',
      waiting_payment:'st-new', completed:'st-completed', cancelled:'st-cancelled' };
    bottomGrid += '<div class="ws-bottom-col" id="wsBottomOrders">'
      + '<div class="ws-bottom-col-hd">'
      + '<span class="ws-bottom-col-hd-title">Замовлення'
      + (otherOrders.length ? ' <span class="ws-bottom-cnt">' + otherOrders.length + '</span>' : '')
      + '</span>'
      + '<span class="ws-bottom-col-hd-sum">Сума</span>'
      + '<span class="ws-bottom-col-hd-st">Статус</span>'
      + '</div>';
    if (otherOrders.length === 0) {
      bottomGrid += '<div class="ws-bottom-empty">Попередніх немає</div>';
    } else {
      otherOrders.forEach(function(o) {
        var sum   = o.sum_total > 0 ? '₴' + Math.round(o.sum_total).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') : '—';
        var stLbl = ORD_ST_LBL[o.status] || o.status || '';
        var stCls = 'ws-bottom-row-st ' + (ORD_ST_CSS[o.status] || '');
        bottomGrid += '<div class="ws-bottom-row ws-bottom-order-row" data-order-id="' + o.id + '">'
          + '<span class="ws-bottom-row-num">' + self.esc(o.number || ('#' + o.id)) + '</span>'
          + '<span class="ws-bottom-row-sum">' + sum + '</span>'
          + '<span class="' + stCls + '">' + stLbl + '</span>'
          + '</div>';
      });
    }
    bottomGrid += '</div>';

    // Col 2: Demands/shipments — populated by renderBottomGrid after flow loads
    bottomGrid += '<div class="ws-bottom-col ws-bottom-col-sep" id="wsBottomDemands">'
      + '<div class="ws-bottom-col-hd"><span class="ws-bottom-col-hd-title">Відвантаження</span><span class="ws-bottom-col-hd-sum">Сума</span><span class="ws-bottom-col-hd-st">Статус</span></div>'
      + '<div class="ws-bottom-empty">…</div>'
      + '</div>';

    // Col 3: TTNs — populated by renderBottomGrid after flow loads
    bottomGrid += '<div class="ws-bottom-col ws-bottom-col-sep" id="wsBottomTtns">'
      + '<div class="ws-bottom-col-hd"><span class="ws-bottom-col-hd-title">Відправки</span><span class="ws-bottom-col-hd-sum" style="grid-column:2/span 2;text-align:left">Статус</span></div>'
      + '<div class="ws-bottom-empty">…</div>'
      + '</div>';

    bottomGrid += '</div>';

    ctxHtml += '</div>'
             + createBar
             + activeOrdHtml
             + '<div class="ws-ctx-detail-zone" id="wsDetailZone">'
             + '<div class="ws-ctx-detail-empty"><div>📄</div><div>Натисніть документ у схемі</div></div>'
             + '</div>'
             + bottomGrid;

    document.getElementById('wsCtx').innerHTML = '<div class="ws-ctx-split">' + ctxHtml + '</div>';

    // Bind active order card click
    var activeOrdEl = document.getElementById('wsActiveOrder');
    if (activeOrdEl) {
      activeOrdEl.addEventListener('click', function() {
        activeOrdEl.classList.add('active');
        document.getElementById('wsBottomOrders') && document.getElementById('wsBottomOrders').querySelectorAll('.ws-bottom-order-row').forEach(function(r) { r.classList.remove('active'); });
        self.loadOrderFlow(ord.id, (cp && cp.id_ms) ? cp.id_ms : '');
      });
    }

    // Bind previous orders click
    var bottomOrders = document.getElementById('wsBottomOrders');
    if (bottomOrders) {
      bottomOrders.querySelectorAll('.ws-bottom-order-row').forEach(function(row) {
        row.addEventListener('click', function() {
          bottomOrders.querySelectorAll('.ws-bottom-order-row').forEach(function(r) { r.classList.remove('active'); });
          row.classList.add('active');
          if (activeOrdEl) activeOrdEl.classList.remove('active');
          var ordId = parseInt(row.dataset.orderId, 10);
          self.loadOrderFlow(ordId, (cp && cp.id_ms) ? cp.id_ms : '');
        });
      });
    }

    if (ord) {
      if (activeOrdEl) activeOrdEl.classList.add('active');
      this.loadOrderFlow(ord.id, cp.id_ms || '');
    }
  },

  // ── Order flow fetch + render ──────────────────────────────────────────────
  loadOrderFlow: function(orderId, idMs) {
    var self = this;
    fetch('/counterparties/api/get_order_flow?order_id=' + orderId + '&id_ms=' + encodeURIComponent(idMs || ''))
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        self._flowData = d;
        // Initialize editable state (deep copy, assign _localId to each item)
        var stateItems = (d.items || []).map(function(it) {
          var copy = JSON.parse(JSON.stringify(it));
          copy._localId = String(it.id);
          return copy;
        });
        self._orderState    = { order: JSON.parse(JSON.stringify(d.order || {})), items: stateItems };
        self._orderOriginal = JSON.parse(JSON.stringify(self._orderState));
        self.renderFlowGraph(d);
      });
  },

  // ── Load demand detail and render form ───────────────────────────────────
  loadDemandForm: function(demandId, flowData) {
    var self = this;
    fetch('/counterparties/api/get_demand_detail?demand_id=' + demandId)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) { showToast('Помилка: ' + (d.error || ''), true); return; }
        d._flowData = flowData;
        var stateItems = (d.items || []).map(function(it) {
          var copy = JSON.parse(JSON.stringify(it));
          copy._localId = String(it.id);
          // map sum_row → sum; pre-calc vat_amount so _updateDemandFooter works immediately
          copy.sum = parseFloat(it.sum_row) || 0;
          var vatRate = parseFloat(it.vat_rate) || 0;
          copy.vat_amount = vatRate > 0
            ? Math.round((copy.sum - copy.sum / (1 + vatRate / 100)) * 100) / 100 : 0;
          copy.discount_amount = 0;
          return copy;
        });
        self._demandState    = { demand: JSON.parse(JSON.stringify(d.demand)), items: stateItems };
        self._demandOriginal = JSON.parse(JSON.stringify(self._demandState));
        self._demandFlowData = d;
        self.renderDemandForm(d);
      });
  },

  // ── Build sorted document timeline ────────────────────────────────────────
  buildTimeline: function(d) {
    var items = [];
    // Order always first
    items.push({ type:'order', data: d.order, moment: d.order.moment || '' });

    // Collect the rest
    var others = [];
    (d.demands          || []).forEach(function(x) { others.push({ type:'demand',   data:x, moment:x.moment||'' }); });
    (d.ttns_np          || []).forEach(function(x) { others.push({ type:'ttn_np',   data:x, moment:x.moment||'' }); });
    (d.ttns_up          || []).forEach(function(x) { others.push({ type:'ttn_up',   data:x, moment:x.moment||'' }); });
    (d.order_deliveries || []).forEach(function(x) { others.push({ type:'delivery', data:x, moment:x.created_at||'' }); });
    (d.payments         || []).forEach(function(x) { others.push({ type:'payment',  data:x, moment:x.moment||'' }); });
    (d.returns          || []).forEach(function(x) { others.push({ type:'return',   data:x, moment:x.moment||'' }); });
    (d.return_logistics || []).forEach(function(x) { others.push({ type:'ret_log',  data:x, moment:x.created_at||'' }); });

    others.sort(function(a, b) {
      if (!a.moment) return 1;
      if (!b.moment) return -1;
      return a.moment.localeCompare(b.moment);
    });
    items = items.concat(others);

    // Placeholders for missing document types
    var hasType = {};
    items.forEach(function(it) { hasType[it.type] = true; });
    // "delivery" placeholder covers all delivery types: ttn_np, ttn_up, courier, pickup
    var hasDelivery = hasType.ttn_np || hasType.ttn_up || hasType.delivery;
    if (!hasType.demand)   items.push({ type:'demand',   data:null, moment:'', empty:true });
    if (!hasDelivery)      items.push({ type:'delivery', data:null, moment:'', empty:true });
    if (!hasType.payment)  items.push({ type:'payment',  data:null, moment:'', empty:true });

    return items;
  },

  // ── Render flow graph (horizontal wf-nodes) ────────────────────────────────
  renderFlowGraph: function(d) {
    var self    = this;
    var chainEl = document.getElementById('wsFlowChain');
    if (!chainEl) return;

    var timeline = this.buildTimeline(d);
    var typeMeta = {
      order:    { cls:'wf-order',    icon:'📦', label:'Замовлення' },
      demand:   { cls:'wf-demand',   icon:'📋', label:'Відвантаження' },
      ttn_np:   { cls:'wf-ttn-np',   icon:'🚚', label:'Нова Пошта' },
      ttn_up:   { cls:'wf-ttn-up',   icon:'📬', label:'Укрпошта' },
      delivery: { cls:'wf-delivery', icon:'🚴', label:'Доставка' },
      payment:  { cls:'wf-payment',  icon:'💰', label:'Оплата' },
      return:   { cls:'wf-return',   icon:'↩️', label:'Повернення' },
      ret_log:  { cls:'wf-ret-log',  icon:'↩',  label:'Повернення' },
      done:     { cls:'wf-done',     icon:'✓',  label:'Отримано' },
    };

    // Add terminal "done" node when order is fully delivered
    var order = d.order || {};
    if (order.shipment_status === 'delivered') {
      timeline.push({ type: 'done', data: null, moment: '' });
    }

    var html = '';
    timeline.forEach(function(item, i) {
      var m     = typeMeta[item.type] || typeMeta.order;
      var empty = !!item.empty;
      var dt    = item.data;
      var cls   = 'wf-node ' + m.cls + (empty ? ' wf-empty' : '');

      var id2 = '', amt = '', progressBar = '';

      if (item.type === 'done') {
        // Terminal node — no extra content
      } else if (!empty && dt) {
        if (item.type === 'order') {
          id2 = (dt.number ? '#' + dt.number : '') + (dt.moment ? ' від ' + dt.moment.substr(0,10) : '');
          amt = dt.sum_total ? '₴' + self.formatNum(dt.sum_total) : '';
        } else if (item.type === 'demand') {
          id2 = (dt.number ? '#' + dt.number : '') + (dt.moment ? ' від ' + dt.moment.substr(0,10) : '');
          var demAmt = parseFloat(dt.sum_total) > 0 ? dt.sum_total : (parseFloat(dt.sum_paid) > 0 ? dt.sum_paid : null);
          amt = demAmt ? '₴' + self.formatNum(demAmt) : '';
        } else if (item.type === 'ttn_np') {
          var npNum = dt.int_doc_number ? String(dt.int_doc_number) : '';
          var prog  = self._deliveryProgress('ttn_np', dt);
          id2 = npNum ? npNum.substr(-10) : '';
          amt = dt.backward_delivery_money > 0 ? 'Накл. ₴' + self.formatNum(dt.backward_delivery_money) : (prog.label || dt.state_name || '');
          progressBar = self._renderDeliveryBar(prog);
          if (prog.refused) cls += ' wf-ttn-refused';
        } else if (item.type === 'ttn_up') {
          var prog  = self._deliveryProgress('ttn_up', dt);
          id2 = dt.barcode ? String(dt.barcode).substr(-10) : '';
          amt = dt.postPayUah > 0 ? '₴' + self.formatNum(dt.postPayUah) : (prog.label || dt.lifecycle_status || '');
          progressBar = self._renderDeliveryBar(prog);
          if (prog.refused) cls += ' wf-ttn-refused';
        } else if (item.type === 'payment') {
          id2 = (dt.source === 'bank' ? 'Банк' : 'Каса') + (dt.moment ? ' · ' + dt.moment.substr(0,10) : '');
          amt = dt.amount ? '₴' + self.formatNum(dt.amount) : '';
        } else if (item.type === 'delivery') {
          var ODL_STATUS = { pending:'очікується', sent:'передано', delivered:'отримано' };
          var methodIcons = { pickup:'🏪', courier:'🚴', novaposhta:'🚚', ukrposhta:'📬' };
          m = { cls: m.cls, icon: methodIcons[dt.method_code] || '🚴', label: dt.method_name || 'Доставка' };
          id2 = ODL_STATUS[dt.status] || dt.status;
          amt = dt.delivered_at ? dt.delivered_at.substr(0,10) : (dt.sent_at ? dt.sent_at.substr(0,10) : '');
        } else if (item.type === 'return') {
          id2 = (dt.number ? '#' + dt.number : '') + (dt.moment ? ' від ' + dt.moment.substr(0,10) : '');
          amt = dt.sum_total ? '₴' + self.formatNum(dt.sum_total) : '';
        } else if (item.type === 'ret_log') {
          var RL_TYPE   = { novaposhta_ttn:'НП ТТН', ukrposhta_ttn:'УП ТТН', manual:'Інший спосіб', left_with_client:'У клієнта', auto_ttn:'Авто ТТН' };
          var RL_STATUS = { expected:'очікується', in_transit:'в дорозі', received:'отримано', cancelled:'скасовано' };
          id2 = RL_TYPE[dt.return_type] || dt.return_type;
          if (dt.return_ttn_number) id2 += ' #' + dt.return_ttn_number;
          amt = RL_STATUS[dt.status] || dt.status;
        }
      } else if (item.type !== 'done') {
        if (item.type === 'delivery') {
          var dmName = d && d.order && d.order.delivery_method_name;
          id2 = dmName ? '+ ' + dmName : '+ Доставка';
        } else {
          id2 = '+ Створити';
        }
      }

      html += '<div class="' + cls + '" data-type="' + item.type + '" data-idx="' + i + '">'
            + '<div class="wf-node-lbl">' + m.label + '</div>'
            + (id2 ? '<div class="wf-node-id">' + self.esc(String(id2)) + '</div>' : '')
            + progressBar
            + (amt ? '<div class="wf-node-amt">' + self.esc(String(amt)) + '</div>' : '')
            + '</div>';
      if (i < timeline.length - 1) {
        html += '<div class="wf-arrow"><span class="wf-dot-sep"></span><span class="wf-dot-sep"></span><span class="wf-dot-sep"></span></div>';
      }
    });

    chainEl.innerHTML = html;
    if (timeline.length <= 4) {
      chainEl.classList.add('wf-chain-stretch');
      chainEl.classList.remove('wf-chain-many');
    } else {
      chainEl.classList.remove('wf-chain-stretch');
      chainEl.classList.add('wf-chain-many');
    }

    // Click handlers — all nodes including empty placeholders
    chainEl.querySelectorAll('.wf-node').forEach(function(node) {
      node.addEventListener('click', function() {
        chainEl.querySelectorAll('.wf-node').forEach(function(n){ n.classList.remove('wf-active'); });
        node.classList.add('wf-active');
        var idx  = parseInt(node.dataset.idx, 10);
        var item = timeline[idx];
        self.openDocDetail(item, d);
      });
    });

    // Create-bar button handlers
    var createBar = document.getElementById('wsCreateBar');
    if (createBar) {
      createBar.querySelectorAll('.ws-create-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          self._openCreateForm(btn.dataset.create, d);
        });
      });
    }

    // Populate demands/TTNs columns in bottom grid
    self.renderBottomGrid(d);

    // Auto-open order node if present
    var orderNode = chainEl.querySelector('.wf-node[data-type="order"]');
    if (orderNode) { orderNode.click(); }
  },

  // ── Render bottom 3-column grid (demands + TTNs) ───────────────────────────
  renderBottomGrid: function(d) {
    var self = this;

    // Col 2: Demands
    var demandsEl = document.getElementById('wsBottomDemands');
    if (demandsEl) {
      var demands = d.demands || [];
      var DEM_ST_LBL = { new:'Нове', confirmed:'Підтв.', processing:'Вик.',
        shipped:'Відвант.', done:'Готово', cancelled:'Скас.' };
      var DEM_ST_CSS = { new:'st-new', confirmed:'st-new', processing:'st-new',
        shipped:'st-shipped', done:'st-completed', cancelled:'st-cancelled' };
      var html = '<div class="ws-bottom-col-hd">'
        + '<span class="ws-bottom-col-hd-title">Відвантаження'
        + (demands.length ? ' <span class="ws-bottom-cnt">' + demands.length + '</span>' : '')
        + '</span>'
        + '<span class="ws-bottom-col-hd-sum">Сума</span>'
        + '<span class="ws-bottom-col-hd-st">Статус</span>'
        + '</div>';
      if (demands.length === 0) {
        html += '<div class="ws-bottom-empty">Немає</div>';
      } else {
        demands.forEach(function(dem) {
          var sum   = parseFloat(dem.sum_total) > 0
            ? '₴' + Math.round(dem.sum_total).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') : '—';
          var stLbl = DEM_ST_LBL[dem.status] || dem.status || '';
          var stCls = 'ws-bottom-row-st ' + (DEM_ST_CSS[dem.status] || '');
          html += '<div class="ws-bottom-row ws-bottom-demand-row" data-demand-id="' + dem.id + '">'
            + '<span class="ws-bottom-row-num">#' + self.esc(dem.number || ('d' + dem.id)) + '</span>'
            + '<span class="ws-bottom-row-sum">' + sum + '</span>'
            + '<span class="' + stCls + '">' + stLbl + '</span>'
            + '</div>';
        });
      }
      demandsEl.innerHTML = html;
      demandsEl.querySelectorAll('.ws-bottom-demand-row').forEach(function(row) {
        row.addEventListener('click', function() {
          demandsEl.querySelectorAll('.ws-bottom-demand-row').forEach(function(r) { r.classList.remove('active'); });
          row.classList.add('active');
          self.loadDemandForm(parseInt(row.dataset.demandId, 10), d);
        });
      });
    }

    // Col 3: TTNs (NP + UP)
    var ttnsEl = document.getElementById('wsBottomTtns');
    if (ttnsEl) {
      var allTtns = [];
      (d.ttns_np || []).forEach(function(t) {
        allTtns.push({ type: 'ttn_np', num: t.int_doc_number, status: self._npStatusLabel(t), moment: t.moment, data: t });
      });
      (d.ttns_up || []).forEach(function(t) {
        allTtns.push({ type: 'ttn_up', num: t.barcode, status: t.lifecycle_status, moment: t.moment, data: t });
      });
      allTtns.sort(function(a, b) { return (a.moment || '').localeCompare(b.moment || ''); });

      var TTN_ST_CSS_MAP = {
        'Отримано': 'st-delivered', 'Доставлено': 'st-delivered', 'Вручено': 'st-delivered',
        'У відділенні': 'st-branch',
        'В дорозі': 'st-transit', 'В місті відправника': 'st-transit', 'В місті одержувача': 'st-transit',
        'Кур\'єр доставляє': 'st-transit',
        'Чернетка': 'st-draft',
        'Повернуто': 'st-cancelled', 'Повертається': 'st-cancelled', 'Повернення': 'st-cancelled',
        'Відмова': 'st-cancelled', 'Відмовлено': 'st-cancelled',
        'Видалено': 'st-cancelled', 'Не знайдено': 'st-cancelled'
      };
      var html = '<div class="ws-bottom-col-hd">'
        + '<span class="ws-bottom-col-hd-title">Відправки'
        + (allTtns.length ? ' <span class="ws-bottom-cnt">' + allTtns.length + '</span>' : '')
        + '</span>'
        + '<span class="ws-bottom-col-hd-sum" style="grid-column:2/span 2;text-align:left">Статус</span>'
        + '</div>';
      if (allTtns.length === 0) {
        html += '<div class="ws-bottom-empty">Немає</div>';
      } else {
        allTtns.forEach(function(t, idx) {
          var stRaw = t.status || '—';
          var stCls = 'ws-bottom-row-st';
          for (var k in TTN_ST_CSS_MAP) { if (stRaw.indexOf(k) === 0) { stCls += ' ' + TTN_ST_CSS_MAP[k]; break; } }
          html += '<div class="ws-bottom-row ws-bottom-ttn-row" data-ttn-idx="' + idx + '">'
            + '<span class="ws-bottom-row-num">' + self.esc(t.num ? String(t.num).slice(-8) : '—') + '</span>'
            + '<span class="' + stCls + '" style="grid-column:2/span 2;text-align:left">' + self.esc(stRaw) + '</span>'
            + '</div>';
        });
      }
      ttnsEl.innerHTML = html;
      ttnsEl.querySelectorAll('.ws-bottom-ttn-row').forEach(function(row) {
        row.addEventListener('click', function() {
          ttnsEl.querySelectorAll('.ws-bottom-ttn-row').forEach(function(r) { r.classList.remove('active'); });
          row.classList.add('active');
          var idx = parseInt(row.dataset.ttnIdx, 10);
          var t = allTtns[idx];
          self.openDocDetail({ type: t.type, data: t.data }, d);
        });
      });
    }
  },

  // ── Open document detail in bottom zone ───────────────────────────────────
  openDocDetail: function(item, d) {
    var self   = this;
    var detEl  = document.getElementById('wsDetailZone');
    if (!detEl) return;
    detEl.style.display = '';

    var type = item.type;
    var dt   = item.data;

    if (type === 'order') {
      var tmpEl = document.createElement('div');
      tmpEl.id  = 'wsOrderForm';
      tmpEl.className = 'ws-order-form';
      tmpEl.innerHTML = '<div style="font-size:11px;color:#9ca3af;padding:16px;text-align:center">Завантаження…</div>';
      detEl.innerHTML = '';
      detEl.appendChild(tmpEl);
      this.renderOrderForm(d);
      return;
    }

    if (type === 'demand') {
      var tmpEl = document.createElement('div');
      tmpEl.id = 'wsDemandForm';
      tmpEl.className = 'ws-order-form';
      tmpEl.innerHTML = '<div style="font-size:11px;color:#9ca3af;padding:16px;text-align:center">Завантаження…</div>';
      detEl.innerHTML = '';
      detEl.appendChild(tmpEl);
      this.loadDemandForm(dt.id, d);
      return;
    }

    var icon = '', title = '', rows = '', acts = '';

    if (type === 'ttn_np') {
      icon  = '🚚';
      var npNum = dt.int_doc_number ? String(dt.int_doc_number) : '';
      title = 'ТТН Нова Пошта' + (npNum ? ' · ' + npNum.substr(-8) : '');
      rows += this._ddr('Номер',       npNum || '—');
      rows += this._ddr('Статус',      this._npStatusLabel(dt));
      rows += this._ddr('Місто',       dt.city_recipient_desc || '—');
      if (dt.backward_delivery_money > 0)
        rows += this._ddr('Накл. платіж',       '₴' + this.formatNum(dt.backward_delivery_money));
      if (dt.estimated_delivery_date)
        rows += this._ddr('Доставка',  dt.estimated_delivery_date.substr(0,10));
      if (npNum)
        acts += '<a href="https://novaposhta.ua/tracking/' + encodeURIComponent(npNum) + '" target="_blank" class="btn btn-sm">Відстежити →</a>';

    } else if (type === 'ttn_up') {
      icon  = '📬';
      title = 'ТТН Укрпошта' + (dt.barcode ? ' · ' + String(dt.barcode).substr(-8) : '');
      rows += this._ddr('Штрихкод',  dt.barcode || '—');
      rows += this._ddr('Статус',    dt.lifecycle_status || '—');
      rows += this._ddr('Місто',     dt.recipient_city || '—');
      if (dt.postPayUah > 0)
        rows += this._ddr('Накл. платіж',     '₴' + this.formatNum(dt.postPayUah));

    } else if (type === 'payment') {
      icon  = '💰';
      title = (dt.source === 'bank' ? 'Банківський платіж' : 'Касовий платіж')
            + (dt.doc_number ? ' #' + dt.doc_number : '');
      rows += this._ddr('Сума',    '₴' + this.formatNum(dt.amount));
      rows += this._ddr('Дата',    dt.moment ? dt.moment.substr(0,10) : '—');
      rows += this._ddr('Канал',   dt.source === 'bank' ? 'Банк' : 'Каса');

    } else if (type === 'delivery') {
      var ODL_STATUS_LABEL = { pending:'Очікується', sent:'Передано кур\'єру', delivered:'Доставлено', cancelled:'Скасовано' };
      icon  = dt.method_code === 'pickup' ? '🏪' : '🚴';
      title = (dt.method_name || 'Доставка') + (dt.status ? ' · ' + (ODL_STATUS_LABEL[dt.status] || dt.status) : '');
      rows += this._ddr('Спосіб',  dt.method_name || '—');
      rows += this._ddr('Статус',  ODL_STATUS_LABEL[dt.status] || dt.status || '—');
      if (dt.sent_at)      rows += this._ddr('Відправлено', dt.sent_at.substr(0,10));
      if (dt.delivered_at) rows += this._ddr('Отримано',    dt.delivered_at.substr(0,10));
      if (dt.comment)      rows += this._ddr('Коментар',    dt.comment);
      acts += '<button type="button" class="btn btn-sm" onclick="WS._openDeliveryEditForm(' + dt.id + ')">Змінити статус</button>';

    } else if (type === 'return') {
      icon  = '↩️';
      title = 'Повернення' + (dt.number ? ' #' + dt.number : '');
      rows += this._ddr('Сума',     '₴' + this.formatNum(dt.sum_total));
      rows += this._ddr('Дата',     dt.moment ? dt.moment.substr(0,10) : '—');
      if (dt.description) rows += this._ddr('Коментар', dt.description);

    } else if (item.empty) {
      // Empty placeholder — open creation form
      this._openCreateForm(type, d);
      return;
    }

    detEl.innerHTML = '<div class="ws-doc-detail">'
      + '<div class="ws-doc-detail-head">'
      + '<span class="ws-doc-detail-icon">' + icon + '</span>'
      + '<span class="ws-doc-detail-title">' + this.esc(title) + '</span>'
      + '</div>'
      + rows
      + (acts ? '<div class="ws-doc-detail-acts">' + acts + '</div>' : '')
      + '</div>';
  },

  _ddr: function(lbl, val) {
    return '<div class="ws-ddr"><span class="ws-ddr-lbl">' + lbl + '</span><span class="ws-ddr-val">' + this.esc(String(val)) + '</span></div>';
  },

  // ── Create-form dispatcher (delivery, payment, demand) ────────────────────
  // Opens a creation form in wsDetailZone based on document type.
  // Called from create-bar buttons and from empty flow-graph node clicks.
  _openCreateForm: function(type, d) {
    var self   = this;
    var detEl  = document.getElementById('wsDetailZone');
    if (!detEl) return;

    var order = d && d.order;
    if (!order) return;

    if (type === 'delivery') {
      var dm = order.delivery_method_code;
      if (dm === 'novaposhta' || dm === 'ukrposhta') {
        self._openTtnManualForm(dm === 'novaposhta' ? 'np' : 'up', order.id, d);
      } else {
        // courier / pickup / not set — show order_delivery form
        self._openDeliveryForm(order, d);
      }
    } else if (type === 'payment') {
      detEl.innerHTML = '<div class="ws-doc-detail"><div class="ws-doc-detail-head"><span class="ws-doc-detail-icon">💰</span><span class="ws-doc-detail-title">Ручна оплата — незабаром</span></div><div style="padding:12px;font-size:12px;color:#6b7280">Введення платіжних документів вручну буде реалізоване в наступному оновленні.</div></div>';
    } else if (type === 'demand') {
      detEl.innerHTML = '<div class="ws-doc-detail"><div class="ws-doc-detail-head"><span class="ws-doc-detail-icon">📋</span><span class="ws-doc-detail-title">Нове відвантаження — незабаром</span></div><div style="padding:12px;font-size:12px;color:#6b7280">Створення відвантаження вручну буде реалізоване в наступному оновленні.</div></div>';
    }
  },

  // ── Delivery (courier/pickup) creation / status-change form ──────────────
  _openDeliveryForm: function(order, d, existingId) {
    var self  = this;
    var detEl = document.getElementById('wsDetailZone');
    if (!detEl) return;

    var WS_DM = <?php echo json_encode(array_values($wsDeliveryMethods)); ?>;
    var preselectedDmId    = order.delivery_method_id       ? parseInt(order.delivery_method_id)       : 0;
    var preselectedHasTtn  = order.delivery_method_has_ttn  ? parseInt(order.delivery_method_has_ttn)  : 0;
    if (preselectedHasTtn) preselectedDmId = 0;

    var dmOpts = '<option value="">— обрати спосіб —</option>';
    WS_DM.forEach(function(dm) {
      if (parseInt(dm.has_ttn)) return;
      var sel = (preselectedDmId && preselectedDmId === parseInt(dm.id)) ? ' selected' : '';
      dmOpts += '<option value="' + dm.id + '"' + sel + '>' + self.esc(dm.name_uk) + '</option>';
    });

    // Pre-populate from existing record if editing
    var existingRec = null;
    if (existingId && d && d.order_deliveries) {
      for (var ei = 0; ei < d.order_deliveries.length; ei++) {
        if (parseInt(d.order_deliveries[ei].id) === parseInt(existingId)) {
          existingRec = d.order_deliveries[ei]; break;
        }
      }
    }
    if (existingRec) {
      // Override preselectedDmId from the actual record
      var recDmId = parseInt(existingRec.delivery_method_id) || 0;
      dmOpts = '<option value="">— обрати спосіб —</option>';
      WS_DM.forEach(function(dm) {
        if (parseInt(dm.has_ttn)) return;
        var sel = (recDmId && recDmId === parseInt(dm.id)) ? ' selected' : '';
        dmOpts += '<option value="' + dm.id + '"' + sel + '>' + self.esc(dm.name_uk) + '</option>';
      });
    }

    var today = new Date().toISOString().substr(0,10);
    var initStatus  = existingRec ? existingRec.status    : 'pending';
    var initSentAt  = existingRec ? (existingRec.sent_at     ? existingRec.sent_at.substr(0,10)     : today) : today;
    var initDelivAt = existingRec ? (existingRec.delivered_at ? existingRec.delivered_at.substr(0,10) : '')   : '';
    var initComment = existingRec ? (existingRec.comment || '') : '';

    var statusOpts = ['pending:Очікується','sent:Передано кур\'єру','delivered:Доставлено'].map(function(s){
      var p = s.split(':'); return '<option value="'+p[0]+'"'+(initStatus===p[0]?' selected':'')+'>'+p[1]+'</option>';
    }).join('');

    detEl.innerHTML = '<div class="ws-doc-detail" id="wsDelivForm">'
      + '<div class="ws-doc-detail-head"><span class="ws-doc-detail-icon">🚴</span>'
      + '<span class="ws-doc-detail-title">' + (existingRec ? 'Редагувати доставку' : 'Доставка кур\'єром / самовивіз') + '</span>'
      + '<button type="button" id="wsDfCloseBtn" style="margin-left:auto;background:none;border:none;font-size:16px;cursor:pointer;color:#9ca3af;padding:0 4px" title="Закрити">✕</button>'
      + '</div>'
      + '<div class="ws-ddr"><span class="ws-ddr-lbl">Спосіб</span>'
      +   '<select id="wsDfMethod" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:2px 6px">' + dmOpts + '</select></div>'
      + '<div class="ws-ddr"><span class="ws-ddr-lbl">Статус</span>'
      +   '<select id="wsDfStatus" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:2px 6px">' + statusOpts + '</select></div>'
      + '<div class="ws-ddr"><span class="ws-ddr-lbl">Дата відправки</span>'
      +   '<input type="date" id="wsDfSentAt" value="' + self.esc(initSentAt) + '" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:2px 4px"></div>'
      + '<div class="ws-ddr"><span class="ws-ddr-lbl">Дата доставки</span>'
      +   '<input type="date" id="wsDfDelivAt" value="' + self.esc(initDelivAt) + '" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:2px 4px"></div>'
      + '<div class="ws-ddr"><span class="ws-ddr-lbl">Коментар</span>'
      +   '<input type="text" id="wsDfComment" value="' + self.esc(initComment) + '" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:2px 4px;width:160px" placeholder="необов\'язково"></div>'
      + '<div class="ws-doc-detail-acts">'
      +   '<button type="button" class="btn btn-sm" id="wsDfCancelBtn">Скасувати</button>'
      +   '<button type="button" class="btn btn-primary btn-sm" id="wsDfSaveBtn">Зберегти</button>'
      + '</div></div>';

    function wsDfClose() {
      detEl.innerHTML = '<div class="ws-ctx-detail-empty"><div>📄</div><div>Натисніть документ у схемі</div></div>';
      var ch = document.getElementById('wsFlowChain');
      if (ch) ch.querySelectorAll('.wf-node').forEach(function(n){ n.classList.remove('wf-active'); });
    }
    document.getElementById('wsDfCloseBtn').addEventListener('click', wsDfClose);
    document.getElementById('wsDfCancelBtn').addEventListener('click', wsDfClose);

    document.getElementById('wsDfSaveBtn').addEventListener('click', function() {
      var dmId    = parseInt(document.getElementById('wsDfMethod').value)  || 0;
      var status  = document.getElementById('wsDfStatus').value;
      var sentAt  = document.getElementById('wsDfSentAt').value;
      var delivAt = document.getElementById('wsDfDelivAt').value;
      var comment = document.getElementById('wsDfComment').value;
      if (!dmId) { alert('Оберіть спосіб доставки'); return; }

      var body = (existingId ? 'id=' + existingId + '&' : '')
               + 'customerorder_id=' + order.id
               + '&delivery_method_id=' + dmId
               + '&status=' + encodeURIComponent(status)
               + (sentAt  ? '&sent_at='      + encodeURIComponent(sentAt)  : '')
               + (delivAt ? '&delivered_at=' + encodeURIComponent(delivAt) : '')
               + (comment ? '&comment='      + encodeURIComponent(comment) : '');

      fetch('/counterparties/api/save_order_delivery', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body
      }).then(function(r){ return r.json(); }).then(function(res) {
        if (!res.ok) { alert('Помилка: ' + (res.error || '')); return; }
        showToast(existingId ? 'Доставку оновлено' : 'Доставку зареєстровано');
        self.loadOrderFlow(order.id, '');
      });
    });
  },

  // ── TTN manual entry form ─────────────────────────────────────────────────
  _openTtnManualForm: function(carrier, orderId, d) {
    var self  = this;
    var detEl = document.getElementById('wsDetailZone');
    if (!detEl) return;

    // ── Укрпошта: keep simple manual form ────────────────────────────────────
    if (carrier !== 'np') {
      detEl.innerHTML = '<div class="ws-doc-detail" id="wsTtnForm">'
        + '<div class="ws-doc-detail-head"><span class="ws-doc-detail-icon">📬</span>'
        + '<span class="ws-doc-detail-title">Введення ТТН · Укрпошта</span>'
        + '<button type="button" id="wsTtnCloseBtn" style="margin-left:auto;background:none;border:none;font-size:16px;cursor:pointer;color:#9ca3af;padding:0 4px">✕</button>'
        + '</div>'
        + '<div class="ws-ddr"><span class="ws-ddr-lbl">Номер ТТН</span>'
        + '<input type="text" id="wsTtnNumber" style="font-size:12px;border:1px solid #d1d5db;border-radius:4px;padding:2px 6px;width:175px" placeholder="0300012345678" autocomplete="off"></div>'
        + '<div style="padding:2px 0 6px 0;font-size:10px;color:#9ca3af">Статус оновиться автоматично</div>'
        + '<div class="ws-doc-detail-acts">'
        + '<button type="button" class="btn btn-sm" id="wsTtnCancelBtn">Скасувати</button>'
        + '<button type="button" class="btn btn-sm" id="wsTtnCarrierToggle" style="background:#f3f4f6">Перейти на НП</button>'
        + '<button type="button" class="btn btn-primary btn-sm" id="wsTtnSaveBtn">Зберегти</button>'
        + '</div></div>';

      function upClose() {
        detEl.innerHTML = '<div class="ws-ctx-detail-empty"><div>📄</div><div>Натисніть документ у схемі</div></div>';
        var ch = document.getElementById('wsFlowChain');
        if (ch) ch.querySelectorAll('.wf-node').forEach(function(n){ n.classList.remove('wf-active'); });
      }
      document.getElementById('wsTtnCloseBtn').addEventListener('click', upClose);
      document.getElementById('wsTtnCancelBtn').addEventListener('click', upClose);
      document.getElementById('wsTtnSaveBtn').addEventListener('click', function() {
        var num = (document.getElementById('wsTtnNumber').value || '').trim();
        if (!num) { alert('Введіть номер ТТН'); return; }
        fetch('/counterparties/api/save_ttn_manual', {
          method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: 'customerorder_id=' + orderId + '&carrier=up&ttn_number=' + encodeURIComponent(num)
        }).then(function(r){ return r.json(); }).then(function(res) {
          if (!res.ok) { alert('Помилка: ' + (res.error || '')); return; }
          showToast('ТТН збережено');
          self.loadOrderFlow(orderId, '');
        });
      });
      document.getElementById('wsTtnCarrierToggle').addEventListener('click', function() {
        self._openTtnManualForm('np', orderId, d);
      });
      return;
    }

    // ── Нова Пошта: full creation form ───────────────────────────────────────
    var iS = 'font-size:11px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;width:100%;box-sizing:border-box';
    var iSS = iS + ';width:calc(100% - 2px)';
    var lS = 'color:#9ca3af;font-size:10px;display:block;margin-bottom:2px';

    detEl.innerHTML = '<div class="ws-doc-detail" id="wsTtnNpForm">'
      + '<div class="ws-doc-detail-head"><span class="ws-doc-detail-icon">🚚</span>'
      + '<span class="ws-doc-detail-title">Створити ТТН · Нова Пошта</span>'
      + '<button type="button" id="npTtnCloseBtn" style="margin-left:auto;background:none;border:none;font-size:16px;cursor:pointer;color:#9ca3af;padding:0 4px">✕</button>'
      + '</div>'
      + '<div id="npTtnBody" style="padding-top:4px"><div style="text-align:center;padding:20px;color:#9ca3af;font-size:12px">Завантаження…</div></div>'
      + '</div>';

    function npClose() {
      detEl.innerHTML = '<div class="ws-ctx-detail-empty"><div>📄</div><div>Натисніть документ у схемі</div></div>';
      var ch = document.getElementById('wsFlowChain');
      if (ch) ch.querySelectorAll('.wf-node').forEach(function(n){ n.classList.remove('wf-active'); });
    }
    document.getElementById('npTtnCloseBtn').addEventListener('click', npClose);

    // Fetch prefill data
    fetch('/novaposhta/api/get_ttn_form?order_id=' + orderId)
      .then(function(r){ return r.json(); })
      .then(function(res) {
        if (!res.ok) {
          document.getElementById('npTtnBody').innerHTML =
            '<div style="padding:12px;color:#dc2626;font-size:12px">Помилка: ' + self.esc(res.error||'') + '</div>';
          return;
        }
        self._renderNpTtnForm(res.data, orderId, d);
      })
      .catch(function() {
        document.getElementById('npTtnBody').innerHTML =
          '<div style="padding:12px;color:#dc2626;font-size:12px">Мережева помилка</div>';
      });
  },

  _renderNpTtnForm: function(data, orderId, d) {
    var self = this;
    var body = document.getElementById('npTtnBody');
    if (!body) return;

    var senders   = data.senders   || [];
    var recipient = data.recipient || {};
    var iS  = 'font-size:11px;border:1px solid #d1d5db;border-radius:4px;padding:3px 6px;width:100%;box-sizing:border-box;background:#fff';
    var lS  = 'color:#9ca3af;font-size:10px;display:block;margin-bottom:2px;margin-top:6px';
    var rowS = 'display:grid;grid-template-columns:1fr 1fr;gap:6px';

    // Build sender options
    var sOpts = '';
    senders.forEach(function(s) {
      var sel = (s.Ref === data.sender_ref) ? ' selected' : '';
      sOpts += '<option value="' + self.esc(s.Ref) + '"' + sel + '>' + self.esc(s.Description) + '</option>';
    });

    // Service type options
    var stOpts = [
      ['WarehouseWarehouse', 'Відділення → Відділення'],
      ['WarehouseDoors',     'Відділення → Адреса'],
      ['DoorsWarehouse',     'Адреса → Відділення'],
      ['DoorsDoors',          'Адреса → Адреса'],
    ];
    var stHtml = stOpts.map(function(o){
      var sel = (o[0] === 'WarehouseWarehouse') ? ' selected' : '';
      return '<option value="' + o[0] + '"' + sel + '>' + o[1] + '</option>';
    }).join('');

    var autocompleteStyle = 'position:relative';
    var ddStyle = 'position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:4px;max-height:160px;overflow-y:auto;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,.12);font-size:11px;display:none';
    var ddItemStyle = 'padding:5px 8px;cursor:pointer;border-bottom:1px solid #f3f4f6;line-height:1.3';

    // Determine if address delivery based on service type
    var html = '<div style="overflow-y:auto;max-height:calc(100vh - 220px);padding-bottom:8px">';

    // — Відправник —
    html += '<div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin:4px 0 6px;letter-spacing:.5px">Відправник</div>';
    html += '<label style="' + lS.replace('margin-top:6px','') + '">Відправник</label>';
    html += '<select id="npSenderRef" style="' + iS + '">' + sOpts + '</select>';
    html += '<label style="' + lS + '">Адреса відправки</label>';
    html += '<select id="npSenderAddr" style="' + iS + '"><option value="">Завантаження…</option></select>';

    // — Одержувач —
    html += '<div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin:10px 0 6px;letter-spacing:.5px">Одержувач</div>';
    html += '<div style="' + rowS + '">';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Прізвище</label>'
          + '<input type="text" id="npRcpLast"   value="' + self.esc(recipient.last_name||'')   + '" style="' + iS + '"></div>';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Ім\'я</label>'
          + '<input type="text" id="npRcpFirst"  value="' + self.esc(recipient.first_name||'')  + '" style="' + iS + '"></div>';
    html += '</div>';
    html += '<label style="' + lS + '">По батькові</label>';
    html += '<input type="text" id="npRcpMiddle" value="' + self.esc(recipient.middle_name||'') + '" style="' + iS + '">';
    html += '<label style="' + lS + '">Телефон</label>';
    html += '<input type="text" id="npRcpPhone"  value="' + self.esc(recipient.phone||'')       + '" style="' + iS + '" placeholder="0671234567">';

    // City autocomplete
    html += '<label style="' + lS + '">Місто одержувача</label>';
    html += '<div style="' + autocompleteStyle + '">';
    html += '<input type="text" id="npCityInput" style="' + iS + '" placeholder="Введіть місто…" autocomplete="off"'
          + ' value="' + self.esc(recipient.city_hint||'') + '">';
    html += '<input type="hidden" id="npCityRef" value="">';
    html += '<div id="npCityDd" style="' + ddStyle + '"></div>';
    html += '</div>';

    // Warehouse/Address section (toggled by service type)
    html += '<div id="npWhSection">';
    html += '<label style="' + lS + '">Відділення</label>';
    html += '<div style="' + autocompleteStyle + '">';
    html += '<input type="text" id="npWhInput" style="' + iS + '" placeholder="Відділення або поштомат…" autocomplete="off"'
          + ' value="' + self.esc(recipient.address_hint||'') + '">';
    html += '<input type="hidden" id="npWhRef" value="' + self.esc(recipient.np_warehouse_ref||'') + '">';
    html += '<div id="npWhDd" style="' + ddStyle + '"></div>';
    html += '</div></div>';

    // Address delivery section
    html += '<div id="npAddrSection" style="display:none">';
    html += '<label style="' + lS + '">Вулиця</label>';
    html += '<div style="' + autocompleteStyle + '">';
    html += '<input type="text" id="npStreetInput" style="' + iS + '" placeholder="Вулиця…" autocomplete="off">';
    html += '<input type="hidden" id="npStreetRef" value="">';
    html += '<div id="npStreetDd" style="' + ddStyle + '"></div>';
    html += '</div>';
    html += '<div style="' + rowS + ';margin-top:4px">';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Будинок</label><input type="text" id="npBuilding" style="' + iS + '"></div>';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Квартира</label><input type="text" id="npFlat"     style="' + iS + '"></div>';
    html += '</div></div>';

    // — Вантаж —
    html += '<div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin:10px 0 6px;letter-spacing:.5px">Вантаж</div>';
    html += '<label style="' + lS.replace('margin-top:6px','') + '">Тип доставки</label>';
    html += '<select id="npServiceType" style="' + iS + '">' + stHtml + '</select>';
    html += '<div style="' + rowS + ';margin-top:4px">';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Вага (кг)</label>'
          + '<input type="number" id="npWeight" value="0.5" step="0.1" min="0.1" style="' + iS + '"></div>';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Місць</label>'
          + '<input type="number" id="npSeats"  value="1"   step="1"   min="1"   style="' + iS + '"></div>';
    html += '</div>';
    html += '<label style="' + lS + '">Опис</label>';
    html += '<input type="text" id="npDesc" value="Товар" style="' + iS + '">';
    html += '<label style="' + lS + '">Оголошена вартість (грн)</label>';
    html += '<input type="number" id="npCost" value="1" min="1" style="' + iS + '">';

    // — Оплата —
    html += '<div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin:10px 0 6px;letter-spacing:.5px">Оплата</div>';
    html += '<div style="' + rowS + '">';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Платник</label>'
          + '<select id="npPayerType" style="' + iS + '">'
          + '<option value="Recipient">Одержувач</option>'
          + '<option value="Sender">Відправник</option>'
          + '<option value="ThirdPerson">Третя особа</option>'
          + '</select></div>';
    html += '<div><label style="' + lS.replace('margin-top:6px','') + '">Спосіб оплати</label>'
          + '<select id="npPayMethod" style="' + iS + '">'
          + '<option value="Cash">Готівка</option>'
          + '<option value="NonCash">Безготівка</option>'
          + '</select></div>';
    html += '</div>';
    html += '<label style="' + lS + '">Накладений платіж (грн), 0 = без</label>';
    html += '<input type="number" id="npBackMoney" value="' + (data.backward_money_hint||0) + '" min="0" step="0.01" style="' + iS + '">';

    html += '<label style="' + lS + '">Дата відправки</label>';
    html += '<input type="date" id="npDate" value="' + (new Date().toISOString().slice(0,10)) + '" style="' + iS + '">';

    html += '<div id="npTtnError" style="display:none;color:#dc2626;font-size:11px;margin-top:6px;padding:6px;background:#fef2f2;border-radius:4px;line-height:1.4"></div>';

    html += '</div>'; // scrollable

    html += '<div class="ws-doc-detail-acts">';
    html += '<button type="button" class="btn btn-sm" id="npTtnCancelBtn">Скасувати</button>';
    html += '<button type="button" class="btn btn-sm" id="npTtnUpBtn" style="background:#f3f4f6">Укрпошта</button>';
    html += '<button type="button" class="btn btn-primary btn-sm" id="npTtnSubmitBtn">Створити ТТН</button>';
    html += '</div>';

    body.innerHTML = html;

    // ── Close / cancel ───────────────────────────────────────────────────────
    function npClose2() {
      var detEl2 = document.getElementById('wsDetailZone');
      if (detEl2) detEl2.innerHTML = '<div class="ws-ctx-detail-empty"><div>📄</div><div>Натисніть документ у схемі</div></div>';
      var ch = document.getElementById('wsFlowChain');
      if (ch) ch.querySelectorAll('.wf-node').forEach(function(n){ n.classList.remove('wf-active'); });
    }
    document.getElementById('npTtnCancelBtn').addEventListener('click', npClose2);
    document.getElementById('npTtnUpBtn').addEventListener('click', function(){
      self._openTtnManualForm('up', orderId, d);
    });

    // ── Service type toggle ──────────────────────────────────────────────────
    var stSel = document.getElementById('npServiceType');
    function toggleDeliverySection() {
      var st = stSel.value;
      var isAddr = (st === 'WarehouseDoors' || st === 'DoorsDoors');
      document.getElementById('npWhSection').style.display    = isAddr ? 'none' : '';
      document.getElementById('npAddrSection').style.display  = isAddr ? ''     : 'none';
    }
    stSel.addEventListener('change', toggleDeliverySection);
    toggleDeliverySection();

    // ── Load sender addresses ────────────────────────────────────────────────
    function loadSenderAddresses(senderRef) {
      var addrSel = document.getElementById('npSenderAddr');
      if (!addrSel) return;
      addrSel.innerHTML = '<option value="">Завантаження…</option>';
      fetch('/novaposhta/api/get_senders?sender_ref=' + encodeURIComponent(senderRef))
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (!res.ok || !res.addresses.length) {
            addrSel.innerHTML = '<option value="">Адреси не знайдено</option>';
            return;
          }
          addrSel.innerHTML = '';
          res.addresses.forEach(function(a){
            var sel = a.is_default ? ' selected' : '';
            addrSel.innerHTML += '<option value="' + self.esc(a.Ref) + '"' + sel
              + ' data-city="' + self.esc(a.CityRef||'') + '"'
              + ' data-city-desc="' + self.esc(a.CityDescription||'') + '">'
              + self.esc(a.Description||a.Ref) + '</option>';
          });
        });
    }

    var senderSel = document.getElementById('npSenderRef');
    if (senderSel.value) loadSenderAddresses(senderSel.value);
    senderSel.addEventListener('change', function(){
      loadSenderAddresses(this.value);
    });

    // ── City autocomplete ────────────────────────────────────────────────────
    function makeAutocomplete(inputId, ddId, hiddenId, fetchFn, renderFn) {
      var inp    = document.getElementById(inputId);
      var dd     = document.getElementById(ddId);
      var hidden = document.getElementById(hiddenId);
      if (!inp || !dd || !hidden) return;
      var timer;

      inp.addEventListener('input', function(){
        clearTimeout(timer);
        var q = inp.value.trim();
        if (q.length < 2) { dd.style.display = 'none'; return; }
        timer = setTimeout(function(){
          fetchFn(q, function(items){
            if (!items.length) { dd.style.display = 'none'; return; }
            dd.innerHTML = items.slice(0, 15).map(function(item){
              return '<div class="np-dd-item" style="padding:5px 8px;cursor:pointer;border-bottom:1px solid #f3f4f6;line-height:1.3" data-ref="' + self.esc(item.Ref) + '" data-label="' + self.esc(renderFn(item).label) + '" data-extra="' + self.esc(renderFn(item).extra||'') + '">'
                + '<div style="font-size:11px">' + self.esc(renderFn(item).label) + '</div>'
                + (renderFn(item).extra ? '<div style="font-size:10px;color:#9ca3af">' + self.esc(renderFn(item).extra) + '</div>' : '')
                + '</div>';
            }).join('');
            dd.style.display = 'block';
          });
        }, 280);
      });

      dd.addEventListener('mousedown', function(e){
        var item = e.target.closest('.np-dd-item');
        if (!item) return;
        var ref   = item.dataset.ref;
        var label = item.dataset.label;
        inp.value    = label;
        hidden.value = ref;
        dd.style.display = 'none';
        inp.dispatchEvent(new Event('np-selected', { bubbles: true }));
      });

      document.addEventListener('click', function(e){
        if (!inp.contains(e.target) && !dd.contains(e.target)) dd.style.display = 'none';
      });
    }

    var curSenderRef = function(){ return document.getElementById('npSenderRef').value; };

    // City
    makeAutocomplete('npCityInput', 'npCityDd', 'npCityRef',
      function(q, cb) {
        fetch('/novaposhta/api/search_city?q=' + encodeURIComponent(q) + '&sender_ref=' + encodeURIComponent(curSenderRef()))
          .then(function(r){ return r.json(); }).then(function(res){ cb(res.cities||[]); });
      },
      function(city) {
        return { label: city.Description, extra: city.SettlementTypeDescription || '' };
      }
    );

    // When city selected — reset warehouse
    document.getElementById('npCityInput').addEventListener('np-selected', function(){
      document.getElementById('npWhInput').value  = '';
      document.getElementById('npWhRef').value    = '';
      document.getElementById('npStreetInput').value = '';
      document.getElementById('npStreetRef').value   = '';
    });

    // Warehouse
    makeAutocomplete('npWhInput', 'npWhDd', 'npWhRef',
      function(q, cb) {
        var cityRef = document.getElementById('npCityRef').value;
        if (!cityRef) { cb([]); return; }
        fetch('/novaposhta/api/search_warehouse?city_ref=' + encodeURIComponent(cityRef)
          + '&q=' + encodeURIComponent(q)
          + '&sender_ref=' + encodeURIComponent(curSenderRef()))
          .then(function(r){ return r.json(); }).then(function(res){ cb(res.warehouses||[]); });
      },
      function(wh) {
        return { label: 'Відд. №' + wh.Number + (wh.ShortAddress ? ': ' + wh.ShortAddress : ''), extra: wh.Description };
      }
    );

    // Street
    makeAutocomplete('npStreetInput', 'npStreetDd', 'npStreetRef',
      function(q, cb) {
        var cityRef = document.getElementById('npCityRef').value;
        if (!cityRef) { cb([]); return; }
        fetch('/novaposhta/api/search_street?city_ref=' + encodeURIComponent(cityRef)
          + '&q=' + encodeURIComponent(q)
          + '&sender_ref=' + encodeURIComponent(curSenderRef()))
          .then(function(r){ return r.json(); }).then(function(res){ cb(res.streets||[]); });
      },
      function(s) { return { label: s.Description, extra: s.StreetsType || '' }; }
    );

    // If city_hint present — trigger search to preload warehouses dropdown hint
    if (data.recipient && data.recipient.city_hint) {
      var cityInp = document.getElementById('npCityInput');
      if (cityInp && !data.recipient.np_warehouse_ref) {
        // Leave city text as hint, user will search
      }
    }

    // ── Submit ───────────────────────────────────────────────────────────────
    document.getElementById('npTtnSubmitBtn').addEventListener('click', function() {
      var btn    = this;
      var errDiv = document.getElementById('npTtnError');
      errDiv.style.display = 'none';

      var senderRef  = document.getElementById('npSenderRef').value;
      var addrSel    = document.getElementById('npSenderAddr');
      var senderAddr = addrSel ? addrSel.value : '';
      var cityRcpRef = document.getElementById('npCityRef').value;
      var cityRcpDesc= document.getElementById('npCityInput').value;
      var phone      = document.getElementById('npRcpPhone').value.trim();
      var weight     = parseFloat(document.getElementById('npWeight').value) || 0;
      var serviceType= document.getElementById('npServiceType').value;
      var whRef      = document.getElementById('npWhRef').value;
      var whDesc     = document.getElementById('npWhInput').value;

      if (!senderRef)   { errDiv.textContent = 'Оберіть відправника';         errDiv.style.display=''; return; }
      if (!senderAddr)  { errDiv.textContent = 'Оберіть адресу відправки';    errDiv.style.display=''; return; }
      if (!cityRcpRef)  { errDiv.textContent = 'Оберіть місто одержувача';    errDiv.style.display=''; return; }
      if (!phone)       { errDiv.textContent = 'Введіть телефон одержувача';  errDiv.style.display=''; return; }
      if (weight <= 0)  { errDiv.textContent = 'Вага повинна бути > 0';       errDiv.style.display=''; return; }
      if ((serviceType === 'WarehouseWarehouse' || serviceType === 'DoorsWarehouse') && !whRef) {
        errDiv.textContent = 'Оберіть відділення одержувача'; errDiv.style.display=''; return;
      }

      // Get sender city from selected address option
      var addrOpt = addrSel ? addrSel.options[addrSel.selectedIndex] : null;
      var citySenderRef  = addrOpt ? (addrOpt.dataset.city     || '') : '';
      var citySenderDesc = addrOpt ? (addrOpt.dataset.cityDesc  || '') : '';

      // Date: convert to dd.mm.yyyy for NP API
      var dateVal = document.getElementById('npDate').value; // YYYY-MM-DD
      var dateParts = dateVal.split('-');
      var dateNp = dateParts.length === 3 ? dateParts[2]+'.'+dateParts[1]+'.'+dateParts[0] : '';

      var body = [
        'customerorder_id=' + orderId,
        'sender_ref='              + encodeURIComponent(senderRef),
        'sender_address_ref='      + encodeURIComponent(senderAddr),
        'city_sender_ref='         + encodeURIComponent(citySenderRef),
        'city_sender_desc='        + encodeURIComponent(citySenderDesc),
        'city_recipient_ref='      + encodeURIComponent(cityRcpRef),
        'city_recipient_desc='     + encodeURIComponent(cityRcpDesc),
        'service_type='            + encodeURIComponent(serviceType),
        'recipient_type=PrivatePerson',
        'recipient_last_name='     + encodeURIComponent(document.getElementById('npRcpLast').value.trim()),
        'recipient_first_name='    + encodeURIComponent(document.getElementById('npRcpFirst').value.trim()),
        'recipient_middle_name='   + encodeURIComponent(document.getElementById('npRcpMiddle').value.trim()),
        'recipient_phone='         + encodeURIComponent(phone),
        'counterparty_id='         + (data.recipient && data.recipient.counterparty_id ? data.recipient.counterparty_id : 0),
        'recipient_warehouse_ref=' + encodeURIComponent(whRef),
        'recipient_address_desc='  + encodeURIComponent(whDesc),
        'recipient_street_ref='    + encodeURIComponent(document.getElementById('npStreetRef').value),
        'recipient_building='      + encodeURIComponent(document.getElementById('npBuilding').value.trim()),
        'recipient_flat='          + encodeURIComponent(document.getElementById('npFlat').value.trim()),
        'weight='                  + weight,
        'seats_amount='            + (parseInt(document.getElementById('npSeats').value)||1),
        'cargo_type=Cargo',
        'description='             + encodeURIComponent(document.getElementById('npDesc').value.trim() || 'Товар'),
        'cost='                    + (parseInt(document.getElementById('npCost').value)||1),
        'payment_method='          + encodeURIComponent(document.getElementById('npPayMethod').value),
        'payer_type='              + encodeURIComponent(document.getElementById('npPayerType').value),
        'backward_delivery_money=' + (parseFloat(document.getElementById('npBackMoney').value)||0),
        'date='                    + encodeURIComponent(dateNp),
      ].join('&');

      btn.disabled = true; btn.textContent = 'Створення…';

      fetch('/novaposhta/api/create_ttn', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
      }).then(function(r){ return r.json(); }).then(function(res){
        btn.disabled = false; btn.textContent = 'Створити ТТН';
        if (!res.ok) {
          errDiv.textContent = res.error || 'Невідома помилка';
          errDiv.style.display = '';
          return;
        }
        showToast('ТТН ' + (res.int_doc_number||'') + ' створено');
        self.loadOrderFlow(orderId, '');
      }).catch(function(){
        btn.disabled = false; btn.textContent = 'Створити ТТН';
        errDiv.textContent = 'Мережева помилка';
        errDiv.style.display = '';
      });
    });
  },

  // ── Edit order_delivery status ────────────────────────────────────────────
  _openDeliveryEditForm: function(odId) {
    var self  = this;
    var detEl = document.getElementById('wsDetailZone');
    if (!detEl) return;
    var fd = self._flowData;
    var order = fd && fd.order;
    if (!order) return;
    self._openDeliveryForm(order, fd, odId);
  },

  // ── Delete order delivery ───────────────────────────────────────────────────
  _deleteOrderDelivery: function(odId, orderId) {
    var self = this;
    if (!confirm('Видалити цю доставку? Якщо вона була єдиною — замовлення повернеться в "В роботі".')) return;
    fetch('/counterparties/api/delete_order_delivery', {
      method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'id=' + odId + '&customerorder_id=' + orderId
    }).then(function(r){ return r.json(); }).then(function(res) {
      if (!res.ok) { alert('Помилка: ' + (res.error || '')); return; }
      showToast(res.reverted ? 'Доставку видалено, статус повернуто «В роботі»' : 'Доставку видалено');
      self.loadOrderFlow(orderId, '');
    });
  },

  // ── Inline order form (orders mode) ───────────────────────────────────────
  renderOrderForm: function(d) {
    var self  = this;
    var el    = document.getElementById('wsOrderForm');
    if (!el) return;

    var order    = d.order;
    var items    = d.items    || [];
    var demands  = d.demands  || [];

    // ── Org/employee options (injected from PHP) ──────────────────────────────
    var WS_ORGS             = <?php echo json_encode(array_values($wsOrgs)); ?>;
    var WS_EMPLOYEES        = <?php echo json_encode(array_values($wsEmployees)); ?>;
    var WS_DELIVERY_METHODS = <?php echo json_encode(array_values($wsDeliveryMethods)); ?>;
    var WS_PAYMENT_METHODS  = <?php echo json_encode(array_values($wsPaymentMethods)); ?>;

    // Build org select HTML
    var orgOpts = '<option value="">— організація —</option>';
    var orgIsVat = false;
    WS_ORGS.forEach(function(o) {
      var sel = (parseInt(order.organization_id) === parseInt(o.id)) ? ' selected' : '';
      orgOpts += '<option value="' + o.id + '"' + sel + '>' + self.esc(o.short_name || o.name) + '</option>';
      if (sel && o.vat_number) orgIsVat = true;
    });

    // Build employee select HTML
    var empOpts = '<option value="">— відповідальний —</option>';
    WS_EMPLOYEES.forEach(function(e) {
      var sel = (parseInt(order.manager_employee_id) === parseInt(e.id)) ? ' selected' : '';
      empOpts += '<option value="' + e.id + '"' + sel + '>' + self.esc(e.name) + '</option>';
    });

    // Store vat flag in order state so new items use correct default
    self._orgIsVat = orgIsVat;
    var ttnsNp   = d.ttns_np  || [];
    var ttnsUp   = d.ttns_up  || [];
    var payments = d.payments || [];
    var returns  = d.returns  || [];

    var STATUS_LABELS = {
      draft:'Чернетка', new:'Нове', confirmed:'Підтверджено', in_progress:'В роботі',
      waiting_payment:'Очікує оплату', completed:'Завершено', cancelled:'Скасовано'
    };
    var curStatus   = order.status || 'draft';
    var isCancelled = (curStatus === 'cancelled');
    var isEarly     = (curStatus === 'draft' || curStatus === 'new');
    var cpPhone     = self._activeCp && self._activeCp.phone ? self._activeCp.phone : '';
    var svgPrint = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">'
      + '<rect x="3" y="1" width="10" height="6" rx="1"/><rect x="3" y="9" width="10" height="6" rx="1"/>'
      + '<path d="M3 9.5H2a1 1 0 01-1-1V7a1 1 0 011-1h12a1 1 0 011 1v1.5a1 1 0 01-1 1h-1"/>'
      + '<circle cx="12.5" cy="7.5" r="0.7" fill="currentColor" stroke="none"/>'
      + '</svg>';
    var svgPhone = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
      + '<path d="M3 2h3l1 3-2 1a9 9 0 004 4l1-2 3 1v3a1 1 0 01-1 1A13 13 0 012 3a1 1 0 011-1z"/>'
      + '</svg>';
    var svgReturn = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">'
      + '<path d="M3 7V4a1 1 0 011-1h8"/><path d="M9 1l3 2-3 2"/>'
      + '<path d="M13 9v3a1 1 0 01-1 1H4"/><path d="M7 15l-3-2 3-2"/>'
      + '</svg>';
    var svgCancel = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">'
      + '<circle cx="8" cy="8" r="6"/>'
      + '<line x1="5.5" y1="5.5" x2="10.5" y2="10.5"/><line x1="10.5" y1="5.5" x2="5.5" y2="10.5"/>'
      + '</svg>';

    var svgSend = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round">'
      + '<path d="M1 7 L13.5 1 L9.5 13 L6.5 7.5 Z" fill="currentColor" stroke="none"/>'
      + '<line x1="6.5" y1="7.5" x2="13.5" y2="1"/>'
      + '</svg>';
    var svgPrint2 = '<svg width="15" height="14" viewBox="0 0 15 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round">'
      + '<rect x="3.5" y="0.8" width="8" height="3.5" rx="0.5"/>'
      + '<rect x="0.8" y="4.3" width="13.4" height="5.8" rx="1.2"/>'
      + '<rect x="3.5" y="9.2" width="8" height="4" rx="0.5"/>'
      + '<line x1="5.2" y1="11" x2="9.8" y2="11" stroke-width="1.1"/>'
      + '<line x1="5.2" y1="12.5" x2="8.3" y2="12.5" stroke-width="1.1"/>'
      + '<circle cx="11.8" cy="7" r="0.9" fill="currentColor" stroke="none"/>'
      + '</svg>';
    // ── Pipeline block (above header) ─────────────────────────────────────────
    var autoFlags = (d.status_auto_flags) ? d.status_auto_flags : {};
    var cancelledFrom = d.cancelled_from_status || null;
    var pipelineBlockHtml = '<div id="wsPipelineBar" class="ws-pl-block">' + self._buildPipelineBarInner(curStatus, order.id, autoFlags, null, cancelledFrom) + '</div>';

    // ── Return panel (inline, collapsible) ────────────────────────────────────
    var retPanelHtml = (!isCancelled && !isEarly)
      ? '<div class="ws-ret-panel" id="wsRetPanel">'
          + '<div class="ws-ret-head">'
          +   '<span>↩ Оформити повернення</span>'
          +   '<button type="button" id="wsRetClose">×</button>'
          + '</div>'
          + '<div class="ws-ret-body">'
          +   '<div class="ws-ret-note">Оберіть спосіб — після збереження документ з\'явиться у ланцюжку вгорі.</div>'
          +   '<div class="ws-ret-opts">'
          +     '<div class="ws-ret-opt" data-ret="novaposhta_ttn"><span class="ws-ret-opt-icon">🚚</span><span class="ws-ret-opt-lbl">Нова Пошта</span><span class="ws-ret-opt-sub">Ввести номер ТТН</span></div>'
          +     '<div class="ws-ret-opt" data-ret="ukrposhta_ttn"><span class="ws-ret-opt-icon">📬</span><span class="ws-ret-opt-lbl">Укрпошта</span><span class="ws-ret-opt-sub">Ввести номер ТТН</span></div>'
          +     '<div class="ws-ret-opt" data-ret="manual"><span class="ws-ret-opt-icon">📦</span><span class="ws-ret-opt-lbl">Інший спосіб</span><span class="ws-ret-opt-sub">Кур\'єр, особисто тощо</span></div>'
          +     '<div class="ws-ret-opt" data-ret="left_with_client"><span class="ws-ret-opt-icon">🎁</span><span class="ws-ret-opt-lbl">Залишили клієнту</span><span class="ws-ret-opt-sub">Брак, жест доброї волі</span></div>'
          +   '</div>'
          +   '<div class="ws-ret-input-row" id="wsRetInputRow" style="display:none">'
          +     '<input type="text" class="ws-ret-input" id="wsRetInput" placeholder="">'
          +     '<button type="button" class="ws-ret-save-btn" id="wsRetSave">Зберегти</button>'
          +   '</div>'
          + '</div>'
        + '</div>'
      : '';

    // ── Header (outside edit zone: number, badges, action buttons) ──────────
    var trafficBadge = '';
    if (d.traffic_source) {
      trafficBadge = '<span class="ws-traffic-badge" style="background:' + d.traffic_source.color + '22;color:' + d.traffic_source.color + ';margin-left:4px">' + self.esc(d.traffic_source.label) + (d.traffic_source.campaign ? ' · ' + self.esc(d.traffic_source.campaign) : '') + '</span>';
    }
    var headHtml = '<div class="ws-of-head">'
      + '<span class="ws-of-head-num">#' + self.esc(order.number || '—') + '</span>'
      + trafficBadge
      + (order.moment ? '<span class="ws-of-head-date">' + order.moment.substr(0,10) + '</span>' : '')
      + self.payStatusBadge(order.payment_status)
      + self.shipStatusBadge(order.shipment_status)
      + '<span class="ws-of-head-sep"></span>'
      + '<div class="ws-of-head-btns">'
      + '<a href="/customerorder/edit?id=' + order.id + '" target="_blank" class="ws-of-head-btn ws-of-icon-btn" title="Відкрити повну форму замовлення">'
      +   '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
      +   '<path d="M14 8.5V13a1 1 0 01-1 1H3a1 1 0 01-1-1V3a1 1 0 011-1h4.5"/>'
      +   '<path d="M10 2h4v4"/><path d="M7 9L14 2"/>'
      +   '</svg></a>'
      + '<button type="button" class="ws-of-head-btn ws-of-icon-btn" id="wsOaSendBtn" title="Надіслати клієнту / команди">' + svgSend + '</button>'
      + '<button type="button" class="ws-of-head-btn ws-of-icon-btn" id="wsOaPrintBtn" onclick="PrintModal.open(\'order\',' + order.id + ',0)" title="Друкувати документ">' + svgPrint2 + '</button>'
      + (cpPhone ? '<a href="tel:' + self.esc(cpPhone) + '" class="ws-of-head-btn ws-of-icon-btn" title="Подзвонити ' + self.esc(cpPhone) + '">' + svgPhone + '</a>' : '')
      + '<span class="ws-of-btns-sep"></span>'
      + '<button type="button" class="ws-of-head-btn ws-of-icon-btn ws-of-btn-edit" id="wsOfEditBtn" title="Редагувати позиції">'
      +   '<svg width="11" height="14" viewBox="0 0 11 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">'
      +   '<rect x="1.5" y="0.8" width="8" height="9" rx="1"/>'
      +   '<path d="M1.5 9.8 L5.5 13.2 L9.5 9.8"/>'
      +   '<line x1="1.5" y1="3.3" x2="9.5" y2="3.3" stroke-width="1.1"/>'
      +   '</svg></button>'
      + '<button type="button" class="ws-of-head-btn ws-of-icon-btn ws-of-save-btn" id="wsOfSaveBtn" title="Зберегти зміни" style="display:none">💾</button>'
      + '</div>'
      + '</div>';

    // Build delivery method select HTML
    var dmOpts = '<option value="">— доставка —</option>';
    WS_DELIVERY_METHODS.forEach(function(dm) {
      var sel = (order.delivery_method_id && parseInt(order.delivery_method_id) === parseInt(dm.id)) ? ' selected' : '';
      dmOpts += '<option value="' + dm.id + '"' + sel + '>' + self.esc(dm.name_uk) + '</option>';
    });

    // Build payment method select HTML
    var pmOpts = '<option value="">— оплата —</option>';
    WS_PAYMENT_METHODS.forEach(function(pm) {
      var sel = (order.payment_method_id && parseInt(order.payment_method_id) === parseInt(pm.id)) ? ' selected' : '';
      pmOpts += '<option value="' + pm.id + '"' + sel + '>' + self.esc(pm.name_uk) + '</option>';
    });

    // ── Meta row — inside edit zone (org, vat badge, employee, delivery method, payment method) ──
    var metaRowHtml = '<div class="ws-of-meta-row">'
      + '<select class="ws-of-meta-sel ws-of-org-sel" id="wsOfOrgSel" title="Організація (продавець)">' + orgOpts + '</select>'
      + (orgIsVat ? '<span class="ws-of-vat-badge" id="wsOfVatBadge">ПДВ</span>' : '<span class="ws-of-vat-badge ws-of-vat-none" id="wsOfVatBadge">без ПДВ</span>')
      + '<select class="ws-of-meta-sel ws-of-emp-sel" id="wsOfEmpSel" title="Відповідальний">' + empOpts + '</select>'
      + '<select class="ws-of-meta-sel ws-of-deliv-sel" id="wsOfDelivSel" title="Спосіб доставки">' + dmOpts + '</select>'
      + '<select class="ws-of-meta-sel ws-of-pay-sel" id="wsOfPaySel" title="Спосіб оплати">' + pmOpts + '</select>'
      + '</div>';

    // ── Items table (editable) ────────────────────────────────────────────────
    var itemsHtml = '<div class="ws-of-items-wrap"><table class="ws-of-items">'
      + '<colgroup>'
      + '<col class="ws-of-col-name"><col class="ws-of-col-qty"><col class="ws-of-col-unit"><col style="width:52px"><col class="ws-of-col-price">'
      + '<col class="ws-of-col-disc"><col class="ws-of-col-vat"><col class="ws-of-col-sum"><col class="ws-of-col-del">'
      + '</colgroup>'
      + '<thead><tr>'
      + '<th class="left">Товар</th><th>К-ть</th><th>Од.</th><th title="Залишок на складі (Склад)">Зал.</th><th>Ціна</th>'
      + '<th title="Знижка %">Зн%</th><th>ПДВ</th><th>Сума</th><th></th>'
      + '</tr></thead><tbody>';

    items.forEach(function(it) {
      var qty   = parseFloat(it.quantity)         || 0;
      var ship  = parseFloat(it.shipped_quantity) || 0;
      var disc  = parseFloat(it.discount_percent) || 0;
      var vat   = parseFloat(it.vat_rate)         || 0;
      var sum   = parseFloat(it.sum)              || 0;
      var stock = (it.stock_sklad !== null && it.stock_sklad !== undefined && it.stock_sklad !== '') ? parseInt(it.stock_sklad, 10) : null;
      var stkLow = stock !== null && stock < qty;

      var nameTitle = (it.article ? '[' + it.article + '] ' : '') + (it.name || '');
      var shipNote  = ship > 0 && ship < qty ? ' <span style="color:#ea580c;font-size:9px">відвант:' + ship + '</span>' : '';
      var stkCell   = stock !== null
        ? '<td class="ws-of-stk-cell' + (stkLow ? ' ws-of-stk-warn' : '') + '" title="Залишок на складі">' + stock + '</td>'
        : '<td class="ws-of-stk-cell ws-of-stk-unknown" title="Товар не знайдено у прайсі Складу">—</td>';

      itemsHtml += '<tr class="ws-of-items-body" data-item-id="' + it.id + '" data-local-id="' + it.id + '" data-sum-changed="0">'
        + '<td class="ws-of-name-cell" title="' + self.esc(nameTitle) + '">'
        +   (it.article ? (it.product_id ? '<a class="ws-of-sku" href="/catalog?selected=' + parseInt(it.product_id, 10) + '" target="_blank" title="Відкрити в каталозі">' + self.esc(it.article) + '</a>' : '<span class="ws-of-sku">' + self.esc(it.article) + '</span>') : '')
        +   '<span class="ws-of-nm">' + self.esc(it.name || '—') + '</span>'
        +   shipNote
        + '</td>'
        + '<td><input class="ws-cell-input" data-field="quantity" value="' + qty + '" type="text"></td>'
        + '<td class="ws-of-unit-cell">' + self.esc(it.unit || 'шт') + '</td>'
        + stkCell
        + '<td><input class="ws-cell-input" data-field="price" value="' + parseFloat(it.price).toFixed(2) + '" type="text"></td>'
        + '<td><input class="ws-cell-input" data-field="discount_percent" value="' + (disc > 0 ? disc : '') + '" placeholder="0" type="text"></td>'
        + '<td><select class="ws-cell-sel" data-field="vat_rate">'
        +     '<option value="0"' + (vat === 0 ? ' selected' : '') + '>—</option>'
        +     '<option value="20"' + (vat === 20 ? ' selected' : '') + '>20%</option>'
        +   '</select></td>'
        + '<td><input class="ws-cell-input sum-field" data-field="sum_row" value="' + sum.toFixed(2) + '" type="text"></td>'
        + '<td><button type="button" class="ws-item-del-btn" title="Видалити рядок">×</button></td>'
        + '</tr>';
    });

    itemsHtml += '</tbody></table></div>';

    // Add product block — OUTSIDE scroll wrapper; disabled until edit mode
    var addProductHtml = '<div class="ws-of-add-product">'
      + '<input type="text" class="ws-item-search" id="wsItemSearch" placeholder="+ Додати товар… (увімкніть режим ✏)" autocomplete="off" disabled>'
      + '</div>';

    // Edit mode banner (hidden by default, shown when editing)
    var editBarHtml = '<div class="ws-of-edit-bar" id="wsOfEditBar">'
      + '<span class="ws-of-edit-bar-label">✏ Режим редагування</span>'
      + '<span class="ws-of-edit-bar-hint">Зміни не збережено — натисніть 💾 або Скасувати</span>'
      + '<button type="button" class="ws-of-edit-bar-cancel" id="wsOfEditCancel">Скасувати</button>'
      + '<button type="button" class="ws-of-edit-bar-done" id="wsOfEditDone">💾 Зберегти</button>'
      + '</div>';

    // ── Footer ────────────────────────────────────────────────────────────────
    var sumDiscount = parseFloat(order.sum_discount) || 0;
    var sumVat      = parseFloat(order.sum_vat)      || 0;
    var sumTotal    = parseFloat(order.sum_total)    || 0;

    var footHtml = '<div class="ws-of-foot">'
      + '<div class="ws-of-foot-bottom">'
      +   '<div class="ws-of-foot-comment">'
      +     '<textarea id="wsOfComment" placeholder="Коментар до замовлення…" readonly>' + self.esc(order.description || '') + '</textarea>'
      +   '</div>'
      +   '<div class="ws-of-foot-totals" id="wsOfTotals">'
      +     self._footTotalsHtml(sumDiscount, sumVat, sumTotal)
      +   '</div>'
      + '</div>'
      + '</div>';

    // ── Doc tabs ──────────────────────────────────────────────────────────────
    var demandHtml = demands.length > 0
      ? demands.map(function(dem) {
          return '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:7px;padding:8px 10px;margin-bottom:6px">'
            + '<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">'
            + '<span style="font-size:11px;font-weight:700">#' + self.esc(dem.number||'') + '</span>'
            + '<span style="font-size:10px;color:#2563eb">' + self.esc(dem.status||'') + '</span>'
            + '<span style="margin-left:auto;font-size:11px;font-weight:700;color:#2563eb">₴' + self.formatNum(dem.sum_total) + '</span>'
            + '</div>'
            + '<div style="font-size:10px;color:#6b7280">Оплачено: ₴' + self.formatNum(dem.sum_paid)
            + (dem.moment ? ' · ' + dem.moment.substr(0,10) : '') + '</div>'
            + '</div>';
        }).join('')
      : '<div style="font-size:11px;color:#d1d5db;padding:8px 0">Відвантажень немає</div>';

    var ttnHtml = '';
    ttnsNp.forEach(function(t) {
      var num = t.int_doc_number ? String(t.int_doc_number) : '';
      ttnHtml += '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:7px;padding:8px 10px;margin-bottom:6px">'
        + '<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">'
        + '<span style="font-size:10px;font-weight:700;color:#ea580c">НП</span>'
        + '<span style="font-size:10px;font-weight:600">' + self.esc(num) + '</span>'
        + '</div>'
        + '<div style="font-size:10px;color:#6b7280">' + self.esc(self._npStatusLabel(t)) + (t.city_recipient_desc ? ' · ' + self.esc(t.city_recipient_desc) : '') + '</div>'
        + (t.backward_delivery_money > 0 ? '<div style="font-size:10px;color:#ea580c">Накл.пл.: ₴' + self.formatNum(t.backward_delivery_money) + '</div>' : '')
        + (num ? '<a href="https://novaposhta.ua/tracking/' + encodeURIComponent(num) + '" target="_blank" style="font-size:10px;color:#7c3aed">Відстежити →</a>' : '')
        + '</div>';
    });
    ttnsUp.forEach(function(t) {
      ttnHtml += '<div style="background:#ecfeff;border:1px solid #a5f3fc;border-radius:7px;padding:8px 10px;margin-bottom:6px">'
        + '<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">'
        + '<span style="font-size:10px;font-weight:700;color:#0891b2">УП</span>'
        + '<span style="font-size:10px;font-weight:600">' + self.esc(t.barcode||'') + '</span>'
        + '</div>'
        + '<div style="font-size:10px;color:#6b7280">' + self.esc(t.lifecycle_status||'') + (t.recipient_city ? ' · ' + self.esc(t.recipient_city) : '') + '</div>'
        + '</div>';
    });
    if (!ttnHtml) ttnHtml = '<div style="font-size:11px;color:#d1d5db;padding:8px 0">ТТН немає</div>';

    var payHtml = '';
    if (d.sum_paid > 0) payHtml += '<div class="ws-of-row"><span class="ws-of-lbl">Оплачено (МС)</span><span style="font-size:12px;font-weight:700;color:#16a34a">₴' + self.formatNum(d.sum_paid) + '</span></div>';
    payments.forEach(function(p) {
      payHtml += '<div class="ws-of-row"><span class="ws-of-lbl">' + (p.source==='bank'?'Банк':'Каса') + '</span>'
               + '<span style="font-size:11px">₴' + self.formatNum(p.amount) + ' <span style="color:#9ca3af">· ' + (p.moment?p.moment.substr(0,10):'') + '</span></span></div>';
    });
    if (!payHtml) payHtml = '<div style="font-size:11px;color:#d1d5db;padding:8px 0">Платежів не знайдено</div>';

    var retHtml = returns.length > 0
      ? returns.map(function(r) {
          return '<div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:7px;padding:8px 10px;margin-bottom:6px">'
            + '<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">'
            + '<span style="font-size:11px;font-weight:700">#' + self.esc(r.number||'') + '</span>'
            + '<span style="margin-left:auto;font-size:11px;font-weight:700;color:#dc2626">₴' + self.formatNum(r.sum_total) + '</span>'
            + '</div>'
            + (r.moment ? '<div style="font-size:10px;color:#6b7280">' + r.moment.substr(0,10) + '</div>' : '')
            + '</div>';
        }).join('')
      : '<div style="font-size:11px;color:#d1d5db;padding:8px 0">Повернень немає</div>';

    el.innerHTML = pipelineBlockHtml + retPanelHtml + headHtml
      + '<div class="ws-of-edit-zone" id="wsOfEditZone">'
      +   editBarHtml + metaRowHtml + itemsHtml + addProductHtml + footHtml
      + '</div>';

    var retStatuses  = ['in_progress', 'completed'];
    var hasRetStatus = retStatuses.indexOf(curStatus) !== -1;
    var hasDemand    = demands.filter(function(dem) {
      return dem.status && ['cancelled', 'returned'].indexOf(dem.status) === -1;
    }).length > 0;
    var hasDelivery  = (d.order_deliveries || []).length > 0
                    || (d.ttns_np || []).length > 0
                    || (d.ttns_up || []).length > 0;
    var retAllowed   = hasRetStatus && hasDemand && hasDelivery;
    self._syncCreateBarActions(isCancelled, isEarly, retAllowed);

    el.dataset.orderId = order.id;

    self._bindOrderActions(el, d, order);

    // ── Bind events ───────────────────────────────────────────────────────────

    // Row recalc + sync to state
    el.querySelectorAll('tr.ws-of-items-body').forEach(function(tr) {
      self._bindItemRow(tr);
    });

    // Delete buttons — state only (saved on 💾)
    el.querySelectorAll('.ws-item-del-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var tr = btn.closest('tr');
        if (!tr) return;
        var localId = tr.dataset.localId;
        if (self._orderState) {
          var items = self._orderState.items || [];
          for (var i = 0; i < items.length; i++) {
            if (String(items[i]._localId) === String(localId)) {
              items[i]._deleted = true;
              break;
            }
          }
        }
        tr.remove();
        self._updateFooter();
      });
    });

    // Product search
    self._bindProductSearch(el, d, order);

    // Edit mode helpers
    var editBtn    = el.querySelector('#wsOfEditBtn');
    var saveBtn    = el.querySelector('#wsOfSaveBtn');
    var editDone   = el.querySelector('#wsOfEditDone');
    var searchInp  = el.querySelector('#wsItemSearch');
    var editZone   = el.querySelector('#wsOfEditZone');

    var commentEl2 = el.querySelector('#wsOfComment');
    function enterEditMode() {
      if (editZone) editZone.classList.add('ws-of-editing');
      if (editBtn)   editBtn.style.display   = 'none';
      if (saveBtn)   saveBtn.style.display   = '';
      if (searchInp) { searchInp.disabled = false; searchInp.placeholder = '+ Додати товар…'; }
      if (commentEl2) commentEl2.readOnly = false;
      var firstInp = el.querySelector('tr.ws-of-items-body .ws-cell-input');
      if (firstInp) { firstInp.focus(); firstInp.select(); }
    }
    function exitEditMode() {
      if (editZone) editZone.classList.remove('ws-of-editing');
      if (editBtn)   { editBtn.style.display = ''; }
      if (saveBtn)   { saveBtn.style.display = 'none'; saveBtn.classList.remove('ws-of-save-btn-dirty'); }
      if (searchInp) { searchInp.disabled = true; searchInp.placeholder = '+ Додати товар… (увімкніть режим ✏)'; searchInp.value = ''; }
      if (commentEl2) commentEl2.readOnly = true;
    }

    var cancelBtn = el.querySelector('#wsOfEditCancel');
    if (editBtn)   editBtn.addEventListener('click',  enterEditMode);
    if (saveBtn)   saveBtn.addEventListener('click',   function() { self._saveOrder(el, exitEditMode); });
    if (editDone)  editDone.addEventListener('click',  function() { self._saveOrder(el, exitEditMode); });
    if (cancelBtn) cancelBtn.addEventListener('click', function() { self._cancelEdit(d); exitEditMode(); });

    // Dirty: show 💾 on any change
    el.querySelectorAll('tr.ws-of-items-body .ws-cell-input').forEach(function(inp) {
      inp.addEventListener('input', function() {
        if (saveBtn) { saveBtn.style.display = ''; saveBtn.classList.add('ws-of-save-btn-dirty'); }
      });
    });
    el.querySelectorAll('tr.ws-of-items-body .ws-cell-sel').forEach(function(sel) {
      sel.addEventListener('change', function() {
        if (saveBtn) { saveBtn.style.display = ''; saveBtn.classList.add('ws-of-save-btn-dirty'); }
      });
    });

    // Action bar: Send → command menu, Print already handled via onclick
    var oaSendBtn = el.querySelector('#wsOaSendBtn');
    if (oaSendBtn) {
      oaSendBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        self._showCmdMenu(oaSendBtn, d);
      });
    }

    // Restore edit mode after reload (add/delete item)
    if (self._restoreEditMode) {
      self._restoreEditMode = false;
      enterEditMode();
      // Scroll to last item row
      var lastRow = el.querySelector('tr.ws-of-items-body:last-of-type');
      if (lastRow) lastRow.scrollIntoView({ block: 'nearest' });
    }

    // Comment dirty flag (saved with 💾, not on blur)
    if (commentEl2) {
      commentEl2.addEventListener('input', function() {
        if (saveBtn) { saveBtn.style.display = ''; saveBtn.classList.add('ws-of-save-btn-dirty'); }
      });
    }

    // ── Pipeline bar bindings ─────────────────────────────────────────────────
    self._bindPipelineBar(el);

    // ── Org + Employee selectors — sync to state on change, save with 💾 ────
    var orgSel   = el.querySelector('#wsOfOrgSel');
    var vatBadge = el.querySelector('#wsOfVatBadge');
    var empSel   = el.querySelector('#wsOfEmpSel');

    function updateVatBadge(isVat) {
      if (!vatBadge) return;
      vatBadge.textContent = isVat ? 'ПДВ' : 'без ПДВ';
      vatBadge.classList.toggle('ws-of-vat-none', !isVat);
    }

    if (orgSel) {
      orgSel.addEventListener('change', function() {
        var selOrg = null;
        WS_ORGS.forEach(function(o) { if (String(o.id) === orgSel.value) selOrg = o; });
        var isVat = !!(selOrg && selOrg.vat_number);
        self._orgIsVat = isVat;
        updateVatBadge(isVat);
        // Sync to state — will be saved with 💾
        if (self._orderState) self._orderState.order.organization_id = orgSel.value ? parseInt(orgSel.value) : null;
        if (saveBtn) { saveBtn.style.display = ''; saveBtn.classList.add('ws-of-save-btn-dirty'); }
      });
    }

    if (empSel) {
      empSel.addEventListener('change', function() {
        if (self._orderState) self._orderState.order.manager_employee_id = empSel.value ? parseInt(empSel.value) : null;
        if (saveBtn) { saveBtn.style.display = ''; saveBtn.classList.add('ws-of-save-btn-dirty'); }
      });
    }

    // ── Delivery method selector — saves immediately (separate API) ───────────
    var delivSel = el.querySelector('#wsOfDelivSel');
    if (delivSel) {
      delivSel.addEventListener('change', function() {
        var dmId = delivSel.value ? parseInt(delivSel.value) : 0;
        fetch('/counterparties/api/save_delivery_method', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'order_id=' + orderId + '&delivery_method_id=' + dmId
        }).then(function(r){ return r.json(); }).then(function(res) {
          if (!res.ok) { showToast('Помилка збереження способу доставки', true); return; }
          // Update local state so _openCreateForm sees the new method
          if (self._flowData && self._flowData.order) {
            var dm = WS_DELIVERY_METHODS.filter(function(x){ return parseInt(x.id) === dmId; })[0];
            self._flowData.order.delivery_method_id   = dmId || null;
            self._flowData.order.delivery_method_code = dm ? dm.code    : null;
            self._flowData.order.delivery_method_name = dm ? dm.name_uk : null;
            self._flowData.order.delivery_method_has_ttn = dm ? dm.has_ttn : 0;
          }
          if (self._orderState && self._orderState.order) {
            self._orderState.order.delivery_method_id = dmId || null;
          }
          // Refresh empty delivery node label in the flow graph
          var chainEl = document.getElementById('wsFlowChain');
          if (chainEl) {
            var emptyDeliv = chainEl.querySelector('.wf-node.wf-empty[data-type="delivery"] .wf-node-id');
            if (emptyDeliv && self._flowData) {
              var dmName = self._flowData.order && self._flowData.order.delivery_method_name;
              emptyDeliv.textContent = dmName ? '+ ' + dmName : '+ Доставка';
            }
          }
        });
      });
    }

    // ── Payment method selector — saves immediately (separate API) ────────────
    var paySel = el.querySelector('#wsOfPaySel');
    if (paySel) {
      paySel.addEventListener('change', function() {
        var pmId = paySel.value ? parseInt(paySel.value) : 0;
        fetch('/counterparties/api/save_payment_method', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'order_id=' + orderId + '&payment_method_id=' + pmId
        }).then(function(r){ return r.json(); }).then(function(res) {
          if (!res.ok) { showToast('Помилка збереження способу оплати', true); return; }
          if (self._flowData && self._flowData.order) {
            var pm = WS_PAYMENT_METHODS.filter(function(x){ return parseInt(x.id) === pmId; })[0];
            self._flowData.order.payment_method_id   = pmId || null;
            self._flowData.order.payment_method_code = pm ? pm.code    : null;
            self._flowData.order.payment_method_name = pm ? pm.name_uk : null;
          }
          if (self._orderState && self._orderState.order) {
            self._orderState.order.payment_method_id = pmId || null;
          }
        });
      });
    }

    self._applySelectColors(el);
  },

  _applySelectColors: function(el) {
    var delivColors = {
      '1': '#6b7280', // pickup — gray
      '2': '#ea580c', // courier — orange
      '3': '#0f766e', // novaposhta — teal
      '4': '#0891b2', // ukrposhta — cyan
    };
    var payColors = {
      '1': '#1d4ed8', // bank_company — blue
      '2': '#4f46e5', // bank_personal — indigo
      '3': '#16a34a', // cash — green
      '4': '#ea580c', // cash_on_delivery — orange
      '5': '#7c3aed', // online — purple
    };
    var delivSel = el.querySelector('#wsOfDelivSel');
    var paySel   = el.querySelector('#wsOfPaySel');
    if (delivSel) {
      delivSel.style.color = delivColors[delivSel.value] || '#9ca3af';
      delivSel.addEventListener('change', function() {
        delivSel.style.color = delivColors[delivSel.value] || '#9ca3af';
      });
    }
    if (paySel) {
      paySel.style.color = payColors[paySel.value] || '#9ca3af';
      paySel.addEventListener('change', function() {
        paySel.style.color = payColors[paySel.value] || '#9ca3af';
      });
    }
  },

  _footTotalsHtml: function(disc, vat, total) {
    var html = '';
    if (disc > 0) html += '<div class="ws-of-foot-line"><span class="ws-of-foot-lbl">Знижка</span><span class="ws-of-foot-val">−₴' + this.formatNum(disc) + '</span></div>';
    if (vat  > 0) html += '<div class="ws-of-foot-line"><span class="ws-of-foot-lbl">ПДВ</span><span class="ws-of-foot-val">₴' + this.formatNum(vat) + '</span></div>';
    html += '<div class="ws-of-foot-line"><span class="ws-of-foot-lbl">Разом</span><span class="ws-of-foot-val total">₴' + this.formatNum(total) + '</span></div>';
    return html;
  },

  _bindItemRow: function(tr) {
    var self   = this;
    var inputs = tr.querySelectorAll('.ws-cell-input');
    var vatSel = tr.querySelector('.ws-cell-sel[data-field="vat_rate"]');

    function syncToState() {
      var localId = tr.dataset.localId;
      if (!self._orderState) return;
      var item = null;
      for (var i = 0; i < self._orderState.items.length; i++) {
        if (String(self._orderState.items[i]._localId) === String(localId)) {
          item = self._orderState.items[i]; break;
        }
      }
      if (!item) return;

      function v(field) {
        var inp = tr.querySelector('[data-field="' + field + '"]');
        return inp ? (parseFloat(inp.value) || 0) : (parseFloat(item[field]) || 0);
      }

      item.quantity         = v('quantity');
      item.price            = v('price');
      item.discount_percent = v('discount_percent');
      item.vat_rate         = vatSel ? (parseFloat(vatSel.value) || 0) : (parseFloat(item.vat_rate) || 0);

      // Bidirectional: if sum field was changed, back-calc price from entered sum
      var sumWasEdited = (tr.dataset.sumChanged === '1');
      var enteredSum   = null;
      if (sumWasEdited) {
        enteredSum = v('sum_row');
        var factor = 1 - item.discount_percent / 100;
        item.price = (item.quantity > 0 && factor > 0)
          ? Math.round(enteredSum / item.quantity / factor * 100) / 100 : 0;
        var priceInp = tr.querySelector('[data-field="price"]');
        if (priceInp) priceInp.value = item.price.toFixed(2);
      }

      self._calcItem(item);

      // When user edited sum: preserve their entered value (don't let rounding overwrite it)
      if (sumWasEdited && enteredSum !== null) item.sum = enteredSum;

      // Update sum input in DOM only when qty/price changed (not when user is editing sum)
      var sumInp = tr.querySelector('[data-field="sum_row"]');
      if (sumInp && !sumWasEdited) sumInp.value = item.sum.toFixed(2);

      tr.dataset.sumChanged = '0';
      self._updateFooter();
    }

    inputs.forEach(function(inp) {
      inp.addEventListener('input', function() {
        if (inp.dataset.field === 'sum_row') tr.dataset.sumChanged = '1';
        else tr.dataset.sumChanged = '0';
        syncToState();
      });
    });
    if (vatSel) {
      vatSel.addEventListener('change', syncToState);
    }
  },

  _calcItem: function(item) {
    var qty  = Math.max(parseFloat(item.quantity)  || 0, 0);
    var price = parseFloat(item.price)             || 0;
    var disc  = parseFloat(item.discount_percent)  || 0;
    var vat   = parseFloat(item.vat_rate)          || 0;
    var gross   = Math.round(qty * price * 100) / 100;
    var discAmt = Math.round(gross * disc / 100 * 100) / 100;
    var sumRow  = Math.round((gross - discAmt) * 100) / 100;
    var vatAmt  = vat > 0 ? Math.round((sumRow - sumRow / (1 + vat / 100)) * 100) / 100 : 0;
    item.sum             = sumRow;
    item.discount_amount = discAmt;
    item.vat_amount      = vatAmt;
    return item;
  },

  _updateFooter: function() {
    if (!this._orderState) return;
    var items = this._orderState.items || [];
    var sumDisc = 0, sumVat = 0, sumTotal = 0;
    items.forEach(function(it) {
      if (it._deleted) return;
      sumDisc  += parseFloat(it.discount_amount) || 0;
      sumVat   += parseFloat(it.vat_amount)      || 0;
      sumTotal += parseFloat(it.sum)             || 0;
    });
    this._orderState.order.sum_discount = Math.round(sumDisc  * 100) / 100;
    this._orderState.order.sum_vat      = Math.round(sumVat   * 100) / 100;
    this._orderState.order.sum_total    = Math.round(sumTotal * 100) / 100;
    var totalsEl = document.getElementById('wsOfTotals');
    if (totalsEl) totalsEl.innerHTML = this._footTotalsHtml(
      this._orderState.order.sum_discount,
      this._orderState.order.sum_vat,
      this._orderState.order.sum_total
    );
  },

  _buildItemRowHtml: function(it) {
    var self = this;
    var qty  = parseFloat(it.quantity)         || 0;
    var disc = parseFloat(it.discount_percent) || 0;
    var vat  = parseFloat(it.vat_rate)         || 0;
    var sum  = parseFloat(it.sum)              || 0;
    var stk  = parseFloat(it.stock_quantity)   || 0;
    var res  = parseFloat(it.reserved_quantity)|| 0;
    var stkHint = (stk > 0 || res > 0)
      ? ' <span class="ws-of-stk-hint" title="Залишок/Резерв">(' + (stk||'—') + '/' + (res||'—') + ')</span>' : '';
    return '<td class="ws-of-name-cell">'

      + (it.article ? (it.product_id ? '<a class="ws-of-sku" href="/catalog?selected=' + parseInt(it.product_id, 10) + '" target="_blank" title="Відкрити в каталозі">' + self.esc(it.article) + '</a>' : '<span class="ws-of-sku">' + self.esc(it.article) + '</span>') : '')
      + '<span class="ws-of-nm">' + self.esc(it.name || it.product_name || '—') + '</span>'
      + stkHint
      + '</td>'
      + '<td><input class="ws-cell-input" data-field="quantity" value="' + qty + '" type="text"></td>'
      + '<td class="ws-of-unit-cell">' + self.esc(it.unit || 'шт') + '</td>'
      + '<td><input class="ws-cell-input" data-field="price" value="' + parseFloat(it.price||0).toFixed(2) + '" type="text"></td>'
      + '<td><input class="ws-cell-input" data-field="discount_percent" value="' + (disc > 0 ? disc : '') + '" placeholder="0" type="text"></td>'
      + '<td><select class="ws-cell-sel" data-field="vat_rate">'
      +     '<option value="0"' + (vat === 0 ? ' selected' : '') + '>—</option>'
      +     '<option value="20"' + (vat === 20 ? ' selected' : '') + '>20%</option>'
      +   '</select></td>'
      + '<td><input class="ws-cell-input sum-field" data-field="sum_row" value="' + sum.toFixed(2) + '" type="text"></td>'
      + '<td><button type="button" class="ws-item-del-btn" title="Видалити рядок">×</button></td>';
  },

  _saveOrder: function(el, onDone) {
    var self = this;
    if (!self._orderState) return;
    var ord     = self._orderState.order;
    var orderId = ord.id;
    var version = parseInt(ord.version) || 0;
    var commentEl = el ? el.querySelector('#wsOfComment') : null;
    var description = commentEl ? commentEl.value : (ord.description || '');
    var status      = ord.status || '';
    var items   = self._orderState.items || [];
    var orgId   = ord.organization_id    != null ? parseInt(ord.organization_id)    : '';
    var empId   = ord.manager_employee_id != null ? parseInt(ord.manager_employee_id) : '';
    // Also read current selectors (user may have changed without triggering state sync)
    var orgSel = document.getElementById('wsOfOrgSel');
    var empSel = document.getElementById('wsOfEmpSel');
    var paySel   = document.getElementById('wsOfPaySel');
    var delivSel = document.getElementById('wsOfDelivSel');
    if (orgSel && orgSel.value) orgId = parseInt(orgSel.value);
    if (empSel)                 empId = empSel.value ? parseInt(empSel.value) : '';
    var pmId = ord.payment_method_id != null ? parseInt(ord.payment_method_id) : '';
    if (paySel) pmId = paySel.value ? parseInt(paySel.value) : '';
    var dmId = ord.delivery_method_id != null ? parseInt(ord.delivery_method_id) : '';
    if (delivSel) dmId = delivSel.value ? parseInt(delivSel.value) : '';
    fetch('/counterparties/api/save_order', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'order_id='             + encodeURIComponent(orderId)
          + '&version='             + encodeURIComponent(version)
          + '&description='         + encodeURIComponent(description)
          + '&status='              + encodeURIComponent(status)
          + '&organization_id='     + encodeURIComponent(orgId)
          + '&manager_employee_id=' + encodeURIComponent(empId)
          + '&payment_method_id='   + encodeURIComponent(pmId)
          + '&delivery_method_id='  + encodeURIComponent(dmId)
          + '&items='               + encodeURIComponent(JSON.stringify(items))
    }).then(function(r){ return r.json(); }).then(function(res) {
      if (res.conflict) {
        if (confirm('Замовлення було змінено іншим користувачем.\nОновити та втратити свої зміни?')) {
          self.loadOrderFlow(orderId, self.currentCp && self.currentCp.cp ? (self.currentCp.cp.id_ms||'') : '');
        }
        return;
      }
      if (!res.ok) { showToast('Помилка: ' + (res.error||''), true); return; }
      // Update state from server response
      var stateItems = (res.items||[]).map(function(it) {
        var copy = JSON.parse(JSON.stringify(it));
        copy._localId = String(it.id);
        return copy;
      });
      self._orderState    = { order: res.order, items: stateItems };
      self._orderOriginal = JSON.parse(JSON.stringify(self._orderState));
      // Update flow data
      if (self._flowData) {
        self._flowData.order = res.order;
        self._flowData.items = res.items;
      }
      showToast('Збережено ✓');
      if (onDone) onDone();
      // Re-render form with fresh data
      if (self._flowData) self.renderOrderForm(self._flowData);
    }).catch(function() { showToast('Помилка з\'єднання', true); });
  },

  _cancelEdit: function(flowData) {
    var self = this;
    if (!self._orderOriginal) return;
    self._orderState = JSON.parse(JSON.stringify(self._orderOriginal));
    if (self._flowData) {
      self._flowData.order = self._orderState.order;
      self._flowData.items = self._orderState.items;
      self.renderOrderForm(self._flowData);
    }
    showToast('Зміни скасовано');
  },

  _bindProductSearch: function(el, flowData, order) {
    var self    = this;
    var inp     = el.querySelector('#wsItemSearch');
    var orderId = el.dataset.orderId;
    if (!inp) return;
    var timer = null;
    var dd    = null;

    function removeDd() {
      if (dd) { dd.remove(); dd = null; }
    }

    function buildDd(list) {
      removeDd();
      if (!list.length) return;
      dd = document.createElement('div');
      dd.className = 'ws-item-search-dd';
      dd.innerHTML = list.slice(0, 14).map(function(p) {
        return '<div class="ws-item-search-opt" data-pid="' + p.product_id + '">'
          + '<span class="ws-item-search-opt-art">' + self.esc(p.product_article||'') + '</span>'
          + self.esc(p.name||'') + '</div>';
      }).join('');
      document.body.appendChild(dd);
      var rect = inp.getBoundingClientRect();
      dd.style.cssText = 'position:fixed;z-index:9999;top:' + (rect.bottom+2) + 'px;left:' + rect.left + 'px;min-width:' + Math.max(rect.width,240) + 'px;display:block;';

      dd.querySelectorAll('.ws-item-search-opt[data-pid]').forEach(function(opt) {
        opt.addEventListener('mousedown', function(e) {
          e.preventDefault();
          var pid = opt.dataset.pid;
          // Find product in list
          var product = null;
          for (var j = 0; j < list.length; j++) {
            if (String(list[j].product_id) === String(pid)) { product = list[j]; break; }
          }
          removeDd();
          inp.value = '';
          if (!product || !self._orderState) return;

          // Create new state item
          var localId = 'n' + Date.now();
          var newItem = {
            _localId:          localId,
            id:                null,
            product_id:        parseInt(product.product_id),
            product_name:      product.name || '',
            name:              product.name || '',
            sku:               product.product_article || '',
            article:           product.product_article || '',
            unit:              product.unit || '',
            quantity:          1,
            price:             parseFloat(product.price) || 0,
            discount_percent:  0,
            vat_rate:          parseFloat(product.vat) || (self._orgIsVat ? 20 : 0),
            stock_quantity:    parseFloat(product.quantity) || 0,
            shipped_quantity:  0,
            reserved_quantity: 0,
            weight:            parseFloat(product.weight) || 0,
            sum: 0, discount_amount: 0, vat_amount: 0,
          };
          self._calcItem(newItem);
          self._orderState.items.push(newItem);

          // Insert row in DOM
          var tbody = el.querySelector('.ws-of-items tbody');
          if (tbody) {
            var tr = document.createElement('tr');
            tr.className    = 'ws-of-items-body';
            tr.dataset.localId    = localId;
            tr.dataset.itemId     = '';
            tr.dataset.sumChanged = '0';
            tr.innerHTML = self._buildItemRowHtml(newItem);
            tbody.appendChild(tr);
            self._bindItemRow(tr);
            tr.scrollIntoView({block:'nearest'});
            // Focus qty input
            var qtyInp = tr.querySelector('[data-field="quantity"]');
            if (qtyInp) { qtyInp.focus(); qtyInp.select(); }
          }
          self._updateFooter();
        });
      });
    }

    inp.addEventListener('input', function() {
      clearTimeout(timer);
      var q = inp.value.trim();
      if (q.length < 2) { removeDd(); return; }
      timer = setTimeout(function() {
        fetch('/customerorder/search_product?q=' + encodeURIComponent(q))
          .then(function(r){ return r.json(); })
          .then(function(res) { buildDd((res.ok && res.items) ? res.items : []); })
          .catch(function(){ removeDd(); });
      }, 250);
    });

    inp.addEventListener('blur',    function() { setTimeout(removeDd, 120); });
    inp.addEventListener('keydown', function(e) { if (e.key === 'Escape') { removeDd(); inp.value=''; } });
  },

  // ── Pipeline helpers ────────────────────────────────────────────────────────

  _PIPELINE_STEPS: [
    { label: 'Нове',          jump: 'new',             values: ['draft','new'] },
    { label: 'Прийнято',      jump: 'confirmed',       values: ['confirmed'] },
    { label: 'Очік. оплати',  jump: 'waiting_payment', values: ['waiting_payment'] },
    { label: 'В роботі',      jump: 'in_progress',     values: ['in_progress'] },
    { label: 'Виконано',      jump: 'completed',       values: ['completed'] },
  ],

  _PIPELINE_NEXT: {
    'draft':             { label: 'Прийняти →',      status: 'confirmed' },
    'new':               { label: 'Прийняти →',      status: 'confirmed' },
    'confirmed':         { label: 'Очік. оплату →',  status: 'waiting_payment' },
    'waiting_payment':   { label: 'В роботу →',      status: 'in_progress' },
    'in_progress':       { label: 'Завершити →',     status: 'completed' },
  },

  _buildPipelineBarInner: function(status, orderId, autoFlags, rightHtml, cancelledFromStatus) {
    var self  = this;
    autoFlags = autoFlags || {};
    var steps = self._PIPELINE_STEPS;
    var cancelled = (status === 'cancelled');

    var curIdx = -1;
    steps.forEach(function(s, i) {
      if (s.values.indexOf(status) !== -1) curIdx = i;
    });

    // When cancelled — find the index of the status from which order was cancelled
    var cancelledFromIdx = -1;
    if (cancelled && cancelledFromStatus) {
      steps.forEach(function(s, i) {
        if (s.values.indexOf(cancelledFromStatus) !== -1) cancelledFromIdx = i;
      });
    }

    var stepsHtml = '';
    steps.forEach(function(step, i) {
      var cls = '';
      if (!cancelled) {
        if (i < curIdx)        cls = 'done';
        else if (i === curIdx) cls = 'current';
      } else {
        // Only steps up to and including the cancelled-from status are done (green)
        if (cancelledFromIdx >= 0 && i <= cancelledFromIdx) cls = 'done';
        // else cls stays '' (grey)
      }
      var dotContent = (cls === 'done') ? '&#10003;' : '';
      var isAuto = !!(autoFlags[step.jump]);
      if (i > 0) {
        var connDone = (!cancelled && i <= curIdx) || (cancelled && cancelledFromIdx >= 0 && i <= cancelledFromIdx);
        stepsHtml += '<div class="ws-pl-conn' + (connDone ? ' done-conn' : '') + '"></div>';
      }
      stepsHtml += '<div class="ws-pl-step ' + cls + '" data-pl-status="' + step.jump + '">'
        + '<div class="ws-pl-dot">' + dotContent + '</div>'
        + '<div class="ws-pl-lbl">' + step.label + '</div>'
        + (isAuto ? '<div class="ws-pl-auto">авто</div>' : '')
        + '</div>';
    });

    if (cancelled) {
      // Connector to Скасовано: green only if there was a known previous status
      var connToCancelled = cancelledFromIdx >= 0;
      stepsHtml += '<div class="ws-pl-conn' + (connToCancelled ? ' done-conn' : '') + '"></div>'
        + '<div class="ws-pl-step current cancelled" data-pl-status="cancelled">'
        + '<div class="ws-pl-dot">&#10005;</div>'
        + '<div class="ws-pl-lbl">Скасовано</div>'
        + (autoFlags['cancelled'] ? '<div class="ws-pl-auto">авто</div>' : '')
        + '</div>';
    }

    return '<div class="ws-pl-head-row">'
      + '<span class="ws-pl-title">ПРОГРЕС ЗАМОВЛЕННЯ</span>'
      + (rightHtml || '')
      + '</div>'
      + '<div class="ws-pl-steps">' + stepsHtml + '</div>';
  },

  // Sync Return/Cancel buttons into the create-bar (called after each renderOrderForm)
  _syncCreateBarActions: function(isCancelled, isEarly, retAllowed) {
    var createBar = document.getElementById('wsCreateBar');
    if (!createBar) return;
    // Remove previously injected action buttons
    var old = createBar.querySelectorAll('.ws-create-btn-action');
    old.forEach(function(b) { b.parentNode.removeChild(b); });
    // Return button — visible when not cancelled and not early; disabled if conditions not met
    if (!isCancelled && !isEarly) {
      var retBtn = document.createElement('button');
      retBtn.type = 'button';
      retBtn.id = 'wsOaRetBtn';
      retBtn.className = 'ws-create-btn ws-create-btn-action ws-create-btn-right' + (retAllowed ? '' : ' ws-create-btn-disabled');
      retBtn.disabled = !retAllowed;
      retBtn.title = retAllowed
        ? 'Оформити повернення'
        : 'Потрібне відвантаження, доставка та статус "Відправлено" або "Виконано"';
      retBtn.innerHTML = '<span class="ws-create-icon">↩</span>Повернення';
      createBar.appendChild(retBtn);
    }
    // Cancel button — only when order is not already cancelled
    if (!isCancelled) {
      var cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.id = 'wsOaCancelBtn';
      var cancelFirst = isCancelled || isEarly; // no retBtn rendered before it
      cancelBtn.className = 'ws-create-btn ws-create-btn-action ws-create-btn-danger' + (cancelFirst ? ' ws-create-btn-right' : '');
      cancelBtn.title = 'Скасувати замовлення';
      cancelBtn.innerHTML = '<span class="ws-create-icon">✕</span>Скасувати';
      createBar.appendChild(cancelBtn);
    }
  },

  // Pipeline step order for direction detection (must match server-side getStepOrder).
  _STEP_ORDER: {
    draft: 0, 'new': 0,
    confirmed: 1, waiting_payment: 2, in_progress: 3,
    partially_shipped: 4, shipped: 4, completed: 5,
    cancelled: -1
  },

  _bindPipelineBar: function(el) {
    var self = this;
    var bar = el ? el.querySelector('#wsPipelineBar') : null;
    if (!bar) return;
    bar.addEventListener('click', function(e) {
      var step = e.target.closest('[data-pl-status]');
      if (!step) return;
      if (step.classList.contains('current') || step.classList.contains('cancelled')) return;

      var targetStatus  = step.dataset.plStatus;
      var currentStatus = self._orderState && self._orderState.order && self._orderState.order.status;
      if (!targetStatus || targetStatus === currentStatus) return;

      var fromStep = self._STEP_ORDER[currentStatus] !== undefined ? self._STEP_ORDER[currentStatus] : -2;
      var toStep   = self._STEP_ORDER[targetStatus]  !== undefined ? self._STEP_ORDER[targetStatus]  : -2;
      var isBackward = toStep < fromStep;

      // Moving to "cancelled"
      if (targetStatus === 'cancelled') {
        var cancelMsg = currentStatus === 'completed'
          ? 'Скасувати виконане замовлення? Цю дію важко відмінити.'
          : 'Скасувати замовлення?';
        if (!confirm(cancelMsg)) return;
        self._setOrderStatus(el, targetStatus);
        return;
      }

      // Backward from "completed" — server will block unless target is cancelled (handled above)
      if (isBackward && fromStep >= 5) {
        alert('Із статусу "Виконано" можна перейти лише в "Скасовано".');
        return;
      }

      // Backward from "confirmed" or "waiting_payment" — soft warning
      if (isBackward && fromStep >= 1 && fromStep <= 2) {
        var LABELS = { draft:'Нове', 'new':'Нове', confirmed:'Прийнято',
          waiting_payment:'Очік. оплати', in_progress:'В роботі',
          shipped:'Відправлено', completed:'Виконано' };
        if (!confirm('Повернути статус назад до "' + (LABELS[targetStatus] || targetStatus) + '"?')) return;
      }

      // All other cases (forward, or backward from in_progress/shipped):
      // server validates and returns error if blocked.
      self._setOrderStatus(el, targetStatus);
    });
  },

  _setOrderStatus: function(el, newStatus) {
    var self = this;
    if (!self._orderState || !self._orderState.order) return;
    var orderId = self._orderState.order.id;
    if (!orderId) return;

    var STATUS_LABELS_LOCAL = {
      draft:'Чернетка', new:'Нове', confirmed:'Підтверджено', in_progress:'В роботі',
      waiting_payment:'Очікує оплату', completed:'Завершено', cancelled:'Скасовано'
    };

    var prevStatus = self._orderState.order.status;
    self._orderState.order.status = newStatus;
    var aFlags = (self._flowData && self._flowData.status_auto_flags) ? self._flowData.status_auto_flags : {};

    var bar = el ? el.querySelector('#wsPipelineBar') : null;
    if (bar) {
      bar.innerHTML = self._buildPipelineBarInner(newStatus, orderId, aFlags);
      self._bindPipelineBar(el);
    }

    fetch('/counterparties/api/save_order_status', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'order_id=' + orderId + '&status=' + encodeURIComponent(newStatus)
    }).then(function(r){ return r.json(); }).then(function(d) {
      if (!d.ok) {
        self._orderState.order.status = prevStatus;
        if (bar) { bar.innerHTML = self._buildPipelineBarInner(prevStatus, orderId, aFlags); self._bindPipelineBar(el); }
        // Show error as alert for blocking validation rules (more noticeable than toast)
        var errMsg = d.error || 'Помилка зміни статусу';
        if (errMsg.indexOf('Неможливо') !== -1 || errMsg.indexOf('потрібна') !== -1
            || errMsg.indexOf('Із ') !== -1 || errMsg.indexOf('Є активна') !== -1) {
          alert(errMsg);
        } else {
          showToast('Помилка: ' + errMsg);
        }
      } else {
        showToast(STATUS_LABELS_LOCAL[newStatus] || newStatus);
      }
    }).catch(function() {
      self._orderState.order.status = prevStatus;
      if (bar) { bar.innerHTML = self._buildPipelineBarInner(prevStatus, orderId, aFlags); self._bindPipelineBar(el); }
    });
  },

  // ── Lead context panel ─────────────────────────────────────────────────────
  // ── Order action bar: Повернення / Скасувати ──────────────────────────────
  _bindOrderActions: function(el, d, order) {
    var self    = this;
    var orderId = order.id;

    // Return panel
    var retBtn   = document.getElementById('wsOaRetBtn');
    var retPanel = el.querySelector('#wsRetPanel');
    var retClose = el.querySelector('#wsRetClose');

    if (retBtn && retPanel) {
      retBtn.addEventListener('click', function() {
        var opening = !retPanel.classList.contains('open');
        retPanel.classList.toggle('open');
        retBtn.classList.toggle('ws-oa-active', opening);
        if (opening) {
          retPanel.querySelectorAll('.ws-ret-opt').forEach(function(o) { o.classList.remove('selected'); });
          var ir = retPanel.querySelector('#wsRetInputRow');
          if (ir) ir.style.display = 'none';
          var inp = retPanel.querySelector('#wsRetInput');
          if (inp) inp.value = '';
        }
      });
    }
    if (retClose && retPanel) {
      retClose.addEventListener('click', function() {
        retPanel.classList.remove('open');
        if (retBtn) retBtn.classList.remove('ws-oa-active');
      });
    }
    if (retPanel) {
      retPanel.querySelectorAll('.ws-ret-opt').forEach(function(opt) {
        opt.addEventListener('click', function() {
          retPanel.querySelectorAll('.ws-ret-opt').forEach(function(o) { o.classList.remove('selected'); });
          opt.classList.add('selected');
          var type = opt.dataset.ret;
          var ir   = retPanel.querySelector('#wsRetInputRow');
          var inp  = retPanel.querySelector('#wsRetInput');
          if (ir) ir.style.display = 'flex';
          if (inp) {
            var ph = { novaposhta_ttn: 'Номер зворотної ТТН (Нова Пошта)…',
                       ukrposhta_ttn:  'Номер зворотної ТТН (Укрпошта)…',
                       manual:         'Коментар (необов\u2019язково)…' };
            inp.placeholder = ph[type] || 'Причина (необов\u2019язково)…';
            inp.focus();
          }
        });
      });
      var retSave = retPanel.querySelector('#wsRetSave');
      if (retSave) {
        retSave.addEventListener('click', function() {
          var sel = retPanel.querySelector('.ws-ret-opt.selected');
          if (!sel) { showToast('Оберіть спосіб повернення', true); return; }
          var retType  = sel.dataset.ret;
          var inputVal = (retPanel.querySelector('#wsRetInput').value || '').trim();
          if ((retType === 'novaposhta_ttn' || retType === 'ukrposhta_ttn') && !inputVal) {
            showToast('Введіть номер ТТН', true); return;
          }
          var body = 'order_id=' + orderId + '&return_type=' + encodeURIComponent(retType);
          body += (retType === 'novaposhta_ttn' || retType === 'ukrposhta_ttn')
            ? '&ttn_number='   + encodeURIComponent(inputVal)
            : '&description='  + encodeURIComponent(inputVal);
          retSave.disabled = true;
          fetch('/counterparties/api/save_return_logistics', {
            method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body
          }).then(function(r){ return r.json(); }).then(function(res) {
            retSave.disabled = false;
            if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
            retPanel.classList.remove('open');
            if (retBtn) retBtn.classList.remove('ws-oa-active');
            showToast('Повернення оформлено');
            self.loadOrderFlow(orderId, '');
          }).catch(function() { retSave.disabled = false; showToast('Помилка мережі', true); });
        });
      }
    }

    // Cancel button
    var cancelBtn = document.getElementById('wsOaCancelBtn');
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function() {
        var fd      = self._flowData || {};
        var demands = (fd.demands || []).filter(function(dem) {
          return dem.status && ['cancelled','returned'].indexOf(dem.status) === -1;
        });
        var paySum = parseFloat(fd.sum_payments || 0);
        var descEl = document.getElementById('wsOrderCancelDesc');
        var cascEl = document.getElementById('wsOrderCancelCascade');
        if (descEl) descEl.textContent = 'Замовлення #' + (order.number || orderId) + ' буде скасовано.';
        if (cascEl) {
          var lines = [];
          if (demands.length > 0) lines.push('📋 ' + demands.length + ' відвантаження будуть анульовані');
          if (paySum > 0)         lines.push('💳 Необхідне повернення коштів: \u20b4' + self.formatNum(paySum));
          if (!lines.length)      lines.push('Пов\u2019язаних документів немає.');
          cascEl.innerHTML = lines.map(function(l) {
            return '<div>' + l.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>';
          }).join('');
        }
        var modal = document.getElementById('wsOrderCancelModal');
        if (modal) modal.style.display = 'flex';
        // Rebind confirm (clone removes stale listeners)
        var oldC = document.getElementById('wsOrderCancelConfirm');
        if (oldC) {
          var newC = oldC.cloneNode(true);
          oldC.parentNode.replaceChild(newC, oldC);
          newC.addEventListener('click', function() {
            newC.disabled = true;
            fetch('/counterparties/api/cancel_order', {
              method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: 'order_id=' + orderId
            }).then(function(r){ return r.json(); }).then(function(res) {
              newC.disabled = false;
              var m = document.getElementById('wsOrderCancelModal');
              if (m) m.style.display = 'none';
              if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
              var msg = 'Замовлення скасовано';
              if (res.n_demands > 0) msg += ', ' + res.n_demands + ' відвантажень анульовано';
              if (res.refund_needed) msg += '. Потрібне повернення \u20b4' + self.formatNum(res.refund_sum);
              showToast(msg);
              self.loadOrderFlow(orderId, '');
            }).catch(function() { newC.disabled = false; showToast('Помилка мережі', true); });
          });
        }
      });
    }
  },

  renderLeadCtx: function(d) {
    var lead    = d.lead;
    var matches = d.matches || [];
    var self    = this;
    var html    = '<div class="ws-lead-panel">';

    html += '<div class="ws-lead-badge">⚡ Нове звернення · ' + this.esc(lead.source_label) + '</div>';

    html += '<div class="ws-lead-info">';
    if (lead.phone) html += '<div class="ws-lead-info-row"><span class="ws-lead-info-lbl">Телефон</span><span>' + this.esc(lead.phone) + '</span></div>';
    if (lead.email) html += '<div class="ws-lead-info-row"><span class="ws-lead-info-lbl">Email</span><span>' + this.esc(lead.email) + '</span></div>';
    if (lead.telegram_chat_id) html += '<div class="ws-lead-info-row"><span class="ws-lead-info-lbl">Telegram</span><span>' + this.esc(lead.telegram_chat_id) + '</span></div>';
    html += '<div class="ws-lead-info-row"><span class="ws-lead-info-lbl">Отримано</span><span>' + this.esc(lead.created_at ? lead.created_at.substr(0,16) : '') + '</span></div>';
    html += '</div>';

    html += '<hr class="ws-lead-sep">';

    // Search existing
    html += '<div class="ws-lead-section-title">Прив\'язати до існуючого</div>';
    html += '<input type="text" class="ws-cp-search" id="wsLeadCpSearch" placeholder="Пошук контрагента…" oninput="WS.searchCpForLead(this.value)">';
    html += '<div class="ws-match-list" id="wsMatchList">';

    if (matches.length > 0) {
      matches.forEach(function(m) {
        var matchLbl = m.match_by === 'phone' ? '📞 збіг' : (m.match_by === 'email' ? '✉ збіг' : 'TG збіг');
        html += '<div class="ws-match-item" onclick="WS.identifyLead(' + m.id + ')">'
              + '<span class="ws-match-name">' + self.esc(m.name) + '</span>'
              + '<span class="ws-match-tag">' + matchLbl + '</span>'
              + '</div>';
      });
    } else {
      html += '<div style="font-size:11px;color:#9ca3af;padding:6px 0">Автоматичних збігів не знайдено</div>';
    }

    html += '</div>';

    html += '<hr class="ws-lead-sep">';
    html += '<div class="ws-lead-btns">';
    html += '<button class="ws-lead-btn primary" onclick="WS.openCreateFromLead()">+ Створити нового контрагента</button>';
    html += '<button class="ws-lead-btn" onclick="WS.createDraftOrder()">📦 Створити чернетку замовлення</button>';
    html += '<button class="ws-lead-btn danger" onclick="WS.discardLead()">✕ Відхилити (спам)</button>';
    html += '</div>';
    html += '</div>';

    document.getElementById('wsCtx').innerHTML = html;
  },

  // ── Search counterparty for lead identification ────────────────────────────
  searchCpForLead: function(q) {
    if (!q || q.length < 2) return;
    var self = this;
    fetch('/counterparties/api/search?q=' + encodeURIComponent(q) + '&type=')
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        var html = '';
        if (d.items && d.items.length > 0) {
          d.items.forEach(function(m) {
            html += '<div class="ws-match-item" onclick="WS.identifyLead(' + m.id + ')">'
                  + '<span class="ws-match-name">' + self.esc(m.name) + '</span>'
                  + '<span style="font-size:11px;color:#9ca3af">' + self.esc(m.phone || m.email || '') + '</span>'
                  + '</div>';
          });
        } else {
          html = '<div style="font-size:11px;color:#9ca3af;padding:6px 0">Не знайдено</div>';
        }
        document.getElementById('wsMatchList').innerHTML = html;
      });
  },

  // ── Lead actions ───────────────────────────────────────────────────────────
  _mergeTargetCpId: 0,
  _mergeSupplements: [],

  identifyLead: function(cpId) {
    var self   = this;
    var leadId = this.itemId;
    // First call merge_preview to check for conflicts/supplements
    fetch('/counterparties/api/merge_preview?lead_id=' + leadId + '&counterparty_id=' + cpId)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) { showToast('Помилка: ' + (d.error || '')); return; }

        self._mergeTargetCpId  = cpId;
        self._mergeSupplements = [];

        // Collect supplement field names
        if (d.supplements && d.supplements.length > 0) {
          d.supplements.forEach(function(s) { self._mergeSupplements.push(s.field); });
        }

        // If no conflicts — merge directly
        if (!d.conflicts || d.conflicts.length === 0) {
          self._doMerge(leadId, cpId, {}, self._mergeSupplements);
          return;
        }

        // Show conflict resolution modal
        self._showMergeModal(leadId, cpId, d);
      });
  },

  _showMergeModal: function(leadId, cpId, preview) {
    var self = this;
    var html = '';

    if (preview.supplements && preview.supplements.length > 0) {
      html += '<p style="margin:0 0 12px;font-size:13px;color:#6b7280">'
            + 'Буде автоматично додано: '
            + preview.supplements.map(function(s) {
                return '<strong>' + self.esc(s.label) + '</strong>: ' + self.esc(s.value);
              }).join(', ')
            + '</p>';
    }

    html += '<p style="margin:0 0 10px;font-size:13px;font-weight:600">Конфлікти — оберіть яке значення зберегти:</p>';
    html += '<table style="width:100%;border-collapse:collapse;font-size:13px">';
    html += '<thead><tr>'
          + '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e5e7eb">Поле</th>'
          + '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e5e7eb">Існуючий контрагент</th>'
          + '<th style="text-align:left;padding:6px 8px;border-bottom:1px solid #e5e7eb">Лід (нове)</th>'
          + '</tr></thead><tbody>';

    preview.conflicts.forEach(function(c) {
      var fid = 'mres_' + c.field;
      html += '<tr>'
            + '<td style="padding:8px;border-bottom:1px solid #f3f4f6;font-weight:600">' + self.esc(c.label) + '</td>'
            + '<td style="padding:8px;border-bottom:1px solid #f3f4f6">'
            +   '<label style="display:flex;align-items:center;gap:6px;cursor:pointer">'
            +     '<input type="radio" name="' + fid + '" value="existing" checked> ' + self.esc(c.existing)
            +   '</label></td>'
            + '<td style="padding:8px;border-bottom:1px solid #f3f4f6">'
            +   '<label style="display:flex;align-items:center;gap:6px;cursor:pointer">'
            +     '<input type="radio" name="' + fid + '" value="lead"> ' + self.esc(c.lead)
            +   '</label></td>'
            + '</tr>';
    });

    html += '</tbody></table>';

    // Store conflict fields list for doMergeWithResolutions
    document.getElementById('wsMergeBody').innerHTML = html;
    document.getElementById('wsMergeBody').dataset.leadId     = leadId;
    document.getElementById('wsMergeBody').dataset.cpId       = cpId;
    document.getElementById('wsMergeBody').dataset.conflicts  = JSON.stringify(preview.conflicts.map(function(c){ return c.field; }));
    document.getElementById('wsMergeModal').style.display = 'flex';
  },

  doMergeWithResolutions: function() {
    var body       = document.getElementById('wsMergeBody');
    var leadId     = parseInt(body.dataset.leadId, 10);
    var cpId       = parseInt(body.dataset.cpId, 10);
    var fields     = JSON.parse(body.dataset.conflicts || '[]');
    var resolutions = {};
    fields.forEach(function(field) {
      var radios = document.getElementsByName('mres_' + field);
      for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked) { resolutions[field] = radios[i].value; break; }
      }
    });
    document.getElementById('wsMergeModal').style.display = 'none';
    this._doMerge(leadId, cpId, resolutions, this._mergeSupplements);
  },

  _doMerge: function(leadId, cpId, resolutions, supplements) {
    var self = this;
    var fd = new FormData();
    fd.append('lead_id', leadId);
    fd.append('counterparty_id', cpId);
    // Append resolutions
    Object.keys(resolutions).forEach(function(field) {
      fd.append('resolutions[' + field + ']', resolutions[field]);
    });
    // Append supplements
    supplements.forEach(function(field) {
      fd.append('supplements[]', field);
    });
    fetch('/counterparties/api/identify_lead', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          showToast('Прив\'язано до: ' + d.counterparty_name);
          self.loadInbox();
          self.selectItem('counterparty', d.counterparty_id, self.activeCh);
        } else {
          showToast('Помилка: ' + (d.error || ''));
        }
      });
  },

  openCreateFromLead: function() {
    if (!this.currentLead) return;
    var lead = this.currentLead.lead;
    document.getElementById('wsLcName').value  = lead.display_name || '';
    document.getElementById('wsLcErr').style.display = 'none';
    document.getElementById('wsLeadCreateModal').style.display = 'flex';
  },

  doCreateFromLead: function() {
    var self   = this;
    var leadId = this.itemId;
    var name   = document.getElementById('wsLcName').value.trim();
    var type   = document.getElementById('wsLcType').value;
    var err    = document.getElementById('wsLcErr');
    if (!name) { err.textContent = 'Вкажіть назву'; err.style.display = 'block'; return; }
    err.style.display = 'none';
    var fd = new FormData();
    fd.append('lead_id', leadId);
    fd.append('type', type);
    fd.append('name', name);
    fetch('/counterparties/api/create_from_lead', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        document.getElementById('wsLeadCreateModal').style.display = 'none';
        if (d.ok) {
          showToast('Контрагента створено: ' + d.counterparty_name);
          self.loadInbox();
          self.selectItem('counterparty', d.counterparty_id);
        } else {
          showToast('Помилка: ' + (d.error || ''));
        }
      });
  },

  discardLead: function() {
    var self   = this;
    var leadId = this.itemId;
    if (!confirm('Відхилити це звернення як спам?')) return;
    var fd = new FormData();
    fd.append('lead_id', leadId);
    fetch('/counterparties/api/discard_lead', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          showToast('Звернення відхилено');
          self.kind   = null;
          self.itemId = null;
          document.getElementById('wsHubInner').style.display = 'none';
          document.getElementById('wsEmpty').style.display    = 'flex';
          document.getElementById('wsCtx').innerHTML          = '<div class="ws-ctx-empty">Оберіть контакт</div>';
          self.loadInbox();
        }
      });
  },

  createDraftOrder: function() {
    if (!this.itemId) return;
    // Navigate to new order page with lead context
    showToast('Створення замовлення — незабаром');
  },

  // ── Tab switching ──────────────────────────────────────────────────────────
  switchTab: function(tab, silent) {
    this.activeTab = tab;
    var panes = { chat: 'wsPaneChat', internal: 'wsPaneInternal' };
    Object.keys(panes).forEach(function(k) {
      document.getElementById(panes[k]).style.display = (k === tab) ? 'flex' : 'none';
    });
    if (!silent) {
      if (tab === 'chat') ChatHub.applyChPanel();
      if (tab === 'internal') {
        if (!WS._teamChatInited) {
          WsCpChat.init(WS.activeChatCpId || null);
          WS._teamChatInited = true;
        } else if (WS.activeChatCpId) {
          WsCpChat.setCp(WS.activeChatCpId);
        }
      }
    }
  },

  // ── Chat delegates → ChatHub ───────────────────────────────────────────────
  applyChPanel:    function()       { ChatHub.applyChPanel(); },

  // ── Messages (делегаты → ChatHub) ─────────────────────────────────────────
  loadMessages:   function()     { ChatHub.loadMessages(); },
  isImageUrl:     function(url)  { return ChatHub.isImageUrl(url); },
  renderMessages: function(msgs) { ChatHub.renderMessages(msgs); },

  sendMessage: function() { ChatHub.sendMessage(); },

  // ── Orders ─────────────────────────────────────────────────────────────────
  loadOrders: function() {
    if (this.kind !== 'counterparty') {
      document.getElementById('wsOrdersList').innerHTML = '<div style="padding:20px;text-align:center;font-size:12px;color:#9ca3af">Для лідів замовлень ще немає</div>';
      return;
    }
    var self = this;
    fetch('/counterparties/api/get_orders?id=' + this.itemId)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        var html = '';
        if (d.orders.length === 0) {
          html = '<div style="padding:20px;text-align:center;font-size:12px;color:#9ca3af">Замовлень немає</div>';
        } else {
          d.orders.forEach(function(o) {
            html += '<div class="ws-order-card">'
                  + '<div class="ws-order-head">'
                  + '<div class="ws-order-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg></div>'
                  + '<div style="flex:1;min-width:0">'
                  + '<div style="display:flex;align-items:center;gap:6px;margin-bottom:2px">'
                  + '<span class="ws-order-num">#' + self.esc(o.number) + '</span>'
                  + '<span class="ws-sbadge ' + self.orderBadgeClass(o.status) + '">' + self.esc(o.status_label) + '</span>'
                  + (o.traffic_source ? '<span class="ws-traffic-badge" style="background:' + o.traffic_source.color + '22;color:' + o.traffic_source.color + '">' + self.esc(o.traffic_source.label) + '</span>' : '')
                  + '</div>'
                  + '<div class="ws-order-sub">' + self.esc(o.moment ? o.moment.substr(0,10) : '') + (o.traffic_source && o.traffic_source.campaign ? ' · <span style=\'font-size:10px;color:#9ca3af\'>' + self.esc(o.traffic_source.campaign) + '</span>' : '') + '</div>'
                  + '</div>'
                  + '<span class="ws-order-sum">₴' + self.formatNum(o.sum_total) + '</span>'
                  + '<a class="ws-order-open" href="/customerorder/view?id=' + o.id + '" target="_blank">Відкрити</a>'
                  + '</div>'
                  + '</div>';
          });
        }
        document.getElementById('wsOrdersList').innerHTML = html;
      });
  },

  // ── AI mode ────────────────────────────────────────────────────────────────
  aiKey: function() {
    return (this.kind || '') + '_' + (this.itemId || 0);
  },

  applyAiMode: function() {
    // Only for counterparties
    var show = (this.kind === 'counterparty');
    var banner = document.getElementById('wsModeBanner');
    banner.style.display = show ? 'flex' : 'none';

    var isAi   = show && (this.aiMode[this.aiKey()] !== false); // default ON
    var lbl    = document.getElementById('wsModeLbl');
    var tlbl   = document.getElementById('wsToggleLbl');
    var inp    = document.getElementById('wsMsgInput');
    var sendBtn= document.getElementById('wsSendBtn');
    var takeover = document.getElementById('wsTakeover');
    var hint   = document.getElementById('wsInputHint');

    if (isAi) {
      banner.className = 'ws-mode-banner ai';
      lbl.textContent  = 'AI відповідає автоматично';
      tlbl.textContent = 'Автомат';
      inp.disabled     = true;
      inp.placeholder  = 'Переключіться в ручний режим, щоб написати…';
      sendBtn.disabled = true;
      takeover.style.display = 'flex';
      hint.textContent = 'AI активний';
      hint.className   = 'ws-t-hint';
    } else {
      banner.className = 'ws-mode-banner manual';
      lbl.textContent  = 'Ручний режим — пише оператор';
      tlbl.textContent = 'Ручний';
      inp.disabled     = false;
      inp.placeholder  = 'Написати повідомлення… (Ctrl+Enter щоб надіслати)';
      sendBtn.disabled = false;
      takeover.style.display = 'none';
      hint.textContent = 'AI підказує чернетки';
      hint.className   = 'ws-t-hint active-hint';
    }
  },

  toggleAi: function() {
    var key   = this.aiKey();
    var isAi  = this.aiMode[key] !== false;
    this.aiMode[key] = !isAi;
    this.applyAiMode();
    if (!isAi) return; // switched to manual, focus input
    setTimeout(function() {
      var inp = document.getElementById('wsMsgInput');
      if (!inp.disabled) inp.focus();
    }, 100);
  },

  // ── AI suggest ─────────────────────────────────────────────────────────────
  requestAiSuggest: function() {
    if (this.kind !== 'counterparty') return;
    var self = this;
    var fd   = new FormData();
    fd.append('id', this.itemId);
    fd.append('channel', this.activeCh);
    fetch('/counterparties/api/ai_suggest', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok && d.text) {
          document.getElementById('wsAiDraftTxt').textContent = d.text;
          document.getElementById('wsAiDraft').style.display  = 'flex';
        }
      });
  },

  useAiDraft: function() {
    var txt = document.getElementById('wsAiDraftTxt').textContent;
    document.getElementById('wsMsgInput').value = txt;
    this.hideAiDraft();
    document.getElementById('wsMsgInput').focus();
  },

  hideAiDraft: function() {
    document.getElementById('wsAiDraft').style.display = 'none';
  },

  // ── Templates (делегаты → ChatHub) ────────────────────────────────────────
  toggleTemplates: function(e)              { ChatHub.toggleTemplates(e); },
  loadTplDropdown: function()               { ChatHub.loadTplDropdown(); },
  insertTemplate:  function(body)           { ChatHub.insertTemplate(body); },
  openTplManager:  function()               { ChatHub.openTplManager(); },
  closeTplManager: function()               { ChatHub.closeTplManager(); },
  loadTmList:      function()               { ChatHub.loadTmList(); },
  newTmTemplate:   function()               { ChatHub.newTmTemplate(); },
  editTmTemplate:  function(id,t,b,ch)      { ChatHub.editTmTemplate(id,t,b,ch); },
  cancelTmEdit:    function()               { ChatHub.cancelTmEdit(); },
  saveTmTemplate:  function()               { ChatHub.saveTmTemplate(); },
  deleteTmTemplate:function(id)             { ChatHub.deleteTmTemplate(id); },

  // ── Emoji + File (делегаты → ChatHub) ────────────────────────────────────
  closeAllPickers:    function()       { ChatHub.closeAllPickers(); },
  toggleEmoji:        function(e)      { ChatHub.toggleEmoji(e); },
  openFilePicker:     function()       { ChatHub.openFilePicker(); },
  onFileSelected:     function(input)  { ChatHub.onFileSelected(input); },
  renderAttachPreview:function(f)      { ChatHub.renderAttachPreview(f); },
  removeAttach:       function()       { ChatHub.removeAttach(); },

  // ── New counterparty modal ─────────────────────────────────────────────────
  openNew: function() {
    document.getElementById('wsNewName').value  = '';
    document.getElementById('wsNewPhone').value = '';
    document.getElementById('wsNewEmail').value = '';
    document.getElementById('wsNewErr').style.display = 'none';
    document.getElementById('wsNewModal').style.display = 'flex';
    setTimeout(function(){ document.getElementById('wsNewName').focus(); }, 50);
  },

  closeNew: function() {
    document.getElementById('wsNewModal').style.display = 'none';
  },

  createNew: function() {
    var self  = this;
    var name  = document.getElementById('wsNewName').value.trim();
    var type  = document.getElementById('wsNewType').value;
    var phone = document.getElementById('wsNewPhone').value.trim();
    var email = document.getElementById('wsNewEmail').value.trim();
    var err   = document.getElementById('wsNewErr');
    if (!name) { err.textContent = 'Вкажіть назву або ім\'я'; err.style.display = 'block'; return; }
    err.style.display = 'none';
    var fd = new FormData();
    fd.append('type', type); fd.append('name', name);
    fd.append('phone', phone); fd.append('email', email);
    fetch('/counterparties/api/save_counterparty', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          self.closeNew();
          showToast('Контрагента створено');
          self.loadInbox();
          self.selectItem('counterparty', d.id);
        } else {
          err.textContent = d.error || 'Помилка';
          err.style.display = 'block';
        }
      });
  },

  // ── Channel dots (делегаты → ChatHub) ────────────────────────────────────
  updateChannelDots: function(u)   { ChatHub.updateChannelDots(u); },
  clearChannelDot:   function(ch)  { ChatHub.clearChannelDot(ch); },

  startPolling: function() {
    var self = this;
    this.stopPolling();
    // Сообщения — ChatHub (10s)
    ChatHub.startPolling();
    // Инбокс — обновляем список (20s)
    this.inboxTimer = setInterval(function() {
      self.loadInbox();
    }, 20000);
  },

  stopPolling: function() {
    ChatHub.stopPolling();
    if (this.inboxTimer) { clearInterval(this.inboxTimer); this.inboxTimer = null; }
  },

  // ── Tasks (делегаты → ChatHub) ────────────────────────────────────────────
  loadTasks:        function()       { ChatHub.loadTasks(); },
  renderTasks:      function(t)      { ChatHub.renderTasks(t); },
  addTask:          function()       { ChatHub.addTask(); },
  doneTask:         function(id)     { ChatHub.doneTask(id); },
  toggleSnoozeMenu: function(e, id)  { ChatHub.toggleSnoozeMenu(e, id); },
  wakeTask:         function(id)     { ChatHub.wakeTask(id); },

  // Refresh the task badge on the inbox card without full reload
  refreshInboxCard: function() {
    var self = this;
    if (!this.itemId || this.kind !== 'counterparty') return;
    // Re-fetch inbox silently to update task stats
    fetch('/counterparties/api/get_inbox?mode=' + this.mode)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (!d.ok) return;
        self.inboxData = d;
        self.renderInbox();
      });
  },

  // ── Decay Score (Priority Fatigue model) ──────────────────────────────────
  // Decay + Action Required: score grows as deadline approaches.
  // Max score: priority=5 overdue → 100 + 200 = 300
  computeTaskScore: function(cp) {
    if (!cp.open_task_count) return 0;
    var base = (cp.next_task_priority || 3) * 20;  // 20-100
    if (cp.next_task_due_at) {
      var hoursLeft = (new Date(cp.next_task_due_at).getTime() - Date.now()) / 3600000;
      if (hoursLeft <= 0)      base += 200;
      else if (hoursLeft < 4)  base += 80;
      else if (hoursLeft < 24) base += 40;
      else if (hoursLeft < 72) base += 15;
    } else {
      base += Math.min(cp.open_task_count * 5, 20);
    }
    return base;
  },

  esc: function(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },

  linkify: function(html) {
    return html.replace(/(https?:\/\/[^\s<>"']+)/g, function(url) {
      return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" class="chat-link">' + url + '</a>';
    });
  },

  truncate: function(s, n) {
    if (!s) return '';
    return s.length > n ? s.substr(0, n) + '…' : s;
  },

  initials: function(name) {
    if (!name) return '?';
    var w = name.trim().split(/\s+/);
    if (w.length >= 2) return (w[0][0] + w[1][0]).toUpperCase();
    return name.substr(0, 2).toUpperCase();
  },

  timeAgo: function(dt) {
    if (!dt) return '';
    var now = new Date();
    var d   = new Date(dt.replace(' ', 'T'));
    var sec = Math.floor((now - d) / 1000);
    if (sec < 60)    return 'щойно';
    if (sec < 3600)  return Math.floor(sec/60) + 'хв';
    if (sec < 86400) return Math.floor(sec/3600) + 'год';
    return Math.floor(sec/86400) + 'д';
  },

  formatTime: function(dt) {
    if (!dt) return '';
    var d = new Date((dt || '').replace(' ', 'T'));
    return d.toLocaleTimeString('uk', { hour: '2-digit', minute: '2-digit' });
  },

  formatNum: function(n) {
    var num = parseFloat(n) || 0;
    return num.toLocaleString('uk', { maximumFractionDigits: 0 });
  },

  orderStatusLabel: function(s) {
    var map = {
      draft: 'Чернетка', new: 'Новий', confirmed: 'Підтверджено',
      in_progress: 'В роботі', waiting_payment: 'Оплата', paid: 'Оплачено',
      shipped: 'В дорозі', completed: 'Виконано', cancelled: 'Скасовано'
    };
    return map[s] || (s || '');
  },

  orderBadgeClass: function(s) {
    var map = {
      draft: 'wsb-draft', new: 'wsb-new', confirmed: 'wsb-confirmed',
      in_progress: 'wsb-progress', waiting_payment: 'wsb-waiting',
      completed: 'wsb-done', cancelled: 'wsb-cancelled'
    };
    return map[s] || 'wsb-draft';
  },

  // ── NP status label by state_define ──────────────────────────────────────
  _npStatusLabel: function(dt) {
    var sd = parseInt(dt.state_define, 10);
    var map = {
      1:'Чернетка', 2:'Видалено', 3:'Не знайдено',
      4:'В місті відправника', 5:'В дорозі', 6:'В місті одержувача',
      7:'У відділенні', 8:'У відділенні', 9:'Отримано',
      10:'Повертається', 11:'Повернуто', 41:'В дорозі',
      101:'Кур\'єр доставляє', 102:'Відмова', 103:'Повертається',
      104:'Кур\'єр доставляє', 105:'У відділенні', 106:'Відмова'
    };
    return map[sd] || dt.state_name || '—';
  },

  // ── Delivery progress helpers ─────────────────────────────────────────────
  // Returns { stage: 1..4, refused: bool, label: '' }
  // Stages: 1=чернетка, 2=в дорозі, 3=у відділенні, 4=отримано
  _deliveryProgress: function(type, dt) {
    if (type === 'ttn_np') {
      var sd = parseInt(dt.state_define, 10);
      if (sd === 102 || sd === 106) return { stage: 0, refused: true, label: 'Відмова' };
      if (sd === 10 || sd === 11 || sd === 103) return { stage: 0, refused: true, label: 'Повернення' };
      if (sd === 9) return { stage: 4, label: 'Отримано' };
      if (sd === 7 || sd === 8 || sd === 105) return { stage: 3, label: 'У відділенні' };
      if (sd === 4 || sd === 5 || sd === 6 || sd === 41 || sd === 101 || sd === 104) return { stage: 2, label: 'В дорозі' };
      if (sd === 1) return { stage: 1, label: 'Чернетка' };
      if (sd === 2 || sd === 3) return { stage: 0, label: this._npStatusLabel(dt) };
      return { stage: 0, label: this._npStatusLabel(dt) };
    }
    if (type === 'ttn_up') {
      var ls = (dt.lifecycle_status || '').toUpperCase();
      if (ls === 'DELIVERED')                              return { stage: 4, label: 'Доставлено' };
      if (ls === 'DELIVERING' || ls === 'STORAGE')        return { stage: 3, label: 'У відділенні' };
      if (ls === 'IN_DEPARTMENT' || ls === 'FORWARDING')  return { stage: 2, label: 'В дорозі' };
      if (ls === 'CREATED' || ls === 'REGISTERED')        return { stage: 1, label: 'Оформлено' };
      if (ls === 'RETURNED' || ls === 'RETURNING' || ls === 'CANCELLED' || ls === 'DELETED') {
        return { stage: 0, refused: true, label: 'Повернено' };
      }
      return { stage: 0, label: dt.lifecycle_status || '' };
    }
    return { stage: 0, label: '' };
  },

  // Render 4-pip progress bar HTML
  _renderDeliveryBar: function(prog) {
    if (prog.refused) {
      return '<div class="wf-delivery-bar">'
        + '<span class="wf-db-pip fail"></span><span class="wf-db-line"></span>'
        + '<span class="wf-db-pip fail"></span><span class="wf-db-line"></span>'
        + '<span class="wf-db-pip fail"></span><span class="wf-db-line"></span>'
        + '<span class="wf-db-pip fail"></span>'
        + '</div>';
    }
    var s = prog.stage; // 0..4, we have 4 pips = stages 1-4
    var pip = function(n) {
      if (s >= n) return 'done';
      if (s === n - 1 && s > 0) return 'cur';
      return '';
    };
    var line = function(n) { return s >= n ? 'done' : (s === n - 1 && s > 0 ? 'cur' : ''); };
    return '<div class="wf-delivery-bar">'
      + '<span class="wf-db-pip '  + pip(1)  + '"></span>'
      + '<span class="wf-db-line ' + line(2) + '"></span>'
      + '<span class="wf-db-pip '  + pip(2)  + '"></span>'
      + '<span class="wf-db-line ' + line(3) + '"></span>'
      + '<span class="wf-db-pip '  + pip(3)  + '"></span>'
      + '<span class="wf-db-line ' + line(4) + '"></span>'
      + '<span class="wf-db-pip '  + pip(4)  + '"></span>'
      + '</div>';
  },

  // ── Demand form ─────────────────────────────────────────────────────────────

  renderDemandForm: function(d) {
    var self   = this;
    var el     = document.getElementById('wsDemandForm');
    if (!el) return;

    var demand = d.demand;
    var items  = d.items || [];

    var DEMAND_STATUS_COLORS = {
      new:        'background:#dbeafe;color:#1d4ed8',
      assembling: 'background:#fef3c7;color:#92400e',
      assembled:  'background:#d1fae5;color:#065f46',
      shipped:    'background:#cffafe;color:#0e7490',
      arrived:    'background:#dcfce7;color:#15803d',
      transfer:   'background:#ede9fe;color:#5b21b6',
      robot:      'background:#f3f4f6;color:#6b7280',
    };
    var DEMAND_STATUS_LABELS = {
      new:        'Нове',
      assembling: 'Збирається',
      assembled:  'Зібрано',
      shipped:    'Відвантажено',
      arrived:    'Прибуло',
      transfer:   'Передано',
      robot:      'Автомат',
    };
    var curStatus = demand.status || 'new';
    var curStyle  = DEMAND_STATUS_COLORS[curStatus] || DEMAND_STATUS_COLORS['new'];

    var statusOpts = '';
    ['new','assembling','assembled','shipped','arrived','transfer','robot'].forEach(function(s) {
      statusOpts += '<option value="' + s + '"' + (s === curStatus ? ' selected' : '') + '>' + (DEMAND_STATUS_LABELS[s] || s) + '</option>';
    });

    // ── Payment badge ─────────────────────────────────────────────────────────
    var ownPayments = d.own_payments || [];
    var payBadgeHtml = '';
    if (ownPayments.length > 0) {
      var ownPaid = 0;
      ownPayments.forEach(function(p) { ownPaid += parseFloat(p.amount) || 0; });
      var demTotal = parseFloat(demand.sum_total) || 0;
      var ownStatus = ownPaid <= 0 ? 'not_paid' : (ownPaid < demTotal - 0.01 ? 'partially_paid' : 'paid');
      payBadgeHtml = self.payStatusBadge(ownStatus);
    } else if (d.order_payment_status) {
      payBadgeHtml = self.payStatusBadge(d.order_payment_status);
    } else {
      payBadgeHtml = self.payStatusBadge('not_paid');
    }

    // ── Edit bar ──────────────────────────────────────────────────────────────
    var editBarHtml = '<div class="ws-of-edit-bar" id="wsDfEditBar">'
      + '<span class="ws-of-edit-bar-label">✏ Режим редагування</span>'
      + '<span class="ws-of-edit-bar-hint">Зміни не збережено — натисніть 💾 або Скасувати</span>'
      + '<button type="button" class="ws-of-edit-bar-cancel" id="wsDfEditCancel">Скасувати</button>'
      + '<button type="button" class="ws-of-edit-bar-done" id="wsDfEditDone">💾 Зберегти</button>'
      + '</div>';

    // ── Header ────────────────────────────────────────────────────────────────
    var headHtml = '<div class="ws-of-head">'
      + '<span class="ws-of-head-num">📋 #' + self.esc(demand.number || '—') + '</span>'
      + (demand.moment ? '<span class="ws-of-head-date">' + demand.moment.substr(0,10) + '</span>' : '')
      + '<select class="ws-of-status-sel" id="wsDfStatus" style="' + curStyle + '" onchange="WS.onDemandStatusChange(this)">' + statusOpts + '</select>'
      + payBadgeHtml
      + '<span class="ws-of-head-sep"></span>'
      + '<div class="ws-of-head-btns">'
      + '<button type="button" class="ws-of-head-btn ws-of-icon-btn" id="wsDfEditBtn" title="Редагувати позиції">✏</button>'
      + '<button type="button" class="ws-of-head-btn ws-of-icon-btn ws-of-save-btn" id="wsDfSaveBtn" title="Зберегти зміни" style="display:none">💾</button>'
      + '</div>'
      + '</div>';

    // ── Items table ───────────────────────────────────────────────────────────
    var itemsHtml = '<div class="ws-of-items-wrap"><table class="ws-of-items">'
      + '<colgroup>'
      + '<col class="ws-of-col-name"><col class="ws-of-col-qty"><col class="ws-of-col-unit"><col class="ws-of-col-price">'
      + '<col class="ws-of-col-disc"><col class="ws-of-col-vat"><col class="ws-of-col-sum"><col class="ws-of-col-del">'
      + '</colgroup>'
      + '<thead><tr>'
      + '<th class="left">Товар</th><th>К-ть</th><th>Од.</th><th>Ціна</th>'
      + '<th title="Знижка %">Зн%</th><th>ПДВ</th><th>Сума</th><th></th>'
      + '</tr></thead><tbody>';

    if (!self._demandState) {
      var stateItems2 = items.map(function(it) {
        var c = JSON.parse(JSON.stringify(it)); c._localId = String(it.id); return c;
      });
      self._demandState = { demand: JSON.parse(JSON.stringify(demand)), items: stateItems2 };
    }

    items.forEach(function(it) {
      var qty  = parseFloat(it.quantity)         || 0;
      var disc = parseFloat(it.discount_percent) || 0;
      var vat  = parseFloat(it.vat_rate)         || 0;
      var sum  = parseFloat(it.sum_row)          || 0;
      var ship = parseFloat(it.shipped_quantity) || 0;
      var shipNote = (ship > 0 && ship < qty) ? ' <span style="color:#ea580c;font-size:9px">відвант:' + ship + '</span>' : '';

      itemsHtml += '<tr class="ws-of-items-body" data-item-id="' + it.id + '" data-local-id="' + it.id + '" data-sum-changed="0">'
        + '<td class="ws-of-name-cell">'
        +   (it.article ? (it.product_id ? '<a class="ws-of-sku" href="/catalog?selected=' + parseInt(it.product_id, 10) + '" target="_blank" title="Відкрити в каталозі">' + self.esc(it.article) + '</a>' : '<span class="ws-of-sku">' + self.esc(it.article) + '</span>') : '')
        +   '<span class="ws-of-nm">' + self.esc(it.name || it.product_name || '—') + '</span>'
        +   shipNote
        + '</td>'
        + '<td><input class="ws-cell-input" data-field="quantity" value="' + qty + '" type="text"></td>'
        + '<td class="ws-of-unit-cell">' + self.esc(it.unit || 'шт') + '</td>'
        + '<td><input class="ws-cell-input" data-field="price" value="' + parseFloat(it.price||0).toFixed(2) + '" type="text"></td>'
        + '<td><input class="ws-cell-input" data-field="discount_percent" value="' + (disc > 0 ? disc : '') + '" placeholder="0" type="text"></td>'
        + '<td><select class="ws-cell-sel" data-field="vat_rate">'
        +     '<option value="0"'  + (vat === 0  ? ' selected' : '') + '>—</option>'
        +     '<option value="20"' + (vat === 20 ? ' selected' : '') + '>20%</option>'
        +   '</select></td>'
        + '<td><input class="ws-cell-input sum-field" data-field="sum_row" value="' + sum.toFixed(2) + '" type="text"></td>'
        + '<td><button type="button" class="ws-item-del-btn" title="Видалити рядок">×</button></td>'
        + '</tr>';
    });

    itemsHtml += '</tbody></table></div>';

    // ── Add product ───────────────────────────────────────────────────────────
    var addProductHtml = '<div class="ws-of-add-product">'
      + '<input type="text" class="ws-item-search" id="wsDfItemSearch" placeholder="+ Додати товар… (увімкніть режим ✏)" autocomplete="off" disabled>'
      + '</div>';

    // ── Footer ────────────────────────────────────────────────────────────────
    var sumVat   = parseFloat(demand.sum_vat)   || 0;
    var sumTotal = parseFloat(demand.sum_total) || 0;
    var footHtml = '<div class="ws-of-foot">'
      + '<div class="ws-of-foot-comment">'
      +   '<textarea id="wsDfComment" placeholder="Коментар до відвантаження…">' + self.esc(demand.description || '') + '</textarea>'
      + '</div>'
      + '<div class="ws-of-foot-totals" id="wsDfTotals">'
      + self._footTotalsHtml(0, sumVat, sumTotal)
      + '</div>'
      + '</div>';

    el.innerHTML = editBarHtml + headHtml + itemsHtml + addProductHtml + footHtml;
    el.dataset.demandId = demand.id;

    // ── Bind row events ───────────────────────────────────────────────────────
    el.querySelectorAll('tr.ws-of-items-body').forEach(function(tr) {
      self._bindDemandItemRow(tr);
    });

    // Delete buttons
    el.querySelectorAll('.ws-item-del-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var tr = btn.closest('tr');
        if (!tr) return;
        var localId = tr.dataset.localId;
        if (self._demandState) {
          var ditems = self._demandState.items || [];
          for (var i = 0; i < ditems.length; i++) {
            if (String(ditems[i]._localId) === String(localId)) { ditems[i]._deleted = true; break; }
          }
        }
        tr.remove();
        self._updateDemandFooter();
      });
    });

    // Product search
    self._bindDemandProductSearch(el);

    // Edit mode helpers
    var editBtn   = el.querySelector('#wsDfEditBtn');
    var saveBtn   = el.querySelector('#wsDfSaveBtn');
    var editDone  = el.querySelector('#wsDfEditDone');
    var cancelBtn = el.querySelector('#wsDfEditCancel');
    var searchInp = el.querySelector('#wsDfItemSearch');

    function enterEditMode() {
      el.classList.add('ws-of-editing');
      if (editBtn)   editBtn.style.display   = 'none';
      if (saveBtn)   saveBtn.style.display   = '';
      if (searchInp) { searchInp.disabled = false; searchInp.placeholder = '+ Додати товар…'; }
    }
    function exitEditMode() {
      el.classList.remove('ws-of-editing');
      if (editBtn)   editBtn.style.display   = '';
      if (saveBtn)   { saveBtn.style.display = 'none'; saveBtn.classList.remove('ws-of-save-btn-dirty'); }
      if (searchInp) { searchInp.disabled = true; searchInp.placeholder = '+ Додати товар… (увімкніть режим ✏)'; searchInp.value = ''; }
    }

    if (editBtn)   editBtn.addEventListener('click',  enterEditMode);
    if (saveBtn)   saveBtn.addEventListener('click',   function() { self._saveDemand(el, exitEditMode); });
    if (editDone)  editDone.addEventListener('click',  function() { self._saveDemand(el, exitEditMode); });
    if (cancelBtn) cancelBtn.addEventListener('click', function() { self._cancelDemandEdit(); exitEditMode(); });

    // Dirty
    el.querySelectorAll('tr.ws-of-items-body .ws-cell-input').forEach(function(inp) {
      inp.addEventListener('input', function() {
        if (saveBtn) { saveBtn.style.display = ''; saveBtn.classList.add('ws-of-save-btn-dirty'); }
      });
    });

    // Restore edit mode after reload
    if (self._restoreDemandEditMode) {
      self._restoreDemandEditMode = false;
      enterEditMode();
      var lastRow = el.querySelector('tr.ws-of-items-body:last-of-type');
      if (lastRow) lastRow.scrollIntoView({ block: 'nearest' });
    }

    // Comment auto-save on blur
    var commentEl = el.querySelector('#wsDfComment');
    if (commentEl) {
      commentEl.addEventListener('blur', function() {
        var demId = el.dataset.demandId;
        if (!demId) return;
        fetch('/counterparties/api/save_demand', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: 'demand_id=' + demId
            + '&status=' + encodeURIComponent(demand.status || 'new')
            + '&description=' + encodeURIComponent(commentEl.value)
            + '&items=' + encodeURIComponent(JSON.stringify([]))
        }).catch(function(){});
      });
    }
  },

  onDemandStatusChange: function(sel) {
    var STATUS_COLORS = {
      new:        'background:#dbeafe;color:#1d4ed8',
      assembling: 'background:#fef3c7;color:#92400e',
      assembled:  'background:#d1fae5;color:#065f46',
      shipped:    'background:#cffafe;color:#0e7490',
      arrived:    'background:#dcfce7;color:#15803d',
      transfer:   'background:#ede9fe;color:#5b21b6',
      robot:      'background:#f3f4f6;color:#6b7280',
    };
    var st = sel.value;
    sel.style.cssText = STATUS_COLORS[st] || STATUS_COLORS['new'];
    var el = document.getElementById('wsDemandForm');
    var demandId = el ? el.dataset.demandId : 0;
    if (!demandId) return;
    var commentEl = el ? el.querySelector('#wsDfComment') : null;
    var description = commentEl ? commentEl.value : '';
    fetch('/counterparties/api/save_demand', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'demand_id=' + demandId
        + '&status=' + encodeURIComponent(st)
        + '&description=' + encodeURIComponent(description)
        + '&items=' + encodeURIComponent(JSON.stringify([]))
    }).then(function(r){ return r.json(); }).then(function(d) {
      if (!d.ok) showToast('Помилка: ' + (d.error || ''));
      else showToast('Статус оновлено');
    });
  },

  _bindDemandItemRow: function(tr) {
    var self   = this;
    var inputs = tr.querySelectorAll('.ws-cell-input');
    var vatSel = tr.querySelector('.ws-cell-sel[data-field="vat_rate"]');

    function syncToState() {
      var localId = tr.dataset.localId;
      if (!self._demandState) return;
      var item = null;
      for (var i = 0; i < self._demandState.items.length; i++) {
        if (String(self._demandState.items[i]._localId) === String(localId)) {
          item = self._demandState.items[i]; break;
        }
      }
      if (!item) return;

      function v(field) {
        var inp = tr.querySelector('[data-field="' + field + '"]');
        return inp ? (parseFloat(inp.value) || 0) : (parseFloat(item[field]) || 0);
      }

      item.quantity         = v('quantity');
      item.price            = v('price');
      item.discount_percent = v('discount_percent');
      item.vat_rate         = vatSel ? (parseFloat(vatSel.value) || 0) : (parseFloat(item.vat_rate) || 0);

      var sumWasEdited = (tr.dataset.sumChanged === '1');
      if (sumWasEdited) {
        var enteredSum = v('sum_row');
        var factor = 1 - item.discount_percent / 100;
        item.price = (item.quantity > 0 && factor > 0)
          ? Math.round(enteredSum / item.quantity / factor * 100) / 100 : 0;
        var priceInp = tr.querySelector('[data-field="price"]');
        if (priceInp) priceInp.value = item.price.toFixed(2);
      }

      self._calcItem(item);
      item.sum_row = item.sum;

      var sumInp = tr.querySelector('[data-field="sum_row"]');
      if (sumInp && !sumWasEdited) sumInp.value = item.sum.toFixed(2);
      tr.dataset.sumChanged = '0';
      self._updateDemandFooter();
    }

    inputs.forEach(function(inp) {
      inp.addEventListener('input', function() {
        if (inp.dataset.field === 'sum_row') tr.dataset.sumChanged = '1';
        else tr.dataset.sumChanged = '0';
        syncToState();
      });
    });
    if (vatSel) vatSel.addEventListener('change', syncToState);
  },

  _updateDemandFooter: function() {
    if (!this._demandState) return;
    var items    = this._demandState.items || [];
    var sumVat   = 0;
    var sumTotal = 0;
    items.forEach(function(it) {
      if (it._deleted) return;
      sumVat   += parseFloat(it.vat_amount) || 0;
      sumTotal += parseFloat(it.sum)        || 0;
    });
    this._demandState.demand.sum_total = Math.round(sumTotal * 100) / 100;
    this._demandState.demand.sum_vat   = Math.round(sumVat   * 100) / 100;
    var totalsEl = document.getElementById('wsDfTotals');
    if (totalsEl) totalsEl.innerHTML = this._footTotalsHtml(0,
      this._demandState.demand.sum_vat,
      this._demandState.demand.sum_total);
  },

  _saveDemand: function(el, onDone) {
    var self = this;
    if (!self._demandState) return;
    var dem       = self._demandState.demand;
    var demandId  = dem.id;
    var statusSel = el ? el.querySelector('#wsDfStatus')  : null;
    var commentEl = el ? el.querySelector('#wsDfComment') : null;
    var status      = statusSel ? statusSel.value : (dem.status || 'new');
    var description = commentEl ? commentEl.value : (dem.description || '');
    var items = self._demandState.items || [];

    fetch('/counterparties/api/save_demand', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: 'demand_id='    + encodeURIComponent(demandId)
          + '&status='      + encodeURIComponent(status)
          + '&description=' + encodeURIComponent(description)
          + '&items='       + encodeURIComponent(JSON.stringify(items))
    }).then(function(r){ return r.json(); }).then(function(res) {
      if (!res.ok) { showToast('Помилка: ' + (res.error || ''), true); return; }
      var stateItems = (res.items || []).map(function(it) {
        var copy = JSON.parse(JSON.stringify(it));
        copy._localId    = String(it.id);
        copy.sum         = parseFloat(it.sum_row) || 0;
        var vr           = parseFloat(it.vat_rate) || 0;
        copy.vat_amount  = vr > 0 ? Math.round((copy.sum - copy.sum / (1 + vr / 100)) * 100) / 100 : 0;
        copy.discount_amount = 0;
        return copy;
      });
      self._demandState    = { demand: res.demand, items: stateItems };
      self._demandOriginal = JSON.parse(JSON.stringify(self._demandState));
      // Refresh flow graph node if possible
      if (self._flowData && self._flowData.demands) {
        for (var i = 0; i < self._flowData.demands.length; i++) {
          if (String(self._flowData.demands[i].id) === String(demandId)) {
            self._flowData.demands[i] = res.demand; break;
          }
        }
        self.renderFlowGraph(self._flowData);
      }
      showToast('Збережено ✓');
      if (onDone) onDone();
      if (self._demandFlowData) {
        self._demandFlowData.demand = res.demand;
        self._demandFlowData.items  = res.items;
        self.renderDemandForm(self._demandFlowData);
      }
    }).catch(function() { showToast('Помилка з\'єднання', true); });
  },

  _cancelDemandEdit: function() {
    if (!this._demandOriginal) return;
    this._demandState = JSON.parse(JSON.stringify(this._demandOriginal));
    if (this._demandFlowData) {
      this._demandFlowData.demand = this._demandState.demand;
      this._demandFlowData.items  = this._demandState.items;
      this.renderDemandForm(this._demandFlowData);
    }
    showToast('Зміни скасовано');
  },

  _bindDemandProductSearch: function(el) {
    var self = this;
    var inp  = el.querySelector('#wsDfItemSearch');
    if (!inp) return;
    var timer = null;
    var dd    = null;

    function removeDd() { if (dd) { dd.remove(); dd = null; } }

    function buildDd(list) {
      removeDd();
      if (!list.length) return;
      dd = document.createElement('div');
      dd.className = 'ws-item-search-dd';
      dd.innerHTML = list.slice(0, 14).map(function(p) {
        return '<div class="ws-item-search-opt" data-pid="' + p.product_id + '">'
          + '<span class="ws-item-search-opt-art">' + self.esc(p.product_article || '') + '</span>'
          + self.esc(p.name || '') + '</div>';
      }).join('');
      document.body.appendChild(dd);
      var rect = inp.getBoundingClientRect();
      dd.style.cssText = 'position:fixed;z-index:9999;top:' + (rect.bottom + 2) + 'px;left:' + rect.left + 'px;min-width:' + Math.max(rect.width, 240) + 'px;display:block;';

      dd.querySelectorAll('.ws-item-search-opt[data-pid]').forEach(function(opt) {
        opt.addEventListener('mousedown', function(e) {
          e.preventDefault();
          var pid = opt.dataset.pid;
          var product = null;
          for (var j = 0; j < list.length; j++) {
            if (String(list[j].product_id) === String(pid)) { product = list[j]; break; }
          }
          removeDd();
          inp.value = '';
          if (!product || !self._demandState) return;

          var localId = 'n' + Date.now();
          var newItem = {
            _localId:         localId,
            id:               null,
            product_id:       parseInt(product.product_id),
            product_name:     product.name || '',
            name:             product.name || '',
            sku:              product.product_article || '',
            article:          product.product_article || '',
            quantity:         1,
            price:            parseFloat(product.price) || 0,
            discount_percent: 0,
            vat_rate:         parseFloat(product.vat) || 0,
            sum_row: 0, sum: 0, vat_amount: 0, discount_amount: 0,
          };
          self._calcItem(newItem);
          newItem.sum_row = newItem.sum;
          self._demandState.items.push(newItem);

          var tbody = el.querySelector('.ws-of-items tbody');
          if (tbody) {
            var tr = document.createElement('tr');
            tr.className = 'ws-of-items-body';
            tr.dataset.localId    = localId;
            tr.dataset.itemId     = '';
            tr.dataset.sumChanged = '0';
            tr.innerHTML = '<td class="ws-of-name-cell">'
              + (newItem.article ? (newItem.product_id ? '<a class="ws-of-sku" href="/catalog?selected=' + parseInt(newItem.product_id, 10) + '" target="_blank" title="Відкрити в каталозі">' + self.esc(newItem.article) + '</a>' : '<span class="ws-of-sku">' + self.esc(newItem.article) + '</span>') : '')
              + '<span class="ws-of-nm">' + self.esc(newItem.name || '—') + '</span>'
              + '</td>'
              + '<td><input class="ws-cell-input" data-field="quantity" value="1" type="text"></td>'
              + '<td><input class="ws-cell-input" data-field="price" value="' + newItem.price.toFixed(2) + '" type="text"></td>'
              + '<td><input class="ws-cell-input" data-field="discount_percent" value="" placeholder="0" type="text"></td>'
              + '<td><select class="ws-cell-sel" data-field="vat_rate"><option value="0" selected>—</option><option value="20">20%</option></select></td>'
              + '<td><input class="ws-cell-input sum-field" data-field="sum_row" value="' + newItem.sum.toFixed(2) + '" type="text"></td>'
              + '<td><button type="button" class="ws-item-del-btn" title="Видалити рядок">×</button></td>';
            tbody.appendChild(tr);
            self._bindDemandItemRow(tr);
            // Bind delete for new row
            var delBtn = tr.querySelector('.ws-item-del-btn');
            if (delBtn) {
              delBtn.addEventListener('click', function() {
                for (var i = 0; i < self._demandState.items.length; i++) {
                  if (String(self._demandState.items[i]._localId) === localId) {
                    self._demandState.items[i]._deleted = true; break;
                  }
                }
                tr.remove();
                self._updateDemandFooter();
              });
            }
            tr.scrollIntoView({ block: 'nearest' });
            var qtyInp = tr.querySelector('[data-field="quantity"]');
            if (qtyInp) { qtyInp.focus(); qtyInp.select(); }
          }
          self._updateDemandFooter();
          var saveBtn = el.querySelector('#wsDfSaveBtn');
          if (saveBtn) { saveBtn.style.display = ''; saveBtn.classList.add('ws-of-save-btn-dirty'); }
        });
      });
    }

    inp.addEventListener('input', function() {
      clearTimeout(timer);
      var q = inp.value.trim();
      if (q.length < 2) { removeDd(); return; }
      timer = setTimeout(function() {
        fetch('/customerorder/search_product?q=' + encodeURIComponent(q))
          .then(function(r){ return r.json(); })
          .then(function(res) { buildDd((res.ok && res.items) ? res.items : []); })
          .catch(function(){ removeDd(); });
      }, 250);
    });

    inp.addEventListener('blur',    function() { setTimeout(removeDd, 120); });
    inp.addEventListener('keydown', function(e) { if (e.key === 'Escape') { removeDd(); inp.value = ''; } });
  },

  payStatusBadge: function(s) {
    var map = {
      not_paid:       { cls: 'wsof-pay-none',    label: 'Не оплачено' },
      partially_paid: { cls: 'wsof-pay-partial',  label: 'Частково' },
      paid:           { cls: 'wsof-pay-done',     label: 'Оплачено' },
      overdue:        { cls: 'wsof-pay-overdue',  label: 'Прострочено' },
      refund:         { cls: 'wsof-pay-refund',   label: 'Повернення' },
    };
    var m = map[s] || map['not_paid'];
    return '<span class="ws-of-mini-badge ' + m.cls + '" title="Статус оплати">₴ ' + m.label + '</span>';
  },

  shipStatusBadge: function(s) {
    var map = {
      not_shipped:       { cls: 'wsof-ship-none',      label: 'Не відвант.' },
      reserved:          { cls: 'wsof-ship-reserved',  label: 'Резерв' },
      partially_shipped: { cls: 'wsof-ship-partial',   label: 'Частково' },
      shipped:           { cls: 'wsof-ship-done',      label: 'Відвантажено' },
      delivered:         { cls: 'wsof-ship-delivered', label: 'Доставлено' },
      returned:          { cls: 'wsof-ship-returned',  label: 'Повернено' },
    };
    var m = map[s] || map['not_shipped'];
    return '<span class="ws-of-mini-badge ' + m.cls + '" title="Статус відвантаження">📦 ' + m.label + '</span>';
  },

  // ── Command menu (💬 / chat) ───────────────────────────────────────────────
  _showCmdMenu: function(anchor, flowData) {
    var self  = this;
    var exist = document.getElementById('wsCmdMenu');
    if (exist) { exist.remove(); return; }

    var order   = flowData ? flowData.order    : {};
    var ttnsNp  = (flowData && flowData.ttns_np)  ? flowData.ttns_np  : [];
    var ttnsUp  = (flowData && flowData.ttns_up)  ? flowData.ttns_up  : [];
    var demands = (flowData && flowData.demands)   ? flowData.demands  : [];

    var cmds = [
      { key:'details',  icon:'📋', label:'Деталі замовлення',    shortcut:'/деталі'   },
      { key:'invoice',  icon:'📄', label:'Рахунок на оплату',    shortcut:'/рахунок'  },
      { key:'pay',      icon:'💳', label:'Посилання на оплату',  shortcut:'/оплата'   },
      { key:'ttn',      icon:'🚚', label:'Номер ТТН',            shortcut:'/ттн',      disabled: ttnsNp.length + ttnsUp.length === 0 },
      { key:'shipment', icon:'📦', label:'Накладна відвантаж.',  shortcut:'/накладна', disabled: demands.length === 0 },
    ];

    var html = '<div class="ws-cmd-menu" id="wsCmdMenu">'
      + '<div class="ws-cmd-menu-head">💬 Надіслати клієнту</div>';
    cmds.forEach(function(cmd) {
      html += '<div class="ws-cmd-item' + (cmd.disabled ? ' disabled' : '') + '" data-cmd="' + cmd.key + '">'
        + '<span class="ws-cmd-icon">' + cmd.icon + '</span>'
        + '<span class="ws-cmd-label">' + cmd.label + '</span>'
        + '<span class="ws-cmd-shortcut">' + cmd.shortcut + '</span>'
        + '</div>';
    });
    html += '<div style="border-top:1px solid #f3f4f6;margin:4px 0"></div>'
      + '<div class="ws-cmd-menu-head">👤 Команда</div>'
      + '<div class="ws-cmd-item" data-cmd="share_employee">'
      + '<span class="ws-cmd-icon">📨</span>'
      + '<span class="ws-cmd-label">Надіслати співробітнику</span>'
      + '<span class="ws-cmd-shortcut"></span>'
      + '</div>';
    html += '</div>';

    var wrap = document.createElement('div');
    wrap.innerHTML = html;
    var menu = wrap.firstChild;
    document.body.appendChild(menu);

    // Position near anchor (above if near bottom, below otherwise)
    var rect = anchor.getBoundingClientRect();
    var menuH = 200; // estimated
    var top = rect.bottom + 4;
    if (top + menuH > window.innerHeight) top = rect.top - menuH - 4;
    menu.style.cssText = 'position:fixed;z-index:9999;top:' + top + 'px;right:' + (window.innerWidth - rect.right) + 'px';

    menu.querySelectorAll('.ws-cmd-item:not(.disabled)').forEach(function(item) {
      item.addEventListener('click', function() {
        menu.remove();
        var cmd = item.dataset.cmd;

        if (cmd === 'share_employee') {
          ShareOrder.open({
            orderId:        order.id,
            orderNumber:    order.number || String(order.id),
            orderDate:      order.moment ? order.moment.substr(0, 10) : '',
            orderSum:       order.sum_total || '0',
            counterpartyId: self.itemId || 0
          });
          return;
        }

        // Switch to chat tab first
        var chatTab = document.querySelector('.ws-hub-tab[data-tab="chat"]');
        if (chatTab && !chatTab.classList.contains('active')) { chatTab.click(); }

        if (cmd === 'invoice' && flowData && flowData.order && flowData.order.id) {
          // Generate PDF on server, then send link
          var inp = document.getElementById('wsMsgInput');
          if (inp) { inp.value = '⏳ Формую рахунок…'; inp.dispatchEvent(new Event('input')); }
          var fd = new FormData();
          fd.append('order_id', flowData.order.id);
          fetch('/print/api/generate_order_pdf', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              var inp2 = document.getElementById('wsMsgInput');
              if (!inp2) return;
              if (d.ok) {
                var order = flowData.order;
                var num   = order.number || d.number || '';
                var date  = order.moment ? order.moment.substr(0, 10) : '';
                inp2.value = 'Рахунок №' + num + (date ? ' від ' + date : '') + ':\n' + d.url;
              } else {
                inp2.value = d.error || 'Помилка генерації PDF';
              }
              inp2.focus();
              inp2.dispatchEvent(new Event('input'));
            })
            .catch(function() {
              var inp2 = document.getElementById('wsMsgInput');
              if (inp2) { inp2.value = self._buildCmdText(cmd, flowData); inp2.focus(); inp2.dispatchEvent(new Event('input')); }
            });
        } else {
          var text = self._buildCmdText(cmd, flowData);
          var inp  = document.getElementById('wsMsgInput');
          if (inp) { inp.value = text; inp.focus(); inp.dispatchEvent(new Event('input')); }
        }
      });
    });

    setTimeout(function() {
      document.addEventListener('click', function closeMenu(e) {
        if (!menu.contains(e.target)) { menu.remove(); document.removeEventListener('click', closeMenu); }
      });
    }, 10);
  },

  _buildCmdText: function(cmd, flowData) {
    var self    = this;
    var order   = flowData ? flowData.order   : {};
    var ttnsNp  = (flowData && flowData.ttns_np)  ? flowData.ttns_np  : [];
    var ttnsUp  = (flowData && flowData.ttns_up)  ? flowData.ttns_up  : [];
    var demands = (flowData && flowData.demands)   ? flowData.demands  : [];

    var num  = order.number || '—';
    var date = order.moment ? order.moment.substr(0,10) : '';
    var sum  = self.formatNum(parseFloat(order.sum_total) || 0);

    if (cmd === 'details') {
      return 'Замовлення #' + num + ' від ' + date + '\nСума до сплати: ₴' + sum;
    }
    if (cmd === 'invoice') {
      return 'Рахунок до сплати\nЗамовлення #' + num + ' від ' + date
        + '\nСума: ₴' + sum + '\n\n[Реквізити для оплати]';
    }
    if (cmd === 'pay') {
      return 'Оплата за замовлення #' + num + '\nСума: ₴' + sum + '\n\n[посилання на оплату]';
    }
    if (cmd === 'ttn') {
      var parts = [];
      ttnsNp.forEach(function(t) {
        if (t.int_doc_number) {
          parts.push('Нова Пошта: ' + t.int_doc_number + (t.state_name ? ' (' + t.state_name + ')' : '')
            + '\nhttps://novaposhta.ua/tracking/' + encodeURIComponent(String(t.int_doc_number)));
        }
      });
      ttnsUp.forEach(function(t) {
        if (t.barcode) parts.push('Укрпошта: ' + t.barcode + (t.lifecycle_status ? ' (' + t.lifecycle_status + ')' : ''));
      });
      return parts.length ? parts.join('\n\n') : 'ТТН ще не оформлений';
    }
    if (cmd === 'shipment') {
      var dem = demands[0] || {};
      return 'Накладна #' + (dem.number || '—') + ' від ' + (dem.moment ? dem.moment.substr(0,10) : '')
        + '\nСума: ₴' + self.formatNum(parseFloat(dem.sum_total) || 0);
    }
    return '';
  },
};

document.addEventListener('DOMContentLoaded', function() { WS.init(); });

function showToast(msg, isError) {
  var t = document.getElementById('wsToast');
  if (!t) return;
  t.textContent = msg;
  t.style.background = isError ? '#dc2626' : '#1f2937';
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(function() { t.classList.remove('show'); }, 2200);
}
</script>

<!-- ══ Reminder modal ════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="wsReminderModal" style="display:none">
  <div class="modal-box" style="max-width:380px">
    <div class="modal-head">
      <span>🔔 Нагадати</span>
      <button class="modal-close" onclick="WS.closeReminder()">&#x2715;</button>
    </div>
    <div class="modal-body" style="display:flex;flex-direction:column;gap:12px">
      <div class="cpp-field">
        <label>Дата та час</label>
        <input type="datetime-local" id="wsReminderDate" style="width:100%" onchange="this.blur()">
      </div>
      <div class="cpp-field">
        <label>Нагадування (текст)</label>
        <textarea id="wsReminderBody" rows="3" placeholder="Зателефонувати, уточнити умови…" style="width:100%"></textarea>
      </div>
      <div class="cpp-field" id="wsReminderAssignWrap">
        <label>Призначити співробітнику (необов'язково)</label>
        <select id="wsReminderAssign" style="width:100%">
          <option value="">— всім (загальне) —</option>
        </select>
      </div>
      <div id="wsReminderErr" style="display:none" class="modal-error"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="WS.closeReminder()">Скасувати</button>
      <button class="btn btn-primary" id="wsReminderSaveBtn" onclick="WS.saveReminder()">Зберегти</button>
    </div>
  </div>
</div>

<style>
.ws-remind-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 0 10px; height: 30px; border-radius: 6px;
    border: 1px solid #e5e7eb; background: #fff; color: #6b7280;
    font-size: 12px; font-family: inherit; cursor: pointer;
    transition: background .12s, border-color .12s, color .12s;
}
.ws-remind-btn:hover { background: #fef9c3; border-color: #fde68a; color: #92400e; }
/* Reminder bubble in chat */
.ws-msg-row.reminder .ws-bubble {
    background: #fef9c3; color: #78350f;
    border: 1px solid #fde68a; border-radius: 8px;
}
.ws-msg-row.reminder .ws-bubble::before {
    content: '🔔 '; font-size: 13px;
}

/* ── Stats chip near name ──────────────────────────────────────────────────── */
.ws-hub-stats { font-size: 11px; color: #6b7280; margin-top: 1px; }
.ws-hub-stats strong { color: #111827; font-weight: 600; }

/* ── Context split layout ──────────────────────────────────────────────────── */
.ws-ctx-split { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
.ws-ctx-contacts {
    padding: 7px 12px 6px; font-size: 11px; color: #6b7280;
    border-bottom: 1px solid #f3f4f6; flex-shrink: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ws-ctx-contacts a { color: #7c3aed; text-decoration: none; }
.ws-ctx-contacts a:hover { text-decoration: underline; }
.ws-ctx-flow-zone { flex-shrink: 0; padding: 10px 0 8px; border-bottom: 1px solid #f3f4f6; background: #fff; }
.ws-ctx-active-order { flex-shrink: 0; background: #fff; border-top: 1px solid #f3f4f6; cursor: pointer; }
.ws-ctx-active-order-row {
    display: flex; align-items: center; gap: 5px;
    padding: 6px 12px; font-size: 11px; transition: background .1s;
}
.ws-ctx-active-order:hover .ws-ctx-active-order-row { background: #f5f3ff; }
.ws-ctx-active-order.active .ws-ctx-active-order-row { background: #ede9fe; }
.ws-ctx-active-order-num { font-weight: 700; color: #111827; white-space: nowrap; flex-shrink: 0; }
.ws-ctx-active-order-date { color: #9ca3af; font-size: 10px; white-space: nowrap; flex-shrink: 0; }
.ws-ctx-active-order-sum { font-weight: 700; color: #7c3aed; white-space: nowrap; margin-left: auto; flex-shrink: 0; }
/* ── 3-column bottom grid ─────────────────────────────────────────────────── */
.ws-ctx-bottom-grid {
    flex: 1; min-height: 0; display: grid;
    grid-template-columns: 1fr 1fr 1fr; grid-template-rows: 1fr;
    border-top: 2px solid #e5e7eb; background: #fff; overflow: hidden;
}
.ws-bottom-col { display: flex; flex-direction: column; overflow-y: auto; min-height: 0; height: 100%; }
.ws-bottom-col-sep { border-left: 1px solid #f3f4f6; }
.ws-bottom-col-hd {
    display: grid; grid-template-columns: 1fr 40px 54px;
    align-items: center; gap: 3px;
    padding: 4px 8px 3px; font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .4px; color: #9ca3af;
    background: #fff; position: sticky; top: 0; z-index: 1;
    border-bottom: 1px solid #f3f4f6;
}
.ws-bottom-col-hd-title { display: flex; align-items: center; gap: 4px; }
.ws-bottom-col-hd-sum   { text-align: right; }
.ws-bottom-col-hd-st    { text-align: right; }
.ws-bottom-cnt {
    background: #f3f4f6; border-radius: 8px; font-size: 9px;
    padding: 0 4px; color: #6b7280; font-weight: 400;
    text-transform: none; letter-spacing: 0;
}
.ws-bottom-row {
    display: grid; grid-template-columns: 1fr 40px 54px;
    align-items: center; gap: 3px;
    padding: 3px 8px; font-size: 10px; cursor: pointer;
    border-bottom: 1px solid #f9fafb; transition: background .1s;
    min-width: 0;
}
.ws-bottom-row:hover { background: #f5f3ff; }
.ws-bottom-row.active { background: #ede9fe; }
.ws-bottom-row-num  { font-weight: 600; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.ws-bottom-row-sum  { font-weight: 600; color: #7c3aed; white-space: nowrap; text-align: right; font-size: 9px; }
.ws-bottom-row-st   { font-size: 9px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: right; }
.ws-bottom-row-st.st-new       { color: #2563eb; }
.ws-bottom-row-st.st-paid      { color: #16a34a; }
.ws-bottom-row-st.st-shipped   { color: #0f766e; }
.ws-bottom-row-st.st-completed { color: #6b7280; }
.ws-bottom-row-st.st-cancelled { color: #dc2626; }
.ws-bottom-row-st.st-delivered { color: #16a34a; }
.ws-bottom-row-st.st-draft    { color: #9ca3af; }
.ws-bottom-row-st.st-transit  { color: #2563eb; }
.ws-bottom-row-st.st-branch   { color: #ea580c; }
.ws-bottom-empty { font-size: 10px; color: #d1d5db; padding: 6px 8px; }
/* legacy */
.ws-ctx-order-history-cnt {
    background: #f3f4f6; border-radius: 8px; font-size: 10px;
    padding: 0 5px; color: #6b7280; font-weight: 400;
    text-transform: none; letter-spacing: 0;
}
.ws-ctx-order-row {
    display: flex; align-items: center; gap: 5px;
    padding: 4px 12px; cursor: pointer; font-size: 11px;
    border-bottom: 1px solid #f9fafb; transition: background .1s;
}
.ws-ctx-order-row:hover { background: #f5f3ff; }
.ws-ctx-order-row.active { background: #ede9fe; }
.ws-ctx-order-row-num { font-weight: 600; color: #111827; white-space: nowrap; flex-shrink: 0; }
.ws-ctx-order-row-date { color: #9ca3af; font-size: 10px; white-space: nowrap; flex-shrink: 0; }
.ws-ctx-order-row-sum { font-weight: 600; color: #7c3aed; white-space: nowrap; margin-left: auto; flex-shrink: 0; }
.ws-ctx-detail-zone { flex-shrink: 0; overflow-y: auto; max-height: 40vh; min-height: 60px; background: #fafafa; }
.ws-ctx-detail-empty {
    display: flex; align-items: center; justify-content: center;
    height: 100%; min-height: 80px; font-size: 11px; color: #d1d5db;
    text-align: center; padding: 12px; flex-direction: column; gap: 6px;
}

/* ── Document flow graph ───────────────────────────────────────────────────── */
.wf-chain {
    display: flex; align-items: stretch; gap: 0;
    overflow-x: auto; padding: 4px 12px 6px;
    scrollbar-width: none; -ms-overflow-style: none;
}
.wf-chain::-webkit-scrollbar { display: none; }
.wf-node {
    flex-shrink: 0; width: 160px;
    border: 1.5px solid #e5e7eb; border-radius: 24px;
    padding: 9px 10px 8px; cursor: pointer; text-align: center;
    transition: border-color .12s, box-shadow .12s;
    display: flex; flex-direction: column; align-items: center; gap: 3px;
}
/* ≤4 нод — рівномірно на всю ширину */
.wf-chain-stretch .wf-node { flex: 1; width: auto; }
.wf-node:hover  { border-color: #a78bfa; box-shadow: 0 1px 5px rgba(124,58,237,.14); }
.wf-node.wf-active { box-shadow: 0 0 0 2.5px currentColor; }
.wf-node.wf-empty { border-style: dashed; border-color: #d1d5db; background: #fafafa !important; }
.wf-node.wf-empty:hover { border-color: #7c3aed; }
.wf-order   { background: #faf5ff; border-color: #c4b5fd; }
.wf-demand  { background: #eff6ff; border-color: #bfdbfe; }
.wf-ttn-np  { background: #fff7ed; border-color: #fed7aa; }
.wf-ttn-up  { background: #ecfeff; border-color: #a5f3fc; }
.wf-payment { background: #f0fdf4; border-color: #bbf7d0; }
.wf-return  { background: #fff1f2; border-color: #fecdd3; }
.wf-order.wf-active   { color: #7c3aed; }
.wf-demand.wf-active  { color: #2563eb; }
.wf-ttn-np.wf-active  { color: #ea580c; }
.wf-ttn-up.wf-active  { color: #0891b2; }
.wf-payment.wf-active { color: #16a34a; }
.wf-return.wf-active  { color: #dc2626; }
.wf-node-lbl { font-size: 10px; font-weight: 700; color: #374151; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wf-node-id  { font-size: 9px; color: #6b7280; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wf-node-amt { font-size: 9px; font-weight: 600; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wf-order   .wf-node-amt { color: #7c3aed; }
.wf-demand  .wf-node-amt { color: #2563eb; }
.wf-ttn-np  .wf-node-amt { color: #ea580c; }
.wf-ttn-up  .wf-node-amt { color: #0891b2; }
.wf-payment .wf-node-amt { color: #16a34a; }
.wf-return  .wf-node-amt { color: #dc2626; }
.wf-node.wf-empty .wf-node-id { color: #9ca3af; font-weight: 400; }
.wf-arrow { flex-shrink: 0; display: flex; align-items: center; gap: 4px; padding: 0 8px; align-self: center; }
.wf-dot-sep { width: 4px; height: 4px; border-radius: 50%; background: #d1d5db; flex-shrink: 0; }
/* ── Delivery progress bar (inside TTN nodes) ───────────────────────────────── */
.wf-delivery-bar { display: flex; align-items: center; gap: 0; margin: 3px 0 1px; width: 100%; justify-content: center; }
.wf-db-pip  { width: 6px; height: 6px; border-radius: 50%; background: #e5e7eb; flex-shrink: 0; transition: background .15s; }
.wf-db-line { flex: 1; height: 1.5px; background: #e5e7eb; max-width: 10px; transition: background .15s; }
.wf-db-pip.done  { background: #22c55e; }
.wf-db-pip.cur   { background: #f97316; box-shadow: 0 0 0 2px #fed7aa; }
.wf-db-pip.fail  { background: #ef4444; }
.wf-db-line.done { background: #22c55e; }
.wf-db-line.cur  { background: linear-gradient(90deg, #22c55e 50%, #e5e7eb 50%); }
/* Done terminal node */
.wf-done {
    background: #f0fdf4; border-color: #86efac;
    animation: wfDonePulse 1.8s ease-in-out 1;
}
.wf-done .wf-node-icon { font-size: 20px; line-height: 1; }
.wf-done .wf-node-type { color: #16a34a; }
.wf-done.wf-active { color: #15803d; }
@keyframes wfDonePulse {
    0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
    50%  { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
    100% { box-shadow: none; }
}
/* Refused/returned TTN */
.wf-ttn-refused { background: #fff1f2 !important; border-color: #fecdd3 !important; }
.wf-ttn-refused .wf-node-sub { color: #dc2626; font-weight: 600; }
.wf-delivery { background: #f0fdf4; border-color: #bbf7d0; }
.wf-delivery.wf-active { background: #dcfce7; border-color: #4ade80; }
.wf-delivery.wf-empty { background: #f9fafb; border-color: #d1fae5; border-style: dashed; }
/* ── Document create bar ───────────────────────────────────────────────────── */
.ws-create-bar { display:flex; align-items:center; gap:6px; padding:6px 10px; background:#f8fafc; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; flex-shrink:0; }
.ws-create-btn { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; font-size:11px; font-weight:500; color:#374151; background:#fff; border:1px solid #d1d5db; border-radius:5px; cursor:pointer; white-space:nowrap; transition:background .12s,border-color .12s; font-family:inherit; }
.ws-create-btn:hover { background:#f0f9ff; border-color:#93c5fd; color:#1d4ed8; }
.ws-create-icon { font-size:13px; }
/* ── Document detail pane ──────────────────────────────────────────────────── */
.ws-doc-detail { padding: 12px 14px; animation: wsDdIn .12s ease; }
@keyframes wsDdIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.ws-doc-detail-head {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #f3f4f6;
}
.ws-doc-detail-icon { font-size: 18px; line-height: 1; }
.ws-doc-detail-title { font-size: 13px; font-weight: 700; color: #111827; flex: 1; min-width: 0; }
.ws-doc-detail-link { font-size: 10px; color: #7c3aed; text-decoration: none; flex-shrink: 0; }
.ws-doc-detail-link:hover { text-decoration: underline; }
.ws-ddr { display: flex; gap: 6px; margin-bottom: 7px; align-items: flex-start; }
.ws-ddr-lbl { color: #9ca3af; flex-shrink: 0; width: 88px; font-size: 11px; }
.ws-ddr-val { color: #111827; font-weight: 500; font-size: 11px; word-break: break-word; flex: 1; }
.ws-doc-detail-acts { margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; }

/* ── Inline order form ─────────────────────────────────────────────────────── */
.ws-order-form { display: flex; flex-direction: column; overflow-y: auto; }

/* Header */
.ws-of-head {
    display: flex; align-items: center; gap: 5px;
    padding: 8px 10px 7px; border-bottom: 1px solid #f3f4f6; flex-shrink: 0;
    min-height: 0;
}
.ws-of-head-num  { font-size: 12px; font-weight: 800; color: #111827; white-space: nowrap; flex-shrink: 0; }
.ws-of-head-date { font-size: 10px; color: #9ca3af; white-space: nowrap; flex-shrink: 0; }
.ws-of-status-sel {
    font-weight: 700; border-radius: 5px; padding: 2px 5px; font-size: 10px;
    border: none; cursor: pointer; font-family: inherit; flex-shrink: 0;
}
.ws-of-head-sep { flex: 1; }
.ws-of-head-btns { display: flex; gap: 3px; flex-shrink: 0; }
.ws-of-head-btn {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: 10px; font-weight: 600; padding: 3px 7px; border-radius: 5px;
    border: 1px solid #e5e7eb; background: #f9fafb; color: #374151;
    cursor: pointer; font-family: inherit; white-space: nowrap; position: relative;
    transition: background .1s, border-color .1s;
}
.ws-of-head-btn:hover { background: #ede9fe; border-color: #c4b5fd; color: #7c3aed; }
.ws-of-icon-btn { padding: 4px 8px !important; font-size: 12px !important; }
.ws-of-editing .ws-of-btn-edit { display: none !important; }
.ws-of-btn-print { background: #7c3aed !important; border-color: #6d28d9 !important; color: #fff !important; }
.ws-of-btn-print:hover { background: #6d28d9 !important; border-color: #5b21b6 !important; color: #fff !important; }
.ws-of-btn-send  { background: #059669 !important; border-color: #047857 !important; color: #fff !important; }
.ws-of-btn-send:hover  { background: #047857 !important; border-color: #065f46 !important; color: #fff !important; }
/* Small gap separator between edit and print/send */
.ws-of-btns-sep { display: inline-block; width: 8px; }
/* Payment / shipment status mini-badges in order header */
.ws-of-mini-badge {
    font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 20px;
    white-space: nowrap; flex-shrink: 0; user-select: none;
}
.wsof-pay-none     { background: #fee2e2; color: #991b1b; }
.wsof-pay-partial  { background: #fef3c7; color: #92400e; }
.wsof-pay-done     { background: #dcfce7; color: #15803d; }
.wsof-pay-overdue  { background: #fee2e2; color: #7f1d1d; }
.wsof-pay-refund   { background: #f3e8ff; color: #6b21a8; }
.wsof-ship-none     { background: #f3f4f6; color: #6b7280; }
.wsof-ship-reserved { background: #dbeafe; color: #1e40af; }
.wsof-ship-partial  { background: #fef3c7; color: #92400e; }
.wsof-ship-done     { background: #e0f2fe; color: #0369a1; }
.wsof-ship-delivered{ background: #dcfce7; color: #15803d; }
.wsof-ship-returned { background: #fee2e2; color: #9a3412; }
/* ── Pipeline block ──────────────────────────────────────────────────────── */
.ws-pl-block {
    padding: 9px 12px 10px; border-bottom: 1px solid #e5e7eb;
    background: #fff; flex-shrink: 0;
}
.ws-pl-head-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 8px;
}
.ws-pl-title {
    font-size: 9px; font-weight: 700; color: #9ca3af;
    letter-spacing: .06em; text-transform: uppercase;
}
.ws-pl-steps {
    display: flex; align-items: flex-start; width: 100%;
}
.ws-pl-step {
    display: flex; flex-direction: column; align-items: center;
    gap: 4px; flex-shrink: 0; cursor: pointer; min-width: 60px;
}
.ws-pl-step:hover:not(.current):not(.cancelled) .ws-pl-dot { border-color: #6b7280; }
.ws-pl-conn {
    flex: 1; height: 2px; background: #e5e7eb;
    margin-top: 9px; min-width: 20px;
}
.ws-pl-conn.done-conn { background: #16a34a; }
.ws-pl-dot {
    width: 20px; height: 20px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700; flex-shrink: 0;
    border: 2px solid #d1d5db; background: #fff; color: #fff;
    transition: border-color .15s;
}
.ws-pl-step.done .ws-pl-dot      { background: #16a34a; border-color: #16a34a; }
.ws-pl-step.current .ws-pl-dot   { background: #2563eb; border-color: #2563eb; }
.ws-pl-step.cancelled .ws-pl-dot { background: #dc2626; border-color: #dc2626; }
.ws-pl-lbl {
    font-size: 10px; color: #9ca3af; text-align: center;
    white-space: nowrap; line-height: 1.2;
}
.ws-pl-step.done .ws-pl-lbl      { color: #6b7280; }
.ws-pl-step.current .ws-pl-lbl   { color: #1d4ed8; font-weight: 700; }
.ws-pl-step.cancelled .ws-pl-lbl { color: #dc2626; font-weight: 700; }
.ws-pl-auto {
    font-size: 9px; color: #16a34a; font-weight: 600;
    background: #dcfce7; padding: 0 5px; border-radius: 3px; line-height: 14px;
}
/* Edit mode bar */
.ws-of-edit-bar {
    display: none; align-items: center; gap: 8px;
    padding: 6px 10px; background: #fef3c7; border-bottom: 1px solid #fde68a;
    font-size: 11px; color: #92400e;
}
.ws-of-editing .ws-of-edit-bar { display: flex; }
.ws-of-edit-bar-label { font-weight: 700; flex-shrink: 0; }
.ws-of-edit-bar-hint  { color: #b45309; flex: 1; }
.ws-of-edit-bar-cancel {
    margin-left: auto; padding: 3px 10px; font-size: 11px; font-weight: 600;
    background: transparent; color: #92400e; border: 1px solid #fcd34d; border-radius: 5px;
    cursor: pointer; font-family: inherit; flex-shrink: 0;
}
.ws-of-edit-bar-cancel:hover { background: #fef3c7; }
.ws-of-edit-bar-done  {
    padding: 3px 10px; font-size: 11px; font-weight: 600;
    background: #7c3aed; color: #fff; border: none; border-radius: 5px;
    cursor: pointer; font-family: inherit; flex-shrink: 0;
}
.ws-of-edit-bar-done:hover { background: #6d28d9; }
/* Edit zone wrapper */
.ws-of-edit-zone { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.ws-of-edit-zone.ws-of-editing { border-top: 2px solid #fbbf24; }
.ws-of-save-btn-dirty { background: #fef9c3 !important; border-color: #fbbf24 !important; color: #92400e !important; }
/* ── Order meta row: org + employee ──────────────────────────── */
.ws-of-meta-row { display:flex; align-items:center; gap:6px; padding:5px 10px 5px; border-bottom:1px solid #f0f0f0; background:#fafafa; flex-shrink:0; }
.ws-of-meta-sel { height:26px; border:1px solid transparent; border-radius:6px; font-size:11px; padding:0 6px; background:transparent; color:#374151; font-family:inherit; pointer-events:none; max-width:180px; -webkit-appearance:none; -moz-appearance:none; appearance:none; }
.ws-of-editing .ws-of-meta-sel { border-color:#e5e7eb; background:#fff; pointer-events:auto; cursor:pointer; -webkit-appearance:auto; -moz-appearance:auto; appearance:auto; }
.ws-of-editing .ws-of-meta-sel:focus { outline:none; border-color:#a78bfa; }
.ws-of-org-sel { font-style:italic; font-weight:700; font-size:12px !important; color:#1e3a5f !important; max-width:200px; }
.ws-of-emp-sel { max-width:150px; }
.ws-of-deliv-sel { max-width:130px; }
.ws-of-pay-sel  { max-width:150px; }
.ws-of-vat-badge { font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; background:#d1fae5; color:#065f46; flex-shrink:0; white-space:nowrap; }
.ws-of-vat-none { background:#f3f4f6; color:#9ca3af; }
/* Search input disabled state */
.ws-item-search:disabled { opacity: .5; cursor: not-allowed; }
.ws-of-editing .ws-item-search:not(:disabled) { border-color: #7c3aed; background: #faf5ff; }
/* Command menu */
.ws-cmd-menu {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    box-shadow: 0 8px 28px rgba(0,0,0,.16); min-width: 220px; overflow: hidden;
}
.ws-cmd-menu-head {
    padding: 7px 12px; font-size: 10px; font-weight: 700; color: #6b7280;
    letter-spacing: .03em; text-transform: uppercase;
    border-bottom: 1px solid #f3f4f6; background: #f9fafb;
}
.ws-cmd-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 12px;
    cursor: pointer; font-size: 12px; color: #374151;
    border-bottom: 1px solid #f9fafb;
}
.ws-cmd-item:last-child { border-bottom: none; }
.ws-cmd-item:hover { background: #f5f3ff; color: #7c3aed; }
.ws-cmd-item.disabled { opacity: .4; cursor: not-allowed; pointer-events: none; }
.ws-cmd-icon { font-size: 14px; flex-shrink: 0; width: 20px; }
.ws-cmd-label { flex: 1; }
.ws-cmd-shortcut { font-size: 10px; color: #9ca3af; font-family: monospace; background: #f3f4f6; padding: 1px 4px; border-radius: 3px; }
/* Print dropdown */
.ws-of-print-dd {
    position: absolute; top: calc(100% + 4px); right: 0; z-index: 200;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,.12); min-width: 160px; padding: 4px 0;
}
.ws-of-print-dd a, .ws-of-print-dd div {
    display: block; padding: 7px 14px; font-size: 11px; color: #374151;
    text-decoration: none; cursor: pointer; white-space: nowrap;
}
.ws-of-print-dd a:hover { background: #ede9fe; color: #7c3aed; }
.ws-of-print-dd div.disabled { color: #c4b5fd; cursor: default; }

/* Items section */
.ws-of-items-wrap { overflow-y: visible; flex-shrink: 0; border-bottom: 1px solid #f3f4f6; }
.ws-of-items { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; }
.ws-of-items thead { position: sticky; top: 0; background: #fff; z-index: 2; }
.ws-of-items th {
    font-size: 9px; text-transform: uppercase; letter-spacing: .3px;
    color: #9ca3af; padding: 5px 3px 5px; border-bottom: 1px solid #f3f4f6;
    text-align: right; white-space: nowrap; overflow: hidden;
}
.ws-of-items th.left { text-align: left; padding-left: 10px; }
.ws-of-items td { padding: 3px 3px; border-bottom: 1px solid #f9fafb; vertical-align: middle; }
.ws-of-items tr.ws-of-items-body:last-child td { border-bottom: none; }
/* Column widths */
.ws-of-col-name { width: auto; }
.ws-of-col-qty  { width: 38px; }
.ws-of-col-unit { width: 28px; }
.ws-of-unit-cell { font-size: 10px; color: #9ca3af; text-align: center; white-space: nowrap; }
.ws-of-col-price{ width: 56px; }
.ws-of-col-disc { width: 34px; }
.ws-of-col-vat  { width: 38px; }
.ws-of-col-sum  { width: 56px; }
.ws-of-col-del  { width: 18px; }
/* Inline cell inputs — locked in view mode, active only when editing */
.ws-cell-input {
    width: 100%; border: none; background: transparent; font-family: inherit;
    font-size: 11px; color: #111827; text-align: right; padding: 2px 2px;
    outline: none; font-variant-numeric: tabular-nums; border-radius: 3px;
    pointer-events: none;
}
.ws-of-editing .ws-cell-input { pointer-events: auto; }
.ws-cell-input:focus { background: #fffbeb; outline: 1.5px solid #fbbf24; }
.ws-cell-input::placeholder { color: #d1d5db; }
.ws-cell-input.sum-field { font-weight: 600; color: #374151; }
.ws-cell-sel {
    width: 100%; border: none; background: transparent; font-family: inherit;
    font-size: 10px; color: #6b7280; padding: 2px 1px; outline: none;
    border-radius: 3px; text-align: right; cursor: pointer;
    pointer-events: none;
}
.ws-of-editing .ws-cell-sel { pointer-events: auto; }
.ws-cell-sel:focus { background: #fffbeb; outline: 1.5px solid #fbbf24; }
/* Name cell */
.ws-of-name-cell { padding-left: 10px !important; overflow: hidden; }
.ws-of-sku  { font-size: 9px; color: #c4b5fd; margin-right: 3px; white-space: nowrap; }
a.ws-of-sku { text-decoration: none; cursor: pointer; }
a.ws-of-sku:hover { color: #7c3aed; text-decoration: underline; }
.ws-of-nm   { font-size: 11px; color: #374151; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 100%; }
.ws-of-stk-hint { font-size: 9px; color: #9ca3af; margin-left: 2px; white-space: nowrap; }
.ws-of-stk-hint.low { color: #dc2626; }
.ws-of-stk-cell { font-size: 12px; text-align: center; color: #374151; white-space: nowrap; }
.ws-of-stk-cell.ws-of-stk-warn { color: #dc2626; font-weight: 600; background: #fef2f2; }
.ws-of-stk-cell.ws-of-stk-unknown { color: #d97706; background: #fffbeb; }
/* Delete button — hidden in view mode, shown only when editing */
.ws-item-del-btn {
    display: none; width: 16px; height: 16px; line-height: 14px; text-align: center;
    background: none; border: none; color: #e5e7eb; font-size: 13px; cursor: pointer;
    border-radius: 3px; padding: 0; font-family: inherit; transition: color .1s, background .1s;
}
.ws-of-editing .ws-item-del-btn { display: block; }
.ws-of-editing .ws-of-items-body:hover .ws-item-del-btn { color: #dc2626; }
.ws-item-del-btn:hover { background: #fee2e2 !important; }
/* Add product block (outside scroll wrapper) */
.ws-of-add-product {
    position: relative; padding: 5px 10px;
    border-top: 1px solid #f3f4f6; border-bottom: 1px solid #f3f4f6;
    background: #fafafa;
}
.ws-item-search-wrap { position: relative; }
.ws-item-search {
    width: 100%; border: 1px dashed #d1d5db; border-radius: 5px;
    padding: 3px 8px; font-size: 11px; font-family: inherit;
    background: #fafafa; color: #6b7280; outline: none;
}
.ws-item-search:focus { border-color: #a78bfa; background: #fff; color: #111827; }
.ws-item-search-dd {
    position: absolute; left: 0; top: calc(100% + 3px); z-index: 300;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,.12); width: 260px; max-height: 180px;
    overflow-y: auto; display: none;
}
.ws-item-search-opt {
    padding: 6px 12px; cursor: pointer; font-size: 11px; color: #374151;
    border-bottom: 1px solid #f9fafb;
}
.ws-item-search-opt:last-child { border-bottom: none; }
.ws-item-search-opt:hover { background: #ede9fe; color: #7c3aed; }
.ws-item-search-opt-art { font-size: 9px; color: #9ca3af; margin-right: 4px; }
.ws-of-empty { font-size: 11px; color: #9ca3af; padding: 12px; text-align: center; }

.ws-of-foot {
    padding: 7px 10px; flex-shrink: 0; border-bottom: 1px solid #f3f4f6;
}
.ws-of-foot-bottom { display: flex; align-items: flex-start; gap: 10px; }
.ws-of-foot-comment { flex: 1; min-width: 0; }
.ws-of-foot-comment textarea {
    width: 100%; font-size: 10px; font-family: inherit;
    border: 1px solid transparent; border-radius: 5px; padding: 4px 6px;
    background: transparent; color: #374151; resize: none; outline: none;
    min-height: 64px; height: 64px; max-height: 200px;
    transition: border-color .1s, background .1s;
    cursor: default;
}
.ws-of-editing .ws-of-foot-comment textarea {
    border-color: #f3f4f6; background: #f9fafb; color: #374151;
    resize: vertical; cursor: text;
}
.ws-of-editing .ws-of-foot-comment textarea:focus { border-color: #c4b5fd; background: #fff; }
.ws-of-foot-totals { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
.ws-of-foot-line { display: flex; align-items: baseline; gap: 6px; }
.ws-of-foot-lbl { font-size: 10px; color: #9ca3af; white-space: nowrap; }
.ws-of-foot-val { font-size: 11px; font-weight: 700; color: #374151; font-variant-numeric: tabular-nums; min-width: 58px; text-align: right; }
.ws-of-foot-val.total { color: #7c3aed; font-size: 14px; }

/* Doc tabs */
.ws-of-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.ws-of-lbl { font-size: 11px; color: #9ca3af; flex-shrink: 0; width: 80px; }
.ws-of-val { flex: 1; min-width: 0; }

/* ── Order action bar ─────────────────────────────────────────────────────── */
.ws-order-actions {
    display: flex; align-items: center; gap: 4px;
    padding: 6px 10px; border-bottom: 1px solid #e5e7eb;
    background: #fafafa; flex-shrink: 0;
}
.ws-oa-btn {
    display: inline-flex; align-items: center; gap: 5px;
    height: 28px; padding: 0 8px; border-radius: 6px;
    border: 1px solid #e5e7eb; background: #fff;
    font-size: 11px; font-weight: 500; color: #374151;
    cursor: pointer; transition: background .12s, border-color .12s;
    text-decoration: none; font-family: inherit; flex-shrink: 0;
}
.ws-oa-btn:hover { background: #f3f4f6; border-color: #d1d5db; }
.ws-oa-btn.ws-oa-active { background: #fff7ed; border-color: #fbbf24; }
.ws-oa-amber { border-color: #fbbf24; color: #92400e; }
.ws-oa-amber:hover { background: #fff7ed; border-color: #f59e0b; }
.ws-oa-amber.ws-oa-active { background: #fff7ed; border-color: #f59e0b; }
.ws-oa-red { border-color: #fca5a5; color: #991b1b; }
.ws-oa-red:hover { background: #fff1f2; border-color: #f87171; }
.ws-oa-sep { flex: 1; }

/* ── Return panel ─────────────────────────────────────────────────────────── */
.ws-ret-panel { display: none; border-bottom: 1px solid #fde68a; background: #fff; flex-shrink: 0; }
.ws-ret-panel.open { display: block; }
.ws-ret-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 7px 12px; background: #fff7ed;
    font-size: 12px; font-weight: 600; color: #92400e;
    border-bottom: 1px solid #fde68a;
}
.ws-ret-head button {
    background: none; border: none; cursor: pointer;
    font-size: 18px; color: #b45309; padding: 0 2px; line-height: 1;
}
.ws-ret-body { padding: 10px 12px; }
.ws-ret-note { font-size: 11px; color: #6b7280; margin-bottom: 8px; }
.ws-ret-opts { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 8px; }
.ws-ret-opt {
    flex: 1; min-width: 100px; padding: 7px 9px; border-radius: 8px;
    border: 1.5px solid #e5e7eb; background: #f9fafb;
    cursor: pointer; display: flex; flex-direction: column; gap: 2px;
    transition: border-color .12s, background .12s;
}
.ws-ret-opt:hover   { border-color: #fbbf24; background: #fff7ed; }
.ws-ret-opt.selected { border-color: #f59e0b; background: #fff7ed; }
.ws-ret-opt-icon { font-size: 13px; }
.ws-ret-opt-lbl  { font-size: 11px; font-weight: 600; color: #374151; }
.ws-ret-opt-sub  { font-size: 10px; color: #9ca3af; }
.ws-ret-input-row { display: flex; gap: 6px; align-items: center; }
.ws-ret-input {
    flex: 1; height: 30px; padding: 0 8px; border-radius: 6px;
    border: 1px solid #d1d5db; font-size: 12px; font-family: inherit; outline: none;
}
.ws-ret-input:focus { border-color: #f59e0b; }
.ws-ret-save-btn {
    height: 30px; padding: 0 14px; border-radius: 6px; border: none;
    background: #f59e0b; color: #fff; font-size: 11px; font-weight: 600;
    cursor: pointer; font-family: inherit; flex-shrink: 0;
}
.ws-ret-save-btn:hover { background: #d97706; }
.ws-ret-save-btn:disabled { opacity: .6; cursor: default; }

/* ── ret_log flow node ───────────────────────────────────────────────────── */
.wf-ret-log { border-color: #fbbf24; }
.wf-ret-log .wf-node-lbl { color: #92400e; }
.wf-ret-log .wf-node-id  { color: #92400e; }
.ws-create-btn-right { margin-left: auto; }
.ws-create-btn-right + .ws-create-btn-action { margin-left: 0; }
.ws-create-btn-disabled { opacity: .45; cursor: not-allowed !important; pointer-events: none; }
.ws-create-btn-danger { color: #9a3412; border-color: #fca5a5; }
.ws-create-btn-danger:hover { background: #fff1f2 !important; border-color: #f87171 !important; color: #7f1d1d !important; }
/* All icon buttons in header — muted neutral style */
.ws-of-head-btns .ws-of-icon-btn:not(.ws-of-save-btn) {
    color: #9ca3af; border-color: #e5e7eb; background: #fafafa;
}
.ws-of-head-btns .ws-of-icon-btn:not(.ws-of-save-btn):hover {
    color: #6b7280; background: #f3f4f6; border-color: #d1d5db;
}
#wsOaSendBtn.ws-oa-active { color: #047857 !important; background: #ecfdf5 !important; border-color: #6ee7b7 !important; }
</style>

<script>
(function() {
    // ── Reminder modal ────────────────────────────────────────────────────────
    WS.openReminder = function() {
        if (!this.kind || !this.itemId) return;
        // Default: tomorrow 09:00
        var d = new Date();
        d.setDate(d.getDate() + 1);
        d.setHours(9, 0, 0, 0);
        var pad = function(n) { return n < 10 ? '0' + n : n; };
        var def = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate())
                + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        document.getElementById('wsReminderDate').value = def;
        document.getElementById('wsReminderBody').value = '';
        document.getElementById('wsReminderErr').style.display = 'none';
        document.getElementById('wsReminderModal').style.display = 'flex';
        this.loadEmployees();
        document.getElementById('wsReminderBody').focus();
    };

    WS.closeReminder = function() {
        document.getElementById('wsReminderModal').style.display = 'none';
    };

    WS.loadEmployees = function() {
        var sel = document.getElementById('wsReminderAssign');
        if (sel.dataset.loaded) return;
        fetch('/counterparties/api/get_employees')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;
                d.employees.forEach(function(e) {
                    var opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = e.full_name;
                    sel.appendChild(opt);
                });
                sel.dataset.loaded = '1';
            });
    };

    WS.saveReminder = function() {
        var date   = document.getElementById('wsReminderDate').value;
        var body   = document.getElementById('wsReminderBody').value.trim();
        var assign = document.getElementById('wsReminderAssign').value;
        var errEl  = document.getElementById('wsReminderErr');
        var btn    = document.getElementById('wsReminderSaveBtn');

        if (!date) { errEl.textContent = 'Вкажіть дату'; errEl.style.display = 'block'; return; }
        if (!body) { errEl.textContent = 'Вкажіть текст нагадування'; errEl.style.display = 'block'; return; }

        var params = new URLSearchParams();
        if (this.kind === 'lead') { params.append('lead_id', this.itemId); }
        else                      { params.append('id', this.itemId); }
        params.append('body', body);
        params.append('scheduled_at', date.replace('T', ' '));
        if (assign) params.append('assigned_to', assign);

        btn.disabled = true;
        fetch('/counterparties/api/save_reminder', { method: 'POST', body: params })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.disabled = false;
                if (!d.ok) { errEl.textContent = d.error; errEl.style.display = 'block'; return; }
                WS.closeReminder();
                WS.showToast('Нагадування збережено на ' + date.replace('T', ' '));
            })
            .catch(function() { btn.disabled = false; errEl.textContent = 'Помилка мережі'; errEl.style.display = 'block'; });
    };

    // Close on overlay click
    document.getElementById('wsReminderModal').addEventListener('click', function(e) {
        if (e.target === this) WS.closeReminder();
    });
}());
</script>

<div id="wsToast" class="toast"></div>

<!-- ══ CANCEL ORDER MODAL ════════════════════════════════════════════════════ -->
<div id="wsOrderCancelModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:360px">
    <div class="modal-head">
      <span>Скасувати замовлення?</span>
      <button class="modal-close" id="wsOrderCancelClose">&#x2715;</button>
    </div>
    <div class="modal-body" style="padding:16px 20px">
      <p id="wsOrderCancelDesc" style="font-size:13px;color:var(--text-muted);margin:0 0 12px;line-height:1.6"></p>
      <div id="wsOrderCancelCascade" style="font-size:12px;color:#374151;line-height:2"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost btn-sm" id="wsOrderCancelBack">Назад</button>
      <button class="btn btn-danger btn-sm" id="wsOrderCancelConfirm">Так, скасувати</button>
    </div>
  </div>
</div>
<script>
(function() {
  function closeCancel() { document.getElementById('wsOrderCancelModal').style.display = 'none'; }
  document.getElementById('wsOrderCancelClose').addEventListener('click', closeCancel);
  document.getElementById('wsOrderCancelBack').addEventListener('click', closeCancel);
  document.getElementById('wsOrderCancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancel();
  });
  // Expose helper so _bindOrderActions can open it
  WS._openCancelModal = function() { document.getElementById('wsOrderCancelModal').style.display = 'flex'; };
}());
</script>

<!-- ══ SPAM MODAL ════════════════════════════════════════════════════════════ -->
<div id="wsSpamModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:560px">
    <div class="modal-head">
      <span>Заблоковані відправники</span>
      <button class="modal-close" onclick="WS.closeSpamModal()">&#x2715;</button>
    </div>
    <div class="modal-body" style="padding:0">
      <div id="wsSpamLoading" style="padding:20px;text-align:center;color:#9ca3af;font-size:13px">Завантаження…</div>
      <table id="wsSpamTable" class="crm-table" style="display:none;margin:0">
        <thead><tr>
          <th>Канал</th>
          <th>Відправник</th>
          <th>Заблоковано</th>
          <th></th>
        </tr></thead>
        <tbody id="wsSpamTbody"></tbody>
      </table>
      <div id="wsSpamEmpty" style="display:none;padding:20px;text-align:center;color:#9ca3af;font-size:13px">Список порожній</div>
    </div>
  </div>
</div>

<script>
(function() {
    var channelLabels = { email: 'Email', telegram: 'Telegram', viber: 'Viber', sms: 'SMS', website: 'Сайт' };

    WS.openSpamModal = function() {
        document.getElementById('wsSpamModal').style.display = 'flex';
        WS._loadSpamList();
    };

    WS.closeSpamModal = function() {
        document.getElementById('wsSpamModal').style.display = 'none';
    };

    WS._loadSpamList = function() {
        var loading = document.getElementById('wsSpamLoading');
        var table   = document.getElementById('wsSpamTable');
        var empty   = document.getElementById('wsSpamEmpty');
        var tbody   = document.getElementById('wsSpamTbody');

        loading.style.display = 'block';
        table.style.display   = 'none';
        empty.style.display   = 'none';

        fetch('/counterparties/api/spam_senders')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                loading.style.display = 'none';
                if (!d.ok || !d.rows || d.rows.length === 0) {
                    empty.style.display = 'block';
                    return;
                }
                tbody.innerHTML = '';
                d.rows.forEach(function(row) {
                    var tr = document.createElement('tr');
                    var ch = channelLabels[row.channel] || row.channel;
                    var name = row.display_name && row.display_name !== row.identifier
                        ? WS.esc(row.display_name) + '<br><span style="color:#9ca3af;font-size:11px">' + WS.esc(row.identifier) + '</span>'
                        : WS.esc(row.identifier);
                    var dt = row.blocked_at ? row.blocked_at.substring(0, 16).replace('T', ' ') : '';
                    tr.innerHTML = '<td><span class="badge badge-gray">' + WS.esc(ch) + '</span></td>'
                        + '<td>' + name + '</td>'
                        + '<td style="white-space:nowrap;font-size:12px;color:#6b7280">' + WS.esc(dt) + '</td>'
                        + '<td><button class="btn btn-xs" data-spam-id="' + (parseInt(row.id, 10)) + '">Розблокувати</button></td>';
                    tbody.appendChild(tr);
                });
                table.style.display = '';
            })
            .catch(function() {
                loading.style.display = 'none';
                empty.textContent = 'Помилка завантаження';
                empty.style.display = 'block';
            });
    };

    document.getElementById('wsSpamTbody').addEventListener('click', function(e) {
        var btn = e.target.closest('button[data-spam-id]');
        if (!btn) return;
        var id = parseInt(btn.dataset.spamId, 10);
        if (!id) return;
        btn.disabled = true;
        btn.textContent = '…';
        var fd = new FormData();
        fd.append('action', 'unblock');
        fd.append('id', id);
        fetch('/counterparties/api/spam_senders', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    var tr = btn.closest('tr');
                    if (tr) tr.remove();
                    var tbody = document.getElementById('wsSpamTbody');
                    if (tbody && !tbody.querySelector('tr')) {
                        document.getElementById('wsSpamTable').style.display = 'none';
                        document.getElementById('wsSpamEmpty').style.display = 'block';
                    }
                    WS.showToast('Розблоковано');
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Розблокувати';
                    WS.showToast('Помилка', true);
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = 'Розблокувати';
            });
    });

    document.getElementById('wsSpamModal').addEventListener('click', function(e) {
        if (e.target === this) WS.closeSpamModal();
    });
}());
</script>

<?php require_once __DIR__ . '/../../shared/print-modal.php'; ?>
<?php require_once __DIR__ . '/../../shared/layout_end.php'; ?>
