<?php

//declare(strict_types=1);

/**
 * Papir CRM - Dashboard атрибутов МойСклад
 *
 * Файл: /var/www/papir/modules/moysklad/tools/dashboard.php
 *
 * Что делает:
 * - показывает атрибуты из Papir.Documents_attr, сгруппированные по document
 * - подтягивает связанные значения из Papir.customentity
 * - позволяет редактировать name_main
 * - позволяет запустить sync_document_attributes.php
 * - показывает сводку по количеству записей по каждому document
 *
 * ВАЖНО:
 * - для запуска sync использует shell_exec(), PHP должен иметь право на выполнение CLI-скрипта
 * - DEBUG-места помечены комментариями DEBUG_START / DEBUG_END
 */

$projectRoot = dirname(dirname(dirname(__DIR__)));

require_once $projectRoot . '/modules/database/src/Database.php';
require_once $projectRoot . '/modules/moysklad/moysklad_api.php';
require_once $projectRoot . '/modules/moysklad/src/MoySkladAttributesSync.php';

$dbConfigFile = $projectRoot . '/modules/database/config/databases.php';
$dbConfigs = require $dbConfigFile;
Database::init($dbConfigs);

$dbName = 'Papir';
$syncScript = $projectRoot . '/modules/moysklad/tools/sync_document_attributes.php';

$messages = [];
$errors = [];
$syncOutput = '';
$syncSummary = [];

