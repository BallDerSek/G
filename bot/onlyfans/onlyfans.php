<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, /*['login', 'PROXY']*/);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://onlyfaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=88049';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $c = config::credential(['ua' => fn() => config::uagent('mobile')]);
    $userAgent = $c['ua'];

    inf::setup($userAgent, $cookieFile, $ip, false, $login);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$login");
    $b->task2('ok', "site: $host");
    
} ) ($login, $ip, $host);
#goto tes;
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
        $_0 = checkCF($host.$r, $api, $_0)['html'] ?? null;
        
        #_put('0.html', $_0);
        $f = scraper::payload($_0, 'loginForm')[0] ?? null;
        $po = null;
        #print_r($f); die;
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
            _sle(3);
            #print_r($po); die;
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
        }
        
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    $_fa = Scraper::_xP($dash, "//ul[@id='faucet']//a/@href");
    foreach ($_fa as $fa) {
        if (!$claim) break;
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        $ret99 = 0;
        while (true) {
            $fauu = null;
            $fauu = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent, d: true);
            
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
            #_put('fau.html', $fau); #die;
            
            if (isset($habis[$fa])) {
                $curr = '';
                continue 2;
            }
            
            if ($ban = isBan($fau)) {
                logx('err', " kena ban: " . $ban['ti']);
                /*
                if (!$SLDONE) {
                    $curr = $_c; 
                    break 2;
                }
                */
                styler("waiting for unlocked {$ban['tmr']}", fn() => _sle($ban['sleep']));
                continue;
            }
            
            if (!empty($fau)) {
                
                $po = null;
                $f = scraper::payload($fau, 'fauform')[0]?? null;
                
                if (!empty($f)) {
                    print_r($f);
                    
                    $pa = $f['payload'];
                    
                    $cap = Solve::exec($fau, $host, $api, $pa);
                    
                    if (isset($cap['nocaptcha']) && isset($pa['captcha_answer'])) {
                        $cap = onfCap($fau, $host, $fa, $api);
                    }
                    
                    if (isset($cap['trouble'])) {
                        _sle(5);
                        continue;
                    }
                    $po = array_merge($pa, $cap);
                    
                }
                
                if (!empty($po)) {
                    print_r($po);
                    $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host, inf::$uagent);
                    _put('cla.html', $cla);
                    $_suc = scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                    
                    if (!empty($_suc[1][0])) {
                        $stt = $_suc[1][0];
                        $msg = $_suc[2][0];
                        $is_ok = (stripos($stt, 'success') !== false);
                        
                        Logger::M($login);
                        Logger::X($is_ok ? 'ok' : 'err', "{$stt} ", false);
                        logg(false, $msg);
                        
                        
                        if (stripos($msg, 'address is not allowed.')) {
                            @unlink(inf::$cookie);
                            goto login;
                        }
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) {
                            $habis[$fa] = true;
                            break;
                        }
                        if (stripos($msg, 'flagged')) die;
                        
                        
                    }
                    
                }
                
                _rl('lanjut: ');
                
            }
            
        }
    die;
    }
    
    
    
    
die;
}





tes:
    






function onfCap($html, $host, $reff, $api) {
    $setCAP = microtime(true);
    $img = null;
    $x_cap = ['ins' => 'ASC', 'cnt' => 3];

    $req = Net::X($host.'/faucet/captcha_image?_t=' . (time() * 1000), 'GET', null, inf::$cookie, [], $reff, inf::$uagent, d: true);
    
    if (!empty($req) && $req !== 99) {
        $x_pow = [
            'salt' => $req['headers']['x-pow-salt'][0] ?? '',
            'diff' => (int)($req['headers']['x-pow-difficulty'][0] ?? 2)
        ];
        $x_cap = [
            'ins' => $req['headers']['x-captcha-instruction'][0] ?? 'ASC',
            'cnt' => (int)($req['headers']['x-captcha-target-count'][0] ?? 3)
        ];
        $img = $req['body'];
    }
    
    if (!empty($img)) {
        $solution = Solve::img($api, $host, 'onlyfans', $img);
        if (isset($solution['trouble'])) return ['trouble' => 'reload'];
        
        usort($solution, function($a, $b) use ($x_cap) {
            return ($x_cap['ins'] === 'ASC') 
                ? ($a['area'] <=> $b['area']) 
                : ($b['area'] <=> $a['area']);
        });
        
        $clk = array_slice($solution, 0, $x_cap['cnt']);
        
        $mdt = [];
        $ANS = [];
        $setCLK = microtime(true);
        
        foreach ($clk as $index => $obj) {
            $delay = ($index === 0) ? mt_rand(800000, 1200000) : mt_rand(400000, 700000);
            usleep($delay);
            
            $current = (microtime(true) - $setCLK) * 1000;
            
            $x = (int)max(0, min(449, $obj['center'][0]));
            $y = (int)max(0, min(279, $obj['center'][1]));
            
            $ANS[] = "$x,$y";
            $mdt[] = [
                'x' => $x,
                'y' => $y,
                't' => (int)$current
            ];
        }
        $x_ans = implode(';', $ANS);
        
        $waktu = (int)((microtime(true) - $setCAP) * 1000);
        $bfp = onfFPS(inf::$uagent, $mdt, $waktu);
        $powRes = SolveUtils::Pow($x_pow['salt'], $x_pow['diff']);
        
        return [
            'pow_nonce' => $powRes['nonce'] ?? 0,
            'captcha_answer' => implode(';', $ANS),
            'browser_fingerprint' => $bfp
        ];
    }
    return ['trouble' => 'reload'];
}

function onfFPS($ua, array $mouse, int $waktu) {
    $isMobile = (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false || strpos($ua, 'iPhone') !== false);

    $gl = $isMobile ? 'ANGLE (ARM, Mali-G57, OpenGL ES 3.2)' : 'ANGLE (NVIDIA, NVIDIA GeForce RTX 3060, OpenGL 4.5)';

    $raw = [
        'iw'  => $isMobile ? 360 : 1920,
        'ih'  => $isMobile ? 664 : 1080,
        'gl'  => $gl,
        'sw'  => $isMobile ? 360 : 1920,
        'sh'  => $isMobile ? 800 : 1080,
        'wd'  => false,
        'chr' => true,
        'ua'  => $ua
    ];

    $hwDetails = [
        'gl'  => $raw['gl'],
        'sw'  => $raw['sw'],
        'sh'  => $raw['sh'],
        'wd'  => $raw['wd'],
        'chr' => $raw['chr'],
        'ua'  => $raw['ua']
    ];
    $hardwareHash = djb2(json_encode($hwDetails, JSON_UNESCAPED_SLASHES));

    $payload = [
        'solve_time_ms' => $waktu,
        'hardware_hash' => $hardwareHash,
        'webdriver' => 0,
        'mouse_data' => array_values($mouse),
        'raw' => $raw
    ];

    return base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
}


function djb2($str) {
    $hash = 5381;
    for ($i = 0; $i < strlen($str); $i++) {
        $hash = (($hash * 33) ^ ord($str[$i]));
        $hash = $hash & 0xFFFFFFFF;
    }
    return dechex($hash);
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

function isBan($html) {
    if (!$html) return false;
    if (stripos($html, 'account has been banned')) {
        logx('err', 'Yahhh... Akun Banned Permanen!');
        exit;
    }
    
    if (!stripos($html, 'Temporarily Blocked') && !stripos($html, 'Temporary Ban') && !stripos($html, 'temporarily locked')) {
        return false;
    }

    $countdownText = Scraper::_xP($html, "//*[@id='block-countdown']")[0] ?? '';
    
    $m = 0; 
    $s = 0;
    if (preg_match('/(\d+)\s*minute/', $countdownText, $matchM)) $m = (int)$matchM[1];
    if (preg_match('/(\d+)\s*second/', $countdownText, $matchS)) $s = (int)$matchS[1];

    $r = Scraper::_xP($html, "//div[contains(@class, 'alert-danger')]//p[1]")[0] ?? 'CAPTCHA failed';

    return [
        'ti' => trim($r),
        'tmr' => sprintf('%02d:%02d', $m, $s),
        'sleep' => ($m * 60) + $s + 5 
    ];
}