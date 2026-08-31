<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = Config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://onlyfaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=14749';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = Config::cookie($login);
    $c = Config::credential(['ua' => fn() => Config::uagent('mobile')]);
    $userAgent = $c['ua'];

    Inf::setup($userAgent, $cookieFile, $ip, false, $login);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$login");
    $b->task2('ok', "site: $host");
    
} ) ($login, $ip, $host);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$claim = true;
$habis = [];
$curr = 'usdt';
while (true) {
    $ret = 0;

    do {
        $ret++;
        $l = Inf::check("$host", $headersCF, '/auth/login');

        if ($l['ok']) {
            $dash = $l['html'];
            Logger::X('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        if ($ret >= 10) {
            Logger::X('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            
            if (!empty($_0)) _put(__DIR__.'/lo.html', $_0);
            if (isset($ve) && !empty($ve)) _put(__DIR__.'/ve.html', $ve);
            
            exit; 
        }
        
        
        Logger::X('err', "logging in ", false); 
        _sle(3); _clr();
        $_0 = Net::X($host.$r, 'GET', null, Inf::$cookie, $headersCF, '', Inf::$uagent, d: true);
        if ($_0 === 99) {
            Logger::X('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        $_0 = checkCF($host.$r, $api, $_0)['html'] ?? null;
        
        #_put('0.html', $_0);
        $f = Scraper::payload($_0, 'loginForm')[0] ?? null;
        $po = null;
        #print_r($f); die;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['wallet' => $login];
            $cap = Solve::exec($_0, $host, $api, $pa);
            
            if (isset($cap['trouble'])) {
                $tro = $cap['trouble'];
                Logger::X('warn', "Solver trouble: $tro");
                ($tro === 'proxy') ? _sle(30) : _sle(10);
                continue;
            }
            $cleanCap = array_filter((array)$cap, fn($v, $k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_BOTH);
            $po = array_merge($pa, $cleanCap, $cre);
        }
        
        if (!empty($po)) {
            _sle(3);
            #print_r($po); die;
            $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $headersCF, $host.$r, Inf::$uagent);
            #_put('ve.html', $ve); die;
        }
        
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    $_fa = Scraper::_xP($dash, "//ul[@id='faucet']//a/@href");
    foreach ($_fa as $fa) {
        if (!$claim) break;
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.'processing  ');
        Logger::X('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        $ret99 = 0;
        while (true) {
            $fauu = null;
            $fauu = Net::X($fa, 'GET', null, Inf::$cookie, $headersCF, $host, Inf::$uagent, d: true);
            
            if ($fauu === 99) {
                $ret99++;
                Logger::X('warn', 'Proxy issue, wait 30s');
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
                Logger::X('err', " kena ban: " . $ban['ti']);
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
                $f = Scraper::payload($fau, 'fauform')[0]?? null;
                
                if (!empty($f)) {
                    #print_r($f);
                    
                    $pa = $f['payload'];
                    
                    $cap = Solve::exec($fau, $host, $api, $pa);
                    
                    if (isset($cap['nocaptcha']) && isset($pa['captcha_answer'])) {
                        $cap = onfCap($fau, $host, $fa, $api, $headersCF);
                    }
                    
                    if (isset($cap['trouble'])) {
                        _sle(5);
                        continue;
                    }
                    $po = array_merge($pa, $cap);
                    
                }
                
                if (!empty($po)) {
                    #print_r($po);
                    $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $headersCF, $host, Inf::$uagent);
                    #_put('cla.html', $cla);
                    $_suc = Scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                    
                    if (!empty($_suc[1][0])) {
                        $stt = $_suc[1][0];
                        $msg = $_suc[2][0];
                        $is_ok = (stripos($stt, 'success') !== false);
                        
                        Logger::M($login);
                        Logger::X($is_ok ? 'ok' : 'err', "{$stt} ", false);
                        Logger::G(false, $msg);
                        
                        
                        if (stripos($msg, 'address is not allowed.')) {
                            @unlink(Inf::$cookie);
                            goto login;
                        }
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) {
                            $habis[$fa] = true;
                            break;
                        }
                        if (stripos($msg, 'flagged')) die;
                        
                        styler("waiting for next claim", fn() => _sle(8));
                    }
                    
                }
                
            }
            
        }
    
    }
    
    if (count($habis) === count($_fa)) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (Logger::X('err', 'gak bisa claim') ?: die);
    }
    
    $_sl = Scraper::_xP($dash, "//ul[@id='links']//a/@href");
    foreach ($_sl as $sl) {
        if ($SLDONE) break;
        $_c = basename($sl);
        if (!empty($curr) && (trim(strtoupper($_c)) !== trim(strtoupper($curr)))) continue;
        
        $ret99 = 0;
        do {
            $sho = null;
            $sho = Net::X($sl, 'GET', null, Inf::$cookie, $headersCF, '', Inf::$uagent);
            #_put('sho.html', $sho); die;
            
            if ($sho === 99) {
                $ret99++;
                Logger::X('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            
            if (empty($sho)) { _sle(5); continue; }
            
            $short = Shortlinks::extract($sho);
            if (empty($short)) continue;
            
            #print_r($short); #die;
            $success_in_page = false; 
            $found_one = false; 
            
            $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
            foreach ($short as $links => [$idd, $lmt]) {
                if (!Shortlinks::limit($lmt) || isset($skipped[$idd])) continue;
                
                $found_one = true;
                $loc = onfSL($idd, $sl, $_c, $headersCF);
                
                if (!$loc) {
                    $skipped[$idd] = true; 
                    continue 2;
                }
                
                $loc_u = parse_url($loc['url'])['host'] ?? '';
                $is_bl = false;
                foreach ($up as $blacklisted) {
                    if (str_contains($loc_u, $blacklisted)) {
                        Logger::X('warn', "Domain Blacklist [$blacklisted] Skipping..");
                        $skipped[$idd] = true;
                        $is_bl = true;
                        break; 
                    }
                }
                if ($is_bl) break; 
                
                Logger::X('info', "Bypassing SL: {$loc['url']}", true, true);
                $start = microtime(true);
                $bakk = Shortlinks::exec($api, $loc['url']);
                
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                $wait = 130 - (int)(microtime(true) - $start);
                if ($wait > 0) styler("waiting for SL", fn() => _sle((int)ceil($wait)));
                
                $ver = null;
                $retVer = 0;
                while (true) {
                    $ver = Net::X($bakk, 'GET', null, Inf::$cookie, $headersCF, $loc['url'], Inf::$uagent);
                    if ($ver === 99) {
                        $retVer++;
                        if ($retVer >= 5) goto login;
                        _sle(30);
                        continue;
                    }
                    break;
                }
                
                if (!empty($ver)) {
                    #_put('ver.html', $ver);
                    
                    $po = null;
                    $f = Scraper::payload($ver, 'claimForm')[0] ?? null;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        
                        $cap = Solve::exec($ver, $host, $api);
                        $po = array_merge($pa, $cap);
                        
                    }
                    
                    if (!empty($po)) {
                        #print_r($po); die;
                        $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $headersCF, $host, Inf::$uagent);
                        
                        $_suc = Scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                        if (!empty($_suc[1][0])) {
                            $stt = $_suc[1][0];
                            $msg = $_suc[2][0];
                            $is_ok = (stripos($stt, 'success') !== false);
                            
                            Logger::M($login);
                            Logger::X($is_ok ? 'ok' : 'err', "{$stt} ", false);
                            Logger::G(false, $msg);
                            if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                $currentIndex = array_search($sl, $_sl);
                                if ($currentIndex !== false && isset($_sl[$currentIndex + 1])) {
                                    $curr = basename($_sl[$currentIndex + 1]);
                                } else $curr = '';
                                break 2; 
                            } elseif (stripos($cla, 'has been sent to your')) $success_in_page = true;
                            
                            break 2;
                        }
                        
                    }
                    
                }
                
            }
            if (!$found_one) {
                Logger::X('err', 'SL habis atau sisa blacklist.');
                $SLDONE = true;
                break; 
            }
            
        } while (!$success_in_page);
        
        
        if ($success_in_page || $curr === "") break; 
    }
    
}





