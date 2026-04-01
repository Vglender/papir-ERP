<?php
require_once __DIR__ . '/novaposhta_bootstrap.php';

$activeNav = 'logistics';
$subNav    = 'np-senders';
$title     = 'НП · Відправники';

$senders = \Papir\Crm\SenderRepository::getAll();

// Attach contacts and addresses to each sender
foreach ($senders as &$s) {
    $s['contacts']  = \Papir\Crm\SenderRepository::getContacts($s['Ref']);
    $s['addresses'] = \Papir\Crm\SenderRepository::getAddresses($s['Ref']);
}
unset($s);

require_once __DIR__ . '/../shared/layout.php';
require_once __DIR__ . '/views/senders.php';
require_once __DIR__ . '/../shared/layout_end.php';
