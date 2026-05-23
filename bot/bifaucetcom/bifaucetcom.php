<?php
if (!defined('ROOT')) { die; }
_die();
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
    Check::Geo();
    $cookieFile = config::cookie($mail);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($mail, 'info');
})($mail, $ip);

$dash = null;
$limit = false;
$shortlink = false;
$SLDONE = false;
$skipped = [];
$claim = false;
$can_withdraw = true;
while (true) {
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
        
        if ($ret >= 10) {
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
                logx('', $msg);
                if (stripos($msg, 'nvalid Captcha')) continue;
                die;
            }
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    #goto sl;
    if (stripos($dash, 'Please check your inbox or spam folder to confirm your account')) {
        $can_withdraw = false;
    }
    
    do {
        
        $ads = Net::C("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
        if ($ads === 99) { _sle(60); continue 2; }
        #_put('ptc.html', $ads);
        
        $_onclick = Scraper::_xP($ads, "//div[@id='local']//div[contains(@class, 'card')][.//button[@onclick]]//button/@onclick");
        $_tim = Scraper::_xP($ads, "//div[@id='local']//div[contains(@class, 'card')][.//button[@onclick]]//div[contains(@class, 'px-3')]/div[2]/div[contains(@class, 'fw-semibold')]");
        
        $url_list = array_map(fn($u) => explode("'", $u)[1] ?? null, $_onclick);
        $vurl = $url_list[0] ?? null;
        
        if ($vurl) {
            $cla = null;
            $tim = isset($_tim[0]) ? (int)preg_replace('/[^0-9]/', '', $_tim[0]) : 0;
            logx('info', "[ $vurl ]: ", false);
            logx('', $tim);
            
            $ret99 = 0;
            while (true) {
                $view = Net::C($vurl, 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip);
                if ($view === 99) {
                    $ret99++;
                    logx('warn', "masalah proxy, warm up dulu");
                    if ($ret99 >= 5) {
                        goto login;
                    }
                    _sle(30);
                    continue;
                }
                $ret99 = 0; 
                if (!empty($view)) {
                    #_put('view.html', $view);
                    $set = microtime(true);
                    $f = scraper::payload($view) ?? [];
                    if (!empty($f)) {
                        #print_r($f);
                        $pa = $f[0]['payload'] ?? [];
                        
                        $cap = [];
                        $cap = solve::exec($view, $host, $api);
                        if (isset($cap['trouble'])) {
                            _sle(60);
                            continue;
                        }
                        $po = array_merge($pa, $cap);
                        #print_r($po);
                        
                        if (!empty($po)) {
                            $end = microtime(true) - $set;
                            $wait = (int)($tim - $end);
                            if ($wait > 0) {
                                styler("waiting for ads: $wait", fn() => _sle($wait));
                            }
                            claim:
                            $cla = Net::X($f[0]['url'], 'POST', $po, inf::$cookie, [], $vurl, inf::$uagent, ip: $ip);
                            if (empty($cla)) goto claim;
                            if ($cla === 99) goto login;
                            break;
                            
                            
                        }
                        
                        
                    }
                }
            }
            if (!empty($cla)) {
                $m = scraper::_jP($cla, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                }
            }
        } else {
            logx('err', 'ptc habis');
            $claim = true;
            break;
        }
    } while (!$claim);

    $zer = Net::C("$host/zeradsptc/earn", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    if (empty($zer) && $zer !== 99) {
        $zer_u = Scraper::_xP($zer, "//a[@id='generateBtn']/preceding-sibling::a[1]/@href")[0] ?? '';
        if (!empty($zer_u)) {
            $zer = Net::X($zer_u, 'GET', null, inf::$cookie, [], "", inf::$uagent);
            if (!empty($zer) && $zer !== 99) {
                if (stripos($zer, 'solve captcha')) {
                    
                    $zerC_m = Scraper::_xP($zer, "//td[contains(text(), 'Click')]/following-sibling::td/img/@src | //font[contains(text(), 'Click')]/../following-sibling::td/img/@src") ?? '';
                    $zerC_o = Scraper::_xP($zer, "//a[contains(@href, 'scid=')]/@href");
                    $zerC_i = Scraper::_xP($zer, "//a[contains(@href, 'scid=')]/img/@src");
                    $zerC_p = [];
                    if ($zerC_m && $zerC_o && $zerC_i) {
                        print_r($zerC_m);
                        print_r($zerC_o);
                        print_r($zerC_i);
                        #$tempDir = _lib('zer');
                        $zer_h = 'https://zerads.com/';
                        #$he = ['image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'];
                        for ($_r = 0; $_r < 2; $_r++) {
                            $M_z = Net::C($zer_h.$zerC_m[0], 'GET', null, inf::$cookie, [], $zer_u, inf::$uagent);
                            
                            if (!empty($M_z) && $M_z !== 99) {
                                $zerC_p['main'] = base64_encode($M_z);
                            }
                            
                        }
                        
                        #_put($tempDir.'/main.jpg', $M_z);
                        foreach ($zerC_i as $i => $u) {
                            $I_z = Net::C($zer_h.$u, 'GET', null, null, [], $zer_u, inf::$uagent);
                            if (!empty($I_z) && $I_z !== 99) {
                                $zerC_p["opt_{$i}"] = base64_encode($I_z);
                                #_put($tempDir."/$i.png", $I_z);
                            }
                        }
                            
                        
                    }
                    
                    if (!empty($zerC_p) && isset($zerC_p['main'])) {
                        
                        if (count($zerC_i) === count($zerC_p) - 1) 
                        logx('', "sesuai");

                        
                    }
                    
                    
                }
            }
                
            die;
        }
    }

#die;
    if (!$limit && $claim) {
        $ret99 = 0; 
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            if ($fau === 99) {
                $ret99++;
                logx('warn', "masalah proxy, warm up dulu");
                if ($ret99 >= 7) {
                    goto login;
                }
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            #_put('fauu.html', $fau);
            
            $po = null;
            $cap = [];
            $f = scraper::payload($fau)[0] ?? [];
            if (empty($f)) {
                if (stripos($fau, '/register')) goto login;
                if (!$SLDONE) break;
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
            }
            
            if (!empty($f['payload'])) {
                $pa = $f['payload'];
                
                $cap = solve::exec($fau, $host, $api);
                if (isset($cap['trouble'])) {
                    _sle(60);
                    continue;
                }
                
                $po = array_merge($pa, $cap);
            }
            
            if (!empty($po)) {
                
                $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, ip: $ip);
                if (empty($cla) || ($cla === 99)) continue;
                
                _put('cla.html', $cla);
                $m = scraper::_jP($cla, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                    if (stripos($m[2][0], 'has been added')) break;
                }
                
                $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
                if (!empty($alert_d)) logx('err', $alert_d[0]);
                    
            }
            
            
        }
    }

    sl:
    do {
        $sho = Net::C("$host/links", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
        #_put('sho.html', $sho);
        
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
        if (empty($short)) {
            logx('info', "sl abis");
            $SLDONE = true;
            break;
        }
        
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
        
        $can_process = false; 
        foreach ($short as $links => [$idd, $lmt]) {
            
            if (!limit($lmt) || isset($skipped[$idd])) continue;
            
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
                $skipped[$idd] = true;
                continue; 
            }
            
            $loc_u = parse_url($loc)['host'];
            $is_bl = false;
            foreach ($up as $blacklisted) {
                if (str_contains($loc_u, $blacklisted)) {
                    logx('warn', "Domain $blacklisted Skipping..");
                    $skipped[$idd] = true;
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
            #var_dump($bakk);
            
            if (!$bakk) {
                $skipped[$idd] = true; 
                _sle(5);
                continue; 
            }
            
            styler("waiting for SL", fn() => _sle(50));
            
            $retVer = 0;
            while (true) {
                $ver = Net::C($bakk, 'GET', null, inf::$cookie, [], $loc, inf::$uagent);
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
                $m = scraper::_jP($ver, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                    break 2;
                }
            }
            
            break 2;
        }
        
        if (!$can_process) {
            logx('info', "sl abis");
            $SLDONE = true;
        }
        
    } while (!$SLDONE);
    
    
    
}





tes:


