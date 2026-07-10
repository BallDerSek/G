<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://vipfaucet.de';
$domain = parse_url($host, PHP_URL_HOST);
$r = '';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, false, $login);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$login");
    $b->task2('ok', "site: $host");
    
} ) ($login, $ip, $host);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$claim = true;
$curr = '';
$habis = [];
$needSL = false;

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        @unlink(inf::$cookie);
        $_0 = Net::C($host, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        
        if ($ret >= 10) die;
        if ($_0 === 99) {
            Logger::X('warn', 'Proxy issue, wait 30s');
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        
        $f = scraper::payload($_0)[0] ?? null;
        if (!empty($f)) {
            $po = array_merge($f['payload'], ['user' => $login]);
            $zer_u = $f['url'] .'?'. http_build_query($po);
            
        }
        
    } while (empty($zer_u));
    
    if (!empty($zer_u)) {
        $setF = microtime(true);
        $zera = new Zera($host, $api, $login);
        $zerads = $zera->exec($zer_u);
        if (($zerads === 'claim') && $claim) $zera->cleanup();
        
    }
    
    styler('cooldown', fn() => _sle(100));
    
}