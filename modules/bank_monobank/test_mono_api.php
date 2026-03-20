<?php

require_once '/var/www/papir/modules/bank_monobank/monobank_api.php';

/* $mono = new MonobankApi('/var/www/papir/modules/bank_monobank/storage');
$clients = $mono->getAllClientsInfo();

print_r($clients);
 */
 
/*  $mono = new MonobankApi('/var/www/papir/modules/bank_monobank/storage');

$from = strtotime('2026-03-01 00:00:00');
$to   = strtotime('2026-03-13 23:59:59');

$statements = $mono->getAllStatements($from, $to);

print_r($statements); */


/* $mono = new MonobankApi('/var/www/papir/modules/bank_monobank/storage');

$statement = $mono->getStatement(
    'uZloeBJsHN8zezwiiTG257ugPP4qANIT4_evQUKhSrYM',
    '-F4Mx2f8ozj22B5u_0v-gA',
    strtotime('2026-03-01 00:00:00'),
    strtotime('2026-03-13 23:59:59')
);

print_r($statement); */
 $date_from = date('Y-m-d',strtotime("-5 days"));
 $mono = new MonobankApi('/var/www/papir/modules/bank_monobank/storage');

$result_mono = $mono->request_mono($date_from);

print_r($result_mono);
 
?>