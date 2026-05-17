<?php
if (!defined('ROOT')) { die; }
die('bloman jadi');
$api = onKeys();

$acc = config::credential([], false, ['mail', 'pass', 'PROXY']);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://bifaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '159.198.47.130';

(function ($mail, $ip) {
    Proxy::load();
    $cookieFile = config::cookie($mail);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($mail, 'info');
})($mail, $ip);



$_0 = Net::X($host."/login", 'GET', null, inf::$cookie, [], '', inf::$uagent, ip: $ip);
_put('0.html', $_0);

$dash = null;
$limit = false;
$shortlink = false;
$SLDONE = false;
$skipped = [];
$claim = false;
$can_withdraw = true;
while (true) {
    $max = 7;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check("$host/dashboard", [], '/register', true);
        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        
        if ($ret >= $max) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            exit; 
        }
        
        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::X("$host/login", 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip, false);
        #_put('0.html', $_0);
        if ($_0 === 99) {
            logx('warn', 'Proxy issue, wait 30s');
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        #var_dump($_0); die;
        $f = scraper::payload($_0)[0] ?? null;
        
        #print_r($f); #die;
        $po = null;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['email' => $mail, 'password' => $pass];
            
            $cap = Solve::exec($_0, $host, $api, $pa);
            
            if (isset($cap['trouble'])) {
                $tro = $cap['trouble'];
                logx('warn', "Solver trouble: $tro");
                ($tro === 'proxy') ? _sle(30) : _sle(10);
                continue;
            }
            $cleanCap = array_filter((array)$cap, fn($v, $k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_BOTH);
            
            $po = array_merge($pa, $cleanCap, $cre);
        }
        
        if (!empty($po)) {
            $ve = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/login", inf::$uagent, false, false, $ip);
            #_put('ve.html', $ve);
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
            if (!empty($alert_d)) {
                $msg = $alert_d[0];
                if (stripos($msg, 'nvalid Captcha')) continue;
                logx('', $msg);
                die;
            }
        }
        
    } while (empty($dash));
    _put('dash.html', $dash);
    
    
    
    
    
    
die;
}