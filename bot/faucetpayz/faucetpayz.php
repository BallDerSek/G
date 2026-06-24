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
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$mail");
    $b->task2('ok', "site: $host");
    
} ) ($mail, $ip, $host);

$limit = false;
$claim = true;
$SLDONE = true;
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
    
    $setF = 0; 
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
                
                if (!$SLDONE || !$ADDONE) {
                    $setF = microtime(true);
                    break;
                }
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
                
            }
            
            if (!empty($po)) {
                #print_r($po);
                $cla = Net::C($host.'/faucet', 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, ip: $ip);
                if (empty($cla) || ($cla === 99)) continue;
                
                #_put('cla.html', $cla);
                
                if (checkATB($atbfail, $cla)) continue;
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
    #_put('ads.html', $ads); die;
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        #print_r($ptcList); die;
        
        if (!empty($ptcList['local'])) {
            
            foreach ($ptcList['local'] as $ptc) {
                [$ad_u, $ad_t] = $ptc;
                $cla = null;
                $view = null;
                
                $view = Net::C($ad_u, 'GET', null, inf::$cookie, [], "$host/ptc", inf::$uagent, false, false, $ip);
                if ($view === 99) continue 2;
                if (!empty($view) && $view !== 99) {
                    $po = null;
                    $f = scraper::payload($view)[0] ?? [];
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        
                        $uid = Scraper::find($view, 'uid', 'input', 'value', 'id')[0] ?? null;
                        $idd = Scraper::_pP($view, 'let id')[0] ?? null;
                        preg_match("/let count = (\d+)/", $view, $cnt);
                        if (isset($cnt[1])) $ad_t = (int)$cnt[1];
                    
                        $go = ['uid' => $uid,'c' => $idd.rand(1, 9999)];
                        Net::C("$host/surf", 'GET', $go, inf::$cookie, [], $ad_u, inf::$uagent);
                        
                        $cap = solve::exec($view, $ad_u, $api, $pa);
                        if (isset($cap['trouble'])) {
                            _sle(60);
                            continue;
                        }
                        $po = array_merge($go, $cap);
                        
                    }
                    
                    if (!empty($po)) {
                        styler("waiting for ads: $ad_t", fn() => _sle($ad_t));
                        $cla = json_decode(Net::X("$host/ajax/surf", 'POST', $po, inf::$cookie, [], $ad_u, inf::$uagent)?: '', 1)['message'] ?? null;
                        
                        if (!empty($cla)) {
                            Logger::M($mail);
                            Logger::G(0, $cla);
                            $endF = microtime(true);
                            if ($setF > 0 && $claim) {
                                $balik = $endF - $setF;
                                if ($balik >= 4 * 60) continue 2;
                            }
                        }
                        
                        
                        
                    }
                    
                }
                
            }
            
        } else {
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
            
            if ($ptcNumb <= 1) $ADDONE = true;
        }
        
        
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
        
    }
    
    if (!$claim && $SLDONE && $ADDONE) {
        print(FGd['CYN'].maskEmail($mail).RSET." ");
        (logx('err', 'beres') ?: die);
    }
    
}



tes:





function parsePtcAds($html, $host) {
    if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $xp = Scraper::dom($html);
    if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
    $baseUrl = rtrim((parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host), '/');
    
    $urls = Scraper::_xP($html, "//a[contains(@href, '/surf/') and not(contains(@class, 'd-none'))]/@href");
    
    $timers = Scraper::_xP($html, "//div[contains(@class, 'pill sec')]");
    
    foreach ($urls as $i => $href) {
        if (strpos($href, 'http') !== 0 && strpos($href, '//') !== 0) {
            $url = (strpos($href, '/') === 0) ? $baseUrl . $href : $baseUrl . '/' . $href;
        } elseif (strpos($href, '//') === 0) {
            $url = 'https:' . $href;
        } else {
            $url = $href;
        }
        
        $timer = 5;
        if (isset($timers[$i])) {
            $text = trim($timers[$i]);
            if (preg_match('/(\d+)\s*s/', $text, $tm)) {
                $timer = (int)$tm[1];
            }
        }
        
        $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
        
        if ($uHost === $host) $result['local'][] = [$url, $timer];
        elseif (strpos($url, 'bitcotasks.com') !== false) $result['bctt'][] = [$url, $timer];
        elseif (strpos($url, 'offerwall.me') !== false) $result['owme'][] = [$url, $timer];
        else $result['external'][] = [$url, $timer];
    }
    
    $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
    
    return $result;
}