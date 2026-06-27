<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['mail', 'pass', 'PROXY']);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://freeltc.online';
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

$hhh = inf::netHead(['uf' => md5($mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
$limit = false;
$claim = true;
$SLDONE = false;
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
        $l = inf::check("$host/dashboard", $hhh, '/register');
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
            $cre = ['uf' => md5($mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'email' => $mail, 'password' => $pass];
            $cap = Solve::exec($_0, $host, $api, $pa);
            
            if (isset($cap['trouble'])) {
                _sle(10);
                continue;
            }
            $po = array_merge($pa, $cap, $cre);
        }
        
        if (!empty($po)) {
            #print_r($po);
            
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $hhh, "$host/login", inf::$uagent, ip: $ip);
            
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
    
    $setF = 0;
    if (!$limit && $claim) {
        $ret99 = 0; 
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, inf::$cookie, $hhh, "$host/dashboard", inf::$uagent, false, false, $ip);
            
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
            if (!empty($f) && stripos($f['url'], 'faucet')) {
                #print_r($f);
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
                
                
                if (!$SLDONE || !$ADDONE) {
                    $setF = microtime(true);
                    break;
                }
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
            }
            
            if (!empty($po)) {
                #print_r($po); die;
                
                $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, $hhh, "$host/faucet", inf::$uagent, ip: $ip);
                if (empty($cla) || ($cla === 99)) continue;
                #_put('cla.html', $cla); #die;
                
                if (checkATB($atbfail, $cla)) continue;
                $m = Scraper::_jP($cla, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/") ?? null;
                
                if (isset($m[1][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(0, $m[1][0]);
                    
                    $atbforce = false;
                    $atbfail = 0;
                    
                    if (stripos($m[1][0], 'has been added')) {
                        $setF = microtime(true);
                        break;
                    }
                }
                
                
            }
            
        }
        
    }
    
    $ads = Net::C("$host/ptc", 'GET', null, inf::$cookie, $hhh, "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('ads.html', $ads);
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        
        if ($ptcNumb <= 1) {
            $ADDONE = true;
        } else {
            #print_r($ptcList);
            
            if (!empty($ptcList['local']) && !$ADDONE) {
                foreach ($ptcList['local'] as $ptc) {
                    
                    $ADDONE = true;
                    continue;
                    
                    [$ad_u, $ad_t] = $ptc;
                    $cla = null;
                    $view = null;
                    
                    $view = Net::C($ad_u, 'GET', null, inf::$cookie, [], "$host/ptc", inf::$uagent, false, false, $ip);
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
                            $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $hhh, $ad_u, inf::$uagent, false, true, $ip);
                            _put('cla.html', $cla); die;
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            
                        }
                        
                        
                    }
                    
                    
                    
                die;
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
            
            /*
            if (!empty($ptcList['zono'])) {
                
                foreach ($ptcList['zono'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    
                    zono($ad_u, $ad_t, $api, $host, $mail);
                    
                die;
                }
            }
            */
            
            
        }
        
    }
    
    if (!$claim && $SLDONE && $ADDONE) {
        
        if ($ALLDONE <= 3) {
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
    if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    
    $xp = Scraper::dom($html);
    if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    
    $result = ['local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
    $baseUrl = rtrim((parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host), '/');
    
    $cards = $xp->query("//div[contains(@class, 'card')][.//button[@onclick]]");
    
    foreach ($cards as $card) {
        $btn = $xp->query(".//button/@onclick", $card);
        if ($btn->length === 0) continue;
        
        $onclick = $btn->item(0)->value;
        $url = '';
        
        if (preg_match("/go_btn\s*\(\s*'([^']+)'/", $onclick, $m)) {
            $url = $m[1];
        }

        elseif (preg_match("/window\.location\s*=\s*'([^']+)'/", $onclick, $m)) {
            $url = $m[1];
        }
        
        if (empty($url)) continue;
        
        if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
            $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        
        $timer = 5;
        $timerEl = $xp->query(".//span[contains(text(), 'seconds')]", $card);
        if ($timerEl->length > 0) {
            $text = trim($timerEl->item(0)->textContent);
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

