<?php
if (!defined('ROOT')) { die; }
#_die();
#$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://slotfruits.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = "okhttp/4.12.0";
    
    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
} ) ($login, $ip, $host);





while (true) {
    $dash = null;
    
    do {
        
        
        
        
        
    die;
    } while (empty($dash))
    
    
    
die;
}