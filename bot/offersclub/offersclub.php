<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://offersclub.eu';
$domain = parse_url($host, PHP_URL_HOST);
$r = '';
$ip = null;

(function ($login, $ip) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
} ) ($login, $ip);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$curr = '';
$dash = null;
$wallOwme = false;
$owmeOFF = true;
while (true) {
    $ret = 0;
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', [], 'loginBox');
        
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
        
        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, [], '', inf::$uagent);
        #_put('0.html', $_0); die;
        if ($_0 === 99) {
            logx('warn', 'Proxy issue, wait 30s');
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        #var_dump($_0); die;
        $f = scraper::payload($_0)[0] ?? null;
        
        $po = null;
        if (!empty($f)) {
            
            $pa = $f['payload'];
            $cre = ['email' => $login];
            
            $cap = Solve::exec($_0, $host, $api, $pa);
            
            if (isset($cap['trouble'])) {
                $tro = $cap['trouble'];
                logx('warn', "Solver trouble: $tro");
                ($tro === 'proxy') ? _sle(30) : _sle(10);
                continue;
            }
            $cleanCap = array_filter((array)$cap, fn($v, $k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_BOTH);
            
            $po = array_merge($pa, $cleanCap, $cre);
            
        }
        
        Net::C($host, 'POST', $po, inf::$cookie, [], $host.$r, inf::$uagent, false, false, $ip);
        
    } while (empty($dash));
    #_put('dash.html', $dash);

    $owmeof = Net::C($host.'/offerwalls/offerwallme', 'GET', null, inf::$cookie, [], '', inf::$uagent);
    if (!empty($owmeof) && $owmeof !== 99) {
        $owme_if = Scraper::_xP($owmeof, "//div[contains(@class, 'offerwall-wrapper')]//iframe/@src");
        $owme_ur = !empty($owme_if) ? trim($owme_if[0]) : null;
    }

    do {
        $ow = new Owme($host, $api, $login);
        $retryList = 0;
        $off = [];
        $anySuccess = false; 
        
        while ($retryList < 5) {
            $owme = Net::C($host.'/offerwalls/offerwallme-ptc', 'GET', null, inf::$cookie, [], '', inf::$uagent);
            #_put('owme.html', $owme); die;
            
            if (!empty($owme) && $owme !== 99) {
                $clicks = Scraper::_xP($owme, "//div[contains(@class, 'ptc-grid')]//a[contains(@class, 'btn-ptc-go')]/@href");
                $timers = Scraper::_xP($owme, "//div[contains(@class, 'ptc-meta')]/span[2]");
                
                if (!empty($clicks) && is_array($clicks)) {
                    foreach ($clicks as $i => $rawUrl) {
                        $o_u = str_replace('&amp;', '&', $rawUrl);
                        $rawTime = $timers[$i] ?? '10';
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
            if ($retryList < 5) _sle(3);
        }
        
        if (empty($off)) {
            logx('err', "habis total kayaknya.");
            #_put('owme.html', $owme);
            $wallOwme = true;
        } else {
            foreach ($off as $ad) {
                $status = $ow->exec($ad['url'], $ad['timer']);
                if ($status) {
                    $anySuccess = true; 
                    styler('Waiting', fn() => _sle(1));
                } 
            }
            if (!$anySuccess) $wallOwme = true;
        }
        
    } while (!$wallOwme);
    
    $wd = Net::C($host.'/withdraw', 'GET', null, inf::$cookie, [], '', inf::$uagent);
    
    if (!empty($wd) && $wd !== 99) {
        
        $jjn = Scraper::_xP($wd, "//span[contains(@class, 'balance-val')]")[0] ?? '0';
        
        $bal = (float) filter_var($jjn, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        
        $po = null;
        $f = scraper::payload($wd)[0] ?? [];
        
        if ($bal >= 0.1 && !empty($f['payload'])) {
            $cre = ['wallet' => $login, 'usd_amount' => $bal, 'method' => '1'];
            $po = array_merge($f['payload'], $cre);
        }
        
        if ($po) {
            $ver = Net::C($host.'/withdraw', 'POST', $po, inf::$cookie, [], $host.'/withdraw', inf::$uagent);
            $_suc = Scraper::_xP($ver, "//div[contains(@class, 'alert-success')]")[0] ?? null;
            if ($_suc) {
                $parts = explode('!', $_suc);
                $hasil = isset($parts[1]) ? trim($parts[1]) : $_suc;
                print(FGd['CYN'].maskEmail($login).RSET." ");
                logx('info', $hasil, true, true);
            }
        }
    }
    
    if ($wallOwme) {
        
        if (!empty($owme_ur)) $ow->wall($owme_ur); 
        
        $ow->cleanup(); 
        styler('Waiting cooldown offerwall.me', fn() => _sle(60));
        $wallOwme = false;
    }
    
}



tes: