<?php
/**
 * Синк повернень покупця: ms.salesreturn + ms.salesreturn_positions
 *   → Papir.salesreturn + salesreturn_item
 *
 * Запуск:
 *   php scripts/sync_ms_salesreturn.php --dry-run
 *   php scripts/sync_ms_salesreturn.php
 */

$_lockFp = fopen('/tmp/sync_ms_salesreturn.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) {
    echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL;
    exit(0);
}

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_salesreturn.log';
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
        'title'    => 'Синк повернень покупця з МойСклад',
        'script'   => 'scripts/sync_ms_salesreturn.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ПОВЕРНЕНЬ ПОКУПЦЯ ===');

// ── Maps ──────────────────────────────────────────────────────────────────────
out('Завантаження map...');

$cpMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM counterparty WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $cpMap[$row['id_ms']] = (int)$row['id'];
out('Контрагентів: ' . count($cpMap));

// demand map: id_ms → id
$demandMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM demand WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $demandMap[$row['id_ms']] = (int)$row['id'];
out('Відвантажень: ' . count($demandMap));

// product map: id_ms → product_id
$productMap = array();
$r = Database::fetchAll('Papir', "SELECT product_id, id_ms FROM product_papir WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $productMap[$row['id_ms']] = (int)$row['product_id'];
out('Товарів: ' . count($productMap));

// existing salesreturn map: id_ms → {id, updated_at}
$existing = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms, updated_at FROM salesreturn");
if ($r['ok']) foreach ($r['rows'] as $row) $existing[$row['id_ms']] = array('id' => (int)$row['id'], 'upd' => $row['updated_at']);
out('В Papir вже є: ' . count($existing));

// ── Основний цикл ─────────────────────────────────────────────────────────────
$inserted = $updated = $skipped = 0;
$offset = 0;

while (true) {
    $rMs = Database::fetchAll('ms',
        "SELECT id, meta, name, moment, applicable, sum, agent, demand, payedSum, state, description, updated
         FROM salesreturn
         ORDER BY id
         LIMIT {$batchSize} OFFSET {$offset}");

    if (!$rMs['ok'] || empty($rMs['rows'])) break;

    foreach ($rMs['rows'] as $ms) {
        $idMs = (string)$ms['meta'];
        if (!$idMs) continue;

        $cpId      = isset($cpMap[$ms['agent']])  ? $cpMap[$ms['agent']]  : null;
        $demandId  = isset($demandMap[$ms['demand']]) ? $demandMap[$ms['demand']] : null;
        $sumTotal  = (float)$ms['sum'];
        $sumPaid   = (float)$ms['payedSum'];
        $stateMs   = $ms['state'];
        $descr     = $ms['description'];
        $moment    = $ms['moment'];
        $applicable = (int)$ms['applicable'];
        $number    = $ms['name'];
        $msUpdated = $ms['updated'];

        if (isset($existing[$idMs])) {
            // UPDATE якщо ms.updated новіший
            $ourUpd = $existing[$idMs]['upd'];
            if ($msUpdated && $ourUpd && strtotime($msUpdated) <= strtotime($ourUpd)) {
                $skipped++;
                continue;
            }
            $ourId = $existing[$idMs]['id'];
            if (!$dryRun) {
                $cpSql     = $cpId     ? $cpId     : 'NULL';
                $demandSql = $demandId ? $demandId : 'NULL';
                $sql = "UPDATE salesreturn SET
                    number       = " . nullOrStr($number, 64) . ",
                    moment       = " . ($moment ? "'" . e($moment) . "'" : 'NULL') . ",
                    applicable   = {$applicable},
                    counterparty_id = {$cpSql},
                    demand_id    = {$demandSql},
                    sum_total    = {$sumTotal},
                    sum_paid     = {$sumPaid},
                    state_ms     = " . nullOrStr($stateMs, 36) . ",
                    description  = " . nullOrStr($descr) . ",
                    updated_at   = NOW()
                WHERE id = {$ourId}";
                Database::query('Papir', $sql);

                // Оновити позиції
                syncItems('salesreturn', $ourId, $idMs, $productMap);
            }
            $updated++;
        } else {
            // INSERT
            $uuid  = generateUuid();
            if (!$dryRun) {
                $cpSql     = $cpId     ? $cpId     : 'NULL';
                $demandSql = $demandId ? $demandId : 'NULL';
                $sql = "INSERT INTO salesreturn
                    (uuid, id_ms, number, moment, applicable, counterparty_id, demand_id,
                     sum_total, sum_paid, state_ms, description, source, sync_state)
                    VALUES (
                    '" . e($uuid) . "', '" . e($idMs) . "',
                    " . nullOrStr($number, 64) . ",
                    " . ($moment ? "'" . e($moment) . "'" : 'NULL') . ",
                    {$applicable}, {$cpSql}, {$demandSql},
                    {$sumTotal}, {$sumPaid},
                    " . nullOrStr($stateMs, 36) . ",
                    " . nullOrStr($descr) . ",
                    'moysklad', 'synced')";
                $res = Database::query('Papir', $sql);
                if ($res['ok']) {
                    $newId = $res['insert_id'];
                    syncItems('salesreturn', $newId, $idMs, $productMap);
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

function syncItems($docTable, $docId, $docIdMs, &$productMap) {
    $itemTable = $docTable . '_item';
    $fkCol     = $docTable . '_id';

    // Кількість позицій в ms
    $rMs = Database::fetchAll('ms',
        "SELECT meta, product_id, quantity, price FROM {$docTable}_positions WHERE meta = '" . Database::escape('ms', $docIdMs) . "'");
    $msCount = $rMs['ok'] ? count($rMs['rows']) : 0;

    $rPapir = Database::fetchAll('Papir', "SELECT COUNT(*) AS cnt FROM {$itemTable} WHERE {$fkCol} = {$docId}");
    $papirCount = ($rPapir['ok'] && $rPapir['rows']) ? (int)$rPapir['rows'][0]['cnt'] : -1;

    if ($msCount === $papirCount) return; // без змін

    // Delete + reinsert
    Database::query('Papir', "DELETE FROM {$itemTable} WHERE {$fkCol} = {$docId}");
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
            "INSERT INTO {$itemTable} ({$fkCol}, line_no, product_id, product_ms_id, quantity, price, sum_row)
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