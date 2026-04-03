<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$cookieFile = getCookie();
$acc = credential(['userAgent' => fn() => getUagent()], true);
$login = $acc['login'];
$userAgent = $acc['userAgent'];

$host = 'https://doge.tube';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=eda70424f7f6';

banner(); 
login: 
    #goto tes;
while (true) {
    $dash = null;
    do {
        $l = checkLogin($host.'/app/profile', headers('', $host, $domain), null, "/auth");
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break;
        }
        
        taskPrintCenter('logging in', 'err');
        Net::C($host.'/app', 'GET', null, $cookieFile, [], $host, $userAgent);
        $lo = json_decode(Net::X($host.'/api/auth/otp/send', 'POST', ['email' => $login], $cookieFile, [], $host."/auth", $userAgent, true) ?: '', true);
        
        if (is_array($lo)) {
            #print_r($lo);
            if (isset($lo['existing']) && $lo['existing'] === false) {
                $needName = filter_var($lo['needName'], FILTER_VALIDATE_BOOLEAN);
                if ($needName) {
                    Net::C($host.$r,'GET', null, $cookieFile, [], '', $userAgent);
                    #$t = tK($api, $userAgent);
                    $t = _cd('token');
                    $lo = json_decode(Net::X($host.'/api/auth/otp/send', 'POST', ['email' => $login, 'hCaptchaToken' => $t, 'name' => strstr($login, '@', true)], $cookieFile, [], $host."/auth", $userAgent, true) ?: '', true);
                    print_r($lo);
                } else {
                    @unlink($cookieFile);
                    continue;
                }
            }
            $ver = json_decode(Net::X($host.'/api/auth/otp/verify', 'POST', ['email' => $login, 'code' => _cd($login)], $cookieFile, [], $host."/auth", $userAgent, true) ?: '', true);
            #print_r($ver);
        } 
        
    } while (empty($dash));
    
    $_fee = Net::X($host.'/api/feed', 'GET', null, $cookieFile, headers('', $host."/app", $domain), '', $userAgent, true) ?: '';
    $_fe  = json_decode($_fee, true);
    print_r($_fe); 

    if (strlen($_fee) > 100 && isset($_fe['items'])) {
        foreach ($_fe['items'] as $item) {
            $content = $item['id'];
            $sec = rand(5, 14);

            styler("wait: $sec", fn() => _sle($sec));

            $cla = json_decode(Net::X($host.'/api/feed/reward', 'POST', ['contentId' => $content, 'watchedSeconds' => $sec], $cookieFile, headers('', $host."/app", $domain), '', $userAgent, true) ?: '', true);
            #print_r($cla);
            if (empty($cla)) continue;
            if (isset($cla['ok']) && !empty($cla['balance'])) {
                logx('ok', 'balance: '.$cla['balance']);
            }
        }
    }
}



function tK($api, $ua) {
    if (!$api) {
        logx('err', 'undefined provider'); exit(1);
    }
    
    while (($t = $api->token('5c6afbd6-b434-4a74-880c-27103632b59c', 'https://doge.tube', 'hc', ['userAgent'=>$ua, 'proxy' => ''])) === false);
    if ($t === null) exit(1);
    
    return $t;
}

function _cd($key = 'code') {
    $baseUrl = getenv('DG');
    if (!$baseUrl) {
        return _rl("$key: ");
    }

    $url = rtrim($baseUrl, '/') . '/get';
    $deadline = time() + 320;

    $payload = ['type' => $key];

    while (time() < $deadline) {
        logx('info', "checking $key");
        $html = Net::X($url, 'POST', $payload, null, [], '', null, true);
        if ($html !== false) {
            if (preg_match('/^' . preg_quote($key, '/') . '=(.+)$/m', trim($html), $m)) {
                return trim($m[1]);
            }
        }
        unset($html);
        sleep(5);
    }

    logx('err', "Timeout: $key");
    exit(1);
}

tes:
var_dump(tk($api, $userAgent));


/*
POST /api/wallet/withdraw HTTP/2

referer: https://doge.tube/app/payouts

{"address":"DGrtQTXnaXBX1WDoHLS2XkKeS7C9SZGCvU","amount":1.004}
*/