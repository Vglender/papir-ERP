<?php
// Unread incoming message count for Чат badge
$_chatRepo2 = new ChatRepository();
$_chatUnread = $_chatRepo2->getUnreadCount($id);
?>
<style>
.cpp-contact-switcher{display:flex;flex-wrap:wrap;gap:4px;padding:6px 10px 5px;border-bottom:1px solid var(--border);background:var(--bg-card);flex-shrink:0}
.cpp-cs-btn{padding:3px 9px;font-size:11px;font-weight:600;background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;cursor:pointer;white-space:nowrap;color:var(--text-muted);transition:background .12s,border-color .12s,color .12s;max-width:140px;overflow:hidden;text-overflow:ellipsis}
.cpp-cs-btn:hover{color:var(--text);border-color:#c0c8d0}
.cpp-cs-btn.active{background:var(--blue-bg);border-color:var(--blue-light);color:var(--blue)}
/* Attachment preview */
.cpp-attach-preview{display:none;align-items:center;gap:8px;padding:5px 8px;background:var(--blue-bg);border:1px solid var(--blue-light);border-radius:var(--radius-sm);margin-bottom:4px}
.cpp-attach-preview.visible{display:flex}
.cpp-attach-thumb{width:38px;height:38px;border-radius:4px;object-fit:cover;flex-shrink:0}
.cpp-attach-icon{width:38px;height:38px;border-radius:4px;background:#ede9fe;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;border:1px solid #e5e7eb}
.cpp-attach-info{flex:1;min-width:0}
.cpp-attach-nm{font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)}
.cpp-attach-sz{font-size:10px;color:var(--text-muted)}
.cpp-attach-rm{border:none;background:transparent;cursor:pointer;color:var(--text-muted);font-size:16px;padding:0 3px;border-radius:4px;line-height:1;flex-shrink:0}
.cpp-attach-rm:hover{background:#fee2e2;color:#dc2626}
</style>
<div class="cpp-wrap" data-id="<?php echo $id; ?>">

<!-- ── Panel header ──────────────────────────────────────────────────────── -->
<div class="cpp-header">
    <div class="cpp-avatar <?php echo htmlspecialchars($cp['type']); ?>"><?php echo $initials; ?></div>
    <div class="cpp-name-block">
        <div class="cpp-name"><?php echo htmlspecialchars($cp['name']); ?></div>
        <div class="cpp-badges">
            <span class="badge <?php echo CounterpartyRepository::typeBadgeClass($cp['type']); ?>">
                <?php echo CounterpartyRepository::typeLabel($cp['type']); ?>
            </span>
            <?php if (!$cp['status']): ?>
                <span class="badge badge-gray">Архів</span>
            <?php endif; ?>
            <?php if ($cp['group_name']): ?>
                <span class="badge" style="background:var(--blue-bg);color:var(--blue)"><?php echo htmlspecialchars($cp['group_name']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="cpp-header-btns">
        <a href="/counterparties/view?id=<?php echo $id; ?>" class="btn btn-sm" title="Відкрити повну картку" target="_blank">↗</a>
        <button class="btn btn-sm cpp-close" title="Закрити">✕</button>
    </div>
</div>

<!-- ── Quick contacts ────────────────────────────────────────────────────── -->
<?php if ($phone || $email): ?>
<div class="cpp-quick-contacts">
    <?php if ($phone): ?>
        <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="cpp-contact-pill">
            <svg width="12" height="12" fill="none" viewBox="0 0 16 16"><path d="M3 2h3l1 4-1.5 1.5a11 11 0 0 0 3 3L10 9l4 1v3a1 1 0 0 1-1 1A13 13 0 0 1 2 3a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="1.5"/></svg>
            <?php echo htmlspecialchars($phone); ?>
        </a>
    <?php endif; ?>
    <?php if ($email): ?>
        <a href="mailto:<?php echo htmlspecialchars($email); ?>" class="cpp-contact-pill">
            <svg width="12" height="12" fill="none" viewBox="0 0 16 16"><rect x="1.5" y="3.5" width="13" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M1.5 5l6.5 5 6.5-5" stroke="currentColor" stroke-width="1.5"/></svg>
            <?php echo htmlspecialchars($email); ?>
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Stats strip ───────────────────────────────────────────────────────── -->
<div class="cpp-stats">
    <div class="cpp-stat">
        <span class="cpp-stat-val"><?php echo (int)$stats['order_count']; ?></span>
        <span class="cpp-stat-lbl">замовлень</span>
    </div>
    <div class="cpp-stat">
        <span class="cpp-stat-val <?php echo $stats['ltv'] > 0 ? 'green' : ''; ?>">
            <?php echo $stats['ltv'] > 0 ? '₴'.number_format((float)$stats['ltv'],0,'.', ' ') : '—'; ?>
        </span>
        <span class="cpp-stat-lbl">LTV</span>
    </div>
    <div class="cpp-stat">
        <span class="cpp-stat-val">
            <?php echo $stats['avg_check'] > 0 ? '₴'.number_format((float)$stats['avg_check'],0,'.', ' ') : '—'; ?>
        </span>
        <span class="cpp-stat-lbl">сер. чек</span>
    </div>
    <div class="cpp-stat">
        <?php if ($stats['last_order_at']):
            $d = new DateTime($stats['last_order_at']); $n = new DateTime(); $df = $n->diff($d);
            if ($df->days===0) $ls='Сьогодні';
            elseif($df->days===1) $ls='Вчора';
            elseif($df->days<=30) $ls=$df->days.'д тому';
            else $ls=(int)($df->days/30).'міс';
        ?>
            <span class="cpp-stat-val"><?php echo $ls; ?></span>
        <?php else: ?>
            <span class="cpp-stat-val" style="color:var(--text-faint)">—</span>
        <?php endif; ?>
        <span class="cpp-stat-lbl">останнє</span>
    </div>
</div>

<!-- ── Tabs ──────────────────────────────────────────────────────────────── -->
<?php
$relCount    = count($contacts) + count($relations);
$feedCount   = count($activities) + (int)$stats['order_count'];
// Build combined feed timeline (newest first)
$feedItems = array();
foreach ($activities as $act) {
    $feedItems[] = array('kind'=>'note',  'date'=>$act['created_at'], 'data'=>$act);
}
foreach ($recentOrders as $ord) {
    $feedItems[] = array('kind'=>'order', 'date'=>$ord['moment'],     'data'=>$ord);
}
usort($feedItems, function($a, $b){ return strcmp($b['date'], $a['date']); });
?>
<div class="cpp-tabs-nav">
    <button class="cpp-tab active" data-tab="req">Реквізити</button>
    <button class="cpp-tab" data-tab="relations">
        Зв'язки<?php if ($relCount): ?> <span class="cpp-tab-cnt"><?php echo $relCount; ?></span><?php endif; ?>
    </button>
    <button class="cpp-tab" data-tab="feed">
        Лента<?php if ($feedCount): ?> <span class="cpp-tab-cnt"><?php echo $feedCount; ?></span><?php endif; ?>
    </button>
    <button class="cpp-tab" data-tab="chat" id="cppTabChat">
        Чат<?php if ($_chatUnread > 0): ?> <span class="cpp-tab-cnt cpp-tab-unread" id="cppUnreadBadge"><?php echo $_chatUnread; ?></span><?php endif; ?>
    </button>
</div>

<!-- ── Tab: Реквізити ────────────────────────────────────────────────────── -->
<div class="cpp-panel" id="cpp-req">

    <div class="cpp-field">
        <label>Назва</label>
        <input type="text" id="cpfName" value="<?php echo htmlspecialchars($cp['name']); ?>">
    </div>

    <?php if ($isCompany): ?>
    <div class="cpp-field">
        <label>Телефон</label>
        <input type="text" id="cpfPhone" value="<?php echo htmlspecialchars((string)$cp['company_phone']); ?>">
    </div>
    <div class="cpp-field">
        <label>Email</label>
        <input type="email" id="cpfEmail" value="<?php echo htmlspecialchars((string)$cp['company_email']); ?>">
    </div>
    <div class="cpp-field">
        <label>Сайт</label>
        <input type="text" id="cpfWebsite" value="<?php echo htmlspecialchars((string)$cp['website']); ?>" placeholder="https://…">
    </div>
    <div class="cpp-field">
        <label>IBAN</label>
        <input type="text" id="cpfIban" value="<?php echo htmlspecialchars((string)$cp['iban']); ?>" placeholder="UA…">
    </div>

    <?php elseif ($isPerson): ?>
    <div class="cpp-field-row">
        <div class="cpp-field">
            <label>Ім'я</label>
            <input type="text" id="cpfFirst" value="<?php echo htmlspecialchars((string)$cp['first_name']); ?>">
        </div>
        <div class="cpp-field">
            <label>Прізвище</label>
            <input type="text" id="cpfLast" value="<?php echo htmlspecialchars((string)$cp['last_name']); ?>">
        </div>
    </div>
    <div class="cpp-field">
        <label>Телефон</label>
        <input type="text" id="cpfPhone" value="<?php echo htmlspecialchars((string)$cp['person_phone']); ?>">
    </div>
    <div class="cpp-field">
        <label>Email</label>
        <input type="email" id="cpfEmail" value="<?php echo htmlspecialchars((string)$cp['person_email']); ?>">
    </div>
    <div class="cpp-field">
        <label>Telegram</label>
        <input type="text" id="cpfTelegram" value="<?php echo htmlspecialchars((string)$cp['telegram']); ?>" placeholder="@username">
    </div>
    <?php endif; ?>

    <div class="cpp-save-row">
        <button class="btn btn-primary btn-sm" id="cpBtnSaveReq">Зберегти</button>
        <span class="cpp-save-status" id="cpSaveStatus"></span>
    </div>
</div>

<!-- ── Tab: Зв'язки (mini graph) ─────────────────────────────────────────── -->
<div class="cpp-panel hidden" id="cpp-relations" style="padding:0">
<?php if (empty($contacts) && empty($relations) && empty($groupMembers)): ?>
    <div class="rg-mini-empty">Зв'язків ще немає.</div>
<?php else: ?>
<div class="rg-mini-wrap">

    <?php if (!empty($groupMembers)): ?>
    <div class="rg-mini-group">
        <span class="rg-mini-group-lbl">Група</span>
        <?php foreach ($groupMembers as $gi => $m):
            $isSelf = ((int)$m['id'] === $id);
            $mInit  = mb_strtoupper(mb_substr($m['name'],0,1,'UTF-8'),'UTF-8');
        ?>
        <?php if ($gi > 0): ?><span class="rg-mini-group-sep">—</span><?php endif; ?>
        <a href="/counterparties/view?id=<?php echo $m['id']; ?>"
           class="rg-mini-group-node <?php echo $isSelf ? 'rg-self' : ''; ?>">
            <?php echo $mInit; ?> <?php echo htmlspecialchars($m['name']); ?>
            <?php if ($m['group_is_head']): ?> ★<?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php $allChildren = array_merge($contacts, $relations); ?>
    <?php if (!empty($allChildren)): ?>
    <div class="rg-mini-main" id="cppRelMain">
        <svg class="rg-mini-svg" id="cppRelSvg"></svg>

        <div class="rg-mini-row">
            <div class="rg-mini-nw">
                <div class="rg-mini-node rg-self <?php echo htmlspecialchars($cp['type']); ?>" id="cppNodeSelf">
                    <div class="rg-mini-av"><?php echo $initials; ?></div>
                    <div class="rg-mini-name"><?php echo htmlspecialchars($cp['name']); ?></div>
                </div>
            </div>
        </div>

        <div class="rg-mini-row">
            <?php foreach ($contacts as $c):
                $cInit     = mb_strtoupper(mb_substr($c['name'],0,1,'UTF-8'),'UTF-8');
                $roleLabel = CounterpartyRepository::relationTypeLabel($c['relation_type']);
            ?>
            <div class="rg-mini-nw" data-child-of="cppNodeSelf">
                <a href="/counterparties/view?id=<?php echo $c['id']; ?>" class="rg-mini-node person">
                    <div class="rg-mini-av"><?php echo $cInit; ?></div>
                    <div class="rg-mini-name"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="rg-mini-role"><?php echo htmlspecialchars($roleLabel); ?></div>
                </a>
            </div>
            <?php endforeach; ?>

            <?php foreach ($relations as $rel):
                $rInit = mb_strtoupper(mb_substr($rel['name'],0,1,'UTF-8'),'UTF-8');
                $rCls  = in_array($rel['type'], array('company','fop','person')) ? $rel['type'] : 'other';
                $rRole = CounterpartyRepository::relationTypeLabel($rel['relation_type']);
            ?>
            <div class="rg-mini-nw" data-child-of="cppNodeSelf">
                <a href="/counterparties/view?id=<?php echo $rel['id']; ?>" class="rg-mini-node <?php echo $rCls; ?>">
                    <div class="rg-mini-av"><?php echo $rInit; ?></div>
                    <div class="rg-mini-name"><?php echo htmlspecialchars($rel['name']); ?></div>
                    <div class="rg-mini-role"><?php echo htmlspecialchars($rRole); ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="rg-mini-empty" style="padding-top:0">Контактних осіб та зв'язків ще немає.</div>
    <?php endif; ?>

</div>
<?php endif; ?>
</div>

<!-- ── Tab: Лента подій ──────────────────────────────────────────────────── -->
<div class="cpp-panel hidden cpp-feed-wrap" id="cpp-feed" style="padding:0">
    <div class="cpp-feed-list" id="cppFeedList">
        <?php if (empty($feedItems)): ?>
        <div class="cpp-feed-empty">Подій ще немає. Залиште першу нотатку.</div>
        <?php else: ?>
        <?php foreach ($feedItems as $item):
            $dtObj = new DateTime($item['date']);
            $now   = new DateTime();
            $diff  = $now->diff($dtObj);
            if ($diff->days === 0)     $dtStr = 'Сьогодні ' . $dtObj->format('H:i');
            elseif ($diff->days === 1) $dtStr = 'Вчора '    . $dtObj->format('H:i');
            elseif ($diff->days <= 30) $dtStr = $diff->days . ' дн. тому';
            else                       $dtStr = $dtObj->format('d.m.Y');
        ?>
        <?php if ($item['kind'] === 'note'): ?>
        <div class="cpp-feed-note" data-act-id="<?php echo (int)$item['data']['id']; ?>">
            <div class="cpp-feed-note-meta">💬 <?php echo $dtStr; ?></div>
            <div class="cpp-feed-note-text"><?php echo htmlspecialchars($item['data']['content']); ?></div>
        </div>
        <?php else:
            $ord = $item['data'];
            $sl  = isset($orderStatusLabels[$ord['status']]) ? $orderStatusLabels[$ord['status']] : $ord['status'];
        ?>
        <div class="cpp-feed-order">
            <div class="cpp-feed-ord-icon">🛒</div>
            <div class="cpp-feed-ord-body">
                <div class="cpp-feed-ord-title">
                    <a href="/customerorder/edit?id=<?php echo $ord['id']; ?>" target="_blank"><?php echo htmlspecialchars($ord['number']); ?></a>
                    <span style="font-variant-numeric:tabular-nums; font-weight:600">₴<?php echo number_format((float)$ord['sum_total'],0,'.',' '); ?></span>
                </div>
                <div class="cpp-feed-ord-meta">
                    <?php echo $dtStr; ?>
                    <span class="badge badge-gray" style="font-size:10px"><?php echo $sl; ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="cpp-chat-input">
        <textarea id="cppChatText" placeholder="Написати нотатку…" rows="1"></textarea>
        <button class="btn btn-primary btn-sm" id="cppChatSend" style="height:36px; flex-shrink:0">↑</button>
    </div>
</div>

<!-- ── Tab: Чат ───────────────────────────────────────────────────────────── -->
<div class="cpp-panel hidden cpp-chat-wrap" id="cpp-chat" style="padding:0">

<?php
// Build list of linked persons that have at least one contact channel
$_chatContacts = array();
foreach ($contacts as $_ct) {
    if ($_ct['phone'] || $_ct['viber'] || $_ct['telegram'] || $_ct['email']) {
        $_chatContacts[] = $_ct;
    }
}
?>
<?php if (!empty($_chatContacts)): ?>
    <!-- Contact switcher -->
    <div class="cpp-contact-switcher" id="cppContactSwitcher">
        <button class="cpp-cs-btn active" data-cs-id="<?php echo $id; ?>">
            🏢 <?php echo htmlspecialchars(mb_strimwidth($cp['name'], 0, 22, '…', 'UTF-8')); ?>
        </button>
        <?php foreach ($_chatContacts as $_ct): ?>
        <button class="cpp-cs-btn" data-cs-id="<?php echo (int)$_ct['id']; ?>" title="<?php echo htmlspecialchars($_ct['name']); ?>">
            👤 <?php echo htmlspecialchars(mb_strimwidth($_ct['name'], 0, 18, '…', 'UTF-8')); ?>
        </button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <!-- Channel tabs -->
    <div class="cpp-ch-tabs">
        <button class="cpp-ch-tab active" data-ch="viber">Viber</button>
        <button class="cpp-ch-tab" data-ch="sms">SMS</button>
        <?php if ($email): ?>
        <button class="cpp-ch-tab" data-ch="email">Email</button>
        <?php endif; ?>
        <button class="cpp-ch-tab" data-ch="telegram">Telegram</button>
        <button class="cpp-ch-tab" data-ch="note">Нотатка</button>
    </div>

    <!-- Messages area -->
    <div class="cpp-msgs" id="cppMsgsList">
        <div class="cpp-msgs-loading">Завантаження…</div>
    </div>

    <!-- Templates row -->
    <div class="cpp-tpl-row" id="cppTplRow"></div>

    <!-- Input area -->
    <input type="file" id="cppFileInput" style="display:none"
           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
    <div class="cpp-chat-input cpp-chat-input-ch" id="cppInputArea">
        <div style="flex:1; display:flex; flex-direction:column; gap:5px; position:relative;">
            <input type="text" id="cppMsgSubject" placeholder="Тема листа…"
                   style="display:none; padding:5px 9px; border:1px solid var(--border-input); border-radius:var(--radius-sm); font-size:12px; font-family:var(--font); outline:none;">
            <!-- Attachment preview -->
            <div class="cpp-attach-preview" id="cppAttachPreview">
                <div id="cppAttachThumb"></div>
                <div class="cpp-attach-info">
                    <div class="cpp-attach-nm" id="cppAttachName"></div>
                    <div class="cpp-attach-sz" id="cppAttachSize"></div>
                </div>
                <button class="cpp-attach-rm" id="cppAttachRm" title="Видалити">×</button>
            </div>
            <textarea id="cppMsgText" placeholder="Написати повідомлення…" rows="2"></textarea>
            <!-- Emoji picker popup -->
            <div id="cppEmojiPicker" class="cpp-emoji-picker" style="display:none"></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;">
            <button class="btn btn-ghost btn-sm cpp-icon-btn" id="cppEmojiBtn" title="Смайлики" style="height:28px;padding:0 7px;font-size:15px">😊</button>
            <button class="btn btn-ghost btn-sm cpp-icon-btn" id="cppFileBtn" title="Прикріпити файл або фото" style="height:28px;padding:0 7px;font-size:14px">📎</button>
            <button class="btn btn-ghost btn-sm cpp-icon-btn" id="cppAiBtn" title="AI підказка" style="height:28px;padding:0 7px;font-size:13px">✨</button>
            <button class="btn btn-ghost btn-sm cpp-icon-btn" id="cppTplMgrBtn" title="Шаблони" style="height:28px;padding:0 7px;font-size:13px">📋</button>
            <button class="btn btn-primary btn-sm" id="cppMsgSend" style="height:28px;padding:0 9px;flex-shrink:0">↑</button>
        </div>
    </div>
</div>

<!-- Template manager modal -->
<div id="cppTplModal" class="modal-overlay" style="display:none">
    <div class="modal-box" style="width:520px;max-height:80vh;display:flex;flex-direction:column">
        <div class="modal-head">
            <span>Шаблони повідомлень</span>
            <button class="modal-close" id="cppTplModalClose">✕</button>
        </div>
        <div class="modal-body" style="flex:1;overflow-y:auto;padding:14px">
            <div id="cppTplList"></div>
            <button class="btn btn-primary btn-sm" id="cppTplAddBtn" style="margin-top:10px">+ Додати шаблон</button>
        </div>
    </div>
</div>

<!-- Template edit form (inside modal) -->
<div id="cppTplEditModal" class="modal-overlay" style="display:none">
    <div class="modal-box" style="width:440px">
        <div class="modal-head">
            <span id="cppTplEditTitle">Новий шаблон</span>
            <button class="modal-close" id="cppTplEditClose">✕</button>
        </div>
        <div class="modal-body" style="padding:14px;display:flex;flex-direction:column;gap:10px">
            <input type="hidden" id="cppTplEditId" value="0">
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Назва</label>
                <input type="text" id="cppTplEditName" placeholder="Назва шаблону…" style="width:100%;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Текст</label>
                <textarea id="cppTplEditBody" rows="4" style="width:100%;box-sizing:border-box;resize:vertical" placeholder="Текст повідомлення…"></textarea>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px">Канали</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <label style="font-size:12px"><input type="checkbox" class="tpl-ch-chk" value="viber"> Viber</label>
                    <label style="font-size:12px"><input type="checkbox" class="tpl-ch-chk" value="sms"> SMS</label>
                    <label style="font-size:12px"><input type="checkbox" class="tpl-ch-chk" value="email"> Email</label>
                    <label style="font-size:12px"><input type="checkbox" class="tpl-ch-chk" value="telegram"> Telegram</label>
                    <label style="font-size:12px"><input type="checkbox" class="tpl-ch-chk" value="note"> Нотатка</label>
                </div>
            </div>
            <div id="cppTplEditErr" class="modal-error" style="display:none"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary btn-sm" id="cppTplEditSave">Зберегти</button>
            <button class="btn btn-ghost btn-sm" id="cppTplEditCancel">Скасувати</button>
        </div>
    </div>
</div>

<!-- ── Panel JS (scoped) ─────────────────────────────────────────────────── -->
<script>
(function(){
    var wrap = document.querySelector('.cpp-wrap[data-id="<?php echo $id; ?>"]');
    if (!wrap) return;
    var cpId = <?php echo $id; ?>;

    // ── Mini relation graph ──────────────────────────────────────────────────
    function drawMiniLines() {
        var main = document.getElementById('cppRelMain');
        var svg  = document.getElementById('cppRelSvg');
        var self = document.getElementById('cppNodeSelf');
        if (!main || !svg || !self) return;
        svg.innerHTML = '';
        svg.style.width  = main.offsetWidth  + 'px';
        svg.style.height = main.offsetHeight + 'px';
        var mr = main.getBoundingClientRect();
        var sr = self.getBoundingClientRect();
        var sx = sr.left - mr.left + sr.width  / 2;
        var sy = sr.top  - mr.top  + sr.height;
        main.querySelectorAll('[data-child-of="cppNodeSelf"] .rg-mini-node').forEach(function(node) {
            var nr   = node.getBoundingClientRect();
            var nx   = nr.left - mr.left + nr.width / 2;
            var ny   = nr.top  - mr.top;
            var midY = sy + (ny - sy) * 0.5;
            var d    = 'M'+sx+' '+sy+' C'+sx+' '+midY+','+nx+' '+midY+','+nx+' '+ny;
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', d);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', '#d0d9e3');
            path.setAttribute('stroke-width', '1.5');
            svg.appendChild(path);
        });
    }

    // ── Panel column reference ───────────────────────────────────────────────
    function getPanelCol() {
        return document.getElementById('cpPanelCol');
    }

    // ── Feed height ──────────────────────────────────────────────────────────
    function setFeedHeight() {
        var panelCol = getPanelCol();
        var feedWrap = wrap.querySelector('.cpp-feed-wrap');
        if (!panelCol || !feedWrap) return;
        var panelH   = panelCol.getBoundingClientRect().height || (window.innerHeight - 80);
        var wrapTop  = feedWrap.getBoundingClientRect().top - panelCol.getBoundingClientRect().top;
        var h        = Math.max(200, panelH - wrapTop - 2);
        feedWrap.style.height = h + 'px';
    }

    // ── Chat height ──────────────────────────────────────────────────────────
    var chatActive = false;

    function setChatHeight() {
        var panelCol = getPanelCol();
        var chatWrap = wrap.querySelector('.cpp-chat-wrap');
        if (!panelCol || !chatWrap) return;

        var panelTop  = panelCol.getBoundingClientRect().top;
        var savedH    = parseInt(localStorage.getItem('cp_panel_h'), 10);
        var autoH     = Math.max(300, window.innerHeight - panelTop - 8);
        var targetH   = (savedH > 100) ? Math.min(savedH, autoH) : autoH;

        panelCol.style.height    = targetH + 'px';
        panelCol.style.maxHeight = 'none';

        // Fill chat wrap with remaining space inside panel
        var chatTop = chatWrap.getBoundingClientRect().top;
        var h = Math.max(200, targetH - (chatTop - panelTop) - 8);
        chatWrap.style.height = h + 'px';
    }

    window.addEventListener('resize', function() {
        if (chatActive && document.contains(wrap)) setChatHeight();
    });

    // ── Tabs ─────────────────────────────────────────────────────────────────
    wrap.querySelectorAll('.cpp-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab      = this.dataset.tab;
            var panelCol = getPanelCol();
            wrap.querySelectorAll('.cpp-tab').forEach(function(b){ b.classList.remove('active'); });
            wrap.querySelectorAll('.cpp-panel').forEach(function(p){ p.classList.add('hidden'); });
            this.classList.add('active');
            var panel = wrap.querySelector('#cpp-' + tab);
            if (panel) { panel.classList.remove('hidden'); }

            if (tab !== 'chat') { stopPolling(); chatActive = false; }

            if (tab === 'relations') {
                setTimeout(drawMiniLines, 50);
                if (panelCol) { panelCol.style.overflowY = ''; panelCol.style.height = ''; panelCol.style.maxHeight = ''; }
            } else if (tab === 'feed') {
                if (panelCol) { panelCol.style.overflowY = 'hidden'; panelCol.style.height = ''; panelCol.style.maxHeight = ''; }
                setFeedHeight();
                var feedList = wrap.querySelector('#cppFeedList');
                if (feedList) feedList.scrollTop = 0;
            } else if (tab === 'chat') {
                chatActive = true;
                if (panelCol) panelCol.style.overflowY = 'hidden';
                setTimeout(function() { setChatHeight(); }, 0);
                loadChatMessages();
                startPolling();
            } else {
                if (panelCol) { panelCol.style.overflowY = ''; panelCol.style.height = ''; panelCol.style.maxHeight = ''; }
            }
        });
    });

    // Save requisites
    var btnSave = wrap.querySelector('#cpBtnSaveReq');
    if (btnSave) {
        btnSave.addEventListener('click', function() {
            var status = wrap.querySelector('#cpSaveStatus');
            status.textContent = '';

            var fd = new FormData();
            fd.append('id', cpId);
            fd.append('name', wrap.querySelector('#cpfName').value.trim());

            <?php if ($isCompany): ?>
            if (wrap.querySelector('#cpfPhone'))  fd.append('phone',   wrap.querySelector('#cpfPhone').value.trim());
            if (wrap.querySelector('#cpfEmail'))  fd.append('email',   wrap.querySelector('#cpfEmail').value.trim());
            if (wrap.querySelector('#cpfWebsite'))fd.append('website', wrap.querySelector('#cpfWebsite').value.trim());
            if (wrap.querySelector('#cpfIban'))   fd.append('iban',    wrap.querySelector('#cpfIban').value.trim());
            <?php elseif ($isPerson): ?>
            if (wrap.querySelector('#cpfLast'))     fd.append('last_name',  wrap.querySelector('#cpfLast').value.trim());
            if (wrap.querySelector('#cpfFirst'))    fd.append('first_name', wrap.querySelector('#cpfFirst').value.trim());
            if (wrap.querySelector('#cpfPhone'))    fd.append('phone',      wrap.querySelector('#cpfPhone').value.trim());
            if (wrap.querySelector('#cpfEmail'))    fd.append('email',      wrap.querySelector('#cpfEmail').value.trim());
            if (wrap.querySelector('#cpfTelegram')) fd.append('telegram',   wrap.querySelector('#cpfTelegram').value.trim());
            <?php endif; ?>

            if (!fd.get('name')) {
                if (typeof showToast === 'function') showToast('Назва обовʼязкова');
                return;
            }
            btnSave.disabled = true;
            fetch('/counterparties/api/save_counterparty', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    btnSave.disabled = false;
                    if (d.ok) {
                        status.textContent = '✓';
                        status.style.color = 'var(--green)';
                        wrap.querySelector('.cpp-name').textContent = fd.get('name');
                        if (typeof showToast === 'function') showToast('Збережено');
                    } else {
                        if (typeof showToast === 'function') showToast(d.error || 'Помилка');
                    }
                });
        });
    }

    // ── Old notes/activity send (Лента) ──────────────────────────────────────
    var cppChatSend = wrap.querySelector('#cppChatSend');
    var cppChatText = wrap.querySelector('#cppChatText');
    if (cppChatSend && cppChatText) {
        cppChatSend.addEventListener('click', function() {
            var content = cppChatText.value.trim();
            if (!content) { cppChatText.focus(); return; }
            cppChatSend.disabled = true;
            var fd = new FormData();
            fd.append('id', cpId);
            fd.append('content', content);
            fetch('/counterparties/api/add_activity', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    cppChatSend.disabled = false;
                    if (d.ok) {
                        cppChatText.value = '';
                        var now = new Date();
                        var ts = 'Сьогодні ' + ('0'+now.getHours()).slice(-2)+':'+('0'+now.getMinutes()).slice(-2);
                        var div = document.createElement('div');
                        div.className = 'cpp-feed-note';
                        div.setAttribute('data-act-id', d.id);
                        div.innerHTML = '<div class="cpp-feed-note-meta">\uD83D\uDCAC ' + ts + '</div>'
                            + '<div class="cpp-feed-note-text">'
                            + String(d.content).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')
                            + '</div>';
                        var feedList = wrap.querySelector('#cppFeedList');
                        if (feedList) feedList.insertBefore(div, feedList.firstChild);
                    } else {
                        if (typeof showToast === 'function') showToast(d.error || 'Помилка');
                    }
                })
                .catch(function() {
                    cppChatSend.disabled = false;
                    if (typeof showToast === 'function') showToast('Помилка мережі');
                });
        });
        cppChatText.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { cppChatSend.click(); }
        });
    }

    // ── Chat (Viber/SMS/Нотатка) ─────────────────────────────────────────────
    var activeChannel  = 'viber';
    var activeChatCpId = cpId;   // switches when contact switcher is clicked
    var chatLoaded     = false;
    var tplCache       = {};
    var pollTimer      = null;
    var attachedFile   = null;   // {url, name, is_image, uploading}
    var msgList        = wrap.querySelector('#cppMsgsList');
    var tplRow         = wrap.querySelector('#cppTplRow');
    var msgText        = wrap.querySelector('#cppMsgText');
    var msgSubject     = wrap.querySelector('#cppMsgSubject');
    var msgSendBtn     = wrap.querySelector('#cppMsgSend');
    var unreadBadge    = document.getElementById('cppUnreadBadge');
    var tgChatId       = <?php echo json_encode($cp['telegram_chat_id'] ? (string)$cp['telegram_chat_id'] : ''); ?>;

    // Contact switcher
    var contactSwitcher = wrap.querySelector('#cppContactSwitcher');
    if (contactSwitcher) {
        contactSwitcher.querySelectorAll('.cpp-cs-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var newId = parseInt(this.dataset.csId, 10);
                if (newId === activeChatCpId) return;
                activeChatCpId = newId;
                contactSwitcher.querySelectorAll('.cpp-cs-btn').forEach(function(b){ b.classList.remove('active'); });
                this.classList.add('active');
                // Update placeholder to show who you're writing to
                var name = this.title || this.textContent.replace(/^[🏢👤]\s*/, '').trim();
                if (msgText) {
                    msgText.placeholder = activeChatCpId === cpId
                        ? 'Написати повідомлення…'
                        : 'Написати ' + name + '…';
                }
                tgChatId = ''; // reset telegram state for new contact (will reload from messages)
                tgHasDialog = false;
                loadChatMessages();
                if (activeChannel !== 'note') updateInputState();
            });
        });
    }

    function esc(s) {
        return String(s)
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;');
    }

    function linkifyHtml(html) {
        return html.replace(/(https?:\/\/[^\s<>"']+)/g, function(url) {
            return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" class="chat-link">' + url + '</a>';
        });
    }

    function formatMsgTime(dateStr) {
        var d = new Date(dateStr.replace(' ','T'));
        if (isNaN(d)) return dateStr;
        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        var hm = ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
        if (msgDay.getTime() === today.getTime()) return hm;
        var diff = Math.round((today - msgDay) / 86400000);
        if (diff === 1) return 'Вчора ' + hm;
        if (diff < 7)  return diff + 'д тому ' + hm;
        return ('0'+d.getDate()).slice(-2)+'.'+('0'+(d.getMonth()+1)).slice(-2) + ' ' + hm;
    }

    // For Telegram: disable input until client writes first
    var tgHasDialog = false;

    function updateInputState() {
        var inputArea  = wrap.querySelector('#cppInputArea');
        var hint       = wrap.querySelector('#cppTgHint');
        var linkBlock  = wrap.querySelector('#cppTgLinkBlock');
        if (!inputArea) return;

        // Remove existing hint/link block
        if (hint)      hint.parentNode.removeChild(hint);
        if (linkBlock) linkBlock.parentNode.removeChild(linkBlock);

        if (activeChannel === 'telegram') {
            if (!tgChatId) {
                // No chat_id linked — show linking UI
                inputArea.style.opacity = '0.4';
                inputArea.style.pointerEvents = 'none';
                var lb = document.createElement('div');
                lb.id = 'cppTgLinkBlock';
                lb.style.cssText = 'padding:8px 14px;flex-shrink:0;display:flex;gap:6px;align-items:center;border-top:1px solid var(--border)';
                lb.innerHTML = '<input type="text" id="cppTgChatIdInput" placeholder="Telegram Chat ID…" style="flex:1;padding:5px 8px;border:1px solid var(--border-input);border-radius:var(--radius-sm);font-size:12px;font-family:var(--font);outline:none">'
                    + '<button class="btn btn-primary btn-sm" id="cppTgLinkBtn" style="flex-shrink:0">Прив\'язати</button>';
                inputArea.parentNode.insertBefore(lb, inputArea.nextSibling);

                lb.querySelector('#cppTgLinkBtn').addEventListener('click', function() {
                    var chatIdVal = lb.querySelector('#cppTgChatIdInput').value.trim();
                    if (!chatIdVal) return;
                    var fd = new FormData();
                    fd.append('id',      cpId);
                    fd.append('chat_id', chatIdVal);
                    fetch('/counterparties/api/link_telegram', {method:'POST', body:fd})
                        .then(function(r){ return r.json(); })
                        .then(function(d) {
                            if (d.ok) {
                                tgChatId = chatIdVal;
                                tgHasDialog = true;
                                updateInputState();
                                loadChatMessages();
                                if (typeof showToast === 'function') showToast('Telegram прив\'язано');
                            } else {
                                if (typeof showToast === 'function') showToast(d.error || 'Помилка');
                            }
                        });
                });
            } else if (!tgHasDialog) {
                // Chat_id exists but client hasn't written yet
                inputArea.style.opacity = '0.4';
                inputArea.style.pointerEvents = 'none';
                var h = document.createElement('div');
                h.id = 'cppTgHint';
                h.style.cssText = 'font-size:11px;color:var(--text-muted);text-align:center;padding:4px 14px 6px;flex-shrink:0;';
                h.textContent = 'Відповідь стане доступна після першого повідомлення від клієнта';
                inputArea.parentNode.insertBefore(h, inputArea);
            } else {
                inputArea.style.opacity = '';
                inputArea.style.pointerEvents = '';
            }
        } else {
            inputArea.style.opacity = '';
            inputArea.style.pointerEvents = '';
        }
    }

    function renderMessages(msgs) {
        if (!msgList) return;
        if (!msgs || msgs.length === 0) {
            msgList.innerHTML = '<div class="cpp-msgs-empty">Повідомлень ще немає</div>';
            return;
        }
        var html = '';
        for (var i = 0; i < msgs.length; i++) {
            var m   = msgs[i];
            var dir = m.direction === 'in' ? 'in' : 'out';
            var ts  = formatMsgTime(m.created_at);
            var statusIcon = '';
            if (dir === 'out') {
                if (m.status === 'delivered') statusIcon = ' ✓✓';
                else if (m.status === 'read') statusIcon = ' ✓✓';
                else if (m.status === 'sent') statusIcon = ' ✓';
                else if (m.status === 'failed') statusIcon = ' ✗';
            }
            html += '<div class="cpp-msg cpp-msg-' + dir + '" data-msg-id="' + esc(m.id) + '">';
            if (dir === 'out' && m.operator_name) {
                html += '<div class="cpp-msg-sender">' + esc(m.operator_name) + '</div>';
            }
            if (m.media_url) {
                html += '<div class="cpp-msg-bubble cpp-msg-bubble-media">';
                // AlphaSMS stores real filename in body (e.g. "УКРАЇНА.pdf"), but S3 URLs
                // for non-images have no extension (or trailing dot). Detect type from body first.
                var bodyIsFilename = m.body && /\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|xls|xlsx|txt|ogg|oga|mp3|wav)$/i.test(m.body.trim());
                var imgFromUrl  = /\.(jpg|jpeg|png|gif|webp)(\?|$)/i.test(m.media_url);
                var imgFromBody = bodyIsFilename && /\.(jpg|jpeg|png|gif|webp)$/i.test(m.body.trim());
                if (imgFromUrl || imgFromBody) {
                    html += '<a href="' + esc(m.media_url) + '" target="_blank"><img src="' + esc(m.media_url) + '" alt="" style="max-width:220px;max-height:220px;border-radius:6px;display:block;cursor:pointer"></a>';
                } else {
                    var displayName = bodyIsFilename ? m.body.trim() : m.media_url.split('/').pop().replace(/\.$/, '') || 'файл';
                    var fext = displayName.split('.').pop().toLowerCase();
                    var ficons = {pdf:'📄',doc:'📝',docx:'📝',xls:'📊',xlsx:'📊',txt:'📃',ogg:'🎵',oga:'🎵',mp3:'🎵',wav:'🎵'};
                    var fic = ficons[fext] || '📎';
                    var dlUrl = '/counterparties/api/download_media?url=' + encodeURIComponent(m.media_url) + '&name=' + encodeURIComponent(displayName);
                    var officeExts = ['doc','docx','xls','xlsx'];
                    var viewUrl = (officeExts.indexOf(fext) !== -1)
                        ? 'https://view.officeapps.live.com/op/view.aspx?src=' + encodeURIComponent(m.media_url)
                        : dlUrl;
                    html += '<span style="display:inline-flex;align-items:center;gap:4px">'
                          + '<a href="' + esc(viewUrl) + '" target="_blank" style="display:inline-flex;align-items:center;gap:5px;text-decoration:none;color:inherit;background:rgba(0,0,0,.08);border-radius:6px;padding:5px 8px;font-size:12px">'
                          + fic + ' ' + esc(displayName) + '</a>';
                    if (officeExts.indexOf(fext) !== -1) {
                        html += '<a href="' + esc(dlUrl) + '" target="_blank" title="Завантажити" style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;text-decoration:none;background:rgba(0,0,0,.08);border-radius:6px;font-size:13px">⬇</a>';
                    }
                    html += '</span>';
                }
                // Show body as caption only if it's not a filename (already used as link label)
                var bodyCaption = (!bodyIsFilename && m.body && m.body !== '[медіа]' && m.body !== '[📷 Медіа-повідомлення]' && m.body !== '[файл]') ? m.body : '';
                if (bodyCaption) {
                    html += '<div style="margin-top:4px;font-size:12px;color:#666">' + linkifyHtml(esc(bodyCaption)) + '</div>';
                }
                html += '</div>';
            } else {
                html += '<div class="cpp-msg-bubble">' + linkifyHtml(esc(m.body).replace(/\n/g,'<br>')) + '</div>';
            }
            html += '<div class="cpp-msg-meta">' + esc(ts) + statusIcon + '</div>';
            html += '</div>';
        }
        msgList.innerHTML = html;
        msgList.scrollTop = msgList.scrollHeight;
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(function() { loadChatMessages(true); }, 7000);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function loadChatMessages(silent) {
        if (!msgList) return;
        if (!silent) msgList.innerHTML = '<div class="cpp-msgs-loading">Завантаження…</div>';
        fetch('/counterparties/api/get_messages?id=' + activeChatCpId + '&channel=' + activeChannel + '&limit=60')
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    // For Telegram: check if client has written at least once
                    if (activeChannel === 'telegram') {
                        tgHasDialog = false;
                        for (var i = 0; i < d.messages.length; i++) {
                            if (d.messages[i].direction === 'in') { tgHasDialog = true; break; }
                        }
                        updateInputState();
                    }
                    renderMessages(d.messages);
                    // Clear unread badge for this channel
                    if (unreadBadge) {
                        fetch('/counterparties/api/get_messages?id=' + cpId + '&limit=1')
                            .then(function(r2){ return r2.json(); })
                            .then(function(d2) {
                                // We just need to trigger markRead which happens server-side
                                // Update badge to 0 visually
                                if (unreadBadge) unreadBadge.style.display = 'none';
                            });
                    }
                } else {
                    msgList.innerHTML = '<div class="cpp-msgs-empty">' + esc(d.error || 'Помилка') + '</div>';
                }
            })
            .catch(function() {
                msgList.innerHTML = '<div class="cpp-msgs-empty">Помилка мережі</div>';
            });
        loadTemplates(activeChannel);
    }

    function loadTemplates(channel) {
        if (!tplRow) return;
        if (tplCache[channel]) {
            renderTemplates(tplCache[channel]);
            return;
        }
        fetch('/counterparties/api/get_templates?channel=' + channel)
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    tplCache[channel] = d.templates;
                    renderTemplates(d.templates);
                }
            });
    }

    function renderTemplates(templates) {
        if (!tplRow) return;
        if (!templates || templates.length === 0) {
            tplRow.innerHTML = '';
            return;
        }
        var html = '';
        for (var i = 0; i < templates.length; i++) {
            html += '<button class="cpp-tpl-chip" data-idx="' + i + '" title="' + esc(templates[i].body) + '">'
                  + esc(templates[i].title) + '</button>';
        }
        tplRow.innerHTML = html;
        tplRow.querySelectorAll('.cpp-tpl-chip').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var body = templates[parseInt(this.dataset.idx, 10)].body;
                if (msgText) {
                    msgText.value = body;
                    msgText.focus();
                }
            });
        });
    }

    // Channel tab switching inside chat
    wrap.querySelectorAll('.cpp-ch-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            wrap.querySelectorAll('.cpp-ch-tab').forEach(function(b){ b.classList.remove('active'); });
            this.classList.add('active');
            activeChannel = this.dataset.ch;
            tplRow.innerHTML = '';
            if (msgText) msgText.placeholder = activeChannel === 'note'
                ? 'Написати нотатку…'
                : 'Написати повідомлення…';
            if (msgSubject) {
                if (activeChannel === 'email') {
                    msgSubject.style.display = '';
                } else {
                    msgSubject.style.display = 'none';
                    msgSubject.value = '';
                }
            }
            loadChatMessages();
            updateInputState();
        });
    });

    // Send message
    if (msgSendBtn && msgText) {
        msgSendBtn.addEventListener('click', function() {
            var body = msgText.value.trim();
            if (!body && !attachedFile) { msgText.focus(); return; }
            if (attachedFile && attachedFile.uploading) {
                if (typeof showToast === 'function') showToast('Зачекайте, файл ще завантажується…');
                return;
            }
            msgSendBtn.disabled = true;

            var fd = new FormData();
            fd.append('id',      activeChatCpId);
            fd.append('channel', activeChannel);
            fd.append('body',    body || (attachedFile ? '[файл]' : ''));
            if (attachedFile && attachedFile.url) {
                fd.append('media_url', attachedFile.url);
            }
            if (activeChannel === 'email' && msgSubject) {
                var subj = msgSubject.value.trim();
                if (subj) fd.append('subject', subj);
            }

            fetch('/counterparties/api/send_message', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    msgSendBtn.disabled = false;
                    if (d.ok) {
                        msgText.value = '';
                        if (msgSubject) msgSubject.value = '';
                        removeAttach();
                        loadChatMessages(true); // reload from DB to show sent message
                    } else {
                        if (typeof showToast === 'function') showToast(d.error || 'Помилка відправки');
                    }
                })
                .catch(function() {
                    msgSendBtn.disabled = false;
                    if (typeof showToast === 'function') showToast('Помилка мережі');
                });
        });

        msgText.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { msgSendBtn.click(); }
        });
    }

    // ── File attachment ───────────────────────────────────────────────────────
    var fileInput     = wrap.querySelector('#cppFileInput');
    var fileBtn       = wrap.querySelector('#cppFileBtn');
    var attachPreview = wrap.querySelector('#cppAttachPreview');
    var attachThumb   = wrap.querySelector('#cppAttachThumb');
    var attachName    = wrap.querySelector('#cppAttachName');
    var attachSize    = wrap.querySelector('#cppAttachSize');
    var attachRm      = wrap.querySelector('#cppAttachRm');

    function renderAttachPreview() {
        var af = attachedFile;
        attachName.textContent = af.name;
        if (af.uploading) {
            attachSize.innerHTML = '<span style="color:#7c3aed;font-size:10px">Завантаження…</span>';
            attachThumb.innerHTML = '<div class="cpp-attach-icon">📎</div>';
        } else {
            attachSize.textContent = af.is_image ? 'Зображення готове' : 'Файл готовий';
            if (af.is_image) {
                attachThumb.innerHTML = '<img class="cpp-attach-thumb" src="' + esc(af.url) + '" alt="">';
            } else {
                var ext = af.name.split('.').pop().toUpperCase();
                var icons = {PDF:'📄',DOC:'📝',DOCX:'📝',XLS:'📊',XLSX:'📊',TXT:'📋'};
                attachThumb.innerHTML = '<div class="cpp-attach-icon">' + (icons[ext] || '📎') + '</div>';
            }
        }
        attachPreview.classList.add('visible');
    }

    function removeAttach() {
        attachedFile = null;
        if (attachPreview) attachPreview.classList.remove('visible');
        if (attachThumb)   attachThumb.innerHTML = '';
        if (fileInput)     fileInput.value = '';
    }

    if (fileBtn && fileInput) {
        fileBtn.addEventListener('click', function() {
            fileInput.value = '';
            fileInput.click();
        });
        fileInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var file = this.files[0];
            attachedFile = { url: null, name: file.name, is_image: false, uploading: true };
            renderAttachPreview();
            var fd = new FormData();
            fd.append('file', file);
            fetch('/counterparties/api/upload_message_file', { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        attachedFile = { url: d.url, name: d.name, is_image: d.is_image, uploading: false };
                        renderAttachPreview();
                    } else {
                        if (typeof showToast === 'function') showToast('Помилка: ' + (d.error || ''));
                        removeAttach();
                    }
                }).catch(function() {
                    if (typeof showToast === 'function') showToast('Помилка завантаження файлу');
                    removeAttach();
                });
        });
    }

    if (attachRm) {
        attachRm.addEventListener('click', removeAttach);
    }

    // ── Emoji picker ─────────────────────────────────────────────────────────
    var emojiBtn    = wrap.querySelector('#cppEmojiBtn');
    var emojiPicker = wrap.querySelector('#cppEmojiPicker');
    var EMOJIS = [
        '😊','😀','😂','😍','🙏','👍','👎','❤️','🔥','⭐','✅','❌','📦','🚚',
        '💰','💳','📞','📧','📝','🕐','✉️','🎁','🏷️','💬','📱','🏠','🛒','⚡',
        '😎','🤝','😮','😢','😡','🙄','😑','👋','✌️','💪','🤷','🎉','🔑','🔒'
    ];

    if (emojiBtn && emojiPicker) {
        // Populate picker
        var epHtml = '';
        for (var ei = 0; ei < EMOJIS.length; ei++) {
            epHtml += '<span class="cpp-emoji-item">' + EMOJIS[ei] + '</span>';
        }
        emojiPicker.innerHTML = epHtml;

        emojiPicker.querySelectorAll('.cpp-emoji-item').forEach(function(span) {
            span.addEventListener('click', function() {
                if (msgText) {
                    var start = msgText.selectionStart;
                    var end   = msgText.selectionEnd;
                    var val   = msgText.value;
                    msgText.value = val.substring(0, start) + span.textContent + val.substring(end);
                    msgText.selectionStart = msgText.selectionEnd = start + span.textContent.length;
                    msgText.focus();
                }
                emojiPicker.style.display = 'none';
            });
        });

        emojiBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            emojiPicker.style.display = emojiPicker.style.display === 'none' ? 'grid' : 'none';
        });

        document.addEventListener('click', function(e) {
            if (!emojiPicker.contains(e.target) && e.target !== emojiBtn) {
                emojiPicker.style.display = 'none';
            }
        });
    }

    // ── AI suggest ───────────────────────────────────────────────────────────
    var aiBtn = wrap.querySelector('#cppAiBtn');
    if (aiBtn) {
        aiBtn.addEventListener('click', function() {
            aiBtn.disabled = true;
            aiBtn.textContent = '…';
            var fd = new FormData();
            fd.append('id',      cpId);
            fd.append('channel', activeChannel);
            fetch('/counterparties/api/ai_suggest', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    aiBtn.disabled = false;
                    aiBtn.textContent = '✨';
                    if (d.ok && d.text) {
                        if (msgText) {
                            msgText.value = d.text;
                            msgText.focus();
                        }
                    } else {
                        if (typeof showToast === 'function') showToast(d.error || 'AI не відповів');
                    }
                })
                .catch(function() {
                    aiBtn.disabled = false;
                    aiBtn.textContent = '✨';
                    if (typeof showToast === 'function') showToast('Помилка мережі');
                });
        });
    }

    // ── Template manager ─────────────────────────────────────────────────────
    var tplMgrBtn      = wrap.querySelector('#cppTplMgrBtn');
    var tplModal       = document.getElementById('cppTplModal');
    var tplModalClose  = document.getElementById('cppTplModalClose');
    var tplEditModal   = document.getElementById('cppTplEditModal');
    var tplEditClose   = document.getElementById('cppTplEditClose');
    var tplEditCancel  = document.getElementById('cppTplEditCancel');
    var tplAddBtn      = document.getElementById('cppTplAddBtn');
    var tplListEl      = document.getElementById('cppTplList');

    function refreshTplCache() {
        tplCache = {};
        loadTemplates(activeChannel);
    }

    function loadAllTemplates() {
        if (!tplListEl) return;
        tplListEl.innerHTML = '<div style="color:var(--text-muted);font-size:12px">Завантаження…</div>';
        fetch('/counterparties/api/get_templates?channel=')
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (!d.ok) { tplListEl.innerHTML = '<div style="color:red;font-size:12px">' + esc(d.error||'Помилка') + '</div>'; return; }
                if (!d.templates || d.templates.length === 0) {
                    tplListEl.innerHTML = '<div style="color:var(--text-muted);font-size:12px">Шаблонів ще немає.</div>';
                    return;
                }
                var html = '<table style="width:100%;font-size:12px;border-collapse:collapse">';
                html += '<thead><tr style="border-bottom:1px solid var(--border)">'
                      + '<th style="text-align:left;padding:4px 6px;font-weight:600">Назва</th>'
                      + '<th style="text-align:left;padding:4px 6px;font-weight:600">Канали</th>'
                      + '<th style="padding:4px 6px"></th>'
                      + '</tr></thead><tbody>';
                for (var i = 0; i < d.templates.length; i++) {
                    var t = d.templates[i];
                    html += '<tr style="border-bottom:1px solid var(--border)">'
                          + '<td style="padding:5px 6px">' + esc(t.title) + '</td>'
                          + '<td style="padding:5px 6px;color:var(--text-muted)">' + esc(t.channels) + '</td>'
                          + '<td style="padding:5px 6px;white-space:nowrap">'
                          + '<button class="btn btn-ghost btn-xs tpl-edit-btn" data-tpl=\'' + JSON.stringify(t).replace(/'/g,'&#39;') + '\'>✎</button>'
                          + ' <button class="btn btn-danger btn-xs tpl-del-btn" data-id="' + t.id + '">✕</button>'
                          + '</td></tr>';
                }
                html += '</tbody></table>';
                tplListEl.innerHTML = html;

                tplListEl.querySelectorAll('.tpl-edit-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var t = JSON.parse(this.dataset.tpl);
                        openTplEdit(t);
                    });
                });
                tplListEl.querySelectorAll('.tpl-del-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        if (!confirm('Видалити шаблон?')) return;
                        var fd = new FormData(); fd.append('id', this.dataset.id);
                        fetch('/counterparties/api/delete_template', {method:'POST', body:fd})
                            .then(function(r){ return r.json(); })
                            .then(function(d) {
                                if (d.ok) { loadAllTemplates(); refreshTplCache(); }
                                else { if (typeof showToast === 'function') showToast(d.error||'Помилка'); }
                            });
                    });
                });
            });
    }

    function openTplEdit(t) {
        document.getElementById('cppTplEditId').value = t ? t.id : 0;
        document.getElementById('cppTplEditTitle').textContent = t ? 'Редагувати шаблон' : 'Новий шаблон';
        document.getElementById('cppTplEditName').value = t ? t.title : '';
        document.getElementById('cppTplEditBody').value = t ? t.body : '';
        var chks = document.querySelectorAll('.tpl-ch-chk');
        var activeChs = t ? (t.channels || '').split(',') : ['viber','sms'];
        chks.forEach(function(chk) { chk.checked = activeChs.indexOf(chk.value) !== -1; });
        var errEl = document.getElementById('cppTplEditErr');
        if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }
        if (tplEditModal) tplEditModal.style.display = 'flex';
    }

    if (tplMgrBtn && tplModal) {
        tplMgrBtn.addEventListener('click', function() {
            tplModal.style.display = 'flex';
            loadAllTemplates();
        });
        tplModalClose.addEventListener('click', function() { tplModal.style.display = 'none'; });
        tplModal.addEventListener('click', function(e) { if (e.target === tplModal) tplModal.style.display = 'none'; });
    }

    if (tplAddBtn) {
        tplAddBtn.addEventListener('click', function() { openTplEdit(null); });
    }

    if (tplEditClose)  { tplEditClose.addEventListener('click',  function() { if (tplEditModal) tplEditModal.style.display = 'none'; }); }
    if (tplEditCancel) { tplEditCancel.addEventListener('click', function() { if (tplEditModal) tplEditModal.style.display = 'none'; }); }
    if (tplEditModal)  { tplEditModal.addEventListener('click',  function(e) { if (e.target === tplEditModal) tplEditModal.style.display = 'none'; }); }

    var tplEditSave = document.getElementById('cppTplEditSave');
    if (tplEditSave) {
        tplEditSave.addEventListener('click', function() {
            var id    = document.getElementById('cppTplEditId').value;
            var title = document.getElementById('cppTplEditName').value.trim();
            var body  = document.getElementById('cppTplEditBody').value.trim();
            var chks  = document.querySelectorAll('.tpl-ch-chk:checked');
            var chs   = [];
            chks.forEach(function(c){ chs.push(c.value); });
            var errEl = document.getElementById('cppTplEditErr');

            if (!title || !body) {
                if (errEl) { errEl.textContent = 'Назва та текст обов\'язкові'; errEl.style.display = 'block'; }
                return;
            }
            if (chs.length === 0) {
                if (errEl) { errEl.textContent = 'Оберіть хоча б один канал'; errEl.style.display = 'block'; }
                return;
            }

            tplEditSave.disabled = true;
            var fd = new FormData();
            fd.append('id',       id);
            fd.append('title',    title);
            fd.append('body',     body);
            fd.append('channels', chs.join(','));
            fd.append('status',   1);
            fetch('/counterparties/api/save_template', {method:'POST', body:fd})
                .then(function(r){ return r.json(); })
                .then(function(d) {
                    tplEditSave.disabled = false;
                    if (d.ok) {
                        if (tplEditModal) tplEditModal.style.display = 'none';
                        loadAllTemplates();
                        refreshTplCache();
                    } else {
                        if (errEl) { errEl.textContent = d.error || 'Помилка'; errEl.style.display = 'block'; }
                    }
                })
                .catch(function() {
                    tplEditSave.disabled = false;
                    if (errEl) { errEl.textContent = 'Помилка мережі'; errEl.style.display = 'block'; }
                });
        });
    }

    // ── Close button ─────────────────────────────────────────────────────────
    wrap.querySelector('.cpp-close').addEventListener('click', function() {
        stopPolling();
        var event = new CustomEvent('cpPanelClose');
        document.dispatchEvent(event);
    });
}());
</script>
</div>
