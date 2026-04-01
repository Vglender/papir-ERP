<?php
$r1 = file_put_contents('/tmp/test_web_write.txt', date('H:i:s').' test'.PHP_EOL, FILE_APPEND);
$r2 = @file_put_contents('/tmp/ms_whk_debug.log', date('H:i:s').' web test'.PHP_EOL, FILE_APPEND);
echo json_encode(array('r1'=>$r1, 'r2'=>$r2, 'whoami'=>exec('whoami')));
