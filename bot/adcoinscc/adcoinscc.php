<?php
if (!defined('ROOT')) { die; }
#$api = onKeys();
$api = null;

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://adcoins.cc';
$r = '/?ref=6851'; 
$ip = '';

(function ($login, $ip) {
    Proxy::load();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
})($login, $ip);

$dash = null;
$saldo = 0;
$withdraw = false;
while (true) {
    $ret = 0;
    $ret99 = 0;
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', [], 'loginForm');
        
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
        
        @unlink(inf::$cookie);
        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, [], '', inf::$uagent);
        if ($_0 === 99) {
            $ret99++;
            logx('warn', "masalah proxy, warm up dulu");
            if ($ret99 >= 5) {
                goto login;
            }
            _sle(60);
            continue;
        }
        
        if (!empty($_0)) {
            $pa = solveUtils::webkitID(['action' => 'login', 'email' => $login, 'csrf_token' => ''], $boundary);
            $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];

            Net::C($host.'/api.php', 'POST', $pa, inf::$cookie, $ha, $host.$r, inf::$uagent);
        }
    } while (empty($dash));
    #_put('dash.html', $dash);

    {
    $bon = Net::C($host.'/achievements', 'GET', null, inf::$cookie, [], '', inf::$uagent);
    
    if (!empty($bon) && $bon !== 99) {
        $claims = Scraper::_xP($bon, "//button[contains(@*, 'claim')]/@*");

        foreach ($claims as $act) {
            if (preg_match('/claim\((\d+)/', $act, $m)) {
                $boundary = '';
                $po = SolveUtils::webkitID(['action' => 'claim_achievement', 'achievement_id' => $m[1]], $boundary);
                
                $ver = json_decode(Net::C($host.'/api.php', 'POST', $po, inf::$cookie, ["Content-Type: multipart/form-data; boundary=$boundary"], '', inf::$uagent) ?: '', true);
                
                if (filter_var($ver['success'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    logg(true, 'success ', false);
                    logx('info', ' reward: '.($ver['reward'] ?? 'OK'), true, true);
                }
                usleep(500000);
            }
        }
    }
}
    
    $bal = Scraper::_xP($dash, "//p[contains(text(), 'Balance')]/following-sibling::p")[0] ?? '0';
    $saldo = preg_replace('/[^0-9.]/', '', $bal);
    
    $faucetCount = 0; 
    while (true) {
        if ($faucetCount >= 10) break;
        if ($saldo >= (int)1000) {
            $withdraw = true;
            break;
        }

        $ret99 = 0; 
        $fau = Net::C($host.'/faucet', 'GET', null, inf::$cookie, [], '', inf::$uagent);
        
        if ($fau === 99) {
            $ret99++;
            logx('warn', "masalah proxy, warm up dulu");
            if ($ret99 >= 5) goto login;
            _sle(30);
            continue;
        }
        
        if (!empty($fau)) {
            #_put('fau.html', $fau);
            $tmr = Scraper::_jP($fau, '/initCooldown\((?<v>\d+)\)/');
            $ti = (int)($tmr['v'][0] ?? 0);
            
            if ($ti > 0) {
                styler("WAITING for CLAIM", fn() => _sle($ti));
                $faucetCount++; 
                continue;
            }

            $pa = pA($fau, $host);
            if (isset($pa['trouble'])) {
                _sle(50);
                continue;
            }
            
            if (!empty($pa)) {
                $boundary = '';
                $body = SolveUtils::webkitID($pa, $boundary);
                $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];
                $cla = json_decode(Net::C($host.'/api.php', 'POST', $body, inf::$cookie, $ha, $host.$r, inf::$uagent)?: '', true);
                
                $suc = filter_var($cla['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($suc) {
                    logg(true, 'success ', false);
                    logx('info', ' roll: '.$cla['random_number'], false);
                    logx('', " [ {$cla['new_balance']} ]", true, true);
                    $saldo = $cla['new_balance'];
                    $faucetCount++;
                } else {
                    logg(true, 'failed ', false);
                    logx('err', $cla['message']);
                    _sle(10);
                }
            }
        }
    } 
    
    if ($withdraw) {
        $wd = Net::C($host.'/withdraw', 'GET', null, inf::$cookie, [], '', inf::$uagent);
        
        if (!empty($wd) && $wd !== 99) {
            $coins = Scraper::_xP($wd, "//select[@x-model='crypto']/option/@value");
            
            foreach ($coins as $coin) {
                logg(true, '  tes ilmu: '. strtoupper($coin), false);
                logx('info', ' [ '.$login.' ]');

                $boundary = '';
                $jajan = [
                    'action' => 'withdraw',
                    'amount' => $saldo,
                    'crypto' => $coin
                ];
                $ic = null;
                $attempt = 0;
                while (!$ic && $attempt < 5) {
                    $ic = locally::iCaptcha($wd, $host);
                    if ($ic === 99) goto login;
                    $attempt++;
                }
                if ($ic) $pa = array_merge($jajan, $ic);
                $pa = SolveUtils::webkitID($pa, $boundary);
                
                $wd = null;
                $wd = json_decode(Net::C($host.'/api.php', 'POST', $pa, inf::$cookie, ["Content-Type: multipart/form-data; boundary=$boundary"], $host.'/withdraw', inf::$uagent)?: '', true);
                print_r($wd);
                $suc = filter_var($wd['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
                logx('info', 'withdraw ', false, true);
                if ($suc) {
                    logg(false, $wd['message']);
                    break;
                } else {
                    logx('err', $wd['message']);
                }
            }
        }
    }

    
}
    







function pA($html, $host) {

    $_scr = Scraper::_xP($html, "//script[not(@src)]");
    $code = implode("\n", $_scr);
    $_type = Scraper::_jP($code, '/captchaTypes\s*:\s*\[([^\]]+)\]/');
    
    $types = $_type[1][0] ?? '';
    $_cap = array_map(fn($v) => trim($v, ' "\''), explode(',', $types));

    $pa = [
        'action' => 'claim'
    ];

    if (in_array('slider', $_cap)) {
        $target = Scraper::_jP($code, '/sliderTarget\s*:\s*(?<v>\d+)/');
        $token = Scraper::_jP($code, '/sliderToken\s*:\s*[\'"](?<t>[^\'"]+)[\'"]/');
        
        $pa['captcha_token'] = $token['t'][0] ?? null;
        $tVal = (int)($target['v'][0] ?? 0);
        $pa['slider_position'] = $tVal > 0 ? $tVal + rand(-1, 1) : 0;
    }

    $pa['hcaptcha_token'] = "";
    $pa['turnstile_token'] = "";

    if (in_array('iconcaptcha', $_cap)) {
        $ic = null;
        $attempt = 0;
        while (!$ic && $attempt < 5) {
            $ic = locally::iCaptcha($html, $host);
            if ($ic === 99) return ['trouble' => 'proxy'];
            $attempt++;
        }
        if ($ic) $pa = array_merge($pa, $ic);
    }
#die;
    return $pa;
}