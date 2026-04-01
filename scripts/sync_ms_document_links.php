<?php
/**
 * Синхронизация связей между документами из МойСклад → Papir.document_link
 *
 * Синкаем operations[] из платёжных документов:
 *   paymentin  → может ссылаться на customerorder, invoiceout, demand
 *   paymentout → может ссылаться на purchaseorder, invoicein, supply
 *   cashin     → может ссылаться на customerorder
 *   cashout    → может ссылаться на purchaseorder
 *
 * После загрузки — backfill internal IDs (from_id, to_id) по таблицам
 * finance_bank, finance_cash, customerorder.
 *
 * Запуск:
 *   php scripts/sync_ms_document_links.php --dry-run
 *   php scripts/sync_ms_document_links.php
 *   nohup php scripts/sync_ms_document_links.php > /tmp/sync_ms_document_links.log 2>&1 &
 */

require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/moysklad/moysklad_api.php';

$dryRun  = in_array('--dry-run', $argv);
$fullSync = in_array('--full', $argv); // загрузить ВСЕ документы без фильтра по дате
$logFile = '/tmp/sync_ms_document_links.log';
$myPid   = getmypid();
$lockFile = '/tmp/sync_ms_document_links.lock';

// Инкрементальный синк: документы обновлённые за последние 48 часов
// 48ч с запасом чтобы не пропустить ничего при задержках/ретраях
$updatedFrom = $fullSync ? null : date('Y-m-d H:i:s', strtotime('-48 hours'));

function out($msg)
{
    echo date('[H:i:s] ') . $msg . PHP_EOL;
}

// ── Lock file — защита от параллельного запуска ───────────────────────────

if (!$dryRun) {
    if (file_exists($lockFile)) {
        $existingPid = trim(file_get_contents($lockFile));
        if ($existingPid && file_exists('/proc/' . $existingPid)) {
            out('Уже запущен (PID ' . $existingPid . '). Выход.');
            exit(1);
        }
        out('Удаляем устаревший lock (PID ' . $existingPid . ' не существует).');
        unlink($lockFile);
    }
    file_put_contents($lockFile, $myPid);
}

// ── Регистрация в background_jobs ─────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк зв\'язків документів МойСклад',
        'script'   => 'scripts/sync_ms_document_links.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== РЕАЛЬНИЙ СИНК ===');
out($fullSync ? 'Режим: ПОВНИЙ (всі документи)' : 'Режим: інкрементальний (з ' . $updatedFrom . ')');

// ── Загрузить маппинг ms_type → code из document_type ────────────────────

out('Завантаження document_type...');
$msTypeToCode = array();
$r = Database::fetchAll('Papir', "SELECT `code`, `ms_type` FROM `document_type` WHERE `ms_type` IS NOT NULL");
if ($r['ok']) {
    foreach ($r['rows'] as $row) {
        $msTypeToCode[$row['ms_type']] = $row['code'];
    }
}
out('Завантажено типів: ' . count($msTypeToCode));

// ── Кеш document_type_transition (link_type по паре from+to) ─────────────

$linkTypeCache = array();
$r = Database::fetchAll('Papir', "SELECT `from_type`, `to_type`, `link_type` FROM `document_type_transition`");
if ($r['ok']) {
    foreach ($r['rows'] as $row) {
        $linkTypeCache[$row['from_type'] . '|' . $row['to_type']] = $row['link_type'];
    }
}
out('Завантажено переходів: ' . count($linkTypeCache));

// ── Инициализация MoySkladApi ─────────────────────────────────────────────

$ms = new MoySkladApi();
$entityBase = $ms->getEntityBaseUrl(); // уже содержит entity/

// ── Вспомогательные функции ───────────────────────────────────────────────

/**
 * Извлекает UUID из href МойСклад.
 * href вида: https://api.moysklad.ru/api/remap/1.2/entity/customerorder/UUID
 */
function extractUuid($href)
{
    if (empty($href)) {
        return null;
    }
    $pos = strrpos($href, '/');
    if ($pos === false) {
        return null;
    }
    $uuid = substr($href, $pos + 1);
    // Базовая валидация UUID (8-4-4-4-12)
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
        return $uuid;
    }
    return null;
}

/**
 * Извлекает ms_type из href МойСклад.
 * href вида: .../entity/customerorder/UUID → "customerorder"
 */
function extractMsType($href)
{
    if (empty($href)) {
        return null;
    }
    $uuid = extractUuid($href);
    if (!$uuid) {
        return null;
    }
    // Удаляем UUID и trailing slash
    $base = substr($href, 0, -(strlen($uuid) + 1));
    $pos = strrpos($base, '/');
    if ($pos === false) {
        return null;
    }
    return substr($base, $pos + 1);
}

