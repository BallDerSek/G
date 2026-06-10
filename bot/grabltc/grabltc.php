<?php
if (!defined('ROOT')) { die; }
#_die();
#$api = onKeys();

$acc = config::credential([], false, /*['login', 'PROXY']*/);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://grabltc.com/CryptoHarvest';
$domain = parse_url($host, PHP_URL_HOST);
$r = '';
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

$hhh = ['detail-hints: false'];
$_wd = 1000000;

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host/home.php", $hhh, 'loginForm');

        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        if ($ret >= 10) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            
            if (!empty($_0)) _put(__DIR__.'/lo.html', $_0);
            if (isset($ve) && !empty($ve)) _put(__DIR__.'/ve.html', $ve);
            
            exit; 
        }
        
        logx('err', "logging in ", false); 
        _sle(3); _clr();
        $_0 = Net::C($host.'/login.php', 'GET', null, inf::$cookie, $hhh, '', inf::$uagent);
        if (!empty($_0) && $_0 !== 99) {
            $po = [
                'website' => "", 'email' => $login, 'referral_code' => "FNL98HPJ"
            ];
            $ve = Net::C($host.'/login.php', 'POST', $po, inf::$cookie, $hhh, '', inf::$uagent);
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            if (!empty($ve) && $ve !== 99) {
                $_err = Scraper::_xP($ve, "//div[contains(@class, 'error')]//p[contains(@class, 'text-red')]/text()");
                
                if (!empty($_err[0])) {
                    logx('err', $_err[0]);
                    die;
                }
                
            }
        }
        
    } while (empty($dash));
    _put('dash.html', $dash);
    
    $_se = 'session_' . substr(bin2hex(random_bytes(5)), 0, 9);
    
    
    $bon = json_decode(Net::C($host."/DailyBonus/process_daily.php", 'GET', null, inf::$cookie, $hhh, '', inf::$uagent)?: '', 1);
    if (!empty($bon['status'])) logx($bon['status'], $bon['message']);
    
    /*
    $_fa = [
        'HourlyFaucet' => 'process_hourly',
        'BonusFaucet' => 'process_bonus'
    ];
    foreach ($_fa as $fa => $fe) {
        $u_fau = $host . "/{$fa}/" . strtolower($fa) . ".php";
        $fau = Net::C($u_fau, 'GET', null, inf::$cookie, $hhh, '', inf::$uagent);
        if (!empty($fau) && $fau !== 99) {
            preg_match('/secondsLeft\s*=\s*(\d+)/', $fau, $_s);
            
            $sec = (int)($_s[1] ?? 10);
            if ($sec <= 10) {
                _sle(10);
                $u_cla = $host . "/{$fa}/{$fe}.php";
                $cla = json_decode(Net::C($u_cla, 'GET', null, inf::$cookie, $hhh, '', inf::$uagent)?: '', true);
                var_dump($cla);
                if (!empty($cla['status'])) {
                    $stt = $cla['status'];
                    $msg = isset($cla['total_reward']) ? "claimed {$cla['total_reward']}" : ($cla['message'] ?? 'Unknown');
                    logx($stt, "$fa ", false, true);
                    logg(false, "$msg");
                    
                    
                }
            }
        }
    }
    */
    
    
    $fau = Net::C($host.'/SpinFaucet/spinfaucet.php', 'GET', null, inf::$cookie, $hhh, '', inf::$uagent);
    _put('fau.html', $fau);
    
    
    
die;
}





tes:
    

