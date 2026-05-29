<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

$host = 'https://faucetsamyy.xyz';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=nFCZvKqZRD7o';
$r = '';
$ip = null;

(function ($login, $ip) {
    Proxy::load();
    Check::Geo();
    inf::$cookie = config::cookie($login);
    inf::$uagent = config::uagent('mobile');

    inf::setup(inf::$uagent, inf::$cookie, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
} ) ($login, $ip);

$headersCF = [];
$claim = false;
while (true) {
    $ret = 0;
    
    do {
        $ret++;
        $l = inf::check("$host/faucet.php", $headersCF, '', true);
        
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
        $_0 = Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent, d: true);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        $_0 = checkCF($host, $api, $_0)['html'];
        #_put('0.html', $_0);
        $f = scraper::payload($_0)[0] ?? null;
        $po = null;
        
        if (!empty($f)) {
            #print_r($f) && die;
            
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
        Net::X($host.'/login.php', 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);

    } while (empty($dash));
    #_put('dash.html', $dash);
    
    if (stripos($dash, 'One quick check')) {
        $cap = solve::exec($dash, $host, $api);
        if (isset($cap['trouble'])) continue;
        $ts = json_decode(Net::C($host.'/ts-verify.php', 'POST', $cap, inf::$cookie, [], $host.'/faucet.php', inf::$uagent)?: '', 1);
        if (!empty($ts['ok'])) {
            $claim = filter_var($ts['ok'], FILTER_VALIDATE_BOOLEAN);
        }
    } else {
        $claim = true;
    }

    while ($claim) {
        $fau = null;
        $po = null;
        $cap = [];
        
        $fau = Net::X($host.'/faucet.php', 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        #_put('fau.html', $fau); #die;
        
        if (!empty($fau) && $fau !== 99) {
            $f = scraper::payload($fau)[0] ?? [];
            
            if ($f && ($f['url'] !== 'login.php')) {
                $pa = $f['payload'] ?? [];
                
                $cap = solve::exec($fau, $host, $api, $pa);
                if (isset($cap['trouble'])) continue;
                
                $po = array_merge($pa, $cap);
            } else {
                if (stripos($fau, 'visit the link below')) {
                    $visit = true;
                    break;
                }
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
            }
            
            if (!empty($po)) {
                #print_r($po);
                
                $cla = Net::X($host.'/faucet.php', 'POST', $po, inf::$cookie, $headersCF, $host, inf::$uagent);
                #_put('cla.html', $cla);
                
                $_suc = scraper::_xP($cla, "//div[contains(@class, 'alert-success')]")[0] ?? null;
                if (!empty($_suc)) {
                    print(FGd['CYN'].maskEmail($login).RSET." ");
                    logg(true, $_suc);
                }
                
            }
            
        }
    }
    
    if ($visit) {
        styler('Waiting for ads', fn() => _sle(10));
        
        $vis = Net::X($host.'/faucet.php', 'GET', ['visit_link' => 1], inf::$cookie, $headersCF, '', inf::$uagent);
        #_put('vis.html', $vis);
        $_siv = null;
        $_siv = scraper::_xP($vis, "//div[contains(@class, 'alert-success')]")[0] ?? null;
        if ($_siv) $visit = false;
    }
    
    wd:
    $wd = Net::X($host.'/withdraw.php', 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
    if (!empty($wd) && $wd !== 99) {
        $f = scraper::payload($wd, 'wdForm')[0] ?? null;
        
        $_bal = Scraper::_xP($wd, "//div[@class='wd-hero-sat']/text()");
        (int)$saldo = isset($_bal[0]) ? trim($_bal[0]) : null;
        
        if (!empty($f) && $saldo >= 2000) {
            
            $jjn = Net::X($host.'/withdraw.php', 'POST', $f['payload'], inf::$cookie, $headersCF, '', inf::$uagent);
            #_put('jjn.html', $jjn);
            $_al = scraper::_xP($jjn, "//div[contains(@class, 'alert-error')]")[0] ?? null;
            if ($_al) {
                logx('err', $_al);
                if (stripos($_al, 'banned') || stripos($_al, 'lacklisted') || stripos($_al, 'anti-fraud')) die;
            }
        }
    }
}




tes:
    







function checkCF($url, $api, $body = null, $headersCF = []) {
    
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    
    if (!$html || !$code) return [];
    
    if ($code !== 200 && (stripos($html, 'Just a moment') !== false || stripos($html, 'Attention Required!') !== false)) {
        
        $cf = execCF($api, $url, inf::$cookie, inf::$uagent);
        
        if ($cf) {
            #var_dump($cf);
            [$headersCF, $ua] = $cf;
            inf::setup($ua, inf::$cookie);
            
            if (!empty($headersCF)) {
                for ($try = 1; $try <= 3; $try++) {
                    _sle(3);
                    $fix = Net::X($url, 'GET', null, inf::$cookie, $headersCF, $url, inf::$uagent, d: true);
                    
                    #var_dump($fix);
                    if (!empty($fix) && isset($fix['http_code'])) {
                        $_c = $fix['http_code'];
                        $_b = $fix['body'];
                        
                        if ($_c === 200 || (!stripos($_b, 'Just a moment') !== false || !stripos($_b, 'Attention Required!') !== false)) {
                            
                            config::credential()['ua'] = $ua;
                            
                            return ['html' => $_b, 'head' => $headersCF];
                        }
                    }
                    logx('info', "try-{$try} fail, reloading");
                }
            }
        }
    } else {
        return ['html' => $html, 'head' => $headersCF];
    }
    
    return [];
    
}
