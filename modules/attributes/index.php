<?php
require_once __DIR__ . '/attributes_bootstrap.php';


$groups   = AttributeRepository::getGroups();
$selected = isset($_GET['selected']) ? (int)$_GET['selected'] : 0;

require_once __DIR__ . '/views/index.php';
