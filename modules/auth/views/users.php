<style>
.users-layout { display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start; }
.users-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.users-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex-shrink: 0; }
.user-status-badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.st-active  { background:#dcfce7; color:#166534; }
.st-blocked { background:#fee2e2; color:#991b1b; }
.st-pending { background:#fef9c3; color:#854d0e; }
.row-selected td { background: #eff6ff !important; }
.user-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,#5b8af8,#7c3aed); display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; vertical-align:middle; margin-right:6px; }
</style>

<div class="page-wrap-lg">

    <div class="users-toolbar">
        <h1>Користувачі</h1>
        <button class="btn btn-primary btn-sm" id="btnNewUser">+ Новий</button>
    </div>

    <div class="users-layout">

        <!-- Таблиця ─────────────────────────────────────────────────────── -->
        <div>
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Ім'я</th>
                        <th>Роль</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:24px">Користувачів немає</td></tr>
                <?php else: foreach ($users as $u):
                    $rowSel = ((int)$u['user_id'] === $selectedId) ? ' class="row-selected"' : '';
                ?>
                <tr<?php echo $rowSel; ?> style="cursor:pointer"
                    onclick="window.location='/auth/users?id=<?php echo $u['user_id']; ?>'">
                    <td>
                        <span class="user-avatar"><?php echo htmlspecialchars($u['initials']); ?></span>
                        <?php echo htmlspecialchars($u['display_name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['role_name']); ?></td>
                    <td><?php echo htmlspecialchars($u['phone'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($u['email'] ?: '—'); ?></td>
                    <td>
                        <?php
                        $stMap = array('active'=>'Активний','blocked'=>'Заблокований','pending'=>'Очікує');
                        $stCls = array('active'=>'st-active','blocked'=>'st-blocked','pending'=>'st-pending');
                        $st = $u['status'];
                        ?>
                        <span class="user-status-badge <?php echo $stCls[$st]; ?>"><?php echo $stMap[$st]; ?></span>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Панель редагування ───────────────────────────────────────────── -->
        <div>
            <div class="card" id="userCard">
                <?php if ($selected): ?>
                <div style="font-size:13px;font-weight:700;color:var(--text-muted);margin-bottom:16px;text-transform:uppercase;letter-spacing:.5px">Редагування</div>
                <?php else: ?>
                <div style="font-size:13px;font-weight:700;color:var(--text-muted);margin-bottom:16px;text-transform:uppercase;letter-spacing:.5px">Новий користувач</div>
                <?php endif; ?>

                <form id="userForm">
                    <input type="hidden" name="user_id" id="fUserId" value="<?php echo $selectedId; ?>">

                    <div class="form-row">
                        <label>Ім'я</label>
                        <input type="text" name="display_name" id="fName"
                               value="<?php echo htmlspecialchars($selected ? $selected['display_name'] : ''); ?>"
                               placeholder="Іванов І.І.">
                    </div>
                    <div class="form-row">
                        <label>Телефон</label>
                        <input type="tel" name="phone" id="fPhone"
                               value="<?php echo htmlspecialchars($selected ? $selected['phone'] : ''); ?>"
                               placeholder="+380501234567">
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input type="email" name="email" id="fEmail"
                               value="<?php echo htmlspecialchars($selected ? $selected['email'] : ''); ?>"
                               placeholder="user@example.com">
                    </div>
                    <div class="form-row">
                        <label>Роль</label>
                        <select name="role_id" id="fRole">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>"
                                <?php echo ($selected && (int)$selected['role_id'] === (int)$role['role_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                                <?php echo $role['is_admin'] ? ' (Адмін)' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Статус</label>
                        <select name="status" id="fStatus">
                            <option value="active"  <?php echo ($selected && $selected['status']==='active')  ? 'selected' : ''; ?>>Активний</option>
                            <option value="pending" <?php echo ($selected && $selected['status']==='pending') ? 'selected' : ''; ?>>Очікує</option>
                            <option value="blocked" <?php echo ($selected && $selected['status']==='blocked') ? 'selected' : ''; ?>>Заблокований</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Початковий екран</label>
                        <select name="home_screen">
                            <?php
                            $screens = array('/catalog'=>'Каталог','/prices'=>'Прайси','/customerorder'=>'Замовлення','/counterparties'=>'Контрагенти','/payments'=>'Платежі','/action'=>'Акції');
                            $curHome = isset($selSettings['home_screen']) ? $selSettings['home_screen'] : '/catalog';
                            foreach ($screens as $url => $lbl):
                            ?>
                            <option value="<?php echo $url; ?>" <?php echo ($curHome===$url?'selected':''); ?>><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row" id="rowEmployeeLink">
                        <label>Співробітник (необов'язково)</label>
                        <select name="employee_id" id="fEmployeeId">
                            <option value="">— не прив'язано —</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"
                                <?php echo ($selected && (int)$selected['employee_id']===(int)$emp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!$selected): ?>
                    <div class="form-row" id="rowCreateEmployee">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" name="create_employee" id="fCreateEmployee" value="1">
                            Створити запис співробітника автоматично
                        </label>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:3px">
                            Буде створено нового співробітника з іменем користувача та прив'язано до акаунту
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="btn-row" style="margin-top:18px">
                        <button type="submit" class="btn btn-primary">Зберегти</button>
                        <?php if ($selected): ?>
                        <button type="button" class="btn btn-danger btn-sm" id="btnDelete">Видалити</button>
                        <?php endif; ?>
                    </div>
                    <div id="userMsg" style="font-size:13px;margin-top:10px;"></div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    // Нова форма
    document.getElementById('btnNewUser').addEventListener('click', function () {
        window.location = '/auth/users';
    });

    // Зберегти
    var form = document.getElementById('userForm');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = document.getElementById('userMsg');
        fetch('/auth/api/save_user', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(new FormData(form)).toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) {
                window.location = '/auth/users?id=' + d.user_id;
            } else {
                msg.style.color = '#dc2626';
                msg.textContent = d.error || 'Помилка';
            }
        });
    });

    // Видалити
    var delBtn = document.getElementById('btnDelete');
    if (delBtn) {
        delBtn.addEventListener('click', function () {
            if (!confirm('Видалити користувача?')) { return; }
            fetch('/auth/api/delete_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'user_id=' + document.getElementById('fUserId').value
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { window.location = '/auth/users'; }
                else {
                    document.getElementById('userMsg').textContent = d.error || 'Помилка';
                    document.getElementById('userMsg').style.color = '#dc2626';
                }
            });
        });
    }
}());
</script>
