<?php
/**
 * Cron: close_scansheets.php
 * Closes (prints) open scan sheets for today via NP print URL.
 *
 * Crontab: 0 21 * * * php /var/www/papir/cron/close_scansheets.php >> /var/log/papir/close_scansheets.log 2>&1
 */
require_once __DIR__ . '/../modules/database/database.php';
require_once __DIR__ . '/../modules/novaposhta/novaposhta_bootstrap.php';

$myPid = getmypid();
$todaySql = date('Y-m-d');

echo '[' . date('Y-m-d H:i:s') . '] close_scansheets started (pid=' . $myPid . ')' . PHP_EOL;

\Database::insert('Papir', 'background_jobs', array(
    'title'    => 'Закриття реєстрів НП',
    'script'   => 'cron/close_scansheets.php',
    'log_file' => '/var/log/papir/close_scansheets.log',
    'pid'      => $myPid,
    'status'   => 'running',
));

$rSheets = \Database::fetchAll('Papir',
    "SELECT ss.Ref, ss.Number, ss.sender_ref
     FROM np_scan_sheets ss
     WHERE ss.status = 'open'
       AND DATE(ss.DateTime) = '{$todaySql}'");

if (!$rSheets['ok'] || empty($rSheets['rows'])) {
    echo '[' . date('Y-m-d H:i:s') . '] No open scan sheets for today, nothing to close' . PHP_EOL;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] Found ' . count($rSheets['rows']) . ' open scan sheets for today' . PHP_EOL;

    $closed = 0;
    $errors = 0;

    foreach ($rSheets['rows'] as $ss) {
        $sender = \Papir\Crm\SenderRepository::getByRef($ss['sender_ref']);
        if (!$sender || !$sender['api']) {
            echo '[' . date('Y-m-d H:i:s') . '] SKIP ' . $ss['Number'] . ' — no API key' . PHP_EOL;
            $errors++;
            continue;
        }

        $printUrl = 'https://my.novaposhta.ua/scanSheet/printScanSheet'
            . '/refs[]/' . $ss['Ref']
            . '/type/pdf'
            . '/apiKey/' . $sender['api'];

        $ch = curl_init($printUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isPdf = (strpos($body, '%PDF') === 0);

        if ($httpCode === 200 && $isPdf) {
            \Papir\Crm\ScanSheetRepository::save(array(
                'Ref'     => $ss['Ref'],
                'status'  => 'closed',
                'printed' => 1,
            ));
            $closed++;
            echo '[' . date('Y-m-d H:i:s') . '] Closed ' . $ss['Number'] . PHP_EOL;
        } else {
            $errors++;
            echo '[' . date('Y-m-d H:i:s') . '] FAIL ' . $ss['Number'] . ' — HTTP ' . $httpCode . ', PDF: ' . ($isPdf ? 'yes' : 'no') . PHP_EOL;
        }
    }

    echo '[' . date('Y-m-d H:i:s') . '] Done: closed=' . $closed . ', errors=' . $errors . PHP_EOL;
}

\Database::query('Papir',
    "UPDATE background_jobs SET status='done', finished_at=NOW()
     WHERE pid={$myPid} AND status='running'");

echo '[' . date('Y-m-d H:i:s') . '] close_scansheets finished' . PHP_EOL;