<?php
if (!defined('ROOT')) { die; }
#$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent();

$host = 'https://tfaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/r/280442';

inf::setup($userAgent, $cookieFile);

banner(); 
login:

$dash = null;
while (true) {
    $max = 5;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', [], 'Register Free');
        #_put('dash.html', $l['html']); _rl('tes');
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break; 
        }
        
        if ($ret >= $max) {
            logx('err', 'refresh ip');
            exit; 
        }
        @unlink(inf::$cookie);
        taskPrintCenter("logging in", 'err');
        
        $_0 = Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
        if (!empty($_0)) {
            $lo = json_decode(Net::X($host.'/api/login', 'POST', ['email' => $login, 'ref' => ltrim($r, 'r/')], $cookieFile, [], $host.$r, $userAgent, json: true)?: '', true);
            if (!empty($lo)) {
                #var_dump($lo);
                $err = filter_var($lo['success'], FILTER_VALIDATE_BOOLEAN);
                if (!$err) {
                    logx('err', $lo['message']); 
                    exit;
                }
            }
            _sle(5);
        }
    } while (empty($dash));
    
    while (true) {
        $ads = Net::C($host.'/ads', 'GET', null, $cookieFile, [], '', $userAgent);
        $_u = Scraper::_xP($ads, "//div[@class='ad-card']//a[contains(@class, 'btn-purple')]/@href");
        $_t = Scraper::_xP($ads, "//div[@class='ad-timer-badge']");
        #print_r($_u);
        foreach ($_u as $index => $link) {
            logx('', "  [ $link ] ", false, true);
            #logx('info', ": {$_t[$index]}", true, true);
            $view = Net::C($host.$link, 'GET', null, $cookieFile, [], '', $userAgent);
            $math = Scraper::find($view, 'math_captcha', 'input', 'name', 'name');

            if (stripos($view, 'solve the math question') !== false && isset($math[0])) {
                $_s = Scraper::_xP($view, "//div[contains(@class, 'fw-700 text-purple')]");
                $_ask = '';
                foreach($_s as $val) {
                    if (str_contains($val, '?')) {
                        $_ask = $val;
                        break;
                    }
                }
                if ($_ask) {
                    $input = str_replace(['×', 'x', '÷'], ['*', '*', '/'], $_ask);
                    if (sscanf($input, "%d %s %d", $q1, $op, $q2) == 3) {
                        $v_a = mA($q1, $q2, $op); 
                        #logx('info', "  $_ask -> $v_a");
                    }
                }
            }
            
            $res = Scraper::_jP($view, '/window\.open\s*\(\s*["\']([^"\']+)["\']/');
            #print_r($res);
            if (!empty($res[1][0])) {
                $v_u = stripslashes($res[1][0]);
                Net::C($v_u, 'GET', null, $cookieFile, [], $host.$link, $userAgent);
                _sle((int)$_t[$index]);
                $pa = ['campaign_id' => (int)basename($link), 'token' => '', 'math_captcha' => $v_a];
            }
            if ($pa) {
                #print_r($pa);
                $ver = json_decode(Net::X($host.'/api/complete-view', 'POST', $pa, $cookieFile, [], $host.$link, $userAgent, json: true)?: '', true);
                if (!empty($ver)) {
                    #var_dump($ver);
                    $suc = filter_var($ver['success'], FILTER_VALIDATE_BOOLEAN);
                    if ($suc) {
                        logx('ok', $ver['message'], true, true);
                    } elseif(!$suc) {
                        logx('err', $ver['message'] ?? 'invalid');
                    }
                }
            }

            #die;
        }
        
        logx('err', 'ptc habis');
        break;
    }

    while (true) {
        $pa = null;
        $fau = Net::C($host.'/faucet', 'GET', null, $cookieFile, [], '', $userAgent);
        
        if (!empty($fau)) {
            #_put('fau.html', $fau);
            $f = scraper::payload($fau)[0];
            $pa = $f['payload'];
            if (str_contains($fau, 'solve the math question ') && isset($pa['math_captcha'])) {
                $_s = scraper::_xP($fau, "//div[contains(@class, 'fw-700 text-purple')]");
                $input = str_replace(['×', 'x', '÷'], ['*', '*', '/'], $_s[0]);
                #print_r($_s);
                if (sscanf($input, "%d %s %d", $q1, $op, $q2) == 3) {
                    $_a = mA($q1, $q2, $op);
                    $pa['math_captcha'] = (int)$_a;
                    logx('info', "  {$_s[0]} -> $_a");
                }
            }
        }

        if ($pa) {
            #var_dump($pa);
            $cla = Net::C($host.$f['url'], 'POST', $pa, $cookieFile, [], $host.'/faucet', $userAgent);
            #_put('cla.html', $cla);
            if (!empty($cla)) {
                $pattern = '/showToast\("([^"]+)"(?:(?:\s*,\s*"([^"]*)")?(?:\s*,\s*"([^"]*)")?)?\)/';

                $res = Scraper::_jP($cla, $pattern);
                #print_r($res);
                if (!empty($res[1])) {
                    $msg = $res[2][0];
                    $type = $res[3][0]; 
                    $cleanMsg = trim(explode('(', $msg)[0]);
                    if ($type === 'success') {
                        logx("ok", '  '.$cleanMsg, true, true);
                        styler('Waiting', fn() => _sle(60));
                    } else {
                        logx("err", $msg);
                    }
                    if (stripos($msg, 'aily claim limit')) {
                        break;
                    }
                    if (stripos($msg, 'locked')) _sle(600);
                }
            }
        }
    }
    
    do { # offerwall.me
            
        $owme = Net::C($host.'/offerwallme', 'GET', null, $cookieFile, [], '', $userAgent);
        #_put('owme.html', $owme);
        if (empty($owme)) continue;

        $clicks = Scraper::_xP($owme, "//div[@id='pane_ptc']//button[contains(@onclick, 'owVisit')]/@onclick");
        $times = Scraper::_xP($owme, "//span[contains(@class, 'ow-badge')][i[contains(@class, 'fa-clock')]]");
        $off = [];
        foreach ($clicks as $i => $onclick) {
            if (preg_match("/owVisit\(this\s*,\s*['\"]([^'\"]+)['\"]/i", $onclick, $match)) {
                $o_u = str_replace('&amp;', '&', $match[1]);
                $rawTime = $times[$i] ?? '10';
                $o_t = (int)filter_var($rawTime, FILTER_SANITIZE_NUMBER_INT);
                $off[] = [
                    'url' => $o_u,
                    'timer' => $o_t ?: 10
                ];
            }
        }
        #print_r($off);
        
        foreach ($off as $ad) {
            
            $o_vU = $ad['url'];
            $o_vT = $ad['timer'];
            
            logx('info', "   [ wait {$o_vT}s for offerwall.me] " , false, true);
            $o_view = Net::C($o_vU, 'GET', null, $cookieFile, [], '', $userAgent, true);
            if (empty($o_view)) continue 2;
            $o_vR = $o_view['url'];

            $o_vPA = [
                'tkn' => Scraper::_jP($o_view['body'], "/var\s+token\s*=\s*'([^']+)';/")[1][0] ?? null,
                'ids' => Scraper::_jP($o_view['body'], "/var\s+sub_id\s*=\s*'([^']+)';/")[1][0] ?? null,
                'idh' => Scraper::_jP($o_view['body'], "/var\s+hash\s*=\s*'([^']+)';/")[1][0] ?? null,
                'key' => Scraper::_jP($o_view['body'], "/var\s+key\s*=\s*'([^']+)';/")[1][0] ?? null,
                'dur' => Scraper::_jP($o_view['body'], "/var\s+duration\s*=\s*(\d+);/")[1][0] ?? null,
                'act' => Scraper::_jP($o_view['body'], "/'action'\s*:\s*'([^']+)'/")[1][0] ?? 'proccessLead',
            ];
            if (in_array(null, $o_vPA, true)) {
                logx('err', "ada perubahan mungkin");
                _put('view.html', $o_view);
                _rl('tess enter');
                continue;
            }
            _sle((int)$o_vPA['dur']);
            
            $o_vCU = 'https://'.parse_url($o_vR)['host'].'/system/libraries/captcha/request.php';
            $o_vCA = json_decode(Net::X($o_vCU, 'POST', ['cID' => '0', 'rT' => '1', 'tM' => 'light'], $cookieFile, [], $o_vR, $userAgent)?: '', true);
            
            foreach ($o_vCA as $_in => $_ic) {
                #logx('info', "  $_in => [ $_ic ]", true, true);
                $o_vCB = Net::X($o_vCU, 'POST', ['cID' => '0', 'rT' => '2', 'pC' => $_ic], $cookieFile, [], $o_vR, $userAgent, d: true);
                if (!empty($o_vCB) && $o_vCB['http_code'] === 200) {
                    #logx('ok', 'solved');
                    $o_vPO = [
                        'hash' => $o_vPA['idh'],
                        'sub_id' => $o_vPA['ids'],
                        'key' => $o_vPA['key'],
                        'token' => $o_vPA['tkn'],
                        'captcha-idhf' => '0',
                        'captcha-hf' => $_ic,
                        'action' => $o_vPA['act']
                    ];
                    #print_r($o_vPO);
                    $o_verf = json_decode(Net::X('https://' . parse_url($o_vR)['host'] . '/system/ajax.php', 'POST', $o_vPO, $cookieFile, [], $o_vR, $userAgent)?: '', true);
                    #var_dump($o_verf);
                    if (!empty($o_verf) && is_array($o_verf)) {
                        $res = $o_verf;
                        #print_r($res);
                        if (isset($res['status']) && $res['status'] == 200) {
                            logx('info', trim(strip_tags($res['message'])) ?? 'success', true, true);
                        } else {
                            logx('err', trim(strip_tags($res['message'])) ?? 'invalid kayaknya');
                        }
                    }
                    styler('Waiting', fn() => _sle(5));
                }
            }

        }

        $wd = true;
    } while (!$wd);
    
    if ($wd) {
        $jajan = _wd(Net::C($host.'/withdraw', 'GET', null, $cookieFile, [], '', $userAgent));
        if (!$jajan) exit;
        
        $success = false;
        foreach ($jajan['coins'] as $coin) {
            $pay = Net::C($host.'/withdraw', 'GET', null, $cookieFile, [], '', $userAgent);
            if (empty($pay)) continue 2;
            $jjn = _wd($pay);
            if (!$jjn) exit;
            $poo = $jjn['payload'];
            $poo['currency'] = $coin;
            $ress = Net::C($host.'/withdraw', 'POST', $poo, $cookieFile, [], $host.'/withdraw', $userAgent);
            _put('wd.html', $ress);
            if (!empty($ress)) {
                $_err = Scraper::_xP($ress, "//div[contains(@class, 'alert-danger')]");
                $_suc = Scraper::_xP($ress, "//div[contains(@class, 'alert-success')]");
                #print_r($_err); #die;
                #print_r($_suc); #die;
                
                if (!empty($_err)) {
                    logx('err', trim(strip_tags($_err[0])));
                    $success = true;
                    if (stripos($_err[0], 'live price')) continue 2;
                    if (stripos($_err[0], 'sufficient funds')) {
                        continue; 
                    }
                    if (stripos($_err[0], 'locked')) {
                        _sle(60); 
                        continue 2;
                    }
                } elseif (!empty($_suc)) {
                    logx('ok', trim(strip_tags($_suc[0])));
                    $success = true;
                    break;
                }
            }
            _sle(2);
        }
        if (!$success) {
            logx('err', 'gak bisa wd kayaknya');
        }
    }
    exit;

}







