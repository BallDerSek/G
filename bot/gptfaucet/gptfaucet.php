<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://gptfaucet.bitcotasks.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?ref=3230';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, false, $login);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$login");
    $b->task2('ok', "site: $host");
    
} ) ($login, $ip, $host);

$headersCF = [];
$skipped = [];
$ADDONE = false;
$BCDONE = false;
$SLDONE = true;
$ALLDONE = 0;
$claim = false;

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        
        $l = inf::check("$host/dashboard", $headersCF, 'loginModalLabel');
        
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
        $_0 = Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
        #_put("0.html", $_0);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        
        $f = scraper::payload($_0)[0] ?? [];
        $po = null;
        
        if (!empty($f)) {
            #print_r($f);
            
            $pa = $f['payload'];
            $cre = ['email' => $login];
            $cap = solve::exec($_0, $host, $api, $pa);
            if (isset($cap['trouble'])) {
                _sle(10);
                continue;
            }
            $po = array_merge($pa, $cap, $cre);
            
        }
        
        if (!empty($po)) {
            #print_r($po);
            $ve = Net::X($host.'/login', 'POST', $po, inf::$cookie, [], $host.$r, inf::$uagent);
            #_put('ve.html', $ve);
        }
        
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    $_bal = Scraper::_xP($dash, "//div[contains(@class, 'card-body')][.//h6[contains(text(), 'Balance')]]//h5[contains(text(), 'Coins')]/text()")[0] ?? null;
    if ($_bal) {
        Logger::M($login);
        Logger::X('info', "[ $_bal ]", true, true);
        $bal = ((int)$_bal);
        
        if ($bal >= 40) {
            $po = null;
            $jjn = [];
            $wd = $dash;
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
                
                $wdd = json_decode(Net::C($host.$jjn['url'], 'POST', $po, inf::$cookie, [], "$host/dashboard", inf::$uagent)?: '', 1)['message'] ?? null;
                if (!empty($wdd)) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    Logger::X('info', $wdd);
                }
            } else {
                Logger::X('err', 'gak bisa wd kayaknya');
            }
        }
        
    }
    
    $setF = microtime(true);
    $ads = Net::X("$host/ptc", 'GET', null, inf::$cookie, [], "$host/offers", inf::$uagent);
    #var_dump($ads);
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        #print_r($ptcList); #die;
        
        if ($ptcNumb == 0) {
            $ADDONE = true;
        } else {
            
            if (!empty($ptcList['local'])) {
                
            }
            
            if (!empty($ptcList['bctt'])) {
                #print_r($ptcList['bctt']);
                foreach ($ptcList['bctt'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    $bctt = new Bctt($host, $api, $login);
                    $ch = $bctt->exec($ad_u, $ad_t);
                    if ($ch === 99) goto login;
                    
                    $endF = microtime(true);
                    if ($setF > 0 && $claim) {
                        $balik = $endF - $setF;
                        if ($balik >= 10 * 60) continue 2;
                    }
                }
            }
            
        }
    }
    
    $off_B = Net::X("$host/offers", 'GET', null, inf::$cookie, [], "$host/offers", inf::$uagent);
    $bctt_I = Scraper::_xP($off_B, "//a[contains(text(), 'Earn More') and contains(@href, 'bitcotasks.com')]/@href")[0] ?? null;
    if (!empty($bctt_I)) {
        $bctt = new bctt($host, $api, $login);
        $bctt_O = $bctt->wall($bctt_I, false, $setF, 4*60);
        if (($bctt_O === 'claim') && $claim) continue;
        if (($bctt_O === 'habis')) $BCDONE = true;
        
    }
    
    if ($SLDONE && $ADDONE && $BCDONE) {
        
        if ($ALLDONE <= 500) {
            $ALLDONE++;
            styler('cooldown', fn() => _sle(600));
            continue;
        }
        
        Logger::M($mail);
        (logx('err', 'beres') ?: die);
        
    }
    
    
}











function parsePtcAds($html, $host) {
    if (empty($html) || $html === 99) {
        return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    }
    
    $result = ['local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
    
    // Parse JSON
    $json = json_decode($html, true);
    if (!$json || empty($json['data'])) {
        return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
    }
    
    foreach ($json['data'] as $item) {
        $url = $item['url'] ?? '';
        $timer = (int)($item['duration'] ?? 5);
        
        if (empty($url)) continue;
        
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
    preg_match('/const currencies = (\{.*?\});/s', $html, $match);
    if (empty($match[1])) return false;
    
    $currencies = json_decode($match[1], true);
    if (empty($currencies)) return false;
    
    $balance = 0;
    $xp = Scraper::dom($html);
    if ($xp) {
        $nodes = $xp->query("//h5[contains(text(), 'Coins')]");
        if ($nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent);
            if (preg_match('/([\d.]+)\s*Coins/', $text, $m)) {
                $balance = (float)$m[1];
            }
        }
    }
    
    $selectedCurrency = null;
    foreach ($currencies as $key => $curr) {
        if (isset($curr['balance_coin']) && $curr['balance_coin'] > 0) {
            $selectedCurrency = $key;
            break;
        }
    }
    
    if (!$selectedCurrency) return false;
    
    $currency = $currencies[$selectedCurrency];
    
    $payload = [
        'amount' => $balance,
        'currency' => $selectedCurrency
    ];
    
    return [
        'url' => '/withdraw/submit',
        'method' => 'POST',
        'payload' => $payload,
        'info' => [
            'coin' => $currency['name'],
            'symbol' => $currency['symbol'],
            'balance' => $balance,
            'usd_price' => $currency['usd_price'],
            'balance_coin' => $currency['balance_coin']
        ]
    ];
}