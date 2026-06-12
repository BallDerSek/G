<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://mix-crypto.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=gamamoch@gmail.com';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, false, $login);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
    print(UNDR.BOLD."site:");
    logx('ok', " $host");
} ) ($login, $ip, $host);

