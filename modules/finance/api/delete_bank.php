<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../finance_bootstrap.php';
require_once __DIR__ . '/finance_ms_sync.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok' => false, 'error' => 'POST required'));
    exit;
}

$id  = isset($_POST['id'])  ? (int)$_POST['id']  : 0;
$ids = isset($_POST['ids']) ? trim($_POST['ids']) : '';

if ($id > 0) {
    // Читаем id_ms и direction до удаления
    $cur = Database::fetchRow('Papir', "SELECT id_ms, direction FROM finance_bank WHERE id = {$id} LIMIT 1");
    $r = Database::query('Papir', "DELETE FROM finance_bank WHERE id = {$id}");
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка видалення'));
        exit;
    }
    // Clean up document_link
    $dlType = ($cur['ok'] && $cur['row'] && $cur['row']['direction'] === 'in') ? 'paymentin' : 'paymentout';
    Database::query('Papir', "DELETE FROM document_link WHERE from_type='{$dlType}' AND from_id={$id}");

    $msErrors = array();
    if ($cur['ok'] && $cur['row'] && !empty($cur['row']['id_ms'])) {
        $msDel = finance_ms_delete($cur['row']['id_ms'], $cur['row']['direction']);
        if (!$msDel['ok']) $msErrors[] = $msDel['error'];
    }

    $resp = array('ok' => true, 'deleted' => $r['affected_rows']);
    if (!empty($msErrors)) $resp['ms_errors'] = $msErrors;
    echo json_encode($resp);
    exit;
}

if ($ids !== '') {
    $idList = array_values(array_filter(array_map('intval', explode(',', $ids))));
    if (empty($idList)) {
        echo json_encode(array('ok' => false, 'error' => 'Не вказані ID'));
        exit;
    }
    $inClause = implode(',', $idList);

    // Читаем id_ms и direction до удаления
    $curRows = Database::fetchAll('Papir',
        "SELECT id_ms, direction FROM finance_bank WHERE id IN ({$inClause}) AND id_ms IS NOT NULL AND id_ms != ''"
    );

    $r = Database::query('Papir', "DELETE FROM finance_bank WHERE id IN ({$inClause})");
    if (!$r['ok']) {
        echo json_encode(array('ok' => false, 'error' => 'Помилка видалення'));
        exit;
    }
    // Clean up document_link (paymentin/paymentout)
    Database::query('Papir', "DELETE FROM document_link WHERE from_type IN ('paymentin','paymentout') AND from_id IN ({$inClause})");

    $msErrors = array();
    if ($curRows['ok'] && !empty($curRows['rows'])) {
        foreach ($curRows['rows'] as $row) {
            $msDel = finance_ms_delete($row['id_ms'], $row['direction']);
            if (!$msDel['ok']) $msErrors[] = $msDel['error'];
        }
    }

    $resp = array('ok' => true, 'deleted' => $r['affected_rows']);
    if (!empty($msErrors)) $resp['ms_errors'] = $msErrors;
    echo json_encode($resp);
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'Не вказаний ID'));