/**
 * Постранично загружает документы заданного типа из МойСклад API.
 * $updatedFrom — строка вида '2026-03-30 00:00:00' или null (без фильтра = полная загрузка).
 * Возвращает массив документов (rows).
 */
function fetchAllMsDocuments($ms, $entityBase, $docType, $updatedFrom = null)
{
    $limit  = 100;
    $offset = 0;
    $all    = array();

    $filterParam = '';
    if ($updatedFrom !== null) {
        // МойСклад filter: ?filter=updatedFrom=YYYY-MM-DD HH:MM:SS
        $filterParam = '&filter=updatedFrom%3D' . urlencode($updatedFrom);
    }

    out("  Завантаження {$docType} з МойСклад" . ($updatedFrom ? " (з {$updatedFrom})" : ' (повна)') . '...');

    while (true) {
        $url = $entityBase . $docType . '?limit=' . $limit . '&offset=' . $offset . $filterParam;
        // query() возвращает объект (json_decode без true), приводим к массиву
        $response = $ms->query($url);
        $response = json_decode(json_encode($response), true);

        if (!$response || !isset($response['rows'])) {
            out("  Помилка або порожня відповідь для {$docType} offset={$offset}");
            break;
        }

        $rows = $response['rows'];
        if (empty($rows)) {
            break;
        }

        foreach ($rows as $row) {
            $all[] = $row;
        }
        out("  {$docType}: завантажено " . count($all) . ' документів...');

        // Проверяем, есть ли ещё страницы
        $size    = isset($response['meta']['size'])  ? (int)$response['meta']['size']  : 0;
        $limit_r = isset($response['meta']['limit']) ? (int)$response['meta']['limit'] : $limit;
        $offset += $limit_r;

        if ($offset >= $size) {
            break;
        }
    }

    out("  {$docType}: всього " . count($all) . ' документів.');
    return $all;
}

// ── Типы платёжных документов и их ожидаемые операции ────────────────────
// Ключ — ms_type платёжного документа
// Значение — список допустимых ms_type в operations[]

$paymentDocTypes = array(
    'paymentin'  => array('customerorder', 'invoiceout', 'demand'),
    'paymentout' => array('purchaseorder', 'invoicein', 'supply'),
    'cashin'     => array('customerorder'),
    'cashout'    => array('purchaseorder'),
);

// ── Статистика ────────────────────────────────────────────────────────────

$stats = array(
    'inserted' => 0,
    'updated'  => 0,
    'skipped'  => 0,
    'errors'   => 0,
    'no_ops'   => 0,
);

// ── Основной цикл: загрузка и запись связей ───────────────────────────────

