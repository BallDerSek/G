<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent();



$host = 'https://cryptofuture.co.in';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=79';

banner(); 
login:
    
$dash = null;
while (true) {
    inf::$uagent = $userAgent;
    inf::$cookie = $cookieFile;
    solve::$uagent = $userAgent;
    solve::$cookie = $cookieFile;
    
    do {
        $l = inf::check("$host/dashboard", [], null, 'auth/login');
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            #var_dump($dash); die;
            break;
        }
        @unlink($cookieFile);
        $_0 = Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
        if (!empty($_0)) {
            $f = scraper::payload($_0)[0];
            print_r($f);
            $po = $f['payload'];
            $po['wallet'] = $login;
            Net::C($f['url'], 'POST', $po, $cookieFile, [], $host.$r, $userAgent);
        }
        
    } while (empty($dash));
    
    $earn = Net::C($host.'/earn', 'GET', null, $cookieFile, [], '', $userAgent);
    
    $_fa = Scraper::_xP($earn, "//a[contains(@class, 'btn-warning')]/@href");
    
    
    foreach ($_fa as $fa) {
        $coin = basename(parse_url($fa)['path']);
        logx('info', $coin);
        
        while (true) {
            $fau = Net::C($fa, 'GET', null, $cookieFile, [], $host.'/dashboard', $userAgent);
            _put('fau.html', $fau);
            
            $cap = solve::exec($fau, $host, null, $api);
            if ($cap === 'reload') continue;
            print_r($cap);
            
            
            $f = scraper::payload($fau)[0];
            print_r($f);
            $pa = $f['payload'];
            $po = array_merge($pa, $cap);
            print_r($po);
            
            $cla = Net::C($f['url'], 'POST', $po, $cookieFile, [], $fa, $userAgent);
            _put('cla.html', $cla);
        die;
        }
        
        
        
        
        
        
        
    die;
    }
    
    
    
    
    
    
    die;
}




