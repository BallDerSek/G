<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['mail', 'pass', 'PROXY']);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://faucetpayz.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '';

(function ($mail, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($mail);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip, false, $mail);
    _cle();
    banner();
    taskPrintCenter($mail, 'info');
    print(UNDR.BOLD."site:");
    logx('ok', " $host");
})($mail, $ip, $host);

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
        $l = inf::check("$host/account", [], '/register', true);
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
        #var_dump($_0); die;
        $f = scraper::payload($_0)[0] ?? null;
        #print_r($f); die;
        $po = null;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['username' => $mail, 'password' => $pass];
            
            $cap = Solve::exec($_0, $host, $api, $pa);
            
            if (isset($cap['trouble'])) {
                $tro = $cap['trouble'];
                logx('warn', "Solver trouble: $tro");
                ($tro === 'proxy') ? _sle(30) : _sle(10);
                continue;
            }
            
            $po = array_merge($pa, $cap, $cre);
        }
        
        if (!empty($po)) {
            $ve = Net::C($host.'/login', 'POST', $po, inf::$cookie, [], "$host/login", inf::$uagent, false, false, $ip);
            #_put('ve.html', $ve); die;
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            $alert_d = Scraper::_jP($ve, '/type:\s*["\']([^"\']+)["\'],\s*message:\s*["\']([^"\']+)["\']/s');
            if (!empty($alert_d[2][0])) {
                $msg = $alert_d[2][0];
                logx('err', $msg);
                if (stripos($msg, 'nvalid Captcha')) continue;
                die;
            }
            
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash); die;
    
    if ($dash && str_contains($dash, 'confirm your email')) {
        $can_withdraw = false;
    }
    
    if (!$limit && $claim) {
        $ret99 = 0; 
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            
            #_put('fau.html', $fau); die;
            
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
            
            $po = null;
            $cap = [];
            $f = scraper::payload($fau)[0] ?? [];
            #print_r($f); die;
            if (empty($f)) {
                if (stripos($fau, '/register')) continue 2;
                
                if (!$ADDONE) break;
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
            }
            
            if (!empty($f['payload'])) {
                $pa = $f['payload'];
                
                if ($atbfail >= 3) $atbforce = true;
                $cap = solve::exec($fau, $host, $api, $pa, $atbforce);
                if (isset($cap['trouble'])) {
                    _sle(60);
                    continue;
                }
                
                $po = array_merge($pa, $cap);
            }
            
            if (!empty($po)) {
                #print_r($po);
                $cla = Net::C($host.'/faucet', 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, ip: $ip);
                if (empty($cla) || ($cla === 99)) continue;
                
                #_put('cla.html', $cla);
                
                if (stripos($cla, 'Invalid AntiBot')) $atbfail++;
                $m = Scraper::_jP($cla, '/type:\s*["\']([^"\']+)["\'],\s*message:\s*["\']([^"\']+)["\']/s');
                if (isset($m[2][0])) {
                    $stt = $m[1][0];
                    $is_ok = $stt === 'danger' ? 'err' : 'suc';
                    $msg = $m[2][0];
                    #print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logm($mail);
                    logx($is_ok, "$stt ", false, true);
                    logg(false, $msg);
                    
                    if (stripos($m[2][0], 'has been added')) {
                        $atbforce = false;
                        $atbfail = 0;
                        $setF = microtime(true);
                        break;
                    }
                    
                    if (stripos($msg, 'get back tomorrow')) {
                        $limit = true;
                        $claim = false; 
                        break;
                    }
                }
            }
            
            
        }
    }
    
    $ads = Net::C("$host/surf", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('ads.html', $ads);
    if (!empty($ads) && $ads !== 99) {
        $_ad = [];
        
        $uvv = Scraper::_xP($ads, "//a[contains(@href, '/surf/') and not(contains(@class, 'd-none'))]/@href");
        $tmr = Scraper::_xP($ads, "//a[not(contains(@class, 'd-none'))]//div[contains(@class, 'pill sec')]/text()[normalize-space()]");
        $url_c = count($uvv);
        $tmr_c = count($tmr);
        for ($i = 0; $i < $url_c; $i++) {
            $_ti = ($i < $tmr_c && trim($tmr[$i]) != '') ? trim($tmr[$i]) : '5s';
            $_ad[] = [$uvv[$i], $_ti];
        }
        
        #print_r($_ad); die;
        if (!empty($_ad[0]) && !$ADDONE) {
            
            for ($rrv = 0; $rrv < 2; $rrv++) {
                $cla = null;
                $view = null;
                
                $ad_u = $host.$_ad[0][0];
                $ad_t = (int)$_ad[0][1];
                
                $view = Net::C($ad_u, 'GET', null, inf::$cookie, [], "$host/surf", inf::$uagent, false, false, $ip);
                if ($view === 99) {
                    $ret99++;
                    if ($ret99 >= 5) continue 2;
                    continue;
                }
                #_put('view.html', $view);
                
                if (!empty($view) && $view !== 99) {
                    $set = microtime(true);
                    $uid = Scraper::find($view, 'uid', 'input', 'value', 'id')[0] ?? null;
                    $idd = Scraper::_pP($view, 'let id')[0] ?? null;
                    preg_match("/let count = (\d+)/", $view, $cnt);
                    if (isset($cnt[1])) $ad_t = (int)$cnt[1];
                    
                    $go = ['uid' => $uid,'c' => $idd.rand(1, 9999)];
                    Net::C("$host/surf", 'GET', $go, inf::$cookie, [], $ad_u, inf::$uagent);
                    
                    $po = null;
                    $cap = solve::exec($view, $host, $api);
                    if (isset($cap['trouble'])) continue;
                    $po = array_merge($go, $cap);
                    
                    $end = microtime(true) - $set;
                    $wait = (int)($ad_t - $end);
                    if ($wait > 0) styler("waiting for ads: $wait", fn() => _sle($wait));
                    
                    $cla = json_decode(Net::X("$host/ajax/surf", 'POST', $po, inf::$cookie, [], $ad_u, inf::$uagent)?: '', 1);
                    
                    #if (str_contains($cla, 'get back tomorrow')) $ADDONE = true;
                    
                    if (!empty($cla)) {
                        $stt = $cla['success'];
                        $is_ok = $stt === 0 ? 'error' : 'success';
                        $msg = $cla['message'];
                        
                        logm($mail);
                        logx($is_ok, "$is_ok ", false, true);
                        logg(0, $msg);
                        break;
                    }
                }
            }
            
        }
        if (empty($_ad)) $ADDONE = true;
    }
    
    if ($limit && $ADDONE) {
        $pa = null;
        $pa = scraper::payload($dash, 'makeWithdrawForm')[0]['payload'] ?? null;
        if (!empty($pa) && $pa['amount'] >= 1000) {
            
            $cre = ['address' => $mail];
            $po = array_merge($pa, $cre);
            
            $jjn = json_decode(Net::X("$host/ajax/withdraw", 'POST', $po, inf::$cookie, [], $host.'/dashboard', inf::$uagent)?: '', 1)["notify"] ?? null;
            #var_dump($jjn);
            if (!empty($jjn['success'])) {
                logm($mail);
                logg(0, $jjn['success']);
            }
        }
        
        die;
    }
    
}



tes:


