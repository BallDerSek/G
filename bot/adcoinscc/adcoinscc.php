<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent('mobile');

$host = 'https://adcoins.cc';
$r = '/?ref=6851'; 
$ip = '';

inf::setup($userAgent, $cookieFile, $ip);

banner(); 
taskPrintCenter($login, 'info');
login:

$dash = null;
while (true) {
    $max = 7;
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
        if ($ret >= $max) {
            logx('err', 'gak tau');
            exit; 
        }
        
        @unlink($cookieFile);
        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
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

            Net::C($host.'/api.php', 'POST', $pa, $cookieFile, $ha, $host.$r, $userAgent);
        }
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    while (true) {
        $ret99 = 0; 
        $fau = Net::C($host.'/faucet', 'GET', null, $cookieFile, [], '', $userAgent);
        #_put('fau.html', $fau);
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
        
        if (!empty($fau)) {

            $tmr = Scraper::_jP($fau, '/initCooldown\((?<v>\d+)\)/');
            $ti = (int)($tmr['v'][0] ?? 0);
            if ($ti > 0) {
                styler("WAITING for CLAIM", fn() => _sle($ti));
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
                $cla = json_decode(Net::C($host.'/api.php', 'POST', $body, $cookieFile, $ha, $host.$r, $userAgent)?: '', true);
                $suc = filter_var($cla['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($suc) {
                    logx('ok', 'success ', false, true);
                    logg(false, ' roll: '.$cla['random_number'], false);
                    logx('', " [ {$cla['new_balance']} ]", true, true);
                } else {
                    logx('err', 'failed ', false, true);
                    logg(false, $cla['message']);
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
