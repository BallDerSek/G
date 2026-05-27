<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

$host = 'https://faucetsamyy.xyz';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=nFCZvKqZRD7o';
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
$skipped = [];
$SLDONE = false;
$claim = true;
$habis = [];
$curr = '';
while (true) {
    $ret = 0;
    
    do {
        $ret++;
        $l = inf::check("$host/index.php", $headersCF, 'loginBtn', true);
        _put('l.html', $l['html']);
        
        
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
        _put('0.html', $_0);
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
        $ve = Net::X($host.'/login.php', 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
        _put('ve.html', $ve);

    } while (empty($dash));
    _put('dash.html', $dash);
    
die;
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
