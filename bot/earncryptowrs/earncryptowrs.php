<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], true, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://earncryptowrs.in';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=10180';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $c = config::credential(['ua' => fn() => config::uagent('mobile')]);
    $userAgent = $c['ua'];
    
    inf::setup($userAgent, $cookieFile, $ip, false, $login);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
    print(UNDR.BOLD."site:");
    logx('ok', " $host");
} ) ($login, $ip, $host);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$claim = true;
$habis = [];
$curr = '';
$atbforce = false;
$atbfail = 0;
while (true) {
    $ret = 0;

    do {
        $ret++;
        $l = inf::check("$host/", $headersCF, '/auth/login', true);
        #_put('l.html', $l['html']); _rl('lanjut: ');
        
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
        
        logx('err', "logging in ", false); 
        _sle(3); _clr();
        $_0 = Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        #_put('0.html', $_0); _rl('lanjut: ');
        $f = scraper::payload($_0)[0] ?? null;
        #print_r($f); #die;
        $po = null;
        
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['wallet' => $login];
            
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
            #print_r($po); _rl('lanjut: ');
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
            #_put('ve.html', $ve);
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
        }
    } while (empty($dash));
    #_put('dash.html', $dash);
    #goto sl;
    $_fa = Scraper::_xP($dash, "//div[@id='faucetMenu']//a/@href");
    #print_r($_fa); die;
    foreach ($_fa as $fa) {
        if (!$claim) break;
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while ($claim) {
            $fau = null;
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent);
            
            #_put('fau.html', $fau); #die;
            
            if ($fau === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            $po = null;
            $cap = [];
            $f = scraper::payload($fau)[0] ?? null;
            if (!empty($f)) {
                #print_r($f);
                
                
                check:
                $cf = Net::C($f['url'], 'GET', null, inf::$cookie, $headersCF, "$host/dashboard", inf::$uagent, d: true);
                $cff = checkCF($f['url'], $api, $cf, $headersCF);
                if (empty($cff['html'])) {
                    continue;
                } else {
                    $headersCF = $cff['head'];
                    $html = $cff['html'];
                }
                
                
                $pa = $f['payload'];
                if ($atbfail >= 3) $atbforce = true;
                $cap = Solve::exec($fau, $host, $api, $pa, $atbforce);
                
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    ($tro === 'proxy') ? _sle(30) : _sle(10);
                    continue;
                }
                
                $po = array_merge($pa, $cap, ['wallet' => $login]);
                
            } else {
                if (stripos($fau, 'claim limit') !== false) continue 2;
                if (stripos($fau, '/auth/login')) continue 3;
                styler("waiting for CLAIM", fn() => _sle(10));
                continue;
            }
            
            if (!empty($po)) {
                
                $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host, inf::$uagent);
                #_put('cla.html', $cla); #die;
                
                if (!empty($cla) && ($cla !== 99)) {
                    
                    $alert_d = scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
                    if (!empty($alert_d)) {
                        logx('', maskEmail($login).' ', false, true);
                        logx('err', $alert_d[0]);
                        if (stripos($alert_d[0], 'is locked') || stripos($alert_d[0], 'is banned')) {
                            $claim = false;
                        }
                        $curr = $_c; 
                        break 2;
                    }
                    
                    $_i = Scraper::_jP($cla, "/icon\s*:\s*['\"]([^'\"]+)['\"]/");
                    $_t = Scraper::_jP($cla, "/title\s*:\s*['\"]([^'\"]+)['\"]/");
                    $_h = Scraper::_jP($cla, "/html\s*:\s*['\"]([^'\"]+)['\"]/");
                    
                    $stt = !empty($_i[1]) ? end($_i[1]) : null;
                    $ttl = !empty($_t[1]) ? end($_t[1]) : null;
                    $msg = !empty($_h[1]) ? end($_h[1]) : null;
                    
                    if ($stt) {
                        print(FGd['CYN'].maskEmail($login).RSET." ");
                        $is_ok = (stripos($stt, 'success') !== false);
                        logx($is_ok ? 'ok' : 'err', "{$ttl} ", false);
                        logg(false, $msg);
                        
                        if (stripos($msg, 'has been sent')) {
                            $atbforce = false;
                            $atbfail = 0;
                        }
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) {
                            $habis[$fa] = true;
                            break;
                        }
                        if (stripos($msg, 'ssion expired') !== false) continue 3;
                        
                        if (stripos($msg, 'verify your account') !== false) {
                            _put('cla.html', $cla);
                            die;
                        }
                        if (stripos($msg, 'Your claim is locked') !== false) {
                            $claim = false;
                            $curr = $_c; 
                            break 2;
                        }
                        if (stripos($msg, 'nvalid Anti-Bot')) {
                            $atbfail++;
                        }
                        if (stripos($msg, 'Shortlink')) {
                            
                            if ($SLDONE) (logx('err', 'Gada SL lagi') ?: die);
                            $curr = $_c; 
                            break 2;
                        }
                    }
                }
                
                styler("waiting for next claim", fn() => _sle(5));
            }
            
        }
        
    }
    
    if (count($habis) === count($_fa)) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
    }
    
    sl:
    $_sl = Scraper::_xP($dash, "//div[@id='linksMenu']//a/@href");
    #print_r($_sl);
    $valid = [];
    $success_in_page = false;
    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (!empty($curr) && strcasecmp(trim($_c), trim($curr)) !== 0) continue;
        
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix', 'oii.io'];
        $ret99 = 0;
        
        do {
            $sho = null;
            $sho = Net::X($sl, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
            #_put('sho.html' ,$sho);
            
            if ($sho === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            
            if (empty($sho)) { _sle(5); continue; }
            
            $short = sScraper::extract($sho);
            if (empty($short)) continue;
            #print_r($short); #die;
            $success_in_page = false; 
            $found_one = false; 
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt) || isset($skipped[$idd])) continue; 
                
                $found_one = true;
                $valid[$links] = [$idd, $lmt];
                
                $go = str_replace("/currency/$_c", "", $sl);
                $ud = $go."/go/{$idd}/".strtoupper($_c);
                
                $f = scraper::payload($sho)[0] ?? [];
                $cap = [];
                $po = null;
                if (!empty($f)) {
                    #print_r($f);
                    $pa = $f['payload'];
                    
                    $cap = Solve::exec($sho, $host, $api, $pa);
                    if (isset($cap['trouble'])) {
                        $tro = $cap['trouble'];
                        ($tro === 'proxy') ? _sle(30) : _sle(10);
                        break;
                    }
                    $po = array_merge($pa, $cap);
                }
                
                $get = null;
                if (!empty($po)) {
                    #print_r($po);
                    
                    $get = Net::X($ud, 'POST', $po, inf::$cookie, $headersCF, $sl, inf::$uagent, foll: false);
                    #var_dump($get);
                    
                }
                preg_match('/location\.href\s*=\s*["\']([^"\']+)["\']/', $get, $match);
                $loc = $match[1] ?? '';
                
                if (!$loc) {
                    $skipped[$idd] = true;
                    break;
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
                if ($is_bl) { _sle(2); break; }

                logx('info', "Bypassing SL: {$loc}", true, true);
                
                $start = microtime(true);
                $bakk = links($api, $loc);
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                $wait = 100 - (int)(microtime(true) - $start);
                if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                
                $retVer = 0;
                while (true) {
                    $ver = Net::C($bakk, 'GET', null, inf::$cookie, $headersCF, $loc, inf::$uagent);
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
                    
                    $_i = Scraper::_jP($ver, "/icon\s*:\s*['\"]([^'\"]+)['\"]/");
                    $_t = Scraper::_jP($ver, "/title\s*:\s*['\"]([^'\"]+)['\"]/");
                    $_h = Scraper::_jP($ver, "/html\s*:\s*['\"]([^'\"]+)['\"]/");
                    
                    $stt = !empty($_i[1]) ? end($_i[1]) : null;
                    $ttl = !empty($_t[1]) ? end($_t[1]) : null;
                    $msg = !empty($_h[1]) ? end($_h[1]) : null;
                    
                    if ($stt) {
                        print(FGd['CYN'].maskEmail($login).RSET." ");
                        $is_ok = (stripos($stt, 'success') !== false);
                        logg(false, $msg);
                        if (stripos($msg, 'sufficient') !== false) {
                            $currentIndex = array_search($sl, $_sl);
                            if ($currentIndex !== false && isset($_sl[$currentIndex + 1])) {
                                $curr = basename($_sl[$currentIndex + 1]);
                            } else {
                                $curr = '';
                            }
                            
                            break 2; 
                        }
                        
                        if ($is_ok) $success_in_page = true;
                    }
                }
                break 2;
            }
            
            if (!$found_one) {
                logx('err', 'SL habis atau sisa blacklist.');
                $SLDONE = true;
                break; 
            }

        } while (!$success_in_page);
        
        if ($success_in_page || $curr === "") break; 
    }
    
    if (!$claim && $SLDONE) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
    }

}



function checkCF($url, $api, $body = null, $headersCF = []) {
    
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    
    if (!$html || !$code) return [];
    
    if ($code !== 200 && (stripos($html, 'Just a moment') !== false || stripos($html, 'Attention Required!') !== false)) {
        
        $cf = execCF($api, $url, inf::$cookie, inf::$uagent);
        
        if ($cf) {
            #var_dump($cf);
            [$headersCF, $ua] = $cf;
            inf::setup($ua, inf::$cookie);
            
            if (!empty($headersCF)) {
                for ($try = 1; $try <= 3; $try++) {
                    _sle(3);
                    $fix = Net::X($url, 'GET', null, inf::$cookie, $headersCF, $url, inf::$uagent, d: true);
                    
                    #var_dump($fix);
                    if (!empty($fix) && isset($fix['http_code'])) {
                        $_c = $fix['http_code'];
                        $_b = $fix['body'];
                        
                        if ($_c === 200 || (!stripos($_b, 'Just a moment') !== false || !stripos($_b, 'Attention Required!') !== false)) {
                            
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
