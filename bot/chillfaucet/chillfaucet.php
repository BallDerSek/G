<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'cwallet', 'PROXY']);
$login = $acc['login'];
$cwid = $acc['cwallet'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://chillfaucet.in';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=31169&xpost=true';
$ip = '156.67.104.252';
$ip = '80.65.208.108';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $c = config::credential(['ua' => fn() => config::uagent('mobile')]);
    $userAgent = $c['ua'];
    
    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
    print(UNDR.BOLD."site:");
    logx('ok', " $host");
} ) ($login, $ip, $host);

$hhh = inf::netHead(['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
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
        
        $l = inf::check("$host/dashboard", $hhh, '/auth/validation', true);
        
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
        Net::X($host.$r, 'GET', null, inf::$cookie, $hhh, '', inf::$uagent);
        $_0 = Net::X($host, 'GET', null, inf::$cookie, $hhh, $host.$r, inf::$uagent);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        
        $f = scraper::payload($_0)[0] ?? [];
        $po = null;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $login];
            $cap = solve::exec($_0, $host, $api, $pa);
            if (isset($cap['trouble'])) {
                _sle(10);
                continue;
            }
            $po = array_merge($pa, $cap, $cre);
        }
        if (!empty($po)) {
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $hhh, '', inf::$uagent);
            #var_dump($ve);
            
        }
        
    #die;
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    #if (!empty($cwid)) $cid = linkCW($dash, $cwid, $hhh, $host);
    
    $_fa = [];
    $xpath = Scraper::dom($dash);
    $allLi = $xpath->query("//li[.//span[text()='Faucet']]//ul[@class='pc-submenu']/li");
    $stop = false;
    foreach ($allLi as $li) {
        $text = trim($li->textContent);
        
        if ($text === 'Cwallet') break;
        
        if ($text === 'FaucetPay') continue;
        
        $link = $xpath->query(".//a", $li)->item(0);
        if (!$link) continue;
        
        $url = $link->getAttribute('href');
        
        if (preg_match('/Claim\s+([A-Z]+)/', $text, $m)) {
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
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $hhh, $host, inf::$uagent);
            
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
                $pa = $f['payload'];
                $crr = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()];
                $cap = solve::exec($fau, $fa, $api, $pa);
                if (isset($cap['trouble'])) {
                    _sle(10);
                    continue;
                }
                $po = array_merge($pa, $cap, $crr);
                
            } else {
                
                if (preg_match('/<b id="minute">(\d+)<\/b>:<b id="second">(\d+)<\/b>/', $fau, $m)) {
                    styler("waiting for next claim", fn() => _sle((int)$m['2']));
                    continue;
                }
                
                if (str_contains($fau, 'Verification Required')) {
                    logx('err', 'verify ur account!', false);
                    
                    $telegramUrl = Scraper::_xP($fau, "//a[contains(@href, 't.me/')]/@href")[0] ?? null;
                    if ($telegramUrl) logx('info', " $telegramUrl");
                    die;
                }
                
                continue 3;
            }
                
            if (!empty($po)) {
                
                $bo = '';
                $body = SolveUtils::webkitID($po, $bo);
                $head = [$he, "Content-Type: multipart/form-data; boundary=$bo"];
                
                $cla = json_decode(Net::X($f['url'], 'POST', $body, inf::$cookie, array_merge($hhh, $head), $fa, inf::$uagent)?: '', 1);
                if (!empty($cla) && isset($cla['status'])) {
                    $stt = $cla['status'];
                    $msg = trim(strip_tags($cla['msg'])) ?? 'unknown';
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
                    
                    if (stripos($msg, 'nvalid Claim') !== false) break;
                    if (stripos($msg, 'link your Cwallet') !== false) {
                        
                        $habis[$fa] = true;
                        break;
                    }
                    
                }
                
                styler("waiting for next claim", fn() => _sle(5));
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
        
        if ($curr_id) Net::X($host . '/account/change_currency','GET',['method' => $curr_id],inf::$cookie,$hhh,$host.'/ptc',inf::$uagent);
        
        $ads = Net::X($host.'/ptc', 'GET', null, inf::$cookie, $hhh, $host, inf::$uagent);
        #_put('ads.html', $ads);
        if ($ads99 >= 3) goto login;
        if ($ads === 99 || empty($ads)) {
            $ads99++;
            continue;
        }
        
        if (!empty($ads)) {
            
            $ptcList = parsePTC($ads);
            #print_r($ptcList); die;
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
                        $ch = postPTC($data, $host.'/ptc/verify/'.$ptc['adId'], array_merge($hhh, $he), true);
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
                        
                        $ch = postPTC($body, $f['url'], array_merge($hhh, $head));
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









function linkCW($html, $cwid, $hhh, $host) {
    if (preg_match("/decodeURIComponent\(match\[1\]\)\s*:\s*'([^']+)'/", $html, $m)) $token = $m[1];
    
    if (preg_match("/token\s*=\s*'([^']+)'/", $html, $m)) $token = $m[1];
    
    $pe = ['cwallet_id' => $cwid, 'csrf_test_name' => $token];
    
    return Net::X($host.'/dashboard/link_cwallet', 'POST', $pe, inf::$cookie, $hhh, $host, inf::$uagent);
    
}

function parsePTC($html) {
    $result = [];
    $seen = [];
    $xpath = Scraper::dom($html);
    $cards = $xpath->query("//div[contains(@class, 'col-md-4')]//div[contains(@class, 'card')]");
    
    foreach ($cards as $card) {
        $button = $xpath->query(".//button", $card)->item(0);
        if (!$button) continue;
        
        $onclick = $button->getAttribute('onclick');
        $value = $button->getAttribute('value');
        
        $titleElem = $xpath->query(".//h4", $card)->item(0);
        $title = trim($titleElem ? $titleElem->textContent : '');
        
        $rewardSpan = $xpath->query(".//i[contains(@class, 'ti-gift')]/..", $card);
        $reward = trim($rewardSpan->item(0) ? $rewardSpan->item(0)->textContent : '');
        
        $timerSpan = $xpath->query(".//i[contains(@class, 'ti-clock')]/..", $card);
        $timerText = trim($timerSpan->item(0) ? $timerSpan->item(0)->textContent : '');
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
        
        if (strpos($onclick, 'startview') !== false) {
            if (preg_match('/startview\([^,]+,\s*(\d+),\s*(\d+)/', $onclick, $m)) {
                $entry['type'] = 'telegram';
                $entry['url'] = $value;
                $entry['timer'] = (int)$m[1];
                $entry['adId'] = (int)$m[2];
                $entry['domain'] = parse_url($value, PHP_URL_HOST);
            }
        } 
        elseif (strpos($onclick, 'go_btn') !== false) {
            if (preg_match("/go_btn\('([^']+)'/", $onclick, $m)) {
                $entry['url'] = $m[1];
            } elseif (strpos($onclick, 'this.value') !== false && $value) {
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