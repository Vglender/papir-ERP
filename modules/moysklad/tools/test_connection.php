<?php

require_once __DIR__ . '/../moysklad_api.php';

$ms = new MoySkladApi();

$link = $ms->getEntityBaseUrl() . 'product?limit=1';
$result = $ms->query($link);

echo '<pre>';
print_r($result);
echo '</pre>';