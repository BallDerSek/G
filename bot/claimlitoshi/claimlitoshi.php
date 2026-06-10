<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'cwallet', 'PROXY']);
$login = $acc['login'];
$cwid = $acc['cwallet'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://claimlitoshi.top';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=38637&xpost=true';
$ip = '154.26.138.53';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');
    
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
$curr = '';
$habis = [];
$ptcc = false;

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host/dashboard", $headersCF, '/auth/validation');
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
        Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        $_0 = Net::X($host, 'GET', null, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
        #_put('0.html', $_0);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        
        $f = scraper::payload($_0)[0] ?? [];
        $po = null;
        $he = '';
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['wallet' => $login];
            $cap = solve::exec($_0, $host, $api, $pa);
            if (isset($cap['trouble'])) {
                _sle(10);
                continue;
            }
            if (isset($cap['headers'])) {
                $po = array_merge($pa, $cap['solution'], $cre);
                $he = $cap['headers'];
            }
            
        }
        
        if (!empty($po)) {
            
            $bo = '';
            $body = SolveUtils::webkitID($po, $bo);
            $head = [$he, "Content-Type: multipart/form-data; boundary=$bo"];
            
            $ve = Net::X($f['url'], 'POST', $body, inf::$cookie, $head, $host.$r, inf::$uagent);
            #var_dump($ve);
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash);
#goto ptc;
    $_fa = [];
    $xpath = Scraper::dom($dash);
    $links = $xpath->query("//li[.//span[text()='Faucet']]//ul[@class='pc-submenu']//a");
    foreach ($links as $link) {
        $text = trim($link->textContent);
        if (!str_contains($text, '~ FP')) continue;
        $url = $link->getAttribute('href');
        if (preg_match('/Claim\s+([A-Z]+)\s+~/', $text, $m)) {
            $_fa[] = [
                'url' => $url,
                'coin' => $m[1]
            ];
        }
    }
    
    foreach ($_fa as $data) {
        if (!$claim) break;
        $fa = $data['url'];
        $_c = $data['coin'];
        
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
            
            if (isset($habis[$fa])) {
                $curr = '';
                continue 2;
            }
            
            $po = null;
            $cap = [];
            $he = '';
            $f = scraper::payload($fau)[0] ?? null;
            
            if (str_contains($fau, 'limit reached')) {
                $curr = '';
                $habis[$fa] = true;
                break;
            }
            
            if (!empty($f) && stripos($f['url'], 'faucet')) {
                #print_r($f); die;
                $pa = $f['payload'];
                $cap = Solve::exec($fau, $host, $api, $pa);
                
                if (isset($cap['trouble'])) continue;
                if (isset($cap['headers'])) {
                    $po = array_merge($pa, $cap['solution']);
                    $he = $cap['headers'];
                } else $po = array_merge($pa, $cap);
                
            } else {
                
                if (stripos($fau, 'Enter Your Cwallet ID') !== false) {
                    
                    /*
                    if (!empty($cwid)) {
                        $pa = $f['payload'];
                        $crw = ['cwallet' => $cwid];
                        $cap = Solve::exec($fau, $host, $api, $pa);
                        
                        if (isset($cap['headers'])) {
                            $po = array_merge($pa, $cap['solution'], $crw);
                            $he = $cap['headers'];
                        } else $po = array_merge($pa, $cap);
                        $ver = json_decode(Net::X(
                            $f['url'],
                            'POST',
                            SolveUtils::webkitID($po, $bo),
                            inf::$cookie,
                            [$he, "Content-Type: multipart/form-data; boundary=$bo"],
                            '',
                            inf::$uagent
                        )?: '', 1)['status'] ?? null;
                        
                        if (!empty($ver) && $ver == 'success') continue 3;
                    }
                    */
                    $habis[$fa] = true;
                    break;
                    
                }
                
                if (preg_match('/<b id="minute">(\d+)<\/b>:<b id="second">(\d+)<\/b>/', $fau, $m)) {
                    styler("waiting for next claim", fn() => _sle((int)$m['2']));
                    continue;
                }
                
                
                continue 3;
            }
                
            if (!empty($po)) {
                $bo = '';
                $body = SolveUtils::webkitID($po, $bo);
                $head = [$he, "Content-Type: multipart/form-data; boundary=$bo"];
                
                $cla = json_decode(Net::X($f['url'], 'POST', $body, inf::$cookie, $head, $fa, inf::$uagent)?: '', 1);
                if (!empty($cla) && isset($cla['status'])) {
                    $stt = $cla['status'];
                    $msg = $cla['msg'] ?? 'unknown';
                    $is_ok = (stripos($stt, 'success') !== false);
                    print(FGd['CYN'].maskEmail($login).RSET." ");
                    
                    logx($is_ok ? 'ok' : 'err', "{$stt} ", false);
                    logg(false, $msg);
                    
                    if (stripos($msg, 'No Faucet EXP left') !== false) {
                        $ptcc = true;
                        $curr_id = basename(parse_url($fa)['path']);
                        $curr = $_c;
                        
                        break 2;
                    }
                    if (preg_match('/sufficient|could not be processed/i', $msg)) {
                        $habis[$fa] = true;
                        break;
                    }
                    if (stripos($msg, 'Shortlink')) {
                        if ($SLDONE) (logx('err', 'Gada SL lagi') ?: die);
                        $curr = $_c;
                        break 2;
                    }
                    
                    
                }
                
                styler("waiting for next claim", fn() => _sle(25));
            }
            
        }
        
    }

    if (count($habis) === count($_fa)) {
        $claim = false; goto ptc;
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
    }

    ptc:
    $ads99 = 0;
    $bcttView = 0;
    $viewed = false;
    while ($ptcc || !$viewed) {
        
        if ($curr_id) Net::X($host . '/account/change_currency','GET',['method' => $curr_id],inf::$cookie,[],$host.'/ptc',inf::$uagent);
        $ads = Net::X($host.'/ptc', 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent);
        
        if ($ads99 >= 3) goto login;
        if ($ads === 99 || empty($ads)) {
            $ads99++;
            continue;
        }
        
        if (!empty($ads)) {
            
            $ptcList = parsePTC($ads);
            #print_r($ptcList);
            foreach ($ptcList as $ptc) {
                
                
                
                if ($ptc['domain'] == 'bitcotasks.com') {
                    
                    #$ch = bct($api, $ptc['url'], $ptc['timer']);
                    $bctt = new Bctt($host, $api, $login);
                    $ch = $bctt->exec($ptc['url'], $ptc['timer']);
                    if ($ch === 99) goto login;
                    
                    if ($bcttView >= 10) {
                        $bctt->cleanup();
                        $viewed = true;
                        $ptcc = false;
                        break;
                    }
                    
                    if ($ch) {
                        $ptcc = false;
                        $bcttView++;
                    }
                }
                
                if ($ptc['domain'] == 't.me') {
                    styler("waiting for ads", fn() => _sle($ptc['timer']));
                    $data = null;
                    $f = scraper::payload($ads, 'submit_form')[0] ?? [];
                    
                    if ($f) {
                        $he = '';
                        $pa = $f['payload'];
                        $sol = Solve::exec($ads, $host, $api, $pa);
                        if (isset($sol['trouble'])) goto login;
                        
                        if (isset($sol['headers'])) {
                            $data = array_merge($pa, $sol['solution']);
                            $he = $sol['headers'];
                        } else $data = array_merge($sol, $pa);
                        
                    }
                    
                    if ($data) {
                        $ch = postPTC($data, $host.'/ptc/verify/'.$ptc['adId'], [$he], true);
                        if ($ch) {
                            $viewed = true;
                            $ptcc = false;
                            break;
                        }
                    }
                    
                    
                }
                
                if ($ptc['domain'] == 'claimlitoshi.top') {
                    
                    $view = Net::C($ptc['url'], 'GET', null, inf::$cookie, [], $host, inf::$uagent);
                    
                    if ($view === 99) goto login;
                    $data = null;
                    $f = scraper::payload($view)[0] ?? [];
                    if (!empty($f)) {
                        styler("waiting for ads", fn() => _sle($ptc['timer']));
                        $he = '';
                        $pa = $f['payload'];
                        $sol = Solve::exec($ads, $host, $api, $pa);
                        if (isset($sol['trouble'])) goto login;
                        
                        if (isset($sol['headers'])) {
                            $data = array_merge($pa, $sol['solution']);
                            $he = $sol['headers'];
                        } else $data = array_merge($sol, $pa);
                        
                    }
                    
                    if (!empty($data)) {
                        $bo = '';
                        $body = SolveUtils::webkitID($data, $bo);
                        $head = [$he, "Content-Type: multipart/form-data; boundary=$bo"];
                        
                        $ch = postPTC($body, $f['url'], $head);
                        if ($ch) {
                            $viewed = true;
                            $ptcc = false;
                            break;
                        }
                        
                    }
                    
                }
                
                
                
            }
            
            
            if (!$viewed && $ptcc === true) die;
        }
    }
    
    
}



