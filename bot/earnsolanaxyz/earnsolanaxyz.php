<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://earnsolana.xyz';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=7974';
$ip = null;

(function ($login, $ip) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $c = config::credential(['ua' => fn() => config::uagent('mobile')]);
    $userAgent = $c['ua'];
    
    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
} ) ($login, $ip);

$headersCF = [];
$skipped = [];
$SLDONE = true;
$claim = true;
$curr = '';
$atbforce = false;
$atbfail = 0;
while (true) {
    $ret = 0;

    do {
        $ret++;
        $l = inf::check("$host/", $headersCF, '/auth/login');
        #_put('l.html', $l['html']); _rl('lanjut: ');
        
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
        
        logx('err', "logging in ", false); 
        _sle(3); _clr();
        $_0 = Net::X($host, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        #_put('0.html', $_0); _rl('lanjut: ');
        $f = scraper::payload($_0)[0] ?? null;
        #print_r($f); #die;
        $po = null;
        
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['wallet' => $login];
            
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
        
        if (!empty($po)) {
            #print_r($po); _rl('lanjut: ');
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host, inf::$uagent);
            #_put('ve.html', $ve);
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
        }
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    $ret99 = 0;
    while ($claim) {
        $fau = Net::C("$host/faucet/currency/sol", 'GET', null, inf::$cookie, $headersCF, "$host/dashboard", inf::$uagent);
        #_put('fau.html', $fau);
        
        if ($fau === 99) {
            $ret99++;
            logx('warn', "masalah proxy, warm up dulu");
            if ($ret99 >= 7) {
                goto login;
            }
            _sle(30);
            continue;
        }
        $ret99 = 0; 
        if (empty($fau)) continue;
        
        check:
            $cf = Net::C("$host/faucet/verify/sol", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, d: true);
                $cff = checkCF("$host/faucet/verify/sol", $api, $cf, $headersCF);
            if (empty($cff['html'])) {
                continue;
            } else {
                $headersCF = $cff['head'];
                $fau = $cff['html'];
            }
        
        $po = null;
        $cap = [];
        $f = scraper::payload($fau)[0] ?? [];
        if (empty($f)) {
            if (stripos($fau, '/auth/login')) goto login;
            if (!$SLDONE) break;
            
            styler('Waiting for faucet', fn() => _sle(5));
            continue;
        }
            
        if (!empty($f['payload'])) {
            $pa = $f['payload'];
            
            if ($atbfail >= 3) $atbforce = true;
            $cap = solve::exec($fau, $host, $api, $pa, false, $atbforce);
            if (isset($cap['trouble'])) {
                _sle(60);
                continue;
            }
            
            $po = array_merge($pa, $cap);
        }
        
        if (!empty($po)) {
            $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, '', inf::$uagent);
            
            if (empty($cla) || ($cla === 99)) continue;
                
            $_suc = scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s") ?? [];
            if (!empty($_suc[2][0])) {
                $msg = $_suc[3][0];
                print(FGd['CYN'].maskEmail($login).RSET." ");
                
                logx($_suc[1][0], $_suc[2][0].' ', false);
                logg(false, $msg);
                
                if (preg_match('/sufficient|banned/i', $msg)) die;
                if (stripos($msg, 'has been sent')) {
                    $atbforce = false;
                    $atbfail = 0;
                }
                if (stripos($msg, 'nvalid Anti-Bot')) {
                    $atbfail++;
                }
                
            }
        }
    }
    
    if (!$claim && $SLDONE) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
    }

}





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
                    
                    #var_dump($fix['body']);
                    if (!empty($fix) && isset($fix['http_code'])) {
                        if ($fix['http_code'] === 200) {
                            config::credential()['ua'] = $ua;
                            
                            return ['html' => $fix['body'], 'head' => $headersCF];
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
