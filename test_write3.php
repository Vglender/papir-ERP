<?php
$logFile = '/var/www/papir/storage/test_hook.log';
$r1 = file_put_contents($logFile, date('H:i:s').' test3'.PHP_EOL, FILE_APPEND);
echo json_encode(array('r1'=>$r1, 'logFile'=>$logFile));
