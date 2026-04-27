<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent('mobile');

$host = 'https://earnton.online';
$r = '/r.php?ref=6';
$ip = null; 

inf::setup($userAgent, $cookieFile, $ip);

banner();
login:
    
$short = false;
$set = microtime(true);
$block = [];
while (true) {
    
    $fa_sl = 0;
    do {
        $l = inf::check($host, [], '/loginForm');
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break; 
        }
        
        @unlink(inf::$cookie);
        taskPrintCenter('logging in', 'err');
        
        $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, [], '', inf::$uagent);
        #_put('0.html', $_0);
        if (empty($_0)) continue;
        
        
        $cap = solve::exec($_0, $host, $api);
        $cre = ['email' => $login];
        
        $po = array_merge($cre, $cap);
        Net::C($host.'/login.php', 'POST', $po, inf::$cookie, [], '', inf::$uagent);
        
    } while (empty($dash));
    
    
    if ($short) {
        logx('info', 'Starting Shortlink');
        $_sl_pages = scraper::_xP($dash, "//div[text()='Shortlinks']/following-sibling::a[contains(@href, 'shortlinks.php')]/@href");
        
        foreach ($_sl_pages as $sl_path) {
            $link_html = Net::C($host.$sl_path, 'GET', null, $cookieFile, [], $host, $userAgent);
            _put('link.html', $link_html);
            if (empty($link_html)) continue;
            
            $buttons = scraper::_xP($link_html, "//button[contains(@class, 'btn-sl-visit')]/@onclick");
            $csrf = Scraper::_pP($link_html, "_csrf");
            #print_r($csrf); die;
            $sl_done = false;
            #print_r($buttons); die;

            foreach ($buttons as $onclick) {
                if (preg_match("/visitShortlink\((\d+),\s*'([^']+)'/", $onclick, $vi)) {
                    $pid = $vi[1]; 
                    $coin = $vi[2];
                    $back = _link($host, $pid, $coin, $csrf[0]);
                    
                    if ($back) {
                        $ref = Net::C($back['go'], 'GET', null, $cookieFile, [], $host.$sl_path, $userAgent, true);
                        $reff = inf::lastLocation($ref['headers']);
                        logx('err', $reff);
                        styler("waiting SL", fn() => _sle(45));
                        
                        $ver = Net::X($back['back'], 'GET', null, $cookieFile, [], $reff, $userAgent);

                        _put('ver.html', $ver); die;
                        if (stripos($ver, 'Reward Claimed')) {
                            logx('ok', " [$coin] SL Claimed!");
                            $sl_done = true;
                            break; 
                        }
                    }
                }
            }

            if ($sl_done) continue; 
        }
        $short = false;
        continue; 
    }
    
    #_put('dash.html', $dash);
    $_fa = scraper::_xP($dash, "//div[text()='Faucets']/following-sibling::a[contains(@href, 'faucet.php')]/@href");
    if (empty($_fa)) {
        _sle(5);
        continue;
    }
    
    $fa_sl = 0;
    $b_fa = 0; 
    $waited = false;
    foreach ($_fa as $index => $fa) {
        parse_str(parse_url($fa)['query'], $_c);
        $_x = strtoupper($_c['coin'] ?? 'COIN'); 

        if (isset($block[$fa])) {
            continue; 
        }

        $b_fa++; 
        logx('info', "  [$_x] ", false, true);

        $ret = 0;
        while ($ret < 3) {
            $fau = Net::C($host.$fa, 'GET', null, $cookieFile, [], $host, $userAgent);
            if (!empty($fau)) break;
            $ret++;
            _sle(2);
        }

        $fo = scraper::payload($fau);
        if (empty($fo)) {
            if (stripos($fau, "Shortlink Required")) {
                logx('err', " Shortlink Required");
                $fa_sl++; 
            } else {
                logx('warn', " Cooldown / Empty");
            }
            $fau = null;
            continue; 
        }

        $cap = solve::exec($fau, $host, $api);
        

        if (!$waited && $set > 0) {
            $end = microtime(true) - $set;
            $sle = 60 - (int)ceil($end);
            if ($sle > 0) {
                styler("wait $sle", fn() => _sle($sle));
            }
            $waited = true; 
        }

        $f = $fo[0];
        $pa = solve::webkitID(array_merge($f['payload'], $cap), $boundary);
        $he = ["Content-Type: multipart/form-data; boundary=$boundary"];
        
        $resClaim = Net::C($host."/claim-ajax.php", 'POST', $pa, $cookieFile, $he, $host.$fa, $userAgent);
        $cla = json_decode($resClaim ?: '', true);

        if (!empty($cla['ok']) && !empty($cla['msg'])) {
            logx('ok', " " . strip_tags($cla['msg']), true, false);
            $set = microtime(true); 
        } elseif (!empty($cla['msg'])) {
            $msg = strip_tags($cla['msg']);
            logx('err', " " . $msg);

            if (stripos($msg, "sufficient faucet balance") || stripos($msg, "sufficient funds")) {
                $block[$fa] = true; 
            }
        }
        $fau = null; 
    }
    
    if ($b_fa > 0 && $fa_sl >= $b_fa) {
        logx('err', "SHORTLINK");
        $short = true;
        continue;
    } 
    
    if ($b_fa === 0 && !empty($_fa)) {
        logx('err', " EMPTY BALANCE");
        exit;
    }
    
#die;
}






function _link($host, $_id, $_ic, $csrf) {
    $referer = $host . '/shortlinks.php?coin=' . strtolower($_ic);

    $go = json_decode(Net::C($host.'/shortlink-init.php', 'POST', "provider_id=$_id&coin=$_ic&csrf_token=$csrf", inf::$cookie, [], $referer, inf::$uagent)?: '', true);
    
    #print_r($go); die;
    
    if (!isset($go['ok']) || !$go['ok'] || empty($go['url'])) return false;
    
    $finalUrl = null;
    if (!empty($go['token'])) {
        Net::C($go['url'], 'GET', null, inf::$cookie, [], $referer, inf::$uagent);
        $finalUrl = $host . '/shortlink-callback.php?token=' . $go['token'];
    } 
    
    return [
        'back' => $finalUrl,
        'go' => $go['url']
    ];
}
