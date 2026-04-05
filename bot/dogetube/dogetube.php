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
                    $t = tK($api, $userAgent);
                    #$t = _cd('token');
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
    #print_r($_fe); 

    if (strlen($_fee) > 100 && isset($_fe['items'])) {
        foreach ($_fe['items'] as $item) {
            $content = $item['id'];
            $sec = rand(5, 14);

            styler("wait: $sec", fn() => _sle($sec));

            $cla = json_decode(Net::X($host.'/api/feed/reward', 'POST', ['contentId' => $content, 'watchedSeconds' => $sec], $cookieFile, headers('', $host."/app", $domain), '', $userAgent, true) ?: '', true);
            #var_dump($cla);
            #print_r($cla);
            if (empty($cla)) continue;
            if (isset($cla['ok']) && !empty($cla['balance'])) {
                logx('ok', 'balance: '.$cla['balance']);
            }
        }
    }

    if (empty($_fe['items']) && empty($_fe['nextOffset'])) {
        logx('err', 'daily limit');
        
        while (true) {
            $_pay = json_decode(Net::X($host.'/api/wallet/me', 'GET', null, $cookieFile, headers('', $host."/app/payouts", $domain), '', $userAgent, true) ?: '', true); 
            
            if (!empty($_pay) && isset($_pay['balance'])) {
                $currentBal = (float)$_pay['balance'];

                if ($currentBal < 1.1) {
                    break; 
                }
                
                $wallet = _cd($login.'::wallet');
                $amount = $currentBal;
                
                logx('info', "mencoba withdraw $amount ke $wallet");
                
                $_wd = json_decode(Net::X($host.'/api/wallet/withdraw', 'POST', ['amount' => $amount, 'address' => $wallet], $cookieFile, headers('', $host."/app/payouts", $domain), '', $userAgent, true) ?: '', true);
                
                if (!empty($_wd) && isset($_wd['trackId'])) {
                    logx('ok', "ID: {$_wd['trackId']} | $wallet::$amount");
                    exit(0);
                } else {
                    logx('err', json_encode($_wd));
                    break;
                }
            } else {
                break;
            }
        }
        exit(20);
    }

    
}



function tK($api, $ua) {
    if (!$api) {
        logx('err', 'undefined provider'); exit(1);
    }
    
    while (($t = $api->token('5c6afbd6-b434-4a74-880c-27103632b59c', 'https://doge.tube', 'hc', ['userAgent'=>$ua, 'proxy' => '', 'invisible' => 1])) === false);
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
        #var_dump($html);
        if ($html !== false) {
            if (preg_match('/' . preg_quote($key, '/') . '\s*=\s*(\S+)/i', $html, $m)) {

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
#var_dump(tk($api, $userAgent));

$t = _cd($login.'::wallet');

var_dump($t);