foreach ($paymentDocTypes as $fromMsType => $allowedToTypes) {

    out("--- {$fromMsType} ---");

    $fromCode = isset($msTypeToCode[$fromMsType]) ? $msTypeToCode[$fromMsType] : $fromMsType;

    $documents = fetchAllMsDocuments($ms, $entityBase, $fromMsType, $updatedFrom);

    if (empty($documents)) {
        out("  Документів не знайдено, пропускаємо.");
        continue;
    }

    $processed = 0;
    $linksFound = 0;

    foreach ($documents as $doc) {
        $processed++;

        // UUID документа-источника
        $fromHref  = isset($doc['meta']['href']) ? $doc['meta']['href'] : '';
        $fromMsId  = extractUuid($fromHref);

        if (!$fromMsId) {
            $stats['errors']++;
            continue;
        }

        // operations[] — массив связанных документов
        $operations = isset($doc['operations']) ? $doc['operations'] : array();

        if (empty($operations)) {
            $stats['no_ops']++;
            continue;
        }

        foreach ($operations as $op) {
            $toHref  = isset($op['meta']['href']) ? $op['meta']['href'] : '';
            $toMsId  = extractUuid($toHref);
            $toMsType = extractMsType($toHref);

            if (!$toMsId || !$toMsType) {
                continue;
            }

            // Пропускаем типы, не входящие в допустимые
            if (!in_array($toMsType, $allowedToTypes)) {
                continue;
            }

            $toCode = isset($msTypeToCode[$toMsType]) ? $msTypeToCode[$toMsType] : $toMsType;

            // link_type из кеша (вместо запроса в БД на каждую операцию)
            $cacheKey = $fromCode . '|' . $toCode;
            $linkType = isset($linkTypeCache[$cacheKey]) ? $linkTypeCache[$cacheKey] : null;

            $linkedSum = isset($op['linkedSum']) ? (float)$op['linkedSum'] : null;

            $fromMsIdEsc = Database::escape('Papir', $fromMsId);
            $toMsIdEsc   = Database::escape('Papir', $toMsId);
            $fromCodeEsc = Database::escape('Papir', $fromCode);
            $toCodeEsc   = Database::escape('Papir', $toCode);
            $linkTypeEsc = $linkType ? ("'" . Database::escape('Papir', $linkType) . "'") : 'NULL';
            $linkedSumSql = ($linkedSum !== null) ? $linkedSum : 'NULL';

            if (!$dryRun) {
                $sql = "INSERT INTO `document_link`
                        (`from_type`, `from_ms_id`, `to_type`, `to_ms_id`, `link_type`, `linked_sum`)
                        VALUES
                        ('{$fromCodeEsc}', '{$fromMsIdEsc}', '{$toCodeEsc}', '{$toMsIdEsc}', {$linkTypeEsc}, {$linkedSumSql})
                        ON DUPLICATE KEY UPDATE
                            `link_type`  = VALUES(`link_type`),
                            `linked_sum` = VALUES(`linked_sum`)";

                $r = Database::query('Papir', $sql);
                if (!$r['ok']) {
                    $stats['errors']++;
                    continue;
                }

                // affected_rows=1 → INSERT, affected_rows=2 → UPDATE (MySQL ON DUPLICATE KEY)
                if (isset($r['affected_rows'])) {
                    if ($r['affected_rows'] == 1) {
                        $stats['inserted']++;
                    } elseif ($r['affected_rows'] == 2) {
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    $stats['inserted']++;
                }
            } else {
                // dry-run: просто считаем
                $stats['inserted']++;
            }

            $linksFound++;
        }

        if ($processed % 500 === 0) {
            out("  {$fromMsType}: оброблено {$processed} документів, знайдено зв'язків: {$linksFound}");
        }
    }

    out("  {$fromMsType}: завершено. Документів: {$processed}, зв'язків: {$linksFound}");
}

out('Підсумок синку: inserted=' . $stats['inserted']
    . ', updated=' . $stats['updated']
    . ', skipped=' . $stats['skipped']
    . ', no_ops=' . $stats['no_ops']
    . ', errors=' . $stats['errors']);

// ── Backfill: проставить from_id и to_id по ms_id ────────────────────────

out('--- Backfill internal IDs ---');

if (!$dryRun) {

    // from_id для paymentin / paymentout → finance_bank
    out('Backfill from_id (paymentin/paymentout → finance_bank)...');
    $r = Database::query('Papir',
        "UPDATE `document_link` dl
         JOIN `finance_bank` fb ON fb.id_ms = dl.from_ms_id
         SET dl.from_id = fb.id
         WHERE dl.from_type IN ('paymentin', 'paymentout')
           AND dl.from_ms_id IS NOT NULL
           AND dl.from_id IS NULL"
    );
    out('  finance_bank from_id: ' . (isset($r['affected_rows']) ? $r['affected_rows'] : '?') . ' оновлено.');

    // from_id для cashin / cashout → finance_cash
    out('Backfill from_id (cashin/cashout → finance_cash)...');
    $r = Database::query('Papir',
        "UPDATE `document_link` dl
         JOIN `finance_cash` fc ON fc.id_ms = dl.from_ms_id
         SET dl.from_id = fc.id
         WHERE dl.from_type IN ('cashin', 'cashout')
           AND dl.from_ms_id IS NOT NULL
           AND dl.from_id IS NULL"
    );
    out('  finance_cash from_id: ' . (isset($r['affected_rows']) ? $r['affected_rows'] : '?') . ' оновлено.');

    // to_id для customerorder → customerorder.id
    out('Backfill to_id (customerorder)...');
    $r = Database::query('Papir',
        "UPDATE `document_link` dl
         JOIN `customerorder` co ON co.id_ms = dl.to_ms_id
         SET dl.to_id = co.id
         WHERE dl.to_type = 'customerorder'
           AND dl.to_ms_id IS NOT NULL
           AND dl.to_id IS NULL"
    );
    out('  customerorder to_id: ' . (isset($r['affected_rows']) ? $r['affected_rows'] : '?') . ' оновлено.');

    // to_id для purchaseorder — пока нет таблицы, пропускаем
    // to_id для invoiceout, demand, invoicein, supply — пока нет таблиц, пропускаем

    out('Backfill завершено.');

} else {
    out('DRY RUN: backfill пропущено.');
}

// ── Завершение ────────────────────────────────────────────────────────────

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );

    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

out('Готово.');
