<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$cookieFile = getCookie();
$userAgent = getUagent();
$acc = credential([], true);
$login = $acc['login'];

$host = 'https://cryptoclaps.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '91.204.209.6';

banner(); 
login:

while (true) {
    $claim = false;
    
    do {
        $_0 = Net::C($host, 'GET', null, $cookieFile, [], '', $userAgent);
        if (!empty($_0)) {
            _put('0.html', $_0);
            $capt = capt::cha($_0);
            $curr = xScraper::xPath($_0, "//select[@id='coin-select']/option[not(@disabled)]/@value") ?? '';
            $csrf = rScraper::jPath($_0, "/csrf_token['\"]?\s*,\s*['\"]([a-f0-9]{32,})/i") ?? '';
            #print_r($csrf);
            if ($csrf && $curr) {
                $claim = true;
                continue;
            }
        } 
    } while (!$claim);
    
    if (!$claim) continue;
    logx('ok', 'using '.$login,true,true);
    while (true) {
    $set = microtime(true);
        $he = headers($host, $host, $domain);
        
        $_pa = [
            'action' => 'validate_wallet',
            'wallet' => $login,
            'csrf_token' => $csrf[1][0]
        ];
        $boundary = '';
        $pa = webkitId($_pa, $boundary);
        $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];
        $head = array_merge($he, $ha);
        
        $_1 = json_decode(Net::C($host, 'POST', $pa, $cookieFile, $head, $host, $userAgent)?: '', true);
        
        if (empty($_1)) continue;
        var_dump($_1);
        if (!empty($_1) && empty($v['valid'])) {
            
            $_pe = [
                'email' => $login,
                'action' => 'get_channel',
                'csrf_token' => $csrf[1][1] ?? $csrf[1][0]
            ];
            $pe = webkitId($_pe, $boundary);
        }
        
        $_2 = json_decode(Net::C($host, 'POST', $pe, $cookieFile, $head, $host, $userAgent)?: '', true);
        
        if (empty($_2)) continue;
        var_dump($_2);
        if (!empty($_2['success']) && !empty($chnl = $_2['channel'])) {
            
            $retry = 0;
            while (($t = getKeys($api)->token($capt['keys'][0], $host, $capt['type'])) === false && $retry++ < 5);
            #if (!$t) continue;
            
            $_po = [
                'action' => 'send_reward',
                'wallet' => $login,
                'email' => $login,
                'csrf_token' => $csrf[1][2] ?? $csrf[1][0],
                'channel_url' => $chnl['url'],
                'cf-turnstile-response' => $t,
                'coin' => $curr[0]
            ];
            $po = webkitId($_po, $boundary);
            
            $end = microtime(true);
            $wait = $chnl['watch_time'] - ($end - $set);
            if ($wait >= 0) {
                styler("waiting $wait", fn() => _sle((int)ceil($wait)));
            }
            
        } elseif (str_contains($_2['message'], 'All tasks completed')) {
            print(DIMM.BOLD.FGo['MAG']."  $login  ".RSET);
            logx('err', $_2['message']);
            break 2;
            
        }
        $_3 = json_decode(Net::C($host, 'POST', $po, $cookieFile, $head, $host, $userAgent)?: '', true);
        var_dump($_3);
        if (!empty($_3['success'])) {
            print(DIMM.BOLD.FGo['MAG']."  $login  ".RSET);
            logx('info', $_3['message'], true, true);
        } elseif (!empty($_3['message'])) {
            logx('err', $_3['message']);
        }
        
    }

}
