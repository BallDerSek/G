<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, /*['mail', 'pass', 'PROXY']*/);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://claimcoin.in';
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
        $l = inf::check("$host/dashboard", [], '/register');
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
            #print_r($po); die;
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, [], "$host/login", inf::$uagent, ip: $ip);
            
            _put('ve.html', $ve); #die;
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
            if (!empty($alert_d)) {
                $msg = trim(strip_tags($alert_d[0]));
                logx('', $msg);
                if (stripos($msg, 'nvalid Captcha')) continue;
                die;
            }
            
            
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash); 
    
    if ($dash && str_contains($dash, 'confirm your email')) {
        $can_withdraw = false;
        die(Logger::X('err', 'confirm email dulu'));
    }
    
    if (!$limit && $claim) {
        $ret99 = 0; 
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            
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
            if (!empty($f)) {
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
                #_put('fau.html', $fau); die;
                
                if (!$SLDONE || !$ADDONE) break;
                
                styler('Waiting for faucet', fn() => _sle(10));
                continue;
            }
            
            if (!empty($po)) {
                $po['recaptchav3'] = $po['g-recaptcha-response'];
                $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, false, false, $ip);
                if (empty($cla) || ($cla === 99)) continue;
                
                #_put('cla.html', $cla); die;
                
                $err_d = scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
                if (!empty($err_d[0])) {
                    logm($mail);
                    logx('err', $err_d[0], true, true);
                    if (checkATB($atbfail, $err_d[0])) continue;
                }
                
                $m = Scraper::_jP($cla, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/") ?? null;
                if (isset($m[1][0])) {
                    Logm($mail);
                    logg(0, $m[1][0]);
                    $atbforce = false;
                    $atbfail = 0;
                    
                }
                #styler('Waiting for faucet', fn() => _sle(15));
            }
            
        }
    
    }
    
    
    $ads = Net::X("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    _put('ads.html', $ads);
    
    
    
    die;
    if (!empty($ads) && $ads !== 99) {
        $_ad = [];
        
        $uvv = Scraper::_xP($ads, "//div[@id='window']//button/@onclick | //div[@id='iframe']//button/@onclick");
        $tmr = Scraper::_xP($ads, "//div[@id='window']//span[contains(@class, 'badge-danger')] | //div[@id='iframe']//span[contains(text(),'seconds')]");
        
        
        $url_c = count($uvv);
        $tmr_c = count($tmr);
        
        for ($i = 0; $i < $url_c; $i++) {
            preg_match("/location\.href='([^']+)'/", $uvv[$i], $urlMatch);
            $url = $urlMatch[1] ?? '';
            
            $_ti = ($i < $tmr_c && trim($tmr[$i]) != '') ? trim($tmr[$i]) : '5 seconds';
            preg_match('/(\d+)/', $_ti, $timerMatch);
            $timer = $timerMatch[1] ?? '5';
            
            $_ad[] = [$url, $timer];
        }
        
        if (!empty($_ad[0]) && !$ADDONE) {
            for ($rrv = 0; $rrv < 2; $rrv++) {
                $cla = null;
                $view = null;
                
                $ad_u = $_ad[0][0];
                $ad_t = (int)$_ad[0][1];
                
                $view = Net::X($ad_u, 'GET', null, inf::$cookie, [], "$host/surf", inf::$uagent, false, false, $ip);
                #_put('view.html', $view); die;
                if ($view === 99) {
                    $ret99++;
                    if ($ret99 >= 5) continue 2;
                    continue;
                }
                
                if (!empty($view) && $view !== 99) {
                    $f = scraper::payload($view)[1] ?? [];
                    #print_r($f); die;
                    $po = null;
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cap = solve::exec($view, $ad_u, $api, $pa);
                        if (isset($cap['trouble'])) {
                            _sle(60);
                            continue;
                        }
                        $po = array_merge($pa, $cap);
                        
                    }
                    
                    if (!empty($po)) {
                        #print_r($po);
                        styler("waiting for ads: $ad_t", fn() => _sle($ad_t));
                        
                        $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, [], $ad_u, inf::$uagent, false, true, $ip);
                        #_put('cla.html', $cla); die;
                        
                        $suc_a = Scraper::_jP($cla, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/")[1][0] ?? null;
                        
                        if (!empty($suc_a)) {
                            logm($mail);
                            logg(1, $suc_a);
                            break;
                        }
                    }
                }
                
            }
            
        }
        
        if (empty($_ad)) $ADDONE = true;
        
    }
    
    
}


tes:





