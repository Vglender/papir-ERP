<?php
/**
 * Заполняет document_link из ms-зеркала (без обращения к МойСклад API).
 *
 * Источники:
 *   ms.demand.customerOrder       → document_link: demand → customerorder
 *   ms.supply.purchaseOrder       → document_link: supply → purchaseorder
 *   ms.salesreturn.demand         → document_link: salesreturn → demand
 *   ms.purchaseorder.customerOrders → document_link: purchaseorder → customerorder (только первый)
 *   ms.purchaseorder.supplies       → document_link: purchaseorder → supply (только первый)
 *
 * Также backfill from_id/to_id по наличию данных в Papir.
 *
 * Запуск:
 *   php scripts/sync_ms_document_links_from_mirror.php --dry-run
 *   php scripts/sync_ms_document_links_from_mirror.php
 */

$_lockFp = fopen('/tmp/sync_ms_document_links_from_mirror.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) { echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL; exit(0); }

require_once __DIR__ . '/../modules/database/database.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/sync_ms_document_links_from_mirror.log';
$myPid   = getmypid();

function out($msg)
{
    echo date('[H:i:s] ') . $msg . PHP_EOL;
}

// ── Регистрация в background_jobs ──────────────────────────────────────────

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк зв\'язків документів із ms-дзеркала',
        'script'   => 'scripts/sync_ms_document_links_from_mirror.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ЗВ\'ЯЗКІВ ІЗ ms-ДЗЕРКАЛА ===');

// ── Конфигурация источников ────────────────────────────────────────────────
//
// Каждая запись описывает одну выборку из ms и что она означает:
//   ms_table    — таблица в ms
//   ms_id_col   — колонка с UUID самого документа (from)
//   ms_link_col — колонка с UUID связанного документа (to)
//   from_type   — наш тип источника
//   to_type     — наш тип цели
//   link_type   — тип связи
//   note        — пояснение

$sources = array(
    array(
        'ms_table'    => 'demand',
        'ms_id_col'   => 'meta',
        'ms_link_col' => 'customerOrder',
        'from_type'   => 'demand',
        'to_type'     => 'customerorder',
        'link_type'   => 'shipment',
        'note'        => 'demand → customerorder',
    ),
    array(
        'ms_table'    => 'supply',
        'ms_id_col'   => 'meta',
        'ms_link_col' => 'purchaseOrder',
        'from_type'   => 'supply',
        'to_type'     => 'purchaseorder',
        'link_type'   => 'shipment',
        'note'        => 'supply → purchaseorder',
    ),
    array(
        'ms_table'    => 'salesreturn',
        'ms_id_col'   => 'meta',
        'ms_link_col' => 'demand',
        'from_type'   => 'salesreturn',
        'to_type'     => 'demand',
        'link_type'   => 'return',
        'note'        => 'salesreturn → demand',
    ),
    array(
        'ms_table'    => 'purchaseorder',
        'ms_id_col'   => 'meta',
        'ms_link_col' => 'customerOrders',
        'from_type'   => 'purchaseorder',
        'to_type'     => 'customerorder',
        'link_type'   => 'shipment',
        'note'        => 'purchaseorder → customerorder (только первый из массива)',
    ),
    array(
        'ms_table'    => 'purchaseorder',
        'ms_id_col'   => 'meta',
        'ms_link_col' => 'supplies',
        'from_type'   => 'purchaseorder',
        'to_type'     => 'supply',
        'link_type'   => 'shipment',
        'note'        => 'purchaseorder → supply (только первый из массива)',
    ),
);

// ── Основной цикл ──────────────────────────────────────────────────────────

