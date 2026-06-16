<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], true, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://99faucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=16125';
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
while (true) {
    $ret = 0;

    do {
        $ret++;
        $l = inf::check("$host/dashboard", $headersCF, '/auth/login', true);
        #var_dump($l);

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
        $_0 = Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent, d: true);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        $_0 = checkCF($host, $api, $_0)['html'] ?? null;
        
        #_put('0.html', $_0);
        $f = scraper::payload($_0)[0] ?? null;
        $po = null;
        #print_r($f); #die;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'email' => $login];
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
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
            #_put('ve.html', $ve);

            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            $_sucS = scraper::_jP($ve, "/Swal\.fire\(\s*\{.*?title:\s*'([^']+)'.*?text:\s*'([^']+)'.*?icon:\s*'([^']+)'/s") ?? [];
            
            if (isset($_sucS[2][0])) {
                $msg = $_sucS[2][0];
                logx('err', $msg);
                if (stripos($msg, 'nvalid captcha')) continue;
                
                die;
            }
            
        }
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    
    $_fa = Scraper::_xP($dash, "//div[contains(normalize-space(), 'Faucets')]/following-sibling::div[@class='sub-menu-two']/a/@href");
    foreach ($_fa as $fa) {
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while (true) {
            $fauu = null;
            $fauu = Net::C($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent, d: true);
            
            #_put('fau.html', $fau); #die;
            if ($fauu === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            
            $cff = checkCF($fa, $api, $fauu, $headersCF);
            if (!empty($cff['html'])) {
                $headersCF = $cff['head'];
                $fau = $cff['html'];
            } else {
                $fau = $fauu['body'] ?? null;
            }
            
            if (!empty($fau)) {
                #_put('fau.html', $fau); #die;
                
                $po = null;
                $f = scraper::payload($fau)[0] ?? null;
                
                if ($f) {
                    #print_r($f);
                    $cap = [];
                    $pa = $f['payload'];
                    $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'email' => $login];
                    $cap = Solve::exec($fau, $host, $api, $pa);
                    
                    if (isset($cap['trouble'])) {
                        _sle(5);
                        continue;
                    }
                    $po = array_merge($pa, $cap, $cre);
                    
                } elseif (stripos($fau, 'Need to Complete Atleast 1 Shortlinks')) {
                    $curr = $_c;
                    break 2;
                }
                
                if (!empty($po)) {
                    #print_r($po); #die;
                    $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host, inf::$uagent);
                    #_put('cla.html', $cla); #die;
                    if ($cla && $cla !== 99) {
                        $_suc = scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?title:\s*'([^']+)'.*?text:\s*'([^']+)'.*?icon:\s*'([^']+)'/s") ?? [];
                        $_alr = scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
                        print(FGd['CYN'].maskEmail($login).RSET." ");
                        
                        if (stripos($cla, 'Just a moment')) continue 3;
                        
                        if (!empty($_suc[2][0])) {
                            $status = $_suc[3][0];
                            $msg = $_suc[2][0];
                            
                            logx($status === 'success' ? 'ok' : 'err', "$status ", false);
                            logg(false, "$msg");
                            
                            if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                $habis[$fa] = true;
                                break;
                            }
                            if (preg_match('/blacklisted|flagged|banned/i', $msg)) {
                                die;
                            }
                            
                            if (stripos($msg, 'Shortlink')) {
                                if ($SLDONE) {
                                    logx('err', 'Gada jatah SL lagi');
                                    die;
                                }
                                $curr = $_c;
                                break 2;
                            }
                        }
                        
                        if (!empty($_alr[0])) {
                            logx('err', $_alr[0]);
                            continue;
                        }
                        
                    }
                    
                    #styler("waiting for next claim", fn() => _sle(5));
                }
                
            }
            
        }
        
        
        
    }
    
    if (count($habis) === count($_fa)) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
    }


    $_sl = Scraper::_xP($dash, "//div[contains(normalize-space(), 'Shortlinks')]/following-sibling::div[@class='sub-menu-two']/a/@href");
    #print_r($_sl);
    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) continue;
        
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
            
            $short = sScraper::extract($sho);
            if (empty($short)) continue;
            #print_r($short); #die;
            $success_in_page = false; 
            $found_one = false; 
            
            $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt) || isset($skipped[$idd])) continue; 
                
                $found_one = true;
                $valid[$links] = [$idd, $lmt];
                
                $ud = str_replace("/$_c", "/go/$idd/$_c", $sl);
                
                $getLok = 0;
                $po = null;
                while (true) {
                    $getLok++;
                    $lok = Net::X($ud, 'GET', null, inf::$cookie, [], $sl, inf::$uagent);
                    
                    if ($getLok >= 5) goto login;
                    
                    if (!empty($lok) && $lok !== 99) {
                        $getLok = 0;
                        
                        $f = Scraper::payload($lok)[0] ?? [];
                        if (!empty($f)) {
                            $pa = $f['payload'];
                            
                            $cap = Solve::exec($lok, $host, $api, $pa);
                            
                            if (isset($cap['trouble'])) {
                                _sle(60);
                                continue;
                            }
                            
                            $po = array_merge($pa, $cap);
                            break;
                        }
                    }
                }
                
                $bakk = null;
                if ($po) {
                    $get = Net::C($f['url'], 'POST', $po, inf::$cookie, [], $sl, inf::$uagent, foll: false);
                    if (!empty($get) && $get !== 99) {
                        $matches = Scraper::_pP($get, 'location.href');
                        $loc = $matches[0] ?? ''; 
                        
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
                        $start = microtime(true);
                        $bakk = links($api, $loc);
                    }
                }
                
                if (!$bakk) {
                    $skipped[$idd] = true;
                    _sle(5);
                    continue; 
                }
                
                $wait = 100 - (int)(microtime(true) - $start);
                if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                
                $ver = null;
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
                    _put('ver.html', $ver); #die;
                    $_suc = scraper::_jP($ver, "/Swal\.fire\(\s*\{.*?title:\s*'([^']+)'.*?text:\s*'([^']+)'.*?icon:\s*'([^']+)'/s") ?? [];
                    #print_r($_suc); #die;
                    if (!empty($_suc[1][0])) {
                        $status = $_suc[1][0];
                        $msg = $_suc[2][0];
                        print(FGd['CYN'].maskEmail($login).RSET." ");
                        logx($status === 'success' ? 'ok' : 'err', "$status ", false);
                        logg(false, "$msg");
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) {
                            $currentIndex = array_search($sl, $_sl);
                            if ($currentIndex !== false && isset($_sl[$currentIndex + 1])) {
                                $curr = basename($_sl[$currentIndex + 1]);
                            } else {
                                $curr = '';
                            }
                            break 2; 
                        } elseif (stripos($ver, 'has been sent to your')) {
                                $success_in_page = true;
                                
                            }
                            break 2;
                        
                    }
                }
                
            }
            if (!$found_one) {
                logx('err', 'SL habis atau sisa blacklist.');
                $SLDONE = true;
                break; 
            }
            
        } while (!$success_in_page);
        
        if ($success_in_page || $curr === "") break; 
    }


}

tes:







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
