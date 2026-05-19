<?php
if (!defined('ROOT')) { die; }
die('bloman bener');
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://coindoog.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=36785';
$ip = null;

(function ($login, $ip) {
    Proxy::load();
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
$curr = '';
$dash = null;
while (true) {
    $ret = 0;

    do {
        $ret++;
        $l = inf::check("$host/dashboard", $headersCF, '/auth/login', true);

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
        $_0 = Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        if (stripos($_0, 'Just a moment') !== false || stripos($_0, 'Attention Required!') !== false) {
                logx('warn', 'Cloudflare Detected, solving CF...');
                if ($cf = execCF($api, $host."/faucet/ltc", inf::$cookie, inf::$uagent)) {
                    [$headersCF, $ua] = $cf; 
                    inf::setup($ua, inf::$cookie);
                    _sle(3);
                    continue;
                }
            }
        #_put('0.html', $_0);
        $f = scraper::payload($_0)[0] ?? null;
        $po = null;
        
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'email' => $login];
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
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
            #_put('ve.html', $ve);

            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            $_sucS = scraper::_jP($ve, "/Swal\.fire\(\s*\{.*?title:\s*'([^']+)'.*?text:\s*'([^']+)'.*?icon:\s*'([^']+)'/s") ?? [];
            
            if (isset($_sucS[2][0])) {
                $msg = $_sucS[2][0];
                logx('err', $msg);
                if (stripos($msg, 'nvalid captcha')) continue;
                
                die;
            }
            
        }
    } while (empty($dash));
    _put('dash.html', $dash);
    
    
    $_fa = Scraper::_xP($dash, "//div[contains(normalize-space(), 'Faucets')]/following-sibling::div[@class='sub-menu-two']/a/@href");
    foreach ($_fa as $fa) {
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while (true) {
            $fau = null;
            $fau = Net::C($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent, d: true);
            
            #_put('fau.html', $fau); #die;
            if ($fau === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            $fau = checkCF($fa, $api,$fau);
            
            if (!empty($fau)) {
                _put('fau.html', $fau); #die;
            }
            
        die;
        }
        
        
        
    }
    

die;
    $_sl = Scraper::_xP($dash, "//div[contains(normalize-space(), 'Shortlinks')]/following-sibling::div[@class='sub-menu-two']/a/@href");
    print_r($_sl);
    
    
    
die;
}





function checkCF($url, $api, $body = null) {
    
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    
    if (!$html || !$code) return null;
    
    if ($code !== 200 && (stripos($html, 'Just a moment') !== false || stripos($html, 'Attention Required!') !== false)) {
        
        $cf = execCF($api, $url, inf::$cookie, inf::$uagent);
        
        if ($cf) {
            [$headersCF, $ua] = $cf;
            inf::setup($ua, inf::$cookie);
            
            if (!empty($headersCF)) {
                $fix = Net::C($url, 'GET', null, inf::$cookie, $headersCF, $url, inf::$uagent, d: true);
                
                if (!empty($fix) && isset($fix['http_code'])) {
                    if ($fix['http_code'] === 200) {
                        return $fix['body'];
                    }
                }
            }
        }
    }
    
    return $html;
}
