<?php

require_once '/var/www/papir/modules/database/database.php';

$result = Database::fetchAll('Papir', "SELECT * FROM product_papir LIMIT 20");

if (!$result['ok']) {
    print_r($result['error']);
} else {
    print_r($result['rows']);
}


