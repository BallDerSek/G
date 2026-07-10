<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, /*['mail', 'pass', 'PROXY']*/);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://earnsolana.xyz';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '';

(function ($mail, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $c = config::credential(['ua' => fn() => config::uagent('desktop')]);
    $userAgent = $c['ua'];
    
    inf::setup($userAgent, $cookieFile, $ip, false, $mail);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$mail");
    $b->task2('ok', "site: $host");
    
} ) ($mail, $ip, $host);

$headersCF = [];
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
        $l = inf::check("$host/dashboard", $headersCF, '/register');
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
        $_0 = Net::X("$host/login", 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent, false, false, $ip);
        var_dump($_0); die;
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
            print_r($f); die;
            
                $cf = Net::C($f['url'], 'GET', null, inf::$cookie, $headersCF, "$host/login", inf::$uagent, d: true);
                $cff = checkCF($f['url'], $api, $cf, $headersCF);
                var_dump($cff);
                if (empty($cff['html'])) {
                    continue;
                } else {
                    $headersCF = $cff['head'];
                    $html = $cff['html'];
                }
            
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
            #print_r($po);
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, "$host/login", inf::$uagent, ip: $ip);
            
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
            $fau = Net::X("$host/faucet", 'GET', null, inf::$cookie, $headersCF, "$host/dashboard", inf::$uagent, false, false, $ip);
            
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
            
            $f = scraper::payload($fau)[0] ?? [];
            #print_r($f); die;
            
            $po = null;
            if (!empty($f)) {
                $pa = $f['payload'];
                
                check:
                $cf = Net::C($f['url'], 'GET', null, inf::$cookie, $headersCF, "$host/faucet", inf::$uagent, d: true);
                $cff = checkCF($f['url'], $api, $cf, $headersCF);
                if (empty($cff['html'])) {
                    continue;
                } else {
                    $headersCF = $cff['head'];
                    $html = $cff['html'];
                }
                
                if ($atbfail >= 3) $atbforce = true;
                $cap = solve::exec($fau, $host, $api, $pa, $atbforce);
                
                if (isset($cap['trouble'])) {
                    _sle(60);
                    continue;
                }
                $po = array_merge($pa, $cap);
                
            } else {
                if (empty($f)) {
                    if (stripos($fau, '/register')) continue 2;
                    
                    if (str_contains($fau, 'Daily limit reached')) {
                        $limit = true;
                        break;
                    }
                    
                    continue;
                }
            }
            
            if (!empty($po)) {
                #print_r($po); die;
                $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, "$host/faucet", inf::$uagent, false, true, $ip);
                _put('cla.html', $cla); die;
                if (empty($cla) || ($cla === 99)) continue;
                
                $suc_d = scraper::_xP($cla, "//div[contains(@class, 'alert-success')]");
                $err_d = scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
                
                if (checkATB($atbfail, $cla)) continue;
                
                if (!empty($err_d[0])) {
                    logm($mail);
                    logx('err', $err_d[0], true, true);
                    if (str_contains($err_d[0], 'daily faucet limit')) {
                        $limit = true;
                        break;
                    }
                    
                }
                
                if (!empty($suc_d[0])) {
                    $msg = preg_replace('/^.*?Claim\s+Successful\s*\.\s*/i', '', $suc_d[0]);
                    logm($mail);
                    logx('success', 'success ', false, true);
                    logg(0, $msg);
                    $atbforce = false;
                    $atbfail = 0;
                }
                
                styler('Waiting for faucet', fn() => _sle(10));
            }
            
        }
        
        
    }
    
    $ads = Net::X("$host/ptc", 'GET', null, inf::$cookie, $headersCF, "$host/dashboard", inf::$uagent, false, false, $ip);
    _put('ads.html', $ads);
    
    
    
    
    die;
    if (!empty($ads) && $ads !== 99) {
        $_ad = [];
        $uvv = Scraper::_xP($ads, "//div[@id='window']//button[contains(@onclick, 'location.href')]/@onclick | //div[@id='iframe']//button[contains(@onclick, 'location.href')]/@onclick");
        $tmr = Scraper::_xP($ads, "//div[@id='window']//span[contains(text(),'seconds')] | //div[@id='iframe']//span[contains(text(),'seconds')]");
        $url_c = count($uvv);
        $tmr_c = count($tmr);
        for ($i = 0; $i < $url_c; $i++) {
            preg_match("/location\.href='([^']+)'/", $uvv[$i], $urlMatch);
            $url = $urlMatch[1] ?? '';
            $_ti = ($i < $tmr_c && trim($tmr[$i]) != '') ? trim($tmr[$i]) : '5s';
            preg_match('/(\d+)/', $_ti, $timerMatch);
            $timer = $timerMatch[1] ?? '5';
            $_ad[] = [$url, $timer];
        }
        
        #print_r($_ad); die;
        if (!empty($_ad[0]) && !$ADDONE) {
            for ($rrv = 0; $rrv < 2; $rrv++) {
                $cla = null;
                $view = null;
                
                $ad_u = $_ad[0][0];
                $ad_t = (int)$_ad[0][1];
                
                $view = Net::X($ad_u, 'GET', null, inf::$cookie, [], "$host/surf", inf::$uagent, false, false, $ip);
                if ($view === 99) {
                    $ret99++;
                    if ($ret99 >= 5) continue 2;
                    continue;
                }
                
                if (!empty($view) && $view !== 99) {
                    
                    $f = scraper::payload($view)[0] ?? [];
                    
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
                        styler("waiting for ads: $ad_t", fn() => _sle($ad_t));
                        
                        $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, [], $ad_u, inf::$uagent, false, true, $ip);
                        #_put('cla.html', $cla);
                        
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
    
    sl:
    $ret99 = 0;
    do {
        $sho = Net::C("$host/links", 'GET', null, inf::$cookie, $headersCF, "$host/dashboard", inf::$uagent, false, false, $ip);
        #_put('sl.html', $sho);
        if ($sho === 99) {
            $ret99++;
            logx('warn', "masalah proxy, warm up dulu");
            if ($ret99 >= 7) {
                goto login;
            }
            _sle(30);
            continue;
        }
        $ret99 = 0; 
        if (empty($sho)) continue;
        
        $short = sScraper::extract($sho);
        
        #print_r($short);
        
        if (empty($short) || stripos($sho, '/register')) break;
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix', 'oii.io', 'lnbz.la'];
        
        $can_process = false; 
        foreach ($short as $links => [$idd, $lmt]) {
            
            if (!limit($lmt) || isset($skipped_sho[$idd])) continue;
            
            $can_process = true;
            
            $ud = $host.'/links/go/'.$idd;
            $getVer = 0;
            
            while (true) {
                $get = Net::X($ud, 'GET', null, inf::$cookie, [], $host.'/links', inf::$uagent, ip: $ip, foll: false);
                if ($get === 99) {
                    $getVer++;
                    if ($getVer >= 5) goto login;
                    _sle(30);
                    continue;
                }
                if (!empty($get)) break;
            }
            
            preg_match('/location\.href\s*=\s*["\']([^"\']+)["\']/', $get, $match);
            $loc = $match[1] ?? '';
            
            if (!$loc) {
                $skipped_sho[$idd] = true;
                continue; 
            }
            
            $loc_u = parse_url($loc)['host'];
            $is_bl = false;
            foreach ($up as $blacklisted) {
                if (str_contains($loc_u, $blacklisted)) {
                    logx('warn', "Domain $blacklisted Skipping..");
                    $skipped_sho[$idd] = true;
                    $is_bl = true;
                    break; 
                }
            }
            if ($is_bl) {
                _sle(5);
                continue; 
            }
            
            logx('info', "Bypass: $loc", true, true);
            $bakk = links($api, $loc);
            
            if (!$bakk) {
                $skipped_sho[$idd] = true; 
                _sle(5);
                continue; 
            }
            
            styler("waiting for SL", fn() => _sle(100));
            
            $retVer = 0;
            while (true) {
                $ver = Net::C($bakk, 'GET', null, inf::$cookie, [], $loc, inf::$uagent, ip: $ip);
                if ($ver === 99) {
                    $retVer++;
                    if ($retVer >= 5) goto login;
                    _sle(30);
                    continue;
                }
                break;
            }
            
            if (!empty($ver)) {
                #_put('ver.html', $ver);
                if (stripos($ver, '/register')) goto login;
                
                $suc_s = Scraper::_jP($ver, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/")[1][0] ?? null;
                if (!empty($suc_s)) {
                    logm($mail);
                    logg(0, $suc_s);
                }
                
            }
            
            break 2;
        }
        
        if (!$can_process) {
            logx('info', "sl abis");
            $SLDONE = true;
        }
        
        
    } while (!$SLDONE);
    
    if ($limit && $SLDONE && $ADDONE) {
        $wd = Net::X("$host/withdraw", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
        
        $f = scraper::payload($wd)[0] ?? null;
        $po = null;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['wallet' => $mail];
            
            $cap = Solve::exec($wd, $host, $api, $pa);
            
            if (isset($cap['trouble'])) continue;
            $cleanCap = array_filter((array)$cap, fn($v, $k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_BOTH);
            
            $po = array_merge($pa, $cleanCap, $cre);
        }
        
        if ($po && $po['amount'] >= 5000) {
            $jjn = Net::X($f['url'], 'POST', $po, inf::$cookie, [], "$host/withdraw", inf::$uagent, ip: $ip);
        }
        
        logm($mail);
        (logx('err', 'beres') ?: die);
        
        
    }
    
    
    
}

tes:





function checkCF($url, $api, $body = null, $headersCF = []) {
    
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    
    if (!$html || !$code) return [];
    
    if ($code !== 200 && (stripos($html, 'Just a moment') !== false || stripos($html, 'Attention Required!') !== false)) {
        
        $cf = Cloudflare::exec($api, $url, inf::$cookie, inf::$uagent, ['html' => $html]);
        
        if ($cf) {
            [$headersCF, $ua] = $cf;
            inf::setup($ua, inf::$cookie);
            
            if (!empty($headersCF)) {
                for ($try = 1; $try <= 3; $try++) {
                    _sle(3);
                    $fix = Net::X($url, 'GET', null, inf::$cookie, $headersCF, $url, inf::$uagent, d: true);
                    
                    if (!empty($fix) && isset($fix['http_code'])) {
                        $_c = $fix['http_code'];
                        $_b = $fix['body'];
                        
                        if ($_c === 200 && stripos($_b, 'Just a moment') === false && stripos($_b, 'Attention Required!') === false) {
                            
                            config::credential()['ua'] = $ua;
                            return ['html' => $_b, 'head' => $headersCF];
                        }
                    }
                    logx('info', "try-{$try} fail, reloading");
                }
            }
        }
    } else {
        return ['html' => $html, 'head' => $headersCF];
    }
    
    return [];
}
