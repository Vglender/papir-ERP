<?php
/**
 * Синк списань: ms.loss + ms.loss_positions
 *   → Papir.loss + loss_item
 *
 * Запуск:
 *   php scripts/sync_ms_loss.php --dry-run
 *   php scripts/sync_ms_loss.php
 */

$_lockFp = fopen('/tmp/sync_ms_loss.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) {
    echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL;
    exit(0);
}

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_loss.log';
$myPid     = getmypid();
$batchSize = 500;

function out($msg) { echo date('[H:i:s] ') . $msg . PHP_EOL; }
function e($v)     { return Database::escape('Papir', (string)$v); }
function nullOrStr($v, $max = 0) {
    $v = trim((string)$v);
    if ($v === '') return 'NULL';
    if ($max > 0 && mb_strlen($v, 'UTF-8') > $max) $v = mb_substr($v, 0, $max, 'UTF-8');
    return "'" . e($v) . "'";
}
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
}

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Синк списань з МойСклад',
        'script'   => 'scripts/sync_ms_loss.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК СПИСАНЬ ===');

// ── Maps ──────────────────────────────────────────────────────────────────────
out('Завантаження map...');

// salesreturn map: id_ms → id
$srMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM salesreturn WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $srMap[$row['id_ms']] = (int)$row['id'];
out('Повернень покупця: ' . count($srMap));

$productMap = array();
$r = Database::fetchAll('Papir', "SELECT product_id, id_ms FROM product_papir WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $productMap[$row['id_ms']] = (int)$row['product_id'];
out('Товарів: ' . count($productMap));

$existing = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms, updated_at FROM loss");
if ($r['ok']) foreach ($r['rows'] as $row) $existing[$row['id_ms']] = array('id' => (int)$row['id'], 'upd' => $row['updated_at']);
out('В Papir вже є: ' . count($existing));

// ── Основний цикл ─────────────────────────────────────────────────────────────
$inserted = $updated = $skipped = 0;
$offset = 0;

while (true) {
    $rMs = Database::fetchAll('ms',
        "SELECT id, meta, name, moment, applicable, sum, salesReturn, state, description, updated
         FROM loss
         ORDER BY id
         LIMIT {$batchSize} OFFSET {$offset}");

    if (!$rMs['ok'] || empty($rMs['rows'])) break;

    foreach ($rMs['rows'] as $ms) {
        $idMs = (string)$ms['meta'];
        if (!$idMs) continue;

        $srId      = isset($srMap[$ms['salesReturn']]) ? $srMap[$ms['salesReturn']] : null;
        $sumTotal  = (float)$ms['sum'];
        $stateMs   = $ms['state'];
        $descr     = $ms['description'];
        $moment    = $ms['moment'];
        $applicable = (int)$ms['applicable'];
        $number    = $ms['name'];
        $msUpdated = $ms['updated'];

        if (isset($existing[$idMs])) {
            $ourUpd = $existing[$idMs]['upd'];
            if ($msUpdated && $ourUpd && strtotime($msUpdated) <= strtotime($ourUpd)) {
                $skipped++;
                continue;
            }
            $ourId = $existing[$idMs]['id'];
            if (!$dryRun) {
                $srSql = $srId ? $srId : 'NULL';
                $sql = "UPDATE loss SET
                    number         = " . nullOrStr($number, 64) . ",
                    moment         = " . ($moment ? "'" . e($moment) . "'" : 'NULL') . ",
                    applicable     = {$applicable},
                    salesreturn_id = {$srSql},
                    sum_total      = {$sumTotal},
                    state_ms       = " . nullOrStr($stateMs, 36) . ",
                    description    = " . nullOrStr($descr) . ",
                    updated_at     = NOW()
                WHERE id = {$ourId}";
                Database::query('Papir', $sql);
                syncItems($ourId, $idMs, $productMap);
            }
            $updated++;
        } else {
            $uuid = generateUuid();
            if (!$dryRun) {
                $srSql = $srId ? $srId : 'NULL';
                $sql = "INSERT INTO loss
                    (uuid, id_ms, number, moment, applicable, salesreturn_id,
                     sum_total, state_ms, description)
                    VALUES (
                    '" . e($uuid) . "', '" . e($idMs) . "',
                    " . nullOrStr($number, 64) . ",
                    " . ($moment ? "'" . e($moment) . "'" : 'NULL') . ",
                    {$applicable}, {$srSql},
                    {$sumTotal},
                    " . nullOrStr($stateMs, 36) . ",
                    " . nullOrStr($descr) . ")";
                $res = Database::query('Papir', $sql);
                if ($res['ok']) {
                    syncItems($res['insert_id'], $idMs, $productMap);
                }
            }
            $inserted++;
            $existing[$idMs] = array('id' => 0, 'upd' => date('Y-m-d H:i:s'));
        }
    }

    out("offset={$offset} ins={$inserted} upd={$updated} skip={$skipped}");
    $offset += $batchSize;
    if (count($rMs['rows']) < $batchSize) break;
}

function syncItems($docId, $docIdMs, &$productMap) {
    $rMs = Database::fetchAll('ms',
        "SELECT product_id, quantity, price FROM loss_positions WHERE meta = '" . Database::escape('ms', $docIdMs) . "'");
    $msCount = $rMs['ok'] ? count($rMs['rows']) : 0;

    $rPapir = Database::fetchAll('Papir', "SELECT COUNT(*) AS cnt FROM loss_item WHERE loss_id = {$docId}");
    $papirCount = ($rPapir['ok'] && $rPapir['rows']) ? (int)$rPapir['rows'][0]['cnt'] : -1;

    if ($msCount === $papirCount) return;

    Database::query('Papir', "DELETE FROM loss_item WHERE loss_id = {$docId}");
    if ($msCount === 0 || !$rMs['ok']) return;

    $lineNo = 1;
    foreach ($rMs['rows'] as $pos) {
        $prodId    = isset($productMap[$pos['product_id']]) ? $productMap[$pos['product_id']] : null;
        $qty       = (float)$pos['quantity'];
        $price     = (float)$pos['price'];
        $sumRow    = round($qty * $price, 2);
        $prodSql   = $prodId ? $prodId : 'NULL';
        $prodMsSql = $pos['product_id'] ? "'" . Database::escape('Papir', $pos['product_id']) . "'" : 'NULL';

        Database::query('Papir',
            "INSERT INTO loss_item (loss_id, line_no, product_id, product_ms_id, quantity, price, sum_row)
             VALUES ({$docId}, {$lineNo}, {$prodSql}, {$prodMsSql}, {$qty}, {$price}, {$sumRow})");
        $lineNo++;
    }
}

out("=== Готово: inserted={$inserted}, updated={$updated}, skipped={$skipped} ===");

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'");
}