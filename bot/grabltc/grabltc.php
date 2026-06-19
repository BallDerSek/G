<?php
if (!defined('ROOT')) { die; }
#_die();
#$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://grabltc.com/CryptoHarvest';
$domain = parse_url($host, PHP_URL_HOST);
$r = '';
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
    
} ) ($login, $ip, $host);

$hhh = ['detail-hints: false'];

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host/home.php", $hhh, 'loginForm');

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
        $_0 = Net::C($host.'/login.php', 'GET', null, inf::$cookie, $hhh, '', inf::$uagent);
        if (!empty($_0) && $_0 !== 99) {
            $po = [
                'website' => "", 'email' => $login, 'referral_code' => "FNL98HPJ"
            ];
            $ve = Net::C($host.'/login.php', 'POST', $po, inf::$cookie, $hhh, '', inf::$uagent);
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            if (!empty($ve) && $ve !== 99) {
                $_err = Scraper::_xP($ve, "//div[contains(@class, 'error')]//p[contains(@class, 'text-red')]/text()");
                
                if (!empty($_err[0])) {
                    logx('err', $_err[0]);
                    die;
                }
                
            }
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    $_bal_raw = Scraper::_xP($dash, "//p[contains(@class, 'orbitron') and contains(@class, 'font-bold')]/text()")[0] ?? '0';
    $_bal = (int) str_replace(',', '', trim($_bal_raw));
    if ($_bal >= 1000000) {
        $wd_p = [
            'amount_pts' => (string)$_bal,
            'currency_id' => '2'
        ];
        $wd = json_decode(Net::C($host."/Withdrawal/process_withdraw.php", 'POST', $wd_p, inf::$cookie, $hhh, '', inf::$uagent)?: '', 1)['message'] ?? null;
        
        if (!empty($wd)) {
            print(FGd['CYN'].maskEmail($login).RSET." ");
            logx('info', $wd, true, true);
        }
    }

    $_fa = [
        'HourlyFaucet' => 'process_hourly',
        'BonusFaucet' => 'process_bonus'
    ];
    foreach ($_fa as $fa => $fe) {
        $u_fau = $host . "/{$fa}/" . strtolower($fa) . ".php";
        $fau = Net::C($u_fau, 'GET', null, inf::$cookie, $hhh, '', inf::$uagent);
        if (!empty($fau) && $fau !== 99) {
            preg_match('/secondsLeft\s*=\s*(\d+)/', $fau, $_s);
            
            $sec = (int)($_s[1] ?? 10);
            if ($sec === 0) {
                $u_cla = $host . "/{$fa}/{$fe}.php";
                $cla = json_decode(Net::C($u_cla, 'GET', null, inf::$cookie, $hhh, '', inf::$uagent)?: '', true);
                if (!empty($cla['status'])) {
                    print(FGd['CYN'].maskEmail($login).RSET." ");
                    $stt = $cla['status'];
                    $msg = isset($cla['total_reward']) ? "claimed {$cla['total_reward']}" : ($cla['message'] ?? 'Unknown');
                    logx($stt, "$fa  ", false, true);
                    logg(false, "$msg");
                    
                    
                }
            }
        }
    }
    
    $spin = false;
    for ($rrr = 0; $rrr < 2; $rrr++) {
        $spin_tkn = json_decode(Net::C($host."/SpinFaucet/spinfaucet_backend.php", 'GET', null, inf::$cookie, $hhh, '', inf::$uagent)?: '', 1)["spin_token"] ?? null;
        if ($spin_tkn) {
            $spin_po = ['spin_token' => $spin_tkn];
            $spin_cla = json_decode(Net::X($host."/SpinFaucet/spinfaucet_backend.php", 'POST', $spin_po, inf::$cookie, $hhh, '', inf::$uagent, json: true)?: '', 1);
            
            if (!empty($spin_cla['status'])) {
                print(FGd['CYN'].maskEmail($login).RSET." ");
                $stt = $spin_cla['status'];
                if ($stt === 'success') {
                    if ($spin_cla['total_reward'] > 0) {
                        $msg = "won {$spin_cla['total_reward']}";
                        break;
                    } else $msg = $spin_cla['result_text'];
                } else $msg = $spin_cla['message'] ?? 'Unknown';
                
                logx($stt, "SpinFaucet   ", false, true);
                logg(false, $msg);
            }
        }
    }
    
    $claimed = 0;
    while ($claimed <= 5) {
        $box_ttl = 0;
        $_se = 'session_' . substr(md5(rand()), 0, 9);
        
        for ($i = 1; $i <= 3; $i++) {
            $po = [
                'action' => "open_box",
                'box_id' => (string)rand(1, 5),
                'session_id' => $_se,
                'reward_amount' => "6577"
            ];
            
            $box_get = json_decode(Net::X($host."/Game/game_process.php", 'POST', $po, inf::$cookie, $hhh, '', inf::$uagent)?: '', 1);
            
            if (($box_get['is_winner'] ?? false)) $box_ttl += $box_get['reward_amount'];
            
            if (($box_get['attempts_left'] ?? 1) === 0) break;
            _sle(1);
            
        #die;
        }
        
        $pa = [
            'action' => 'claim_rewards',
            'session_id' => $_se,
            'total_earnings' => $box_ttl
        ];
        #print_r($pa);
        $box_cla = json_decode(Net::C($host."/Game/game_process.php", 'POST', $pa, inf::$cookie, $hhh, '', inf::$uagent)?: '', 1);
        #print_r($box_cla);
        if (!empty($box_cla['status'])) {
            print(FGd['CYN'].maskEmail($login).RSET." ");
            $stt = $box_cla['status'];
            $msg = $box_cla['amount'] ?? $box_cla['note'] ?? 'unknown';
            
            logx($stt, "$stt [ $claimed ] ", false, true);
            logx('info', "claimed: $msg", true, true);
            $claimed++;
        }
        
        _sle(1);
    }
    
    
    
    
    
}

