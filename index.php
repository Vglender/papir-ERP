<?php
// index.php

require __DIR__ . '/vendor/autoload.php';

use Papir\Crm\Router;

$router = new Router();
$router->handleRequest($_SERVER['REQUEST_URI']);