$totalStats = array('inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0);

foreach ($sources as $src) {
    $msTable   = $src['ms_table'];
    $idCol     = $src['ms_id_col'];
    $linkCol   = $src['ms_link_col'];
    $fromType  = $src['from_type'];
    $toType    = $src['to_type'];
    $linkType  = $src['link_type'];
    $note      = $src['note'];

    out("--- {$note} ---");

    $batchSize = 1000;
    $offset    = 0;
    $stats     = array('inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0);

    while (true) {
        $rows = Database::fetchAll('ms',
            "SELECT `{$idCol}` AS from_ms_id, `{$linkCol}` AS to_ms_id
             FROM `{$msTable}`
             WHERE `{$idCol}` IS NOT NULL AND `{$idCol}` != ''
               AND `{$linkCol}` IS NOT NULL AND `{$linkCol}` != ''
             ORDER BY id
             LIMIT {$batchSize} OFFSET {$offset}"
        );

        if (!$rows['ok'] || empty($rows['rows'])) {
            break;
        }

        foreach ($rows['rows'] as $row) {
            $fromMsId = trim((string)$row['from_ms_id']);
            $toMsId   = trim((string)$row['to_ms_id']);

            if ($fromMsId === '' || $toMsId === '') {
                $stats['skipped']++;
                continue;
            }

            $fromMsIdEsc = Database::escape('Papir', $fromMsId);
            $toMsIdEsc   = Database::escape('Papir', $toMsId);
            $fromTypeEsc = Database::escape('Papir', $fromType);
            $toTypeEsc   = Database::escape('Papir', $toType);
            $linkTypeEsc = Database::escape('Papir', $linkType);

            if (!$dryRun) {
                $r = Database::query('Papir',
                    "INSERT INTO document_link
                     (from_type, from_ms_id, to_type, to_ms_id, link_type)
                     VALUES
                     ('{$fromTypeEsc}', '{$fromMsIdEsc}', '{$toTypeEsc}', '{$toMsIdEsc}', '{$linkTypeEsc}')
                     ON DUPLICATE KEY UPDATE
                         link_type = VALUES(link_type)"
                );

                if (!$r['ok']) {
                    $stats['errors']++;
                    continue;
                }

                $affected = isset($r['affected_rows']) ? (int)$r['affected_rows'] : 0;
                if ($affected === 1) {
                    $stats['inserted']++;
                } elseif ($affected === 2) {
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['inserted']++;
            }
        }

        $done = $stats['inserted'] + $stats['updated'] + $stats['skipped'] + $stats['errors'];
        out("  {$note}: оброблено ~{$done}...");

        if (count($rows['rows']) < $batchSize) {
            break;
        }

        $offset += $batchSize;
    }

    out("  Результат: inserted={$stats['inserted']} updated={$stats['updated']} skipped={$stats['skipped']} errors={$stats['errors']}");

    foreach ($stats as $k => $v) {
        $totalStats[$k] += $v;
    }
}

out('');
out('=== ПІДСУМОК ===');
out('inserted=' . $totalStats['inserted']
    . ' updated=' . $totalStats['updated']
    . ' skipped=' . $totalStats['skipped']
    . ' errors='  . $totalStats['errors']);

// ── Backfill from_id / to_id ───────────────────────────────────────────────

out('');
out('--- Backfill internal IDs ---');

if (!$dryRun) {

    // demand: from_id — нет своей таблицы в Papir пока, пропускаем
    // supply: from_id — нет своей таблицы в Papir пока, пропускаем
    // salesreturn: from_id — нет своей таблицы в Papir пока, пропускаем

    // to_id для customerorder
    out('Backfill to_id → customerorder...');
    $r = Database::query('Papir',
        "UPDATE document_link dl
         JOIN customerorder co ON co.id_ms = dl.to_ms_id
         SET dl.to_id = co.id
         WHERE dl.to_type = 'customerorder'
           AND dl.to_ms_id IS NOT NULL
           AND dl.to_id IS NULL"
    );
    out('  customerorder to_id: ' . (isset($r['affected_rows']) ? $r['affected_rows'] : '?') . ' оновлено.');

    // from_id для customerorder (purchaseorder.customerOrders → from=purchaseorder, но нет таблицы)
    // demand → to_id при salesreturn (нет таблицы demand в Papir пока)

    out('Backfill завершено (інші таблиці з\'являться при імпорті demand/supply/salesreturn).');

} else {
    out('DRY RUN: backfill пропущено.');
}

// ── Завершение ─────────────────────────────────────────────────────────────

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'"
    );
}

out('Готово.');
