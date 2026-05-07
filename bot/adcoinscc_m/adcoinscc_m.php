<?php
if (!defined('ROOT')) { die; }

$host = 'https://adcoins.cc';
$r = '/?ref=6851'; 

$mailEnv = getenv('MAIL_LIST');
$proxyEnv = getenv('PROXY_LIST');

if ($mailEnv && $proxyEnv) {
    $emailArr = array_values(array_filter(explode("\n", str_replace("\r", "", $mailEnv))));
    $proxyArr = array_values(array_filter(explode("\n", str_replace("\r", "", $proxyEnv))));
} else {
    $mFile = __DIR__.'/email.txt';
    $pFile = __DIR__.'/proxy.txt';

    if (is_file($mFile) && is_file($pFile)) {
        $emailArr = array_values(array_filter(file($mFile, 2|4), 'trim'));
        $proxyArr = array_values(array_filter(file($pFile, 2|4), 'trim'));
    } else {
        _put($mFile, "email1@gmail.com\nemail2@gmail.com");
        _put($pFile, "socks5://ip:port:user:pass\nip:port:user:pass");
        logx('err', "File baru dibuat, isi dulu");
        die;
    }
}

$trash = ["email1@gmail.com", "email2@gmail.com", "socks5://ip:port:user:pass", "ip:port:user:pass"];
$emailArr = array_values(array_filter($emailArr, fn($v) => !in_array(trim($v), $trash)));
$proxyArr = array_values(array_filter($proxyArr, fn($v) => !in_array(trim($v), $trash)));

if (empty($emailArr) || empty($proxyArr)) {
    logx('err', "Data zonk");
    die;
}

$count = min(count($emailArr), count($proxyArr));



logx('info', 'login = batch(20email), input index (1/dst)');
$bIdx = (int)Config::credential([], true)['login'];

$limit = 20; 
$start = ($bIdx - 1) * $limit;
$end = $start + $limit;

banner(); 

while (true) {
    for ($i = $start; $i < $end; $i++) {
        if (!isset($emailArr[$i])) continue;

        $_email = $emailArr[$i];
        $_proxy = $proxyArr[$i];
        
        if (strpos($_proxy, '://') === false) {
            $proxyFinal = "socks5://" . $_proxy;
        } else {
            $proxyFinal = $_proxy;
        }

        putenv("PROXY=" . $proxyFinal);
        Proxy::Load(); 

        $cookieFile = config::cookie($_email);
        $userAgent = config::uagent('mobile');
        inf::setup($userAgent, $cookieFile, '');

        print(FGd['CYN']." processing ".RSET);
        logx('ok', "[ $_email ]", true, true);

        $dash = null; $ret = 0; $max = 7;
        do {
            $ret++;
            $l = inf::check($host.'/dashboard', [], 'loginForm');
            if ($l['ok']) {
                $dash = $l['html'];
                logx('info', "logged in", false); 
                _sle(3); _clr();
                break;
            }
            if ($ret >= $max) {
                logx('err', 'gak tau');
                continue 2; 
            }
            @unlink(inf::$cookie);
            logx('err', "logging in", false); 
            _sle(3); _clr();
            $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, [], '', inf::$uagent);
            if ($_0 === 99) { _sle(60); continue; }
            if (!empty($_0)) {
                $pa = solveUtils::webkitID(['action' => 'login', 'email' => $_email, 'csrf_token' => ''], $boundary);
                $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];
                $lo = json_decode(Net::C($host.'/api.php', 'POST', $pa, inf::$cookie, $ha, $host.$r, inf::$uagent) ?: '', true);
                $suc = filter_var($lo['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (!$suc) {
                    logx('err', '   '.$lo['message']);
                    continue 2;
                }
            }
        } while (empty($dash));

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
        
        $ret99 = 0; 
        while (true) {
            $fau = Net::C($host.'/faucet', 'GET', null, inf::$cookie, [], '', inf::$uagent);
            if ($fau === 99) {
                $ret99++;
                logx('warn', "masalah proxy, warm up dulu");
                if ($ret99 >= 7) {
                    break;
                }
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (!empty($fau)) {
                $tmr = Scraper::_jP($fau, '/initCooldown\((?<v>\d+)\)/');
                $ti = (int)($tmr['v'][0] ?? 0);
                if ($ti > 0) {
                    logx('err', '   '."$_email Cooldown: {$ti}s");
                    break;
                } else {
                    $pa_f = pA($fau, $host);
                    if (!empty($pa_f) && !isset($pa_f['trouble'])) {
                        $boundary = '';
                        $body = SolveUtils::webkitID($pa_f, $boundary);
                        $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];
                        $cla = json_decode(Net::C($host.'/api.php', 'POST', $body, inf::$cookie, $ha, $host.$r, inf::$uagent)?: '', true);
                        $suc = filter_var($cla['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        if ($suc) {
                            logg(true, '   success ', false);
                            logx('info', ' roll: '.$cla['random_number'], false);
                            logx('', " [ {$cla['new_balance']} ]", true, true);
                            $saldo = $cla['new_balance'];
                            break; 
                        } else {
                            logx('err', '   '.$cla['message']);
                            _sle(10); 
                        }
                    }
                }
            }
        }

        if ((int)$saldo >= 1000) {
            $wd = Net::C($host.'/withdraw', 'GET', null, inf::$cookie, [], '', inf::$uagent);
            if (!empty($wd) && $wd !== 99) {
                $coins = Scraper::_xP($wd, "//select[@x-model='crypto']/option/@value");
                foreach ($coins as $coin) {
                    if (stripos($coin, 'btc') !== false) continue;
                    logg(true, '  tes ilmu: '. strtoupper($coin), false);
                    logx('info', ' [ '.$_email.' ]');
                    $ic = locally::iCaptcha($wd, $host);
                    if ($ic && $ic !== 99) {
                        $pa_wd = array_merge(['action' => 'withdraw', 'amount' => $saldo, 'crypto' => $coin], $ic);
                        $boundary = '';
                        $body_wd = SolveUtils::webkitID($pa_wd, $boundary);
                        $wd_res = json_decode(Net::C($host.'/api.php', 'POST', $body_wd, inf::$cookie, ["Content-Type: multipart/form-data; boundary=$boundary"], $host.'/withdraw', inf::$uagent)?: '', true);
                        if (filter_var($wd_res['success'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                            logx('info', 'withdraw ', false, true);
                            logg(false, $wd_res['message']);
                            break;
                        }
                    }
                }
            }
        }
        putenv("PROXY=");
        _sle(3);
    }
    _sle(10); 
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