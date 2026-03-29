<?php
/**
 * Синк замовлень постачальника: ms.purchaseorder + ms.purchaseorder_positions
 *   → Papir.purchaseorder + purchaseorder_item
 */

$_lockFp = fopen('/tmp/sync_ms_purchaseorder.lock', 'c');
if (!flock($_lockFp, LOCK_EX | LOCK_NB)) {
    echo date('[H:i:s] ') . 'Already running, exit.' . PHP_EOL;
    exit(0);
}

require_once __DIR__ . '/../modules/database/database.php';

$dryRun    = in_array('--dry-run', $argv);
$logFile   = '/tmp/sync_ms_purchaseorder.log';
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
        'title'    => 'Синк замовлень постачальника з МойСклад',
        'script'   => 'scripts/sync_ms_purchaseorder.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

out($dryRun ? '=== DRY RUN ===' : '=== СИНК ЗАМОВЛЕНЬ ПОСТАЧАЛЬНИКА ===');

$cpMap = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms FROM counterparty WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $cpMap[$row['id_ms']] = (int)$row['id'];
out('Контрагентів: ' . count($cpMap));

$productMap = array();
$r = Database::fetchAll('Papir', "SELECT product_id, id_ms FROM product_papir WHERE id_ms IS NOT NULL");
if ($r['ok']) foreach ($r['rows'] as $row) $productMap[$row['id_ms']] = (int)$row['product_id'];
out('Товарів: ' . count($productMap));

$existing = array();
$r = Database::fetchAll('Papir', "SELECT id, id_ms, updated_at FROM purchaseorder");
if ($r['ok']) foreach ($r['rows'] as $row) $existing[$row['id_ms']] = array('id' => (int)$row['id'], 'upd' => $row['updated_at']);
out('В Papir вже є: ' . count($existing));

$inserted = $updated = $skipped = 0;
$offset = 0;

while (true) {
    $rMs = Database::fetchAll('ms',
        "SELECT id, meta, name, moment, applicable, sum, agent, payedSum, shippedSum, waitSum,
                state, description, updated
         FROM purchaseorder ORDER BY id LIMIT {$batchSize} OFFSET {$offset}");

    if (!$rMs['ok'] || empty($rMs['rows'])) break;

    foreach ($rMs['rows'] as $ms) {
        $idMs = (string)$ms['meta'];
        if (!$idMs) continue;

        $cpId       = isset($cpMap[$ms['agent']]) ? $cpMap[$ms['agent']] : null;
        $sumTotal   = (float)$ms['sum'];
        $sumPaid    = (float)$ms['payedSum'];
        $sumShipped = (float)$ms['shippedSum'];
        $sumWait    = (float)$ms['waitSum'];
        $stateMs    = $ms['state'];
        $descr      = $ms['description'];
        $moment     = $ms['moment'];
        $applicable = (int)$ms['applicable'];
        $number     = $ms['name'];
        $msUpdated  = $ms['updated'];

        if (isset($existing[$idMs])) {
            $ourUpd = $existing[$idMs]['upd'];
            if ($msUpdated && $ourUpd && strtotime($msUpdated) <= strtotime($ourUpd)) {
                $skipped++; continue;
            }
            $ourId = $existing[$idMs]['id'];
            if (!$dryRun) {
                $cpSql = $cpId ? $cpId : 'NULL';
                Database::query('Papir', "UPDATE purchaseorder SET
                    number=".nullOrStr($number,64).", moment=".($moment?"'".e($moment)."'":'NULL').",
                    applicable={$applicable}, counterparty_id={$cpSql},
                    sum_total={$sumTotal}, sum_paid={$sumPaid}, sum_shipped={$sumShipped}, sum_wait={$sumWait},
                    state_ms=".nullOrStr($stateMs,36).", description=".nullOrStr($descr).", updated_at=NOW()
                    WHERE id={$ourId}");
                syncPoItems($ourId, $idMs, $productMap);
            }
            $updated++;
        } else {
            if (!$dryRun) {
                $cpSql = $cpId ? $cpId : 'NULL';
                $uuid  = generateUuid();
                $res   = Database::query('Papir', "INSERT INTO purchaseorder
                    (uuid,id_ms,number,moment,applicable,counterparty_id,sum_total,sum_paid,sum_shipped,sum_wait,state_ms,description)
                    VALUES ('".e($uuid)."','".e($idMs)."',".nullOrStr($number,64).",".($moment?"'".e($moment)."'":'NULL').",
                    {$applicable},{$cpSql},{$sumTotal},{$sumPaid},{$sumShipped},{$sumWait},".nullOrStr($stateMs,36).",".nullOrStr($descr).")");
                if ($res['ok']) syncPoItems($res['insert_id'], $idMs, $productMap);
            }
            $inserted++;
            $existing[$idMs] = array('id'=>0,'upd'=>date('Y-m-d H:i:s'));
        }
    }

    out("offset={$offset} ins={$inserted} upd={$updated} skip={$skipped}");
    $offset += $batchSize;
    if (count($rMs['rows']) < $batchSize) break;
}

function syncPoItems($docId, $docIdMs, &$productMap) {
    $rMs = Database::fetchAll('ms',
        "SELECT product_id, quantity, price FROM purchaseorder_positions WHERE meta='".Database::escape('ms',$docIdMs)."'");
    $msCount = $rMs['ok'] ? count($rMs['rows']) : 0;
    $rP = Database::fetchAll('Papir', "SELECT COUNT(*) AS cnt FROM purchaseorder_item WHERE purchaseorder_id={$docId}");
    if ($rP['ok'] && (int)$rP['rows'][0]['cnt'] === $msCount) return;
    Database::query('Papir', "DELETE FROM purchaseorder_item WHERE purchaseorder_id={$docId}");
    if (!$msCount) return;
    $ln = 1;
    foreach ($rMs['rows'] as $pos) {
        $pid = isset($productMap[$pos['product_id']]) ? $productMap[$pos['product_id']] : null;
        $qty = (float)$pos['quantity']; $price = (float)$pos['price']; $sum = round($qty*$price,2);
        Database::query('Papir',
            "INSERT INTO purchaseorder_item (purchaseorder_id,line_no,product_id,product_ms_id,quantity,price,sum_row)
             VALUES ({$docId},{$ln},".($pid?$pid:'NULL').",".($pos['product_id']?"'".Database::escape('Papir',$pos['product_id'])."'":'NULL').",{$qty},{$price},{$sum})");
        $ln++;
    }
}

out("=== Готово: inserted={$inserted}, updated={$updated}, skipped={$skipped} ===");
if (!$dryRun) {
    Database::query('Papir', "UPDATE background_jobs SET status='done', finished_at=NOW() WHERE pid={$myPid} AND status='running'");
}