<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$mail = $acc['mail'];
$pass = $acc['pass'];

$cookieFile = config::cookie($mail);
$userAgent = config::uagent('mobile');

$host = 'https://hatecoin.me';
$domain = parse_url($host, PHP_URL_HOST);
$ip = null;

inf::setup($userAgent, $cookieFile, $ip);

banner(); 
login:

$dash = null;
while (true) {
        
        do {
        $l = inf::check("$host/dashboard", [], '/register');
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            #var_dump($dash); die;
            break;
        }
        
        @unlink($cookieFile);
        taskPrintCenter('logging in', 'err');
        $_0 = Net::C("$host/login", 'GET', null, $cookieFile, [], '', $userAgent, false, false, $ip);
        if (empty($_0)) continue;
        #var_dump($_0); die;
        $f = scraper::payload($_0)[0];
        $pa = $f['payload'];
        
        $cap = null;
        $cap = solve::exec($_0, $host, $api);
        
        $cre = ['email' => $mail, 'password' => $pass];
        $po = array_merge($pa, $cap, $cre);
        #print_r($po);
        
        $ve = Net::C($f['url'], 'POST', $po, $cookieFile, [], "$host/login", $userAgent, false, false, $ip);
        _put('ve.html', $ve);
        $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
        if (!empty($alert_d)) {
            $msg = $alert_d[0]; 
            if (str_contains($msg, 'nvalid Credential')) {
                logx('err', $msg);
                #$register = true;
                #break 2;
            }
            logx('', $msg);
            die;
        }
        
    } while (empty($dash));
    
die;
}