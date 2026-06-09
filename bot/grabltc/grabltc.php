<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://grabltc.com/CryptoHarvest';
$domain = parse_url($host, PHP_URL_HOST);
$r = 'FNL98HPJ';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = "CryptoHarvestApp/1.0_13.02.2025//JK";
    
    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
} ) ($login, $ip, $host);










