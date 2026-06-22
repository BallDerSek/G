<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, /*['mail', 'pass', 'PROXY']*/);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://freeltc.online';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '';

(function ($mail, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($mail);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, false, $mail);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$mail");
    $b->task2('ok', "site: $host");
    
} ) ($mail, $ip, $host);

$hhh = inf::netHead(['uf' => md5($mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
$limit = false;
$claim = true;
$SLDONE = false;
$ADDONE = false;
$skipped = [];
$can_withdraw = true;
$atbforce = false;
$atbfail = 0;

while (true) {
    $dash = null;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check("$host/dashboard", $hhh, '/register');
        #var_dump($l); _rl('lanjut:  ');
        
        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        
        if ($ret >= 10) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            exit; 
        }
        
        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::C("$host/login", 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip);
        
        if ($_0 === 99) {
            logx('warn', 'Proxy issue, wait 30s');
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;

        $f = scraper::payload($_0)[0] ?? null;
        #_put('0.html', $_0);
        $po = null;
        if (!empty($f)) {
            #print_r($f); die;
            $pa = $f['payload'];
            $cre = ['uf' => md5($mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'email' => $mail, 'password' => $pass];
            $cap = Solve::exec($_0, $host, $api, $pa);
            
            if (isset($cap['trouble'])) {
                _sle(10);
                continue;
            }
            $po = array_merge($pa, $cap, $cre);
        }
        
        if (!empty($po)) {
            #print_r($po);
            
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $hhh, "$host/login", inf::$uagent, ip: $ip);
            
            #_put('ve.html', $ve); #die;
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
            
            if (!empty($alert_d)) {
                $msg = $alert_d[0];
                logx('', $msg);
                if (stripos($msg, 'nvalid Captcha')) continue;
                die;
            }
            
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash); 
    
    if (!$limit && $claim) {
        $ret99 = 0; 
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, inf::$cookie, $hhh, "$host/dashboard", inf::$uagent, false, false, $ip);
            
            #_put('fau.html', $fau); #die;
            
            if ($fau === 99) {
                $ret99++;
                logx('warn', "masalah proxy, warm up dulu");
                if ($ret99 >= 5) {
                    goto login;
                }
                _sle(30);
                continue;
            }
            
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            $f = scraper::payload($fau)[1] ?? [];
            #print_r($f); die;
            
            $po = null;
            if (!empty($f) && stripos($f['url'], 'faucet')) {
                #print_r($f);
                $pa = $f['payload'];
                
                if ($atbfail >= 3) $atbforce = true;
                $cap = solve::exec($fau, $host, $api, $pa, $atbforce);
                if (isset($cap['trouble'])) {
                    _sle(60);
                    continue;
                }
                $po = array_merge($pa, $cap);
                
                
                
                
            } else {
                if (stripos($fau, '/register')) continue 2;
                
                
                #if (!$SLDONE || $ADDONE) break;
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
            }
            
            if (!empty($po)) {
                #print_r($po); die;
                
                $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, $hhh, "$host/faucet", inf::$uagent, ip: $ip);
                if (empty($cla) || ($cla === 99)) continue;
                #_put('cla.html', $cla); #die;
                
                if (checkATB($atbfail, $cla)) continue;
                $m = Scraper::_jP($cla, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/") ?? null;
                if (isset($m[1][0])) {
                    Logm($mail);
                    logg(0, $m[1][0]);
                    
                    
                }
                
                
                
            }
            
        }
        
    }
    
    /*
    $offwall = Net::C("$host/offerwall/offerzono", 'GET', null, inf::$cookie, $hhh, "$host/dashboard", inf::$uagent, false, false, $ip);
    if (!empty($offwall) && $offwall !== 99) {
        #_put('off.html', $offwall);
        
        $zon_h = "https://offerzono.com";
        $offz_if = Scraper::_xP($offwall, "//iframe/@src");
        print_r($offz_if);
        
        $par = parse_url($offz_if[0])['query'];
        
        $_0 = Net::C($offz_if[0], 'GET', null, inf::$cookie, [], '', inf::$uagent);
        _put('0.html', $_0);
        
        $f = Scraper::payload($_0)[0] ?? null;
        print_r($f);
        
        
        if (!empty($f) && !empty($f['payload'])) {
            
            $pa = $f['payload'];
            $cap = solve::exec($_0, $offz_if[0], $api, $pa);
            if (isset($cap['trouble'])) continue;
            $po = array_merge($pa, $cap);
            
            
            $_1 = Net::C($f['url'], 'POST', $po, inf::$cookie, [], '', inf::$uagent);
            _put('1.html', $_1);
            
            $offz_PTC = Net::C($zon_h.'/offerwall/ptc?'.$par, 'POST', ['amount' => 10], inf::$cookie, [], '', inf::$uagent);
            _put('ptc.html', $offz_PTC);
            
            
            
            
            
            
            
            
        }
        
        
        
    }
    */
    
    
    
    
    
    
    
}





tes:
    
    
    
    