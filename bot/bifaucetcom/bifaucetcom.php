<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['mail', 'pass', 'PROXY']);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://bifaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '159.198.47.130';

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
$SLDONE = false;
$ADDONE = false;
$skipped_sho = [];
$skipped_ads = []; 
$claim = true;
$can_withdraw = true;
$atbforce = false;
$atbfail = 0;
while (true) {
    $dash = null;
    $owme = null;
    $zer = null;
    $ads = null;
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

    if (stripos($dash, 'Please check your inbox or spam folder to confirm your account')) {
        $can_withdraw = false;
    }
    
    $_bal = Scraper::_xP($dash, "//small[text()='Main Balance']/preceding-sibling::h6/text()")[0] ?? '';
    if ($_bal) {
        print(FGd['CYN'].maskEmail($mail).RSET." ");
        logx('info', "[ $_bal ]", true, true);
        $bal = ((int)$_bal);
        
        if ($can_withdraw && ($bal >= 5000)) {
            $po = null;
            $jjn = [];
            $wd = Net::C("$host/withdraw", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            $jjn = _wd($wd);
            
            if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                $pa = $jjn['payload'];
                
                $cap = solve::exec($wd, $host, $api, $pa);
                if (isset($cap['trouble'])) continue;
                
                $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                if (empty($pa[$walletKey])) $pa[$walletKey] = $mail;
                
                $po = array_merge($pa, $cap);
                
                logg(true, '  tes ilmu: '.$jjn['info']['coin'], false);
                logx('info', ' [ '.$po[$walletKey].' ]');
                
                $wdd = Net::C($jjn['url'], 'POST', $po, inf::$cookie, [], "$host/withdraw", inf::$uagent, false, false, $ip);
                
                $mW = scraper::_jP($wdd, "/Swal\.fire\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'<]+)/i");
                if (!empty($mW[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logx('info', $mW[2][0]);
                }
            } else {
                logx('err', 'gak bisa wd kayaknya');
            }
        } 
    }

    faucet:
    $setF = 0; 
    if ($claim) {
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
            
            if (!empty($f['payload'])) {
                $pa = $f['payload'];
                
                if ($atbfail >= 3) $atbforce = true;
                $cap = solve::exec($fau, $host, $api, $pa, $atbforce);
                if (isset($cap['trouble'])) {
                    _sle(60);
                    continue;
                }
                
                $po = array_merge($pa, $cap);
            } else {
                if (str_contains($fau, '/register')) continue 2;
                
                if (str_contains($fau, 'Daily limit reached, claim Shortlink Wall')) {
                    $limit = true;
                    logx('err', 'daily limit');
                    break;
                }
                
                if (!$SLDONE || $ADDONE) {
                    $setF = microtime(true);
                    break;
                }
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
                
            }
            
            if (!empty($po)) {
                
                $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, ip: $ip);
                if (empty($cla) || ($cla === 99)) continue;
                
                #_put('cla.html', $cla);
                $m = scraper::_jP($cla, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                if (stripos($cla, 'nvalid Anti-Bot')) $atbfail++;
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                    
                    if (stripos($m[2][0], 'has been added')) {
                        #$deadline = 
                        $atbforce = false;
                        $atbfail = 0;
                        $setF = microtime(true);
                        break;
                    }
                }
                
                $alert_d = scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
                if (!empty($alert_d)) logx('err', $alert_d[0]);
                    
            }
        }
    }
    
    
    
    
    
    
    $ads = Net::C("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('ptc.html', $ads); #die;
    if (!empty($ads) && $ads !== 99) {
        $_onclick = Scraper::_xP($ads, "//div[@id='local']//div[contains(@class, 'card')][.//button[@onclick]]//button/@onclick");
        $_tim = Scraper::_xP($ads, "//div[@id='local']//div[contains(@class, 'card')][.//button[@onclick]]//div[contains(@class, 'px-3')]/div[2]/div[contains(@class, 'fw-semibold')]");
        $url_list = array_map(fn($u) => explode("'", $u)[1] ?? null, $_onclick);
        $vurl = $url_list[0] ?? null;
        if ($vurl) {
            $cla = null;
            $view = null;
            $tim = isset($_tim[0]) ? (int)preg_replace('/[^0-9]/', '', $_tim[0]) : 0;
            /*
            logx('info', "[ $vurl ]: ", false);
            logx('', $tim);
            */
            
            $ret99 = 0;
            while (true) {
                $view = Net::C($vurl, 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip);
                if ($view === 99) {
                    $ret99++;
                    logx('warn', "masalah proxy, warm up dulu");
                    if ($ret99 >= 5) continue 2;
                    _sle(30);
                    continue;
                }
                if (!empty($view)) {
                    $ret99 = 0; 
                    break;
                }
                
            }
            if (!empty($view)) {
                #_put('view.html', $view);
                $set = microtime(true);
                $f = scraper::payload($view) ?? [];
                if (!empty($f)) {
                    $cap = [];
                    $pa = $f[0]['payload'] ?? [];
                    $cap = solve::exec($view, $host, $api);
                    if (isset($cap['trouble'])) continue;
                    $po = array_merge($pa, $cap);
                    #print_r($po);
                    
                    if (!empty($po)) {
                        $end = microtime(true) - $set;
                        $wait = (int)($tim - $end);
                        if ($wait > 0) styler("waiting for ads: $wait", fn() => _sle($wait));
                        
                        for ($re = 0; $re < 2; $re++) {
                            $cla = Net::X($f[0]['url'], 'POST', $po, inf::$cookie, [], $vurl, inf::$uagent, ip: $ip);
                            if (!empty($cla) && $cla !== 99) break;
                            
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
        }
    }
    
    sl:
    $ret99 = 0; 
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
        if (empty($short) || stripos($sho, '/register')) break;
        
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix', 'oii.io'];
        
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
            #var_dump($bakk);
            
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
                
                $m = scraper::_jP($ver, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                }
            }
            
            break 2;
        }
        
        if (!$can_process) {
            logx('info', "sl abis");
            $SLDONE = true;
        }
        
    } while (!$SLDONE);

    $owme = Net::C("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('ptc.html', $owme); #die;
    
    if (!empty($owme) && $owme !== 99) {
        $ow = new Owme($host, $api, $mail);
        $off = [];
        $owmeFail = 0;
        $urls = Scraper::_xP($owme, "//div[@id='offerwallme']//div[contains(@class, 'card')]//div[contains(@class, 'mt-auto')]/a/@href");
        $tmrs = Scraper::_xP($owme, "//div[@id='offerwallme']//div[contains(@class, 'card')]//div[contains(@class, 'text-primary') and contains(@class, 'fw-semibold')]/text()");
        
        if (!empty($urls)) {
            foreach ($urls as $index => $url) {
                $tmr = isset($tmrs[$index]) ? $tmrs[$index] : '0';
                $timer = (int)filter_var($tmr, FILTER_SANITIZE_NUMBER_INT);
                $off[] = ['url' => $url, 'timer' => $timer];
            }
        }
        
        if (empty($off)) {
            logx('err', "habis total kayaknya.");
        } else {
            foreach ($off as $ad) {
                if ($owmeFail >= 15) break;
                if (isset($skipped_ads[$ad['url']])) continue;

                if ($setF > 0) {
                    $endF = microtime(true) - $setF;
                    if ($endF >= 4 * 60) break;
                }
                
                $status = $ow->exec($ad['url'], $ad['timer']);
                if ($status) {
                    styler('Waiting', fn() => _sle(5));
                } else {
                    $owmeFail++;
                    $skipped_ads[$ad['url']] = true; 
                }
            }
        }
    }
    
    $zer = Net::C("$host/zeradsptc/earn", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('zer.html', $zer);
    $zer_u = Scraper::_xP($zer, "//a[@id='generateBtn']/preceding-sibling::a[1]/@href")[0] ?? '';
    
    if (!empty($zer_u)) {
        $zera = new Zera($host, $api, $mail);
    
        $zera->exec($zer_u, $ip);
    } elseif (stripos($zer, '/register')) continue;
    
}





tes:











function _wd($html) {
    $res = Scraper::payload($html)[0] ?? null;
    if (!$res) return false;

    $fp_area = explode('data-group="cwallet"', $html)[0] ?? $html;
    
    $cards = explode('class="currency-card', $fp_area);
    array_shift($cards); 

    foreach ($cards as $card) {
        preg_match('/data-name="([^"]+)"/i', $card, $nameMatch);
        $name = $nameMatch[1] ?? '';
        
        if (stripos($name, 'btc') !== false || stripos($name, 'bitcoin') !== false) {
            continue;
        }

        preg_match('/aria-valuenow="(\d+)"/i', $card, $stockMatch);
        $stock = (int)($stockMatch[1] ?? 0);

        if ($stock >= 10) {
            preg_match('/name="method"\s+value="(\d+)"/i', $card, $valMatch);
            $value = $valMatch[1] ?? null;

            if ($value !== null) {
                $res['payload']['method'] = $value;
                
                $res['info'] = [
                    'coin'  => $name,
                    'stock' => $stock . '%'
                ];
                
                return $res;
            }
        }
    }
    
    return false;
}