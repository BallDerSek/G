<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, /*['login', 'PROXY']*/);
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
$SLDONE = true;
$ALLDONE = 0;
$claim = true;
$curr = '';
$curr_id = '';
$habis = [];

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
    
    $side = Net::X($host.'/offers', 'GET', null, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
    #_put('side.html', $side);
    
    $ads = Net::X("$host/ptc", 'GET', null, inf::$cookie, [], "$host/offers", inf::$uagent);
    var_dump($ads);
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        #print_r($ptcList); #die;
        var_dump($ptcNumb);
        
        
    }
    
    
    
    
    
    
    
die;
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