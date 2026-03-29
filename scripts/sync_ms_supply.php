<?php
/**
 * Синк оприходувань: ms.supply + ms.supply_positions
 *   → Papir.supply + supply_item
 *
 * Запуск:
 *   php scripts/sync_ms_supply.php --dry-run
 *   php scripts/sync_ms_supply.php
 */

$_lockFp = fopen('/tmp/sync_ms_supply.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) {
    echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL;
    exit(0);
}

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_supply.log';
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
        'title'    => 'Синк оприходувань з МойСклад',
        'script'   => 'scripts/sync_ms_supply.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ОПРИХОДУВАНЬ ===');

// ── Maps ──────────────────────────────────────────────────────────────────────
out('Завантаження map...');

$cpMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM counterparty WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $cpMap[$row['id_ms']] = (int)$row['id'];
out('Контрагентів: ' . count($cpMap));

// purchaseorder map: id_ms → id
$poMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM purchaseorder WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $poMap[$row['id_ms']] = (int)$row['id'];
out('Замовлень постачальника: ' . count($poMap));

$productMap = array();
$r = Database::fetchAll('Papir', "SELECT product_id, id_ms FROM product_papir WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $productMap[$row['id_ms']] = (int)$row['product_id'];
out('Товарів: ' . count($productMap));

$existing = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms, updated_at FROM supply");
if ($r['ok']) foreach ($r['rows'] as $row) $existing[$row['id_ms']] = array('id' => (int)$row['id'], 'upd' => $row['updated_at']);
out('В Papir вже є: ' . count($existing));

// ── Основний цикл ─────────────────────────────────────────────────────────────
$inserted = $updated = $skipped = 0;
$offset = 0;

while (true) {
    $rMs = Database::fetchAll('ms',
        "SELECT id, meta, name, moment, applicable, sum, agent, purchaseOrder, payedSum, state, updated
         FROM supply
         ORDER BY id
         LIMIT {$batchSize} OFFSET {$offset}");

    if (!$rMs['ok'] || empty($rMs['rows'])) break;

    foreach ($rMs['rows'] as $ms) {
        $idMs = (string)$ms['meta'];
        if (!$idMs) continue;

        $cpId    = isset($cpMap[$ms['agent']])          ? $cpMap[$ms['agent']]          : null;
        $poId    = isset($poMap[$ms['purchaseOrder']])  ? $poMap[$ms['purchaseOrder']]  : null;
        $sumTotal  = (float)$ms['sum'];
        $sumPaid   = (float)$ms['payedSum'];
        $stateMs   = $ms['state'];
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
                $cpSql = $cpId ? $cpId : 'NULL';
                $poSql = $poId ? $poId : 'NULL';
                $sql = "UPDATE supply SET
                    number           = " . nullOrStr($number, 64) . ",
                    moment           = " . ($moment ? "'" . e($moment) . "'" : 'NULL') . ",
                    applicable       = {$applicable},
                    counterparty_id  = {$cpSql},
                    purchaseorder_id = {$poSql},
                    sum_total        = {$sumTotal},
                    sum_paid         = {$sumPaid},
                    state_ms         = " . nullOrStr($stateMs, 36) . ",
                    updated_at       = NOW()
                WHERE id = {$ourId}";
                Database::query('Papir', $sql);
                syncItems($ourId, $idMs, $productMap);
            }
            $updated++;
        } else {
            $uuid = generateUuid();
            if (!$dryRun) {
                $cpSql = $cpId ? $cpId : 'NULL';
                $poSql = $poId ? $poId : 'NULL';
                $sql = "INSERT INTO supply
                    (uuid, id_ms, number, moment, applicable, counterparty_id, purchaseorder_id,
                     sum_total, sum_paid, state_ms)
                    VALUES (
                    '" . e($uuid) . "', '" . e($idMs) . "',
                    " . nullOrStr($number, 64) . ",
                    " . ($moment ? "'" . e($moment) . "'" : 'NULL') . ",
                    {$applicable}, {$cpSql}, {$poSql},
                    {$sumTotal}, {$sumPaid},
                    " . nullOrStr($stateMs, 36) . ")";
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
        "SELECT product_id, quantity, price FROM supply_positions WHERE meta = '" . Database::escape('ms', $docIdMs) . "'");
    $msCount = $rMs['ok'] ? count($rMs['rows']) : 0;

    $rPapir = Database::fetchAll('Papir', "SELECT COUNT(*) AS cnt FROM supply_item WHERE supply_id = {$docId}");
    $papirCount = ($rPapir['ok'] && $rPapir['rows']) ? (int)$rPapir['rows'][0]['cnt'] : -1;

    if ($msCount === $papirCount) return;

    Database::query('Papir', "DELETE FROM supply_item WHERE supply_id = {$docId}");
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
            "INSERT INTO supply_item (supply_id, line_no, product_id, product_ms_id, quantity, price, sum_row)
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