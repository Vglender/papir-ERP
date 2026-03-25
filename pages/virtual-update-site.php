<?php

require_once __DIR__ . '/../modules/database/database.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function logLine(&$messages, $text, $type = 'info')
{
    $messages[] = array(
        'type' => $type,
        'text' => $text,
    );
}

function updateProductQuantityFromMS(&$messages)
{
    /** 1) Забираем все off-товары из product_site (site_id=1) **/
    $mysqli_papir = connectbd('Papir');

    $sqlPapir = "SELECT `site_product_id`
                 FROM `product_site`
                 WHERE `site_id` = 1
                   AND `site_product_id` > 0";

    $resPapir = $mysqli_papir->query($sqlPapir);

    if (!$resPapir) {
        logLine($messages, 'Ошибка чтения product_site: ' . $mysqli_papir->error, 'error');
        $mysqli_papir->close();
        return;
    }

    $siteProductIds = array();

    while ($row = $resPapir->fetch_assoc()) {
        $pid = (int)$row['site_product_id'];
        if ($pid > 0) {
            $siteProductIds[$pid] = $pid;
        }
    }

    $mysqli_papir->close();

    logLine($messages, 'Товаров из product_site (off) для обновления: ' . count($siteProductIds), 'success');

    if (empty($siteProductIds)) {
        logLine($messages, 'В product_site не найдено off-товаров.', 'error');
        return;
    }

    /** 2) Забираем остатки из MS: real + virtual **/
    $mysqli_ms = connectbd('ms');

    $sqlMs = "SELECT
                t.product_id,
                COALESCE(s.real_qty, 0) AS real_qty,
                COALESCE(v.virtual_qty, 0) AS virtual_qty,
                (COALESCE(s.real_qty, 0) + COALESCE(v.virtual_qty, 0)) AS total_qty
              FROM (
                  SELECT CAST(`model` AS UNSIGNED) AS product_id
                  FROM `stock_`
                  WHERE CAST(`model` AS UNSIGNED) > 0

                  UNION

                  SELECT `product_id`
                  FROM `virtual`
                  WHERE `product_id` > 0
              ) t
              LEFT JOIN (
                  SELECT
                      CAST(`model` AS UNSIGNED) AS product_id,
                      SUM(COALESCE(`stock`, 0)) AS real_qty
                  FROM `stock_`
                  WHERE CAST(`model` AS UNSIGNED) > 0
                  GROUP BY CAST(`model` AS UNSIGNED)
              ) s
                  ON s.product_id = t.product_id
              LEFT JOIN (
                  SELECT
                      `product_id`,
                      SUM(COALESCE(`stock`, 0)) AS virtual_qty
                  FROM `virtual`
                  WHERE `product_id` > 0
                  GROUP BY `product_id`
              ) v
                  ON v.product_id = t.product_id";

    $resMs = $mysqli_ms->query($sqlMs);

    if (!$resMs) {
        logLine($messages, 'Ошибка чтения данных из MS: ' . $mysqli_ms->error, 'error');
        $mysqli_ms->close();
        return;
    }

    $msQtyMap = array();

    while ($row = $resMs->fetch_assoc()) {
        $pid = (int)$row['product_id'];
        if ($pid > 0) {
            $msQtyMap[$pid] = (int)$row['total_qty'];
        }
    }

    $mysqli_ms->close();

    logLine($messages, 'Получено товаров с остатками из MS: ' . count($msQtyMap), 'success');

    /** 3) Собираем финальную карту обновления:
     *  - если товар есть в MS -> ставим total_qty
     *  - если товара в MS нет -> ставим 0
     **/
    $updateMap = array();

    foreach ($siteProductIds as $pid) {
        $updateMap[$pid] = isset($msQtyMap[$pid]) ? (int)$msQtyMap[$pid] : 0;
    }

    logLine($messages, 'Итоговое количество товаров к обновлению на сайте: ' . count($updateMap), 'success');

    if (empty($updateMap)) {
        logLine($messages, 'Нет данных для обновления сайта.', 'error');
        return;
    }

    /** 4) Обновляем quantity в OFF **/
    $mysqli_off = connectbd('off');

    $chunkSize = 600;
    $productIds = array_keys($updateMap);
    $total = count($productIds);

    $batchOk = 0;
    $batchErr = 0;

    for ($i = 0; $i < $total; $i += $chunkSize) {
        $chunk = array_slice($productIds, $i, $chunkSize);

        $case = "CASE `product_id` ";
        $in = array();

        foreach ($chunk as $pid) {
            $pid = (int)$pid;
            $qty = (int)$updateMap[$pid];

            $case .= "WHEN {$pid} THEN {$qty} ";
            $in[] = $pid;
        }

        $case .= "END";
        $inList = implode(',', $in);

        $upd = "UPDATE `oc_product`
                SET `quantity` = {$case}
                WHERE `product_id` IN ({$inList})";

        if (!$mysqli_off->query($upd)) {
            $batchErr++;
            logLine($messages, 'Ошибка batch: ' . $mysqli_off->error, 'error');
        } else {
            $batchOk++;
        }
    }

    $mysqli_off->close();

    logLine($messages, 'Обновлено товаров: ' . $total, 'success');
    logLine($messages, 'Успешных batch: ' . $batchOk, 'success');
    logLine($messages, 'Ошибочных batch: ' . $batchErr, $batchErr > 0 ? 'error' : 'success');
}

$messages = array();

updateProductQuantityFromMS($messages);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Обновление остатков сайта</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
        }
        .wrap {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border: 1px solid #d9e0ea;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        h1 {
            margin-top: 0;
        }
        .line {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .info {
            background: #eef4ff;
            color: #1f4db8;
        }
        .success {
            background: #edfdf3;
            color: #157347;
        }
        .error {
            background: #fff1f1;
            color: #9b1c1c;
        }
        .btn-row {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #c8d1dd;
            background: #fff;
            color: #222;
            text-decoration: none;
        }
        .btn-primary {
            background: #1f6feb;
            border-color: #1f6feb;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Обновление остатков на сайте</h1>

        <?php foreach ($messages as $message) { ?>
            <div class="line <?php echo h($message['type']); ?>">
                <?php echo h($message['text']); ?>
            </div>
        <?php } ?>

        <div class="btn-row">
            <a href="/virtual" class="btn">Назад в dashboard</a>
            <a href="/virtual-update-site" class="btn btn-primary">Запустить ещё раз</a>
        </div>
    </div>
</div>
</body>
</html>