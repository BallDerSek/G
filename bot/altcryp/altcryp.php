<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://altcryp.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=45909';
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
    $b->task2('ok', "site: $host");
    
} ) ($login, $ip, $host);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$claim = true;
$curr = '';
$habis = [];
$needSL = false;

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host", $headersCF, '/auth/login');

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
        $_0 = checkCF($host, $api, $_0)['html'] ?? null;
        #_put('0.html', $_0);
        $f = scraper::payload($_0)[0] ?? null;
        $po = null;
        
        #print_r($f);
        
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['wallet' => $login];
            $cap = Solve::exec($_0, $host, $api, $pa);
            if (isset($cap['trouble'])) {
                _sle(10);
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
            
            $_sucS = scraper::_jP($ve, "/Swal\.fire\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'<]+)/i");
            
            if (isset($_sucS[2][0])) {
                $msg = $_sucS[2][0];
                logx('info', $msg, true, true);
                if (stripos($msg, 'nvalid captcha')) continue;
                if (preg_match('/registered|success/i', $msg)) continue;
                die;
            }
            
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    $_fa = Scraper::_xP($dash, "//ul[@id='faucet'][contains(@class,'submenu')]//a/@href");
    #print_r($_fa);
    
    static $afp = null;
    foreach ($_fa as $fa) {
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while (true) {
            $fauu = null;
            $fauu = Net::C($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent, d: true);
            
            if ($fauu === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            
            $cff = checkCF($fa, $api, $fauu, $headersCF);
            
            if (!empty($cff['html'])) {
                $headersCF = $cff['head'];
                $fau = $cff['html'];
            } else {
                $fau = $fauu['body'] ?? null;
            }
            
            #_put('fauu.html', $fauu); die;
            
            if (!empty($fau)) {
                #_put('fau.html', $fau);
                $po = null;
                $f = scraper::payload($fau,'fauform')[0] ?? null;
                
                if ($f) {
                    #print_r($f);
                    $pa = $f['payload'];
                    
                    $cap = Solve::exec($fau, $host, $api, $pa);
                    if (isset($cap['trouble'])) {
                        _sle(5);
                        continue;
                    }
                    if (isset($cap['nocaptcha']) && isset($pa['fp_os_name'])) {
                        if ($afp === null) {
                            $afp = _altcryptoken(inf::$uagent, $login);
                        }
                        $cap = $afp;
                    }
                    $po = array_merge($pa, $cap);
                    
                } else {
                    
                    if (stripos($fau, 'firewall')) {
                        #_put('fwall.html', $fau); die;
                        $ff = scraper::payload($fau)[0] ?? [];
                        $cap = solve::exec($fau, $host, $api, $ff['payload']);
                        
                        $pp = array_merge($ff['payload'], $cap);
                        Net::C($ff['url'], 'POST', $pp, inf::$cookie, $headersCF, $fa, inf::$uagent);
                        continue;
                        
                    }
                    
                    if (str_contains($fau, 'claim limit')) {
                        #_put('fau.html', $fau); 
                        $habis[$fa] = true;
                        break;
                    }
                    
                }
                
                if (!empty($po)) {
                    _sle(7);
                    $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, $headersCF, $fa, inf::$uagent);
                    
                    if (!empty($cla) && $cla !== 99) {
                        #_put('cla.html', $cla);
                        $_suc = scraper::_jP($cla, "/Swal\.fire\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'<]+)/i");
                        print(FGd['CYN'].maskEmail($login).RSET." ");
                        if (isset($_suc[2][0])) {
                            $stt = $_suc[1][0];
                            $msg = $_suc[2][0];
                            
                            logx('info', $stt, false, true);
                            logg(false, "$msg");
                            
                            if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                $habis[$fa] = true;
                                break;
                            }
                            if (preg_match('/banned|flagged|anti-fraud/i', $msg)) {
                                if (empty(getenv('AN'))) die;
                                
                            }
                            #if (preg_match('/banned|flagged/i', $msg)) die;
                        }
                        
                        styler("waiting for next claim", fn() => _sle(5));
                    }
                    
                    
                }
                
            }
        #die;
        }

    }
    
    if (count($habis) === count($_fa)) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
    }
    
    
die;
}





tes:











function _altcryptoken($ua, $mail) {
    
    $os = 'Windows';
    if (str_contains($ua, 'Android')) $os = 'Android';
    elseif (str_contains($ua, 'Macintosh')) $os = 'MacOS';
    elseif (str_contains($ua, 'Linux') && !str_contains($ua, 'Android')) $os = 'Linux';
    
    $_B = 'Chrome';
    if (str_contains($ua, 'Firefox')) $_B = 'Firefox';
    elseif (str_contains($ua, 'Edg')) $_B = 'Edge';
    elseif (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) $_B = 'Safari';
    
    $_M = str_contains($ua, 'Android') || str_contains($ua, 'Mobile');
    
    $resos_desktop = ['1920x1080', '1366x768', '1536x864'];
    $resos_mobile = ['360x800', '412x915', '1080x2400'];
    $cores_desktop = ['4', '6', '8', '12', '16'];
    $cores_mobile = ['4', '6', '8'];
    
    $_reso = $_M ? $resos_mobile[array_rand($resos_mobile)] : $resos_desktop[array_rand($resos_desktop)];
    $_core = $_M ? $cores_mobile[array_rand($cores_mobile)] : $cores_desktop[array_rand($cores_desktop)];
    
    return [
        'fp_device_token' => 'FP_' . abs(crc32($ua . $mail)),
        'fp_os_name' => $os,
        'fp_browser_name' => $_B,
        'fp_screen_res' => $_reso,
        'fp_user_timezone' => TIMEZONE(),
        'fp_browser_lang' => LANGUAGE(),
        'fp_cpu_cores' => $_core,
        'fp_adblocker' => 'Disabled',
    ];
}


function checkCF($url, $api, $body = null, $headersCF = []) {
    
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    
    if (!$html || !$code) return [];
    
    if ($code !== 200 && (stripos($html, 'Just a moment') !== false || stripos($html, 'Attention Required!') !== false)) {
        
        $cf = Cloudflare::exec($api, $url, inf::$cookie, inf::$uagent, ['html' => $html]);
        
        if ($cf) {
            [$headersCF, $ua] = $cf;
            inf::setup($ua, inf::$cookie);
            
            if (!empty($headersCF)) {
                for ($try = 1; $try <= 3; $try++) {
                    _sle(3);
                    $fix = Net::X($url, 'GET', null, inf::$cookie, $headersCF, $url, inf::$uagent, d: true);
                    
                    if (!empty($fix) && isset($fix['http_code'])) {
                        $_c = $fix['http_code'];
                        $_b = $fix['body'];
                        
                        if ($_c === 200 && stripos($_b, 'Just a moment') === false && stripos($_b, 'Attention Required!') === false) {
                            
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
