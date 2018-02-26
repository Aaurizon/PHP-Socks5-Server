<?php // example.php

include_once __DIR__.'/vendor/autoload.php';

$server = new \Aaurizon\ProxyServer\Socks5Server();

$server->run();
