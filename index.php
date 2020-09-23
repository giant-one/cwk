<?php
include __DIR__ . '/Cwk/Autoloader.php';

$http_server = new Cwk\Http('http://0.0.0.0:2345');

$http_server->start();