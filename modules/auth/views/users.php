<style>
.users-layout  { display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start; }
.users-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.users-toolbar h1 { margin: 0; font-size: 18px; font-weight: 700; flex-shrink: 0; }
.users-toolbar .btn { height: 34px; }

/* Статуси */
.st-active   { background:#dcfce7; color:#166534; }
.st-blocked  { background:#fee2e2; color:#991b1b; }
.st-pending  { background:#fef9c3; color:#854d0e; }
.st-inactive { background:#f3f4f6; color:#6b7280; }
.user-status-badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }

/* Аватар */
.user-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,#5b8af8,#7c3aed);
               display:inline-flex; align-items:center; justify-content:center;
               font-size:10px; font-weight:700; color:#fff; vertical-align:middle; margin-right:6px; flex-shrink:0; }
.user-avatar.emp-avatar { background: linear-gradient(135deg,#10b981,#059669); }
.user-avatar.orphan-avatar { background: linear-gradient(135deg,#f59e0b,#d97706); }

/* Рядок виділений */
.row-selected td { background: #eff6ff !important; }

/* Зв'язок: бейдж акаунта */
.acc-badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; }
.acc-badge .role-tag { background:#e0e7ff; color:#3730a3; padding:1px 6px; border-radius:3px; font-weight:600; font-size:11px; }
.acc-badge .admin-tag { background:#fce7f3; color:#9d174d; padding:1px 6px; border-radius:3px; font-weight:600; font-size:11px; }

/* Розділювач orphan */
.orphan-sep td { background:#f9fafb; font-size:11px; font-weight:700; color:var(--text-muted);
                 text-transform:uppercase; letter-spacing:.5px; padding: 6px 12px; border-top:2px solid #e5e7eb; }

/* Секції панелі */
.panel-section { margin-bottom: 20px; }
.panel-section-title { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;
                       letter-spacing:.5px; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid #e5e7eb; }
.panel-section + .panel-section { border-top: 1px solid #e5e7eb; padding-top:20px; }

/* Acc-block (linked user info) */
.acc-block { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; }
.acc-block-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:13px; }
.acc-block-row:last-child { margin-bottom:0; }
.acc-block-label { color:var(--text-muted); width:70px; flex-shrink:0; }

.no-acc-placeholder { text-align:center; padding:16px; color:var(--text-muted); font-size:13px; }

/* Повідомлення форм */
.form-msg { font-size:13px; margin-top:10px; }
</style>

<?php
// Хелпери
function empInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $ini = '';
    foreach ($parts as $p) {
        if ($p !== '') $ini .= mb_strtoupper(mb_substr($p,0,1,'UTF-8'),'UTF-8');
    }
    return mb_substr($ini, 0, 2, 'UTF-8');
}
function userStatusBadge($st) {
    $map = array('active'=>array('Активний','st-active'),'blocked'=>array('Заблокований','st-blocked'),'pending'=>array('Очікує','st-pending'));
    $d = isset($map[$st]) ? $map[$st] : array($st,'st-inactive');
    return '<span class="user-status-badge '.$d[1].'">'.$d[0].'</span>';
}
function empStatusBadge($s) {
    return $s ? '<span class="user-status-badge st-active">Активний</span>'
              : '<span class="user-status-badge st-inactive">Неактивний</span>';
}
?>

<div class="page-wrap-lg">

    <div class="users-toolbar">
        <h1>Співробітники</h1>
        <button class="btn btn-primary btn-sm" id="btnNewEmp">+ Співробітник</button>
        <button class="btn btn-ghost btn-sm" id="btnNewUser">+ Користувач</button>
    </div>

    <div class="users-layout">

        <!-- Таблиця ─────────────────────────────────────────────────────── -->
        <div>
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Ім'я / ПІБ</th>
                        <th>Посада</th>
                        <th>Телефон</th>
                        <th>Обліковий запис</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (empty($employees) && empty($orphanUsers)): ?>
                <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:24px">Записів немає</td></tr>
                <?php endif; ?>

                <!-- Співробітники -->
                <?php foreach ($employees as $e):
                    $isSelEmp = ($selectedEmpId === (int)$e['emp_id']);
                    $rowCls   = $isSelEmp ? ' class="row-selected"' : '';
                    $ini      = empInitials($e['full_name']);
                    $empUrl   = '/auth/users?emp=' . $e['emp_id'];
                ?>
                <tr<?php echo $rowCls; ?> style="cursor:pointer" onclick="window.location='<?php echo $empUrl; ?>'">
                    <td>
                        <span class="user-avatar emp-avatar"><?php echo htmlspecialchars($ini); ?></span>
                        <?php echo htmlspecialchars($e['full_name']); ?>
                        <?php if (!$e['emp_status']): ?>
                        <span style="font-size:11px;color:var(--text-muted);margin-left:4px">(неакт.)</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($e['position_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($e['emp_phone'] ?: '—'); ?></td>
                    <td>
                        <?php if (!empty($e['user_id'])): ?>
                        <div class="acc-badge">
                            <span class="user-avatar" style="width:20px;height:20px;font-size:9px"><?php echo htmlspecialchars($e['initials']); ?></span>
                            <?php if ($e['is_admin']): ?>
                            <span class="admin-tag">Адмін</span>
                            <?php else: ?>
                            <span class="role-tag"><?php echo htmlspecialchars($e['role_name']); ?></span>
                            <?php endif; ?>
                            <?php echo userStatusBadge($e['user_status']); ?>
                        </div>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo empStatusBadge($e['emp_status']); ?></td>
                </tr>
                <?php endforeach; ?>

                <!-- Користувачі без співробітника -->
                <?php if (!empty($orphanUsers)): ?>
                <tr class="orphan-sep"><td colspan="5">Користувачі без співробітника</td></tr>
                <?php foreach ($orphanUsers as $u):
                    $isSelUser = ($selectedUserId === (int)$u['user_id']);
                    $rowCls    = $isSelUser ? ' class="row-selected"' : '';
                    $userUrl   = '/auth/users?id=' . $u['user_id'];
                ?>
                <tr<?php echo $rowCls; ?> style="cursor:pointer" onclick="window.location='<?php echo $userUrl; ?>'">
                    <td>
                        <span class="user-avatar orphan-avatar"><?php echo htmlspecialchars($u['initials']); ?></span>
                        <?php echo htmlspecialchars($u['display_name']); ?>
                    </td>
                    <td><span style="color:var(--text-muted);font-size:11px">без співробітника</span></td>
                    <td><?php echo htmlspecialchars($u['user_phone'] ?: '—'); ?></td>
                    <td>
                        <div class="acc-badge">
                            <?php if ($u['is_admin']): ?>
                            <span class="admin-tag">Адмін</span>
                            <?php else: ?>
                            <span class="role-tag"><?php echo htmlspecialchars($u['role_name']); ?></span>
                            <?php endif; ?>
                            <?php echo userStatusBadge($u['user_status']); ?>
                        </div>
                    </td>
                    <td><?php echo userStatusBadge($u['user_status']); ?></td>
                </tr>
                <?php endforeach; endif; ?>

                </tbody>
            </table>
        </div>

        <!-- Панель ───────────────────────────────────────────────────────── -->
        <div id="sidePanel">

        <?php if ($selEmp): /* ── Редагування співробітника ── */ ?>

            <!-- Картка співробітника -->
            <div class="card" style="margin-bottom:16px">
                <div class="panel-section">
                    <div class="panel-section-title">Дані співробітника</div>
                    <form id="empForm">
                        <input type="hidden" name="emp_id" value="<?php echo $selEmp['emp_id']; ?>">
                        <div class="form-row">
                            <label>ПІБ</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($selEmp['full_name']); ?>" placeholder="Іванов Іван Іванович">
                        </div>
                        <div class="form-row">
                            <label>Посада</label>
                            <input type="text" name="position_name" value="<?php echo htmlspecialchars($selEmp['position_name'] ?: ''); ?>" placeholder="Менеджер">
                        </div>
                        <div class="form-row">
                            <label>Телефон</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($selEmp['emp_phone'] ?: ''); ?>" placeholder="+380501234567">
                        </div>
                        <div class="form-row">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($selEmp['emp_email'] ?: ''); ?>" placeholder="user@example.com">
                        </div>
                        <div class="form-row">
                            <label>Статус</label>
                            <select name="status">
                                <option value="1" <?php echo $selEmp['emp_status'] ? 'selected' : ''; ?>>Активний</option>
                                <option value="0" <?php echo !$selEmp['emp_status'] ? 'selected' : ''; ?>>Неактивний</option>
                            </select>
                        </div>
                        <div class="btn-row" style="margin-top:14px">
                            <button type="submit" class="btn btn-primary btn-sm">Зберегти</button>
                        </div>
                        <div class="form-msg" id="empMsg"></div>
                    </form>
                </div>
            </div>

            <!-- Картка акаунта -->
            <div class="card">
                <div class="panel-section-title">Обліковий запис</div>
                <?php if ($selLinkedUser): ?>
                <div class="acc-block" style="margin-bottom:14px">
                    <div class="acc-block-row">
                        <span class="acc-block-label">Ім'я</span>
                        <strong><?php echo htmlspecialchars($selLinkedUser['user_name']); ?></strong>
                    </div>
                    <div class="acc-block-row">
                        <span class="acc-block-label">Роль</span>
                        <?php if ($selLinkedUser['is_admin']): ?>
                        <span class="user-status-badge" style="background:#fce7f3;color:#9d174d">Адміністратор</span>
                        <?php else: ?>
                        <span class="user-status-badge" style="background:#e0e7ff;color:#3730a3"><?php echo htmlspecialchars($selLinkedUser['role_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="acc-block-row">
                        <span class="acc-block-label">Статус</span>
                        <?php echo userStatusBadge($selLinkedUser['user_status']); ?>
                    </div>
                </div>
                <!-- Редагування акаунта (розгорнуте) -->
                <form id="linkedUserForm">
                    <input type="hidden" name="user_id" value="<?php echo $selLinkedUser['user_id']; ?>">
                    <div class="form-row">
                        <label>Ім'я в системі</label>
                        <input type="text" name="display_name" value="<?php echo htmlspecialchars($selLinkedUser['user_name']); ?>">
                    </div>
                    <div class="form-row">
                        <label>Телефон</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($selLinkedUser['user_phone'] ?: ''); ?>" placeholder="+380501234567">
                    </div>
                    <div class="form-row">
                        <label>Роль</label>
                        <select name="role_id">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>"
                                <?php echo ((int)$selLinkedUser['role_id']===(int)$role['role_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                                <?php echo $role['is_admin'] ? ' (Адмін)' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Статус</label>
                        <select name="status">
                            <option value="active"  <?php echo ($selLinkedUser['user_status']==='active')  ? 'selected':''; ?>>Активний</option>
                            <option value="pending" <?php echo ($selLinkedUser['user_status']==='pending') ? 'selected':''; ?>>Очікує</option>
                            <option value="blocked" <?php echo ($selLinkedUser['user_status']==='blocked') ? 'selected':''; ?>>Заблокований</option>
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
                    <div class="btn-row" style="margin-top:14px">
                        <button type="submit" class="btn btn-primary btn-sm">Зберегти акаунт</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="btnUnlink">Відв'язати</button>
                    </div>
                    <div class="form-msg" id="linkedUserMsg"></div>
                </form>
                <?php else: ?>
                <div class="no-acc-placeholder">Обліковий запис не прив'язано</div>
                <div style="margin-top:10px">
                    <!-- Прив'язати існуючий -->
                    <?php if (!empty($orphanUsers)): ?>
                    <div class="form-row">
                        <label>Прив'язати існуючий</label>
                        <select id="linkUserSelect">
                            <option value="">— вибрати —</option>
                            <?php foreach ($orphanUsers as $ou): ?>
                            <option value="<?php echo $ou['user_id']; ?>"><?php echo htmlspecialchars($ou['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="btn-row" style="margin-bottom:14px">
                        <button type="button" class="btn btn-ghost btn-sm" id="btnLinkUser">Прив'язати</button>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:12px;text-align:center">або</div>
                    <?php endif; ?>
                    <!-- Створити новий -->
                    <form id="createUserForm">
                        <input type="hidden" name="employee_id" value="<?php echo $selEmp['emp_id']; ?>">
                        <div class="form-row">
                            <label>Ім'я в системі</label>
                            <input type="text" name="display_name" value="<?php echo htmlspecialchars($selEmp['full_name']); ?>">
                        </div>
                        <div class="form-row">
                            <label>Телефон</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($selEmp['emp_phone'] ?: ''); ?>" placeholder="+380501234567">
                        </div>
                        <div class="form-row">
                            <label>Роль</label>
                            <select name="role_id">
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Статус</label>
                            <select name="status">
                                <option value="active">Активний</option>
                                <option value="pending" selected>Очікує</option>
                            </select>
                        </div>
                        <div class="btn-row" style="margin-top:14px">
                            <button type="submit" class="btn btn-primary btn-sm">Створити акаунт</button>
                        </div>
                        <div class="form-msg" id="createUserMsg"></div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($selUser): /* ── Редагування orphan-користувача ── */ ?>

            <div class="card">
                <div class="panel-section-title">Обліковий запис</div>
                <form id="userForm">
                    <input type="hidden" name="user_id" value="<?php echo $selUser['user_id']; ?>">
                    <div class="form-row">
                        <label>Ім'я</label>
                        <input type="text" name="display_name" value="<?php echo htmlspecialchars($selUser['display_name']); ?>">
                    </div>
                    <div class="form-row">
                        <label>Телефон</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($selUser['phone'] ?: ''); ?>">
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($selUser['email'] ?: ''); ?>">
                    </div>
                    <div class="form-row">
                        <label>Роль</label>
                        <select name="role_id">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>"
                                <?php echo ((int)$selUser['role_id']===(int)$role['role_id'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                                <?php echo $role['is_admin']?' (Адмін)':''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Статус</label>
                        <select name="status">
                            <option value="active"  <?php echo ($selUser['status']==='active') ?'selected':''; ?>>Активний</option>
                            <option value="pending" <?php echo ($selUser['status']==='pending')?'selected':''; ?>>Очікує</option>
                            <option value="blocked" <?php echo ($selUser['status']==='blocked')?'selected':''; ?>>Заблокований</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Початковий екран</label>
                        <select name="home_screen">
                            <?php
                            $screens = array('/catalog'=>'Каталог','/prices'=>'Прайси','/customerorder'=>'Замовлення','/counterparties'=>'Контрагенти','/payments'=>'Платежі','/action'=>'Акції');
                            $curHome = isset($selSettings['home_screen']) ? $selSettings['home_screen'] : '/catalog';
                            foreach ($screens as $url => $lbl): ?>
                            <option value="<?php echo $url; ?>" <?php echo ($curHome===$url?'selected':''); ?>><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($freeEmployees)): ?>
                    <div class="form-row">
                        <label>Прив'язати до співробітника</label>
                        <select name="employee_id">
                            <option value="">— не прив'язано —</option>
                            <?php foreach ($freeEmployees as $fe): ?>
                            <option value="<?php echo $fe['id']; ?>"><?php echo htmlspecialchars($fe['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="btn-row" style="margin-top:14px">
                        <button type="submit" class="btn btn-primary btn-sm">Зберегти</button>
                        <button type="button" class="btn btn-danger btn-sm" id="btnDeleteUser">Видалити</button>
                    </div>
                    <div class="form-msg" id="userMsg"></div>
                </form>
            </div>

        <?php else: /* ── Форма нового співробітника / порожній стан ── */ ?>

            <div class="card" id="newEmpCard" style="<?php echo (!isset($_GET['new']) ? 'display:none' : ''); ?>">
                <div class="panel-section-title">Новий співробітник</div>
                <form id="newEmpForm">
                    <div class="form-row">
                        <label>ПІБ</label>
                        <input type="text" name="full_name" placeholder="Іванов Іван Іванович" id="newEmpName">
                    </div>
                    <div class="form-row">
                        <label>Посада</label>
                        <input type="text" name="position_name" placeholder="Менеджер">
                    </div>
                    <div class="form-row">
                        <label>Телефон</label>
                        <input type="tel" name="phone" placeholder="+380501234567">
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="user@example.com">
                    </div>
                    <div class="btn-row" style="margin-top:14px">
                        <button type="submit" class="btn btn-primary btn-sm">Створити</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="btnCancelNewEmp">Скасувати</button>
                    </div>
                    <div class="form-msg" id="newEmpMsg"></div>
                </form>
            </div>

            <div class="card" id="newUserCard" style="display:none">
                <div class="panel-section-title">Новий користувач</div>
                <form id="newUserForm">
                    <div class="form-row">
                        <label>Ім'я</label>
                        <input type="text" name="display_name" placeholder="Іванов І.І.">
                    </div>
                    <div class="form-row">
                        <label>Телефон</label>
                        <input type="tel" name="phone" placeholder="+380501234567">
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="user@example.com">
                    </div>
                    <div class="form-row">
                        <label>Роль</label>
                        <select name="role_id">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>"><?php echo htmlspecialchars($role['name']); ?><?php echo $role['is_admin']?' (Адмін)':''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Статус</label>
                        <select name="status">
                            <option value="active">Активний</option>
                            <option value="pending" selected>Очікує</option>
                        </select>
                    </div>
                    <?php if (!empty($freeEmployees)): ?>
                    <div class="form-row">
                        <label>Прив'язати до співробітника</label>
                        <select name="employee_id">
                            <option value="">— не прив'язано —</option>
                            <?php foreach ($freeEmployees as $fe): ?>
                            <option value="<?php echo $fe['id']; ?>"><?php echo htmlspecialchars($fe['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="btn-row" style="margin-top:14px">
                        <button type="submit" class="btn btn-primary btn-sm">Створити</button>
                        <button type="button" class="btn btn-ghost btn-sm" id="btnCancelNewUser">Скасувати</button>
                    </div>
                    <div class="form-msg" id="newUserMsg"></div>
                </form>
            </div>

            <div id="emptyState" style="<?php echo isset($_GET['new']) ? 'display:none' : ''; ?>">
                <div class="card" style="text-align:center;color:var(--text-muted);padding:32px">
                    <div style="font-size:32px;margin-bottom:8px">👥</div>
                    <div>Виберіть рядок або додайте нового співробітника</div>
                </div>
            </div>

        <?php endif; ?>

        </div><!-- /sidePanel -->
    </div>
</div>

<script>
(function () {

    /* ── Toolbar buttons ── */
    document.getElementById('btnNewEmp').addEventListener('click', function () {
        window.location = '/auth/users?new=emp';
    });
    document.getElementById('btnNewUser').addEventListener('click', function () {
        var card = document.getElementById('newUserCard');
        var empCard = document.getElementById('newEmpCard');
        var empty = document.getElementById('emptyState');
        if (card) {
            if (empCard) empCard.style.display = 'none';
            if (empty)   empty.style.display   = 'none';
            card.style.display = '';
        } else {
            window.location = '/auth/users?new=user';
        }
    });
    var cancelNewEmp = document.getElementById('btnCancelNewEmp');
    if (cancelNewEmp) {
        cancelNewEmp.addEventListener('click', function () { window.location = '/auth/users'; });
    }
    var cancelNewUser = document.getElementById('btnCancelNewUser');
    if (cancelNewUser) {
        cancelNewUser.addEventListener('click', function () {
            var card = document.getElementById('newUserCard');
            if (card) card.style.display = 'none';
            var empty = document.getElementById('emptyState');
            if (empty) empty.style.display = '';
        });
    }

    /* ── Новий співробітник ── */
    var newEmpForm = document.getElementById('newEmpForm');
    if (newEmpForm) {
        newEmpForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('newEmpMsg');
            fetch('/auth/api/save_employee', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(newEmpForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { window.location = '/auth/users?emp=' + d.emp_id; }
                else { msg.style.color='#dc2626'; msg.textContent = d.error || 'Помилка'; }
            });
        });
    }

    /* ── Редагування співробітника ── */
    var empForm = document.getElementById('empForm');
    if (empForm) {
        empForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('empMsg');
            fetch('/auth/api/save_employee', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(empForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { showToast('Збережено ✓'); }
                else { msg.style.color='#dc2626'; msg.textContent = d.error || 'Помилка'; }
            });
        });
    }

    /* ── Редагування прив'язаного акаунта ── */
    var linkedUserForm = document.getElementById('linkedUserForm');
    if (linkedUserForm) {
        linkedUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('linkedUserMsg');
            fetch('/auth/api/save_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(linkedUserForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { showToast('Акаунт збережено ✓'); }
                else { msg.style.color='#dc2626'; msg.textContent = d.error || 'Помилка'; }
            });
        });
    }

    /* ── Відв'язати акаунт від співробітника ── */
    var btnUnlink = document.getElementById('btnUnlink');
    if (btnUnlink) {
        btnUnlink.addEventListener('click', function () {
            if (!confirm('Відв\'язати обліковий запис від цього співробітника?')) return;
            var userId = linkedUserForm.querySelector('[name="user_id"]').value;
            fetch('/auth/api/save_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'user_id=' + userId + '&employee_id=&_unlink=1'
                    + '&display_name=' + encodeURIComponent(linkedUserForm.querySelector('[name="display_name"]').value)
                    + '&role_id=' + linkedUserForm.querySelector('[name="role_id"]').value
                    + '&status=' + linkedUserForm.querySelector('[name="status"]').value
                    + '&home_screen=' + encodeURIComponent(linkedUserForm.querySelector('[name="home_screen"]').value)
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { window.location = '/auth/users?emp=' + <?php echo $selectedEmpId; ?>; }
                else { document.getElementById('linkedUserMsg').textContent = d.error || 'Помилка'; }
            });
        });
    }

    /* ── Прив'язати існуючий акаунт ── */
    var btnLinkUser = document.getElementById('btnLinkUser');
    if (btnLinkUser) {
        btnLinkUser.addEventListener('click', function () {
            var sel = document.getElementById('linkUserSelect');
            var userId = sel ? sel.value : '';
            if (!userId) { showToast('Виберіть акаунт', true); return; }
            fetch('/auth/api/save_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'user_id=' + userId + '&employee_id=<?php echo $selectedEmpId; ?>&_link_only=1'
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { window.location = '/auth/users?emp=' + <?php echo $selectedEmpId; ?>; }
                else { showToast(d.error || 'Помилка', true); }
            });
        });
    }

    /* ── Створити акаунт для співробітника ── */
    var createUserForm = document.getElementById('createUserForm');
    if (createUserForm) {
        createUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('createUserMsg');
            fetch('/auth/api/save_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(createUserForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { window.location = '/auth/users?emp=' + <?php echo $selectedEmpId; ?>; }
                else { msg.style.color='#dc2626'; msg.textContent = d.error || 'Помилка'; }
            });
        });
    }

    /* ── Редагування orphan-користувача ── */
    var userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('userMsg');
            fetch('/auth/api/save_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(userForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    showToast('Збережено ✓');
                    // Якщо прив'язали до співробітника — перейти на emp view
                    var empSel = userForm.querySelector('[name="employee_id"]');
                    if (empSel && empSel.value) {
                        window.location = '/auth/users?emp=' + empSel.value;
                    }
                } else {
                    msg.style.color='#dc2626'; msg.textContent = d.error || 'Помилка';
                }
            });
        });

        var btnDelUser = document.getElementById('btnDeleteUser');
        if (btnDelUser) {
            btnDelUser.addEventListener('click', function () {
                if (!confirm('Видалити користувача?')) return;
                fetch('/auth/api/delete_user', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'user_id=' + userForm.querySelector('[name="user_id"]').value
                })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.ok) { window.location = '/auth/users'; }
                    else { document.getElementById('userMsg').textContent = d.error || 'Помилка'; }
                });
            });
        }
    }

    /* ── Новий користувач (форма в панелі) ── */
    var newUserForm = document.getElementById('newUserForm');
    if (newUserForm) {
        newUserForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = document.getElementById('newUserMsg');
            fetch('/auth/api/save_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(new FormData(newUserForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) { window.location = '/auth/users?id=' + d.user_id; }
                else { msg.style.color='#dc2626'; msg.textContent = d.error || 'Помилка'; }
            });
        });
    }

}());
</script>