<?php
/**
 * Бекфіл delivery_method_id / payment_method_id для заказів з МойСклад (2025+).
 * Запуск: nohup php scripts/backfill_order_methods.php > /tmp/backfill_order_methods.log 2>&1 &
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/moysklad/moysklad_api.php';
require_once __DIR__ . '/../modules/customerorder/MsAttributesParser.php';

$logFile = '/tmp/backfill_order_methods.log';
$myPid   = getmypid();

function blog($msg) {
    global $logFile;
    $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

// Реєстрація в моніторі
Database::insert('Papir', 'background_jobs', array(
    'title'    => 'Бекфіл доставки/оплати (2025+)',
    'script'   => 'scripts/backfill_order_methods.php',
    'log_file' => $logFile,
    'pid'      => $myPid,
    'status'   => 'running',
));

blog('Start. PID=' . $myPid);

$ms     = new MoySkladApi();
$parser = new MsAttributesParser();

// Вибираємо тільки ті де хоча б одне поле NULL
$rows = Database::fetchAll('Papir',
    "SELECT id, id_ms, delivery_method_id, payment_method_id
     FROM customerorder
     WHERE id_ms IS NOT NULL
       AND deleted_at IS NULL
       AND moment >= '2025-01-01'
       AND (delivery_method_id IS NULL OR payment_method_id IS NULL)
     ORDER BY id DESC"
);

if (!$rows['ok']) {
    blog('DB error, exit');
    Database::query('Papir', "UPDATE background_jobs SET status='failed', finished_at=NOW() WHERE pid={$myPid} AND status='running'");
    exit(1);
}

$total   = count($rows['rows']);
$updated = 0;
$skipped = 0;
$errors  = 0;
$i       = 0;

blog("Total to process: {$total}");

foreach ($rows['rows'] as $row) {
    $i++;
    $localId = (int)$row['id'];
    $idMs    = $row['id_ms'];

    // Отримуємо документ з МС (attributes завжди в тілі)
    $url = 'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/' . $idMs;
    $doc = $ms->query($url);
    $doc = json_decode(json_encode($doc), true);

    if (empty($doc) || !empty($doc['errors'])) {
        blog("[{$i}/{$total}] ERROR fetch id={$localId} ms={$idMs}");
        $errors++;
        continue;
    }

    // Парсимо атрибути (plain array або rows)
    if (!empty($doc['attributes']['rows']) && is_array($doc['attributes']['rows'])) {
        $attrs = $doc['attributes']['rows'];
    } elseif (!empty($doc['attributes']) && is_array($doc['attributes'])) {
        $attrs = $doc['attributes'];
    } else {
        $attrs = array();
    }

    $parsed = $parser->parse($attrs);

    $upd = array();
    if ($parsed['delivery_method_id'] !== null && $row['delivery_method_id'] === null) {
        $upd['delivery_method_id'] = $parsed['delivery_method_id'];
    }
    if ($parsed['payment_method_id'] !== null && $row['payment_method_id'] === null) {
        $upd['payment_method_id'] = $parsed['payment_method_id'];
    }

    if (empty($upd)) {
        $skipped++;
    } else {
        Database::update('Papir', 'customerorder', $upd, array('id' => $localId));
        $updated++;
        $fields = implode(', ', array_keys($upd));
        blog("[{$i}/{$total}] Updated id={$localId}: {$fields}");
    }

    if ($i % 500 === 0) {
        blog("Progress: {$i}/{$total} | updated={$updated} skipped={$skipped} errors={$errors}");
    }
}

blog("Done: total={$total} updated={$updated} skipped={$skipped} errors={$errors}");

Database::query('Papir',
    "UPDATE background_jobs SET status='done', finished_at=NOW() WHERE pid={$myPid} AND status='running'"
);