function mA($q1, $q2, $op) {
    return match($op) {
        '+' => $q1 + $q2,
        '-' => $q1 - $q2,
        '*', '×', 'x' => $q1 * $q2, 
        '/', '÷' => $q2 != 0 ? (int)($q1 / $q2) : 0,
        default => 0,
    };
}

function _wd($html) {
    logx('info', '   tes ilmu');
    $balText = Scraper::_xP($html, "//span[contains(@class, 'text-purple')]")[0] ?? '0';
    $balance = (int)filter_var($balText, FILTER_SANITIZE_NUMBER_INT);
    
    $minWd = (int)Scraper::_xP($html, "//input[@id='coinsInput']/@min")[0];

    if ($balance < $minWd) {
        logx('err', " $balance gak cukup buat WD (Min: $minWd)");
        return false;
    }

    $forms = Scraper::payload($html);
    $basePayload = $forms[0]['payload'] ?? [];

    $currencies = Scraper::_xP($html, "//div[contains(@class, 'currency-card')]/@data-cur");

    $math_val = 0;
    $_s = Scraper::_xP($html, "//div[contains(@class, 'fw-700 text-purple')]");
    foreach ($_s as $val) {
        if (str_contains($val, '?')) {
            $input = str_replace(['×', 'x', '÷', '=', '?'], ['*', '*', '/', '', ''], $val);
            if (sscanf($input, "%d %s %d", $q1, $op, $q2) == 3) {
                $math_val = mA($q1, $q2, $op);
            }
            break;
        }
    }

    $basePayload['math_captcha'] = $math_val;
    $basePayload['coins'] = $balance;

    return [
        'coins' => $currencies, 
        'payload' => $basePayload,
        'balance' => $balance
    ];
}
