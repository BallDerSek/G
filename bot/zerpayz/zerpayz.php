<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent();

$host = 'https://zerpayz.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?ref=2190';

inf::setup($userAgent, $cookieFile);

banner(); 
login:

$dash = null;
while (true) {
    $max = 5;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', [], 'ont have Faucetpay');
        #_put('dash.html', $l['html']); _rl('tes');
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break; 
        }
        
        if ($ret >= $max) {
            logx('err', 'gak tau');
            exit; 
        }
        @unlink(inf::$cookie);
        taskPrintCenter("logging in", 'err');
        
        $_0 = Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
        #_put('0.html', $_0); die;
        if (!empty($_0)) {
            #print_r(scraper::payload($f));
            $cap = Solve::exec($_0, $host, $api);
            $po = array_merge(['faucetpay_email' => $login], $cap);
            #print_r($po);
            $_1 = Net::C($host.$r, 'POST', $po, $cookieFile, [], $host.$r, $userAgent);
            if (!empty($_1)) {
                #_put('1.html', $_1);
                $err = Scraper::_xP($_1, "//div[contains(@class, 'err-box')]");
                if (isset($err[0])) {
                    logx('err', $err[0]);
                exit;
                }
            }
            _sle(5);
        }
    } while (empty($dash));
    
    while (true) {
        $tap = Net::C($host.'/tap', 'GET', null, $cookieFile, [], '', $userAgent);
        if (!empty($tap)) {
            #_put('tap.html', $tap);
            
            Net::C($host.'/tap?action=advance_step', 'POST', ['_' => '1'], $cookieFile, [], $host.'/tap', $userAgent);
            #var_dump($pat);
            $don = json_decode(Net::C($host.'/tap?action=claim', 'POST', ['steps_done' => '3'], $cookieFile, [], $host.'/tap', $userAgent)?: '', true);
            if (empty($don)) continue;
            #var_dump($don);
            $suc = filter_var($don['success'], FILTER_VALIDATE_BOOLEAN);
            $play = true;
            if ($suc) {
                $play = filter_var($don['can_play_more'], FILTER_VALIDATE_BOOLEAN);
                logg(true, "  {$don['played_today']}", false);
                logx('ok', " {$don['amount']} {$don['currency']}", true);
            } else {
                logx('err', $don['message']);
                if (stripos($don['message'], 'ly limit reached')) $play = false;
            }
            if (!$play) break;
            styler('Waiting next tap', fn() => _sle(65));
        }
        
    
    
    }
    
    $done = false;
    do { # offerwall.me
        
        $ow = new Owme($host, $login);
        $retryList = 0;
        $off = [];
        while ($retryList < 3) {
            $owme = Net::C($host.'/offerwallme', 'GET', null, $cookieFile, [], '', $userAgent);
            #_put('owme.html', $owme);
            if (!empty($owme)) {
                $clicks = Scraper::_xP($owme, "//div[@id='pane_ptc']//button[contains(@onclick, 'owVisit')]/@onclick");
                $times = Scraper::_xP($owme, "//span[contains(@class, 'ow-badge')][i[contains(@class, 'fa-clock')]]");
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
            }
            if (!empty($off)) break;
            
            $retryList++;
            if ($retryList < 3) {
                _sle(3);
            }
        }
        
        if (empty($off)) {
            logx('err', "habis total kaya kayaknya.");
            _put('owme.html', $owme);
            $done = true;
        } else {
            foreach ($off as $ad) {
                $status = $ow->claim($ad['url'], $ad['timer']);
                if ($status) {
                    styler('Waiting', fn() => _sle(5));
                } else {
                    logx('err', "Gagal claim iklan");
                }
            }
        }
        
        
    } while (!$done);

    while (true) {
        $fau = Net::C($host.'/claim', 'GET', null, $cookieFile, [], '', $userAgent);
        #_put('fau.html', $fau);
    
    
    die;
    }

    
/*
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
*/
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