tes:
    







function onfCap($html, $host, $reff, $api, $headersCF) {
    #_sle(3);
    $setCAP = microtime(true);
    $img = null;
    $x_cap = ['ins' => 'ASC', 'cnt' => 3];
    $warna = null;
    $wtype = '';

    $req = Net::X($host.'/faucet/captcha_image?_t=' . (time() * 1000), 'GET', null, Inf::$cookie, $headersCF, $reff, Inf::$uagent, d: true);
    
    if (!empty($req) && $req !== 99) {
        $x_pow = [
            'salt' => $req['headers']['x-pow-salt'][0] ?? '',
            'diff' => (int)($req['headers']['x-pow-difficulty'][0] ?? 2)
        ];
        $sequence = $req['headers']['x-captcha-prompt-sequence'][0] ?? null;
        if (str_contains($html, 'destinations in order') && !empty($sequence)) {
            $warna = [
                'ins' => $sequence,
                'cnt' => count(explode(',', $sequence))
            ];
            $wtype = 'necaptcha';
        } elseif (str_contains($html, 'line to the correct destination ')) {
            $warna = [
                'ins' => $req['headers']['x-captcha-color-name'][0] ?? 'RED',
                'cnt' => (int)($req['headers']['x-captcha-target-count'][0] ?? 1)
            ];
            $wtype = 'necaptcha';
        }
        $x_cap = $warna ?? [
            'ins' => $req['headers']['x-captcha-instruction'][0] ?? 'ASC',
            'cnt' => (int)($req['headers']['x-captcha-target-count'][0] ?? 3)
        ];
        $img = $req['body'] ?? null;
    }
    
    if (!empty($img)) {
        $captype = $wtype ?? 'onlyfans';
        $cappart = $warna ? $x_cap : [];
        
        
        $solution = Solve::img($api, $host, $captype, $img, $cappart);
        if (isset($solution['trouble'])) return ['trouble' => 'reload'];
        
        preg_match_all('/x[=:\s]*(\d+)[,\s]*y[=:\s]*(\d+)/i', $solution, $matches, PREG_SET_ORDER);
        
        if (count($matches) < $x_cap['cnt']) return ['trouble' => 'reload'];
        
        if ($x_cap['ins'] === 'DESC' && !$warna) $matches = array_reverse($matches);
        
        $clk = array_slice($matches, 0, $x_cap['cnt']);
        
        $mdt = [];
        $ANS = [];
        $setCLK = microtime(true);
        
        foreach ($clk as $index => $match) {
            $delay = ($index === 0) ? mt_rand(800000, 1200000) : mt_rand(400000, 700000);
            usleep($delay);
            
            $current = (microtime(true) - $setCLK) * 1000;
            
            $x = (int)max(0, min(449, $match[1]));
            $y = (int)max(0, min(279, $match[2]));
            
            $ANS[] = "$x,$y";
            $mdt[] = [
                'x' => $x,
                'y' => $y,
                't' => (int)$current
            ];
        }
        $x_ans = implode(';', $ANS);
        
        $waktu = (int)((microtime(true) - $setCAP) * 1000);
        $bfp = onfFPS(Inf::$uagent, $mdt, $waktu);
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
    
    $jsonString = json_encode($hwDetails, JSON_UNESCAPED_SLASHES); 
    $hardwareHash = djb2($jsonString); 

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
    for ($i = (strlen($str) - 1); $i >= 0; $i--) $hash = ((($hash * 33) & 0xFFFFFFFF) ^ ord($str[$i])) & 0xFFFFFFFF;
    $sign = sprintf('%u', $hash & 0xFFFFFFFF);
    return base_convert($sign, 10, 16);
}










function onfSL($linkId, $reff, $curr, $headersCF) {

    $token = json_decode(Net::X("https://onlyfaucet.com/links/get_csrf_token", 'GET', null, Inf::$cookie, $headersCF, $reff, Inf::$uagent, true)?: '', 1)['csrf_hash'] ?? null;
    
    if ($token) {
        $payload = [
            'link_id' => $linkId,
            'cur' => strtoupper($curr),
            'csrf_token_name' => $token
        ];
        
        $short = json_decode(
                Net::X("https://onlyfaucet.com/links/verify_go",
                       'POST',
                       SolveUtils::webkitID($payload, $bon),
                       Inf::$cookie, 
                       ["Content-Type: multipart/form-data; boundary=$bon"],
                       $reff,
                       Inf::$uagent)
                ?: '', 1)['url'] ?? null;
        
        if ($short) return ['url' => $short, 'tkn' => $token];
        
    }
    
    return ['trouble' => 'reload'];
}

function checkCF($url, $api, $body = null, $headersCF = []) {
    
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    
    if (!$html || !$code) return [];
    
    if ($code !== 200 && (stripos($html, 'Just a moment') !== false || stripos($html, 'Attention Required!') !== false)) {
        
        $cf = Cloudflare::exec($api, $url, Inf::$cookie, Inf::$uagent, ['html' => $html]);
        
        if ($cf) {
            [$headersCF, $ua] = $cf;
            Inf::setup($ua, Inf::$cookie);
            
            if (!empty($headersCF)) {
                for ($try = 1; $try <= 3; $try++) {
                    _sle(3);
                    $fix = Net::X($url, 'GET', null, Inf::$cookie, $headersCF, $url, Inf::$uagent, d: true);
                    
                    if (!empty($fix) && isset($fix['http_code'])) {
                        $_c = $fix['http_code'];
                        $_b = $fix['body'];
                        
                        if ($_c === 200 && stripos($_b, 'Just a moment') === false && stripos($_b, 'Attention Required!') === false) {
                            
                            Config::credential()['ua'] = $ua;
                            return ['html' => $_b, 'head' => $headersCF];
                        }
                    }
                    Logger::X('info', "try-{$try} fail, reloading");
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
        Logger::X('err', 'Yahhh... Akun Banned Permanen!');
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