// DEBUG_START: можно быстро отключить все debug-блоки страницы
$debugEnabled = true;
// DEBUG_END

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

        if ($action === 'save_name_main') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $nameMain = isset($_POST['name_main']) ? trim((string)$_POST['name_main']) : '';

            if ($id <= 0) {
                $errors[] = 'Некорректный ID записи.';
            } else {
                $update = Database::update($dbName, 'Documents_attr', [
                    'name_main' => ($nameMain !== '' ? $nameMain : null),
                ], [
                    'id' => $id,
                ]);

                // DEBUG_START: результат сохранения псевдонима
                if ($debugEnabled) {
                    $messages[] = 'DEBUG save_name_main: ' . htmlspecialchars(json_encode($update, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                }
                // DEBUG_END

                if ($update['ok']) {
                    $messages[] = 'Псевдоним успешно сохранён.';
                } else {
                    $errors[] = 'Ошибка сохранения псевдонима: ' . $update['error'];
                }
            }
        }

        if ($action === 'run_sync') {
            $selectedDocuments = isset($_POST['documents']) && is_array($_POST['documents'])
                ? array_values(array_filter(array_map('trim', $_POST['documents'])))
                : [];

            $command = 'php ' . escapeshellarg($syncScript);
            foreach ($selectedDocuments as $document) {
                $command .= ' ' . escapeshellarg($document);
            }
            $command .= ' 2>&1';

            // DEBUG_START: команда запуска синхронизации
            if ($debugEnabled) {
                $messages[] = 'DEBUG sync command: ' . htmlspecialchars($command, ENT_QUOTES, 'UTF-8');
            }
            // DEBUG_END

            $syncOutput = (string)shell_exec($command);

            if ($syncOutput === '') {
                $errors[] = 'Синхронизация не вернула вывод. Проверь shell_exec() и права доступа.';
            } else {
                $messages[] = 'Синхронизация выполнена. Ниже показан вывод скрипта.';
            }
        }
    }

    $documentsResult = Database::fetchAll($dbName, "
        SELECT
            da.id,
            da.document,
            da.ms_attribute_id,
            da.name_source,
            da.name_code,
            da.name_main,
            da.data_type,
            da.custom_entity_id,
            da.is_archived,
            da.updated_at,
            da.created_at,
            COUNT(ce.id) AS customentity_count
        FROM Documents_attr da
        LEFT JOIN customentity ce
            ON ce.document = da.document
           AND ce.customentity = da.custom_entity_id
           AND ce.is_archived = 0
        GROUP BY
            da.id,
            da.document,
            da.ms_attribute_id,
            da.name_source,
            da.name_code,
            da.name_main,
            da.data_type,
            da.custom_entity_id,
            da.is_archived,
            da.updated_at,
            da.created_at
        ORDER BY da.document ASC, da.is_archived ASC, da.name_source ASC
    ");

    if (!$documentsResult['ok']) {
        throw new Exception('Ошибка чтения Documents_attr: ' . $documentsResult['error']);
    }

    $customEntityResult = Database::fetchAll($dbName, "
        SELECT
            id,
            document,
            customentity,
            meta,
            name_source,
            name_code,
            is_archived,
            updated_at,
            created_at
        FROM customentity
        ORDER BY document ASC, customentity ASC, is_archived ASC, name_source ASC
    ");

    if (!$customEntityResult['ok']) {
        throw new Exception('Ошибка чтения customentity: ' . $customEntityResult['error']);
    }

    $statsResult = Database::fetchAll($dbName, "
        SELECT
            da.document,
            COUNT(*) AS attributes_total,
            SUM(CASE WHEN da.is_archived = 0 THEN 1 ELSE 0 END) AS attributes_active,
            SUM(CASE WHEN da.is_archived = 1 THEN 1 ELSE 0 END) AS attributes_archived,
            SUM(CASE WHEN da.name_main IS NOT NULL AND da.name_main <> '' THEN 1 ELSE 0 END) AS mapped_aliases,
            COUNT(DISTINCT CASE WHEN ce.id IS NOT NULL AND ce.is_archived = 0 THEN ce.id END) AS customentity_values_active
        FROM Documents_attr da
        LEFT JOIN customentity ce
            ON ce.document = da.document
           AND ce.customentity = da.custom_entity_id
        GROUP BY da.document
        ORDER BY da.document ASC
    ");

    if (!$statsResult['ok']) {
        throw new Exception('Ошибка чтения статистики: ' . $statsResult['error']);
    }

    $documentsRows = $documentsResult['rows'];
    $customEntityRows = $customEntityResult['rows'];
    $statsRows = $statsResult['rows'];

    $grouped = [];
    foreach ($documentsRows as $row) {
        $document = $row['document'];
        if (!isset($grouped[$document])) {
            $grouped[$document] = [];
        }
        $grouped[$document][] = $row;
    }

    $customEntityMap = [];
    foreach ($customEntityRows as $row) {
        $document = $row['document'];
        $customentity = $row['customentity'];
        if (!isset($customEntityMap[$document])) {
            $customEntityMap[$document] = [];
        }
        if (!isset($customEntityMap[$document][$customentity])) {
            $customEntityMap[$document][$customentity] = [];
        }
        $customEntityMap[$document][$customentity][] = $row;
    }

    if ($syncOutput !== '') {
        $syncSummaryResult = Database::fetchAll($dbName, "
            SELECT
                document,
                COUNT(*) AS attributes_total,
                SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) AS attributes_active
            FROM Documents_attr
            GROUP BY document
            ORDER BY document ASC
        ");

        if ($syncSummaryResult['ok']) {
            $syncSummary = $syncSummaryResult['rows'];
        }
    }

    $availableDocuments = array_keys($grouped);
} catch (Exception $e) {
    $errors[] = $e->getMessage();
    $documentsRows = [];
    $customEntityRows = [];
    $statsRows = [];
    $grouped = [];
    $customEntityMap = [];
    $availableDocuments = [];
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
$title     = 'МС атрибути';
$activeNav = 'tools';
$subNav    = 'ms-attrs';
require_once $projectRoot . '/modules/shared/layout.php';
?>
<style>
        :root {
            --bg: #f5f7fb;
            --panel: #ffffff;
            --panel-2: #f8fafc;
            --border: #dbe3ef;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #15803d;
            --danger: #b91c1c;
            --warning: #b45309;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            --radius: 18px;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            background: linear-gradient(180deg, #eef4ff 0%, var(--bg) 140px);
            color: var(--text);
        }

        .page {
            max-width: 1680px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .title-wrap h1 {
            margin: 0 0 6px;
            font-size: 32px;
            line-height: 1.1;
        }

        .title-wrap p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .controls {
            padding: 18px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 18px;
            margin-bottom: 24px;
        }

        .controls h2,
        .section-title {
            margin: 0 0 12px;
            font-size: 20px;
        }

        .doc-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            max-height: 220px;
            overflow: auto;
            padding-right: 6px;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--panel-2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 14px;
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 12px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            transition: .2s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover { background: var(--primary-dark); }

        .btn-light {
            background: #fff;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            padding: 18px;
        }

        .stat-card .label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .stat-card .hint {
            font-size: 13px;
            color: var(--muted);
        }

        .messages,
        .sync-output,
        .sync-summary,
        .table-panel,
        .document-card {
            margin-bottom: 24px;
        }

        .message {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 10px;
            font-size: 14px;
            border: 1px solid transparent;
        }

        .message-success {
            background: #ecfdf3;
            border-color: #b7efc5;
            color: var(--success);
        }

        .message-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: var(--danger);
        }

        .table-wrap {
            overflow: auto;
            border-radius: 16px;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th, td {
            padding: 12px 14px;
            border-bottom: 1px solid #e8eef7;
            vertical-align: top;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f8fbff;
            color: #334155;
            font-weight: 700;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-archived {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-type {
            background: #e0ecff;
            color: #1d4ed8;
        }

        .input-inline {
            width: 100%;
            min-width: 180px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: 14px;
        }

        .inline-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .inline-form .btn {
            padding: 10px 12px;
            min-width: 98px;
        }

        .document-card {
            padding: 18px;
        }

        .document-card h3 {
            margin: 0 0 6px;
            font-size: 22px;
        }

        .document-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 13px;
        }

        .code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 4px 7px;
            display: inline-block;
        }

        .subtable {
            margin-top: 10px;
            border-left: 4px solid #dbeafe;
            padding-left: 12px;
        }

        pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            line-height: 1.45;
            background: #0f172a;
            color: #e2e8f0;
            padding: 18px;
            border-radius: 16px;
            overflow: auto;
        }

		.attr-missing {
			background: #ffe5e5;
		}
		
		.input-missing {
			border: 2px solid #dc2626 !important;
			background: #fff5f5 !important;
			color: #991b1b;
			font-weight: 600;
		}

		.attr-missing td {
			color: #a40000;
			font-weight: 600;
		}



        .muted { color: var(--muted); }
        .right { text-align: right; }

        @media (max-width: 1120px) {
            .controls {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .page { padding: 14px; }
            .title-wrap h1 { font-size: 26px; }
            .inline-form { flex-direction: column; align-items: stretch; }
            th, td { padding: 10px; }
        }
    </style>
<div class="page">

    <?php if (!empty($messages) || !empty($errors)): ?>
        <div class="messages">
            <?php foreach ($messages as $message): ?>
                <div class="message message-success"><?= $message ?></div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="message message-error"><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="panel controls">
        <div>
            <h2>Обновление данных</h2>
            <form method="post">
                <input type="hidden" name="action" value="run_sync">
                <div class="doc-list">
                    <?php foreach ($availableDocuments as $document): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="documents[]" value="<?= h($document) ?>">
                            <span><?= h($document) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex; gap:10px; margin-top:14px; flex-wrap:wrap;">
                    <button class="btn btn-primary" type="submit">Обновить таблицы</button>
                    <button class="btn btn-light" type="button" onclick="toggleAllDocuments(true)">Выбрать все</button>
                    <button class="btn btn-light" type="button" onclick="toggleAllDocuments(false)">Снять выбор</button>
                </div>
                <p class="muted" style="margin:12px 0 0;">Если ничего не выбрать, скрипт синхронизации сам возьмёт список по умолчанию.</p>
            </form>
        </div>

        <div>
            <h2>Краткая сводка</h2>
            <div class="stats-grid" style="margin-bottom:0;">
                <div class="panel stat-card">
                    <div class="label">Документов</div>
                    <div class="value"><?= count($grouped) ?></div>
                    <div class="hint">Групп документов в таблице атрибутов</div>
                </div>
                <div class="panel stat-card">
                    <div class="label">Атрибутов</div>
                    <div class="value"><?= count($documentsRows) ?></div>
                    <div class="hint">Всего записей в Documents_attr</div>
                </div>
                <div class="panel stat-card">
                    <div class="label">Значений справочников</div>
                    <div class="value"><?= count($customEntityRows) ?></div>
                    <div class="hint">Всего записей в customentity</div>
                </div>
                <div class="panel stat-card">
                    <div class="label">Псевдонимов задано</div>
                    <div class="value"><?php
                        $mapped = 0;
                        foreach ($documentsRows as $r) {
                            if (!empty($r['name_main'])) {
                                $mapped++;
                            }
                        }
                        echo $mapped;
                    ?></div>
                    <div class="hint">Атрибуты с заполненным name_main</div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-panel panel" style="padding:18px;">
        <div class="section-title">Статистика по видам документов</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Документ</th>
                        <th class="right">Всего атрибутов</th>
                        <th class="right">Активных</th>
                        <th class="right">Архивных</th>
                        <th class="right">Заполнено name_main</th>
                        <th class="right">Активных значений customentity</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($statsRows)): ?>
                    <tr><td colspan="6">Нет данных.</td></tr>
                <?php else: ?>
                    <?php foreach ($statsRows as $row): ?>
                        <tr>
                            <td><strong><?= h($row['document']) ?></strong></td>
                            <td class="right"><?= (int)$row['attributes_total'] ?></td>
                            <td class="right"><?= (int)$row['attributes_active'] ?></td>
                            <td class="right"><?= (int)$row['attributes_archived'] ?></td>
                            <td class="right"><?= (int)$row['mapped_aliases'] ?></td>
                            <td class="right"><?= (int)$row['customentity_values_active'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($syncOutput !== ''): ?>
        <div class="sync-output panel" style="padding:18px;">
            <div class="section-title">Вывод синхронизации</div>
            <pre><?= h($syncOutput) ?></pre>
        </div>
    <?php endif; ?>

    <?php if (!empty($syncSummary)): ?>
        <div class="sync-summary panel" style="padding:18px;">
            <div class="section-title">Результат после обновления</div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Документ</th>
                        <th class="right">Записей в БД</th>
                        <th class="right">Активных</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($syncSummary as $row): ?>
                        <tr>
                            <td><?= h($row['document']) ?></td>
                            <td class="right"><?= (int)$row['attributes_total'] ?></td>
                            <td class="right"><?= (int)$row['attributes_active'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($grouped as $document => $rows): ?>
        <section class="document-card panel">
            <h3><?= h($document) ?></h3>
            <div class="document-meta">
                <span>Атрибутов: <strong><?= count($rows) ?></strong></span>
                <span>Активных: <strong><?php
                    $active = 0;
                    foreach ($rows as $r) {
                        if ((int)$r['is_archived'] === 0) {
                            $active++;
                        }
                    }
                    echo $active;
                ?></strong></span>
                <span>Архивных: <strong><?= count($rows) - $active ?></strong></span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Статус</th>
                            <th>Источник</th>
                            <th>Code</th>
                            <th>name_main</th>
                            <th>Тип</th>
                            <th>MS attribute ID</th>
                            <th>CustomEntity</th>
                            <th class="right">Значений</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
						<?php
							$rowClass = '';
							$inputClass = '';

							if (!isset($row['name_main']) || trim($row['name_main']) === '') {
								$rowClass = 'attr-missing';
								$inputClass = 'input-missing';
							}
						?>
						<tr class="<?= h($rowClass) ?>">
                            <td><?= (int)$row['id'] ?></td>
                            <td>
                                <?php if ((int)$row['is_archived'] === 0): ?>
                                    <span class="badge badge-active">Активный</span>
                                <?php else: ?>
                                    <span class="badge badge-archived">Архив</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= h($row['name_source']) ?></strong><br>
                                <span class="muted">updated: <?= h($row['updated_at']) ?></span>
                            </td>
                            <td><span class="code"><?= h($row['name_code']) ?></span></td>
                            <td style="min-width:320px;">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="save_name_main">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
									<input
										class="input-inline <?= h($inputClass) ?>"
										type="text"
										name="name_main"
										value="<?= h($row['name_main']) ?>"
										placeholder="Например: payment_status"
									>
                                    <button class="btn btn-primary" type="submit">Сохранить</button>
                                </form>
                            </td>
                            <td><span class="badge badge-type"><?= h($row['data_type']) ?></span></td>
                            <td><span class="code"><?= h($row['ms_attribute_id']) ?></span></td>
                            <td>
                                <?php if (!empty($row['custom_entity_id'])): ?>
                                    <span class="code"><?= h($row['custom_entity_id']) ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="right"><?= (int)$row['customentity_count'] ?></td>
                        </tr>
                        <?php if (!empty($row['custom_entity_id']) && isset($customEntityMap[$document][$row['custom_entity_id']])): ?>
                            <tr>
                                <td colspan="9" style="background:#fcfdff;">
                                    <div class="subtable">
                                        <div style="font-weight:700; margin-bottom:10px;">Значения справочника <?= h($row['custom_entity_id']) ?></div>
                                        <div class="table-wrap">
                                            <table>
                                                <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Статус</th>
                                                    <th>Название</th>
                                                    <th>Code</th>
                                                    <th>Meta</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($customEntityMap[$document][$row['custom_entity_id']] as $ceRow): ?>
                                                    <tr>
                                                        <td><?= (int)$ceRow['id'] ?></td>
                                                        <td>
                                                            <?php if ((int)$ceRow['is_archived'] === 0): ?>
                                                                <span class="badge badge-active">Активный</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-archived">Архив</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= h($ceRow['name_source']) ?></td>
                                                        <td><span class="code"><?= h($ceRow['name_code']) ?></span></td>
                                                        <td><span class="code"><?= h($ceRow['meta']) ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<script>
function toggleAllDocuments(checked) {
    const checkboxes = document.querySelectorAll('input[name="documents[]"]');
    checkboxes.forEach(function (item) {
        item.checked = checked;
    });
}
</script>
<?php require_once $projectRoot . '/modules/shared/layout_end.php'; ?>
