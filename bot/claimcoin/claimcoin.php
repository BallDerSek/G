<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['mail', 'pass', 'PROXY']);
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
$SLDONE = true;
$ADDONE = false;
$ALLDONE = 0;
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
            
            #_put('ve.html', $ve); #die;
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
    
    $_bal = Scraper::_xP($dash, "//h2[contains(text(), 'CCP')]/text()")[0] ?? null;
    if ($_bal) {
        Logger::M($mail);
        Logger::X('info', "[ $_bal ]", true, true);
        $bal = ((int)$_bal);
        
        if ($can_withdraw && ($bal >= 10000)) {
            $po = null;
            $jjn = [];
            $wd = Net::C("$host/withdraw", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            #_put('wd.html', $wd);
            $jjn = _wd($wd);
            if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                $pa = $jjn['payload'];
                
                $cap = solve::exec($wd, $host, $api, $pa);
                if (isset($cap['trouble'])) continue;
                
                $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                if (empty($pa[$walletKey])) $pa[$walletKey] = $mail;
                
                $po = array_merge($pa, $cap);
                
                Logger::G(0, '  tes ilmu: '.$jjn['info']['coin'], false);
                Logger::X('info', ' [ '.$po[$walletKey].' ]');
                
                $wdd = Net::C($jjn['url'], 'POST', $po, inf::$cookie, [], "$host/withdraw", inf::$uagent, false, false, $ip);
                #_put('wd.html', $wdd);
                $mW = Scraper::_jP($wdd, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/") ?? null;
                if (!empty($mW[1][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    Logger::X('info', $mW[1][0]);
                }
                
            } else {
                Logger::X('err', 'gak bisa wd kayaknya');
            }
        }
    }
    
    if (!$limit && $claim) {
        $ret99 = 0; $claimed = 0;
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
            if (!empty($f['payload']) && !empty($f['url'])) {
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
                if (isset($po['recaptchav3'])) $po['recaptchav3'] = $po['g-recaptcha-response'];
                
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
                    
                    $claimed++; if ($claimed >= 100) continue 2;
                    
                }
                styler('Waiting for faucet', fn() => _sle(12));
            }
        }
    }
    
    $ads = Net::X("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('ads.html', $ads);
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        #print_r($ptcList); die;
        if ($ptcNumb <= 1) {
            $ADDONE = true;
        } else {
            
            if (!empty($ptcList['local']) && !$ADDONE) {
                foreach ($ptcList['local'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    $cla = null;
                    $view = null;
                    
                    $view = Net::C($ad_u, 'GET', null, inf::$cookie, [], "$host/ptc", inf::$uagent, false, false, $ip);
                    #_put('view.html', $view); die;
                    
                    if ($view === 99) continue 2;
                    if (!empty($view) && $view !== 99) {
                        $po = null;
                        $f = scraper::payload($view)[1] ?? [];
                        #print_r($f); die;
                        if (!empty($f)) {
                            $pa = $f['payload'];
                            
                            $cap = solve::exec($view, $host, $api, $pa);
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
                            
                            $m = Scraper::_jP($cla, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/") ?? null;
                            if (isset($m[1][0])) {
                                Logger::M($mail);
                                Logger::G(0, $m[1][0]);
                            }
                            
                            
                        }
                        
                    }
                    
                }
                
            }
            
            if (!empty($ptcList['bctt'])) {
                #print_r($ptcList['bctt']); die;
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
    
    
    
    
    if (!$claim && $SLDONE && $ADDONE) {
        
        if ($ALLDONE <= 500) {
            $ALLDONE++;
            styler('cooldown', fn() => _sle(100));
            continue;
        }
        
        Logger::M($mail);
        (logx('err', 'beres') ?: die);
        
    }
    
}


tes:







function parsePtcAds($html, $host) {
    if (empty($html) || $html === 99) {
        return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    }
    
    $result = ['local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
    $baseUrl = rtrim((parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host), '/');
    
    $urls = Scraper::_pP($html, 'location.href');
    // Ambil semua timer
    $timers = Scraper::_xP($html, "//span[contains(@class, 'badge-danger')] | //span[contains(text(), 'seconds')]");
    
    $seen = [];
    foreach ($urls as $i => $url) {
        if (in_array($url, $seen)) continue;
        $seen[] = $url;
        
        if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
            $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        
        $timer = 5;
        if (isset($timers[$i])) {
            $text = trim($timers[$i]);
            if (preg_match('/(\d+)\s*seconds?/', $text, $tm)) {
                $timer = (int)$tm[1];
            }
        }
        
        $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
        
        if ($uHost === $host) {
            $result['local'][] = [$url, $timer];
        } elseif (strpos($url, 'bitcotasks.com') !== false) {
            $result['bctt'][] = [$url, $timer];
        } elseif (strpos($url, 'offerwall.me') !== false) {
            $result['owme'][] = [$url, $timer];
        } elseif (strpos($url, 'offerzono.com') !== false) {
            $result['zono'][] = [$url, $timer];
        } else {
            $result['external'][] = [$url, $timer];
        }
    }
    
    $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['zono']) + count($result['external']);
    
    return $result;
}



function _wd($html) {
    $res = Scraper::payload($html)[1] ?? null;
    
    $cards = explode('class="cards col-xl-6', $html);
    array_shift($cards);
    
    preg_match('/name="amount"[^>]*max="([\d.]+)"/', $html, $balMatch);
    $balance = (float)($balMatch[1] ?? 0);
    
    foreach ($cards as $card) {
        if (stripos($card, 'Bitcoin') !== false || stripos($card, 'BTC') !== false) {
            continue;
        }
        
        preg_match('/aria-valuenow="\s*([\d.]+)"/i', $card, $stockMatch);
        $stock = (float)($stockMatch[1] ?? 0);
        
        if ($stock >= 1) {
            preg_match('/name="method"\s+value="(\d+)"/i', $card, $valMatch);
            $method = $valMatch[1] ?? null;
            
            if ($method) {
                $res['payload']['method'] = $method;
                
                preg_match('/<h4[^>]*>.*?<\/i>\s*([^-]+?)\s*-/i', $card, $nameMatch);
                $name = trim($nameMatch[1] ?? '');
                
                preg_match('/minimumWithdrawal:\s*([\d.]+)/i', $card, $minMatch);
                preg_match('/price:\s*([\d.]+)/i', $card, $rateMatch);
                
                $res['info'] = [
                    'coin' => $name,
                    'stock' => $stock . '%',
                    'min_wd' => (float)($minMatch[1] ?? 0),
                    'rate' => (float)($rateMatch[1] ?? 0),
                    'balance' => $balance
                ];
                
                return $res;
            }
        }
    }
    
    return false;
}