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
$skipped = []; 
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
    if ($claim && !$limit) {
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
                
                /*
                if (str_contains($fau, 'Daily limit reached, claim Shortlink Wall')) {
                    $limit = true;
                    logx('err', 'daily limit');
                    break;
                }
                */
                
                if (!$SLDONE || !$ADDONE) {
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
                
                if (checkATB($atbfail, $cla)) continue;
                $m = scraper::_jP($cla, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                    
                    if (stripos($m[2][0], 'has been added')) {
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
    
    $zer = Net::C("$host/zeradsptc/earn", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('zer.html', $zer);
    $zer_u = Scraper::_xP($zer, "//a[@id='generateBtn']/preceding-sibling::a[1]/@href")[0] ?? '';
    if (!empty($zer_u)) {
        $zera = new Zera($host, $api, $mail);
        $zerads = $zera->exec($zer_u, $setF, 4*60);
        if (($zerads === 'claim') && $claim) continue;
    } elseif (stripos($zer, '/register')) continue;
    #die;
    
    $ads = Net::C("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('ptc.html', $ads); #die;
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        
        if ($ptcNumb <= 1) {
            $ADDONE = true;
        } else {
            #print_r($ptcList);
            if (!empty($ptcList['local']) && !$ADDONE) {
                foreach ($ptcList['local'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    $cla = null;
                    $view = null;
                    
                    $view = Net::C($ad_u, 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip);
                    #_put('view.html', $view);
                    if ($view === 99) continue 2;
                    if (!empty($view) && $view !== 99) {
                        $po = null;
                        $f = scraper::payload($view)[0] ?? [];
                        
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
                            #_put('cla.html', $cla); die;
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            $m = scraper::_jP($cla, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                            
                            if (isset($m[2][0])) {
                                Logger::M($mail);
                                Logger::G(0, $m[2][0]);
                                
                                $endF = microtime(true);
                                if ($setF > 0 && $claim) {
                                    $balik = $endF - $setF;
                                    if ($balik >= 4 * 60) continue 2;
                                }
                                
                            }
                            
                        }
                    }
                    
                }
                
                
            }
            
            if (!empty($ptcList['bctt'])) {
                foreach ($ptcList['bctt'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    $bctt = new Bctt($host, $api, $mail);
                    $ch = $bctt->exec($ad_u, $ad_t);
                    if ($ch === 99) goto login;
                    
                    $endF = microtime(true);
                    if ($setF > 0 && $claim) {
                        $balik = $endF - $setF;
                        if ($balik >= 4 * 60) continue 2;
                    }
                    
                }
            }
            
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
    
    
}





tes:









function parsePtcAds($html, $host) {
    if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $xp = Scraper::dom($html);
    if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
    $baseUrl = rtrim((parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host), '/');
    
    $cards = $xp->query("//div[@id='local']//div[contains(@class, 'card')]");
    foreach ($cards as $card) {
        $btn = $xp->query(".//button[@onclick]", $card);
        if ($btn->length === 0) continue;
        
        $onclick = $btn->item(0)->getAttribute('onclick');
        if (!preg_match("/window\.location\s*=\s*'([^']+)'/", $onclick, $m)) continue;
        
        $url = $m[1];
        if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
            $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        
        $timer = 5;
        $timerEl = $xp->query(".//div[contains(@class, 'fw-semibold') and contains(text(), 'sec')]", $card);
        if ($timerEl->length > 0 && preg_match('/(\d+)\s*sec/', $timerEl->item(0)->textContent, $tm)) {
            $timer = (int)$tm[1];
        }
        $result['local'][] = [$url, $timer];
    }
    
    $urls = Scraper::_xP($html, "//div[@id='bitcotasks']//div[contains(@class, 'card')]//div[contains(@class, 'mt-auto')]/a/@href");
    $tmrs = Scraper::_xP($html, "//div[@id='bitcotasks']//div[contains(@class, 'card')]//div[contains(@class, 'fw-semibold') and contains(text(), 'sec')]/text()");
    foreach ($urls as $i => $url) {
        $timer = 5;
        if (isset($tmrs[$i]) && preg_match('/(\d+)\s*sec/', trim($tmrs[$i]), $m)) {
            $timer = (int)$m[1];
        }
        $result['bctt'][] = [$url, $timer];
    }
    
    $urls = Scraper::_xP($html, "//div[@id='offerwallme']//div[contains(@class, 'card')]//div[contains(@class, 'mt-auto')]/a/@href");
    $tmrs = Scraper::_xP($html, "//div[@id='offerwallme']//div[contains(@class, 'card')]//div[contains(@class, 'fw-semibold') and contains(text(), 'sec')]/text()");
    foreach ($urls as $i => $url) {
        $timer = 5;
        if (isset($tmrs[$i]) && preg_match('/(\d+)\s*sec/', trim($tmrs[$i]), $m)) {
            $timer = (int)$m[1];
        }
        $result['owme'][] = [$url, $timer];
    }
    
    $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
    
    return $result;
}

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