tes:
    





function parsePTC($html) {
    $result = [];
    $xpath = Scraper::dom($html);
    $cards = $xpath->query("//div[contains(@class, 'col-md-6')]//div[contains(@class, 'card')]");
    
    foreach ($cards as $card) {
        $button = $xpath->query(".//button", $card)->item(0);
        if (!$button) continue;
        
        $onclick = $button->getAttribute('onclick');
        $value = $button->getAttribute('value');
        $title = trim($xpath->query(".//h5", $card)->item(0)->textContent ?? '');
        $reward = trim($xpath->query(".//span[contains(@class, 'bg-light-primary')]", $card)->item(0)->textContent ?? '');
        $timerText = trim($xpath->query(".//span[contains(@class, 'bg-light-warning')]", $card)->item(0)->textContent ?? '');
        $timer = (int) filter_var($timerText, FILTER_SANITIZE_NUMBER_INT);
        
        $entry = [
            'title' => $title,
            'reward' => $reward,
            'timer' => $timer,
            'type' => null,
            'url' => null,
            'adId' => null,
            'domain' => null
        ];
        
        // startview
        if (strpos($onclick, 'startview') !== false) {
            if (preg_match('/startview\([^,]+,\s*(\d+),\s*(\d+)/', $onclick, $m)) {
                $entry['type'] = 'telegram';
                $entry['url'] = $value;
                $entry['timer'] = (int)$m[1];
                $entry['adId'] = (int)$m[2];
                $entry['domain'] = parse_url($value, PHP_URL_HOST);
            }
        } 
        // go_btn
        elseif (strpos($onclick, 'go_btn') !== false) {
            if (preg_match("/go_btn\('([^']+)'/", $onclick, $m)) {
                $entry['url'] = $m[1];
            } 
            elseif (strpos($onclick, 'this.value') !== false && $value) {
                $entry['url'] = $value;
            }
            
            if ($entry['url']) {
                $entry['type'] = 'direct';
                $entry['domain'] = parse_url($entry['url'], PHP_URL_HOST);
            }
        }
        
        if ($entry['url'] && !isset($seen[$entry['url']])) {
            $seen[$entry['url']] = true;
            $result[] = $entry;
        }
    }
    
    return $result;
}

function postPTC($data, $url, $head, $un = false) {
    
    $ver = Net::X($url, 'POST', $data, inf::$cookie, $head, '', inf::$uagent);
    #_put('ver.html', $ver);
    
    if (strpos($ver, 'have been credited') !== false) {
        if (preg_match('/message:\s*"([^"]+ credited to your Faucetpay account)"/', $ver, $m)) logg(true, $m[1]);
        return true;
    }
    
    return false;
    
}


