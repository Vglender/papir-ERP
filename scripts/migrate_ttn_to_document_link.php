<?php
/**
 * Одноразова міграція: заповнити document_link записами для TTN.
 * ttn_novaposhta → from_type='ttn_np', ttn_ukrposhta → from_type='ttn_up'
 *
 * Запуск:
 *   nohup php scripts/migrate_ttn_to_document_link.php > /tmp/migrate_ttn.log 2>&1 &
 */
require_once __DIR__ . '/../modules/database/database.php';

$dryRun  = in_array('--dry-run', $argv);
$logFile = '/tmp/migrate_ttn.log';
$myPid   = getmypid();

function logMsg($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

logMsg('Старт' . ($dryRun ? ' (dry-run)' : '') . ', pid=' . $myPid);

if (!$dryRun) {
    Database::insert('Papir', 'background_jobs', array(
        'title'    => 'Міграція TTN → document_link',
        'script'   => 'scripts/migrate_ttn_to_document_link.php',
        'log_file' => $logFile,
        'pid'      => $myPid,
        'status'   => 'running',
    ));
}

// ── ttn_novaposhta ────────────────────────────────────────────────────────────
// from_ms_id = ref (НП UUID, завжди заповнений), to_ms_id = co.id_ms
// Захист від дублів: INSERT IGNORE по uq_ms_link(from_ms_id, to_ms_id)
logMsg('Вставляємо ttn_np...');

$sql = "INSERT IGNORE INTO document_link
            (from_type, from_id, from_ms_id, to_type, to_id, to_ms_id, created_at)
        SELECT 'ttn_np', tn.id, tn.ref,
               'customerorder', tn.customerorder_id, co.id_ms,
               NOW()
        FROM ttn_novaposhta tn
        JOIN customerorder co ON co.id = tn.customerorder_id
        WHERE tn.customerorder_id IS NOT NULL
          AND (tn.deletion_mark IS NULL OR tn.deletion_mark = 0)
          AND tn.ref IS NOT NULL";

if ($dryRun) {
    $r = Database::fetchRow('Papir', "SELECT COUNT(*) AS cnt
        FROM ttn_novaposhta tn
        JOIN customerorder co ON co.id = tn.customerorder_id
        WHERE tn.customerorder_id IS NOT NULL
          AND (tn.deletion_mark IS NULL OR tn.deletion_mark = 0)
          AND tn.ref IS NOT NULL");
    logMsg('[dry-run] ttn_np кандидатів: ' . (int)$r['row']['cnt']);
} else {
    $r = Database::query('Papir', $sql);
    logMsg('ttn_np: вставлено ' . ($r['ok'] ? $r['affected_rows'] : 'ERR: ' . print_r($r, true)));
}

// ── ttn_ukrposhta ─────────────────────────────────────────────────────────────
// from_ms_id = uuid (УП UUID). 45 записів без uuid — пропускаємо (WHERE uuid IS NOT NULL)
logMsg('Вставляємо ttn_up...');

$sql = "INSERT IGNORE INTO document_link
            (from_type, from_id, from_ms_id, to_type, to_id, to_ms_id, created_at)
        SELECT 'ttn_up', tu.id, tu.uuid,
               'customerorder', tu.customerorder_id, co.id_ms,
               NOW()
        FROM ttn_ukrposhta tu
        JOIN customerorder co ON co.id = tu.customerorder_id
        WHERE tu.customerorder_id IS NOT NULL
          AND tu.uuid IS NOT NULL";

if ($dryRun) {
    $r = Database::fetchRow('Papir', "SELECT COUNT(*) AS cnt
        FROM ttn_ukrposhta tu
        JOIN customerorder co ON co.id = tu.customerorder_id
        WHERE tu.customerorder_id IS NOT NULL AND tu.uuid IS NOT NULL");
    logMsg('[dry-run] ttn_up кандидатів: ' . (int)$r['row']['cnt']);
} else {
    $r = Database::query('Papir', $sql);
    logMsg('ttn_up: вставлено ' . ($r['ok'] ? $r['affected_rows'] : 'ERR: ' . print_r($r, true)));
}

// ── Підсумок ──────────────────────────────────────────────────────────────────
$rCount = Database::fetchRow('Papir',
    "SELECT
       SUM(from_type='ttn_np') AS np_cnt,
       SUM(from_type='ttn_up') AS up_cnt
     FROM document_link
     WHERE from_type IN ('ttn_np','ttn_up')");
if ($rCount['ok'] && $rCount['row']) {
    logMsg('document_link підсумок: ttn_np=' . $rCount['row']['np_cnt'] . ', ttn_up=' . $rCount['row']['up_cnt']);
}

if (!$dryRun) {
    Database::query('Papir',
        "UPDATE background_jobs SET status='done', finished_at=NOW()
         WHERE pid={$myPid} AND status='running'");
}

logMsg('Готово.');