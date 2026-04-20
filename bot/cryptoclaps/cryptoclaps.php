<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$userAgent = getUagent();
if (!is_file(LIBDIR.'/mail.txt')) {
    logx('err', 'mail.txt not found');
    die;
}
$emails = file(LIBDIR.'/mail.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$host = 'https://cryptoclaps.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '91.204.209.6';

banner(); 

foreach ($emails as $login) {
    $cookieFile = getCookie($login); 
    
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
                
                if ($csrf && $curr) {
                    $claim = true;
                    continue;
                }
            } 
        } while (!$claim);
        
        if (!$claim) continue;
        logx('ok', 'using '.$login, true, true);
        
        while (true) {
            
            $_pa = [
                'action' => 'validate_wallet',
                'wallet' => $login,
                'csrf_token' => $csrf[1][0]
            ];
            $boundary = '';
            $pa = webkitId($_pa, $boundary);
            $head = ["Content-Type: multipart/form-data; boundary=$boundary"];
            
            $_1 = json_decode(Net::C($host, 'POST', $pa, $cookieFile, $head, $host, $userAgent)?: '', true);
            
            if (!empty($_1) && empty($v['valid'])) {
                $_pe = [
                    'email' => $login,
                    'action' => 'get_channel',
                    'csrf_token' => $csrf[1][1] ?? $csrf[1][0]
                ];
                $pe = webkitId($_pe, $boundary);
            } else {
                continue;
            }
            
            $_2 = json_decode(Net::C($host, 'POST', $pe, $cookieFile, $head, $host, $userAgent)?: '', true);
            
            if (!empty($_2['success']) && !empty($chnl = $_2['channel'])) {
                $set = microtime(true);
                
                $retry = 0;
                while (($t = getKeys($api)->token($capt['keys'][0], $host, $capt['type'])) === false && $retry++ < 5);
                
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
                
                $_3 = json_decode(Net::C($host, 'POST', $po, $cookieFile, $head, $host, $userAgent)?: '', true);
                #var_dump($_3);
                if (!empty($_3['success'])) {
                    print(DIMM.BOLD.FGo['MAG']."  $login  ".RSET);
                    logx('info', $_3['message'], true, true);
                } elseif (!empty($_3['message'])) {
                    logx('err', $_3['message']);
                    if (str_contains($_3['message'], 'sufficient')) break;
                }

            } elseif (str_contains($_2['message'] ?? '', 'All tasks completed')) {
                print(DIMM.BOLD.FGo['MAG']."  $login  ".RSET);
                logx('err', $_2['message']);
                break 2; 
            }
        }
    }
}

logx('ok', 'Semua akun di mail.txt selesai.');
