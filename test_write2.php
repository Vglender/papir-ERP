<?php
$r1 = file_put_contents('/var/www/papir/storage/test_web.txt', date('H:i:s').' test'.PHP_EOL, FILE_APPEND);
$r2 = error_get_last();
echo json_encode(array('r1'=>$r1, 'err'=>$r2));
