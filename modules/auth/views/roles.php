<style>
.roles-layout { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
.roles-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.roles-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; }
.role-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 8px; cursor: pointer;
    font-size: 14px; border: 1px solid transparent; margin-bottom: 4px;
    transition: background .12s;
}
.role-item:hover { background: var(--bg-hover, #f5f7fa); }
.role-item.selected { background: #eff6ff; border-color: #bfdbfe; }
.role-users-count { font-size: 11px; color: var(--text-muted); margin-left: auto; }
.role-admin-badge { background:#fef3c7; color:#92400e; font-size:10px; font-weight:700; padding:1px 6px; border-radius:3px; }
.perms-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.perms-table th { text-align:left; padding:6px 10px; font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border); }
.perms-table td { padding:8px 10px; border-bottom:1px solid var(--border); }
.perms-table tr:last-child td { border-bottom: none; }
.perms-check { display:flex; align-items:center; gap:5px; }
.perms-check input { width:16px; height:16px; cursor:pointer; accent-color:#5b8af8; }
</style>

<div class="page-wrap-lg">

    <div class="roles-toolbar">
        <h1>Ролі та права</h1>
        <a href="/auth/users" class="btn btn-ghost btn-sm">Користувачі</a>
    </div>

    <div class="roles-layout">

        <!-- Ліво: список ролей ───────────────────────────────────────────── -->
        <div>
            <div class="card" style="padding:12px">
                <?php foreach ($roles as $role):
                    $cls = ((int)$role['role_id'] === $selectedId) ? 'role-item selected' : 'role-item';
                ?>
                <div class="<?php echo $cls; ?>"
                     onclick="window.location='/auth/roles?id=<?php echo $role['role_id']; ?>'">
                    <div>
                        <div style="font-weight:600"><?php echo htmlspecialchars($role['name']); ?></div>
                        <?php if ($role['description']): ?>
                        <div style="font-size:12px;color:var(--text-muted)"><?php echo htmlspecialchars($role['description']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($role['is_admin']): ?>
                    <span class="role-admin-badge">ADMIN</span>
                    <?php endif; ?>
                    <span class="role-users-count"><?php echo $role['users_count']; ?> кор.</span>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                    <button class="btn btn-primary btn-sm" style="width:100%" id="btnNewRole">+ Нова роль</button>
                </div>
            </div>
        </div>

        <!-- Право: редагування ───────────────────────────────────────────── -->
        <div>
            <?php if ($selected): ?>
            <div class="card" style="margin-bottom:16px">
                <div style="font-size:13px;font-weight:700;color:var(--text-muted);margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px">Роль</div>
                <form id="roleForm">
                    <input type="hidden" name="role_id" value="<?php echo $selected['role_id']; ?>">
                    <div class="form-row">
                        <label>Назва</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($selected['name']); ?>">
                    </div>
                    <div class="form-row">
                        <label>Опис</label>
                        <input type="text" name="description" value="<?php echo htmlspecialchars($selected['description']); ?>">
                    </div>
                    <div class="form-row" style="flex-direction:row;align-items:center;gap:8px">
                        <input type="checkbox" name="is_admin" value="1" id="chkAdmin"
                            <?php echo $selected['is_admin'] ? 'checked' : ''; ?>>
                        <label for="chkAdmin" style="font-weight:600;font-size:14px">Адмін (повний доступ)</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px">Зберегти назву</button>
                    <span id="roleMsg" style="font-size:13px;margin-left:10px;color:var(--text-muted)"></span>
                </form>
            </div>

            <?php if (!$selected['is_admin']): ?>
            <div class="card">
                <div style="font-size:13px;font-weight:700;color:var(--text-muted);margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px">Матриця прав</div>
                <form id="permsForm">
                    <input type="hidden" name="role_id" value="<?php echo $selected['role_id']; ?>">
                    <table class="perms-table">
                        <thead>
                            <tr>
                                <th>Модуль</th>
                                <th style="width:70px;text-align:center">Перегляд</th>
                                <th style="width:70px;text-align:center">Редагування</th>
                                <th style="width:70px;text-align:center">Видалення</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($modules as $mod):
                            $p = isset($perms[$mod['key']]) ? $perms[$mod['key']] : array('read'=>false,'edit'=>false,'delete'=>false);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mod['label']); ?></td>
                            <td style="text-align:center">
                                <input type="checkbox" name="permissions[<?php echo $mod['key']; ?>][read]" value="1"
                                    <?php echo !empty($p['read']) ? 'checked' : ''; ?>>
                            </td>
                            <td style="text-align:center">
                                <input type="checkbox" name="permissions[<?php echo $mod['key']; ?>][edit]" value="1"
                                    <?php echo !empty($p['edit']) ? 'checked' : ''; ?>>
                            </td>
                            <td style="text-align:center">
                                <input type="checkbox" name="permissions[<?php echo $mod['key']; ?>][delete]" value="1"
                                    <?php echo !empty($p['delete']) ? 'checked' : ''; ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:16px">
                        <button type="submit" class="btn btn-primary">Зберегти права</button>
                        <span id="permsMsg" style="font-size:13px;margin-left:10px;color:var(--text-muted)"></span>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="card" style="color:var(--text-muted);font-size:14px">
                Адмін-роль має необмежений доступ до всіх модулів.
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="card" style="color:var(--text-muted);font-size:14px">
                Оберіть роль зліва або створіть нову.
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Модалка нової ролі -->
<div class="modal-overlay" id="newRoleModal" style="display:none">
    <div class="modal-box" style="max-width:380px">
        <div class="modal-head">
            Нова роль
            <button class="modal-close" id="closeRoleModal">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <label>Назва</label>
                <input type="text" id="newRoleName" placeholder="Наприклад: Менеджер">
            </div>
            <div class="form-row">
                <label>Опис</label>
                <input type="text" id="newRoleDesc" placeholder="Короткий опис ролі">
            </div>
            <div class="modal-error" id="newRoleErr" style="display:none"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" id="btnCreateRole">Створити</button>
            <button class="btn btn-ghost" id="closeRoleModal2">Скасувати</button>
        </div>
    </div>
</div>

<script>
(function () {
    // Зберегти назву ролі
    var roleForm = document.getElementById('roleForm');
    if (roleForm) {
        roleForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('roleMsg');
            fetch('/auth/api/save_role', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(roleForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    msg.style.color = '#16a34a';
                    msg.textContent = 'Збережено';
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    msg.style.color = '#dc2626';
                    msg.textContent = d.error || 'Помилка';
                }
            });
        });
    }

    // Зберегти права
    var permsForm = document.getElementById('permsForm');
    if (permsForm) {
        permsForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('permsMsg');
            fetch('/auth/api/save_permissions', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(permsForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    msg.style.color = '#16a34a';
                    msg.textContent = 'Збережено';
                    setTimeout(function () { msg.textContent = ''; }, 2500);
                } else {
                    msg.style.color = '#dc2626';
                    msg.textContent = d.error || 'Помилка';
                }
            });
        });
    }

    // Модалка нової ролі
    var modal = document.getElementById('newRoleModal');
    document.getElementById('btnNewRole').addEventListener('click', function () {
        modal.style.display = 'flex';
        document.getElementById('newRoleName').focus();
    });
    function closeModal() { modal.style.display = 'none'; }
    document.getElementById('closeRoleModal').addEventListener('click', closeModal);
    document.getElementById('closeRoleModal2').addEventListener('click', closeModal);

    document.getElementById('btnCreateRole').addEventListener('click', function () {
        var name = document.getElementById('newRoleName').value.trim();
        var desc = document.getElementById('newRoleDesc').value.trim();
        var err  = document.getElementById('newRoleErr');
        if (!name) { err.style.display='block'; err.textContent='Вкажіть назву'; return; }

        fetch('/auth/api/save_role', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'name=' + encodeURIComponent(name) + '&description=' + encodeURIComponent(desc)
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.ok) { window.location = '/auth/roles?id=' + d.role_id; }
            else { err.style.display='block'; err.textContent = d.error || 'Помилка'; }
        });
    });
}());
</script>
