<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://linksfly.link';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=84';
$ip = null;

(function ($login, $ip) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
} ) ($login, $ip);

$skipped = [];
$SLDONE = true;
$claim = true;
$curr = '';
while (true) {
    $ret = 0;

    do {
        $ret++;
        $l = inf::check("$host/app/dashboard", [], '/auth/login', true);
        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        if ($ret >= 10) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            
            if (isset($_0) && !empty($_0)) _put(__DIR__.'/lo.html', $_0);
            if (isset($ve) && !empty($ve)) _put(__DIR__.'/ve.html', $ve);
            
            exit; 
        }
        
        logx('err', "logging in ", false); 
        _sle(3); _clr();
        $_0 = Net::X($host.$r, 'GET', null, inf::$cookie, [], '', inf::$uagent);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        $f = scraper::payload($_0)[1] ?? [0] ?? null;
        $po = null;
        
        if (!empty($f)) {
            #print_r($f); die;
            $pa = $f['payload'];
            $cre = ['wallet' => $login, 'uid' => md5($login), 'private_ip' => IP()];
            
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
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, [], $host.$r, inf::$uagent);
            #_put('ve.html', $ve);
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
        }
    } while (empty($dash));
    #_put('dash.html', $dash); die;
    #goto sl;
    $_fa = Scraper::_xP($dash, "//li[contains(@class, 'pc-hasmenu')][.//span[text()='Faucet']]//ul[contains(@class, 'pc-submenu')]//a/@href");
    #print_r($_fa);
    foreach ($_fa as $fa) {
        if (!$claim) break;
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while ($claim) {
            $fau = null;
            $fau = Net::X($fa, 'GET', null, inf::$cookie, [], $host, inf::$uagent);
            
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
            $f = scraper::payload($fau) ?? null;
            if (!empty($f)) {
                $pa = null;
                foreach ($f as $fo) {
                    if (stripos($fo['url'], 'verify')) {
                        $pa = $fo['payload'];
                        break;
                    }
                    if (stripos($fo['url'], 'login')) goto login;
                }
                
                if ($pa === null) (logx('err', 'web update!!') ?: die);
                
                if (isset($pa['puzzle_answer']) && stripos($fau, 'olve to claim')) {
                    $ins = Scraper::_xP($fau, "//label[contains(., 'Solve to claim')]//span/text()");
                    if (!empty($ins)) {
                        $soal = trim($ins[0]);
                        $inss = str_replace('×', '*', $soal);
                        if (preg_match('/(\d+)\s*([\+\-\*\/])\s*(\d+)/', $inss, $_m)) {
                            $q1 = (int)$_m[1];
                            $op = $_m[2];
                            $q2 = (int)$_m[3];
                            $pa['puzzle_answer'] = SolveUtils::math($q1, $q2, $op);
                        } else {
                            continue;
                        }
                    }
                }
                
                $cap = Solve::exec($fau, $host, $api, $pa);
                
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    ($tro === 'proxy') ? _sle(30) : _sle(10);
                    continue;
                }
                
                $po = array_merge($pa, $cap, ['wallet' => $login]);
                
            } else {
                if (stripos($fau, 'claim limit') !== false) continue 2;
                styler("waiting for CLAIM", fn() => _sle(10));
                continue;
            }
            
            if (!empty($po)) {
                $cla = Net::X($fo['url'], 'POST', $po, inf::$cookie, [], $host, inf::$uagent);
                #_put('cla.html', $cla);
                if (!empty($cla) && ($cla !== 99)) {
                    
                    $alert_d = scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
                    if (!empty($alert_d)) {
                        logx('', maskEmail($login).' ', false, true);
                        logx('err', $alert_d[0]);
                        if (stripos($alert_d[0], 'is locked')) {
                            $claim = false;
                        }
                        $curr = $_c; 
                        break 2;
                    }
                    
                    $_suc = null;
                    $_suc = scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?text:\s*'([^']+)'/s") ?? [];
                    if (!empty($_suc[2][0])) {
                        $status = $_suc[1][0]; 
                        $msg = $_suc[2][0];
                        
                        print(FGd['CYN'] . maskEmail($login) . RSET . " ");
                        logx($status === 'success' ? 'ok' : 'err', "$status ", false);
                        logg(false, $msg);
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) break;
                        
                        if (stripos($msg, 'Shortlink')) {
                            if ($SLDONE) {
                                logx('err', 'Gada jatah SL lagi');
                                die;
                            }
                            $curr = $_c; 
                            break 2;
                        }
                        
                    }
                }
                
                styler("waiting for next claim", fn() => _sle(5));
            }
            
        }
        
    }
    /*
    sl:
    $_sl = Scraper::_xP($dash, "//li[contains(@class, 'pc-hasmenu')][.//span[text()='ShortLinks']]//ul[contains(@class, 'pc-submenu')]//a/@href");
    #print_r($_sl);
    $valid = [];
    $success_in_page = false;
    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) continue;
        
        $up = ['earnow','shortano', 'shortino', 'fc-lc'];
        $ret99 = 0;
        
        do {
            $sho = null;
            $sho = Net::X($sl, 'GET', null, inf::$cookie, [], '', inf::$uagent);
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
                    
                    $get = Net::X($ud, 'POST', $po, inf::$cookie, [], $sl, inf::$uagent, foll: false);
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
    */
    if (!$claim && $SLDONE) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
    }

}




tes:
