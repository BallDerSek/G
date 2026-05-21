<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://onlyfaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=88049';
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
$SLDONE = false;
$claim = false;
$curr = '';
$dash = null;
while (true) { 
    $ret = 0;

    do {
        $ret++;
        $l = inf::check("$host/", $headersCF, '/auth/login');
        
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
        _sle(3); _clr();$_0 = Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent, d: true);
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        $_0 = checkCF($host, $api, $_0);
        #_put('0.html', $_0);
        $f = scraper::payload($_0)[0] ?? null;
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
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
            #_put('ve.html', $ve);

            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            $_sucS = scraper::_jP($ve, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s") ?? [];
            if (isset($_sucS[3][0])) (logx('err', $_sucS[3][0]) ?: die);
            
        }
    } while (empty($dash));
    #_put('dash.html', $dash); die;
    
#goto sl;
    $successCount = 0; 
    $_fa = Scraper::_xP($dash, "//ul[@id='faucet']//a/@href");
    foreach ($_fa as $fa) {
        if (!$claim) break;
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while (true) {
            $fau = null;
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent, d: true);
            
            if ($fau === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            $fau = checkCF($fa, $api, $fau);
            
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

            $po = null;
            $f = scraper::payload($fau)[0] ?? null;
            #_put('fau.html', $fau); #die;
            if ($f) {
                $cap = [];
                $pa = $f['payload'];
                $cap = onfCap($fau, $fa, $api, $pa, $headersCF);

                if (empty($cap)) {
                    $cap = Solve::exec($fau, $host, $api, $pa);
                }
                
                if (isset($cap['nocaptcha']) && isset($pa['_aid'])) {
                    $cfg = Scraper::_xP($fau, "//div[@id='_bk']/@data-config")[0] ?? null;
                    $cap = onfAid($cfg);
                }
                
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    ($tro === 'proxy') ? _sle(30) : _sle(10);
                    continue;
                }
                
                #var_dump($cap);
                $po = array_merge($pa, $cap);
            }
            
            if (!empty($po)) {
                print_r($po); die;
                _sle(3);
                $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host, inf::$uagent);
                if ($cla && $cla !== 99) { 
                    #_put('cla.html', $cla);
                    $_suc = null;
                    $_suc = scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s") ?? [];
                    if (!empty($_suc[1][0])) {
                        $status = $_suc[1][0]; 
                        $msg = $_suc[2][0];
                        print(FGd['CYN'] . maskEmail($login) . RSET . " ");
                        logx($status === 'success' ? 'ok' : 'err', "$status ", false);
                        logg(false, $msg);
                        if (stripos($msg, 'has been sent')) $successCount++;
                        
                        if ($successCount >= 100) {
                            $successCount = 0; 
                            $curr = $_c; 
                            break 2; 
                        }
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) break;
                        if (stripos($msg, 'flagged')) die;
                        if (stripos($msg, 'Shortlink')) {
                            if ($SLDONE) {
                                logx('err', 'Gada jatah SL lagi');
                                die;
                            }
                            $curr = $_c; 
                            break 2;
                        }
                    }
                    
                }
    
                styler("waiting for next claim", fn() => _sle(5));
            }
        }
    }

sl:
    $valid = [];
    $success_in_page = false;
    $_sl = Scraper::_xP($dash, "//ul[@id='links']//a/@href");
    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (!empty($curr) && strcasecmp(trim($_c), trim($curr)) !== 0) continue;
        
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
        $ret99 = 0;
        
        do {
            $sho = null;
            $sho = Net::X($sl, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
            
            if ($sho === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            
            if (empty($sho)) { _sle(5); continue; }
            
            $short = sScraper::extract($sho);
            if (empty($short)) continue;
            #print_r($short); die;
            $success_in_page = false; 
            $found_one = false; 
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt) || isset($skipped[$idd])) continue;
                
                $loc = null; 
                $bakk = null; 
                $get = null;
                
                $found_one = true;
                $valid[$links] = [$idd, $lmt];
                $loc = onfSL($idd, $sl, $_c);
                #var_dump($loc);
                if (isset($loc['trouble'])) {
                    _sle(30);
                    continue;
                }
                
                if (!$loc) {
                    $skipped[$idd] = true; 
                    continue 2;
                }
                
                $loc_u = parse_url($loc['url'])['host'] ?? '';
                $is_bl = false;
                foreach ($up as $blacklisted) {
                    if (str_contains($loc_u, $blacklisted)) {
                        logx('warn', "Domain Blacklist [$blacklisted] Skipping..");
                        $skipped[$idd] = true;
                        $is_bl = true;
                        break; 
                    }
                }
                if ($is_bl) break; 
                
                logx('info', "Bypassing SL: {$loc['url']}", true, true);
                $start = microtime(true);
                $bakk = links($api, $loc['url']);
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                $wait = 100 - (int)(microtime(true) - $start);
                if ($wait > 0) styler("waiting for SL", fn() => _sle((int)ceil($wait)));
                
                $retGet = 0;
                while (true) {
                    $get = null; 
                    $cap = null; 
                    $po = null; 
                    $pa = null; 
                    $ver = null;
                    $boundary = '';
                    
                    $get = Net::X($bakk, 'GET', null, inf::$cookie, $headersCF, $loc['url'], inf::$uagent, d:true);
                    
                    if ($get === 99) {
                        $retGet++;
                        logx('warn', 'Proxy issue, wait 30s');
                        if ($retGet >= 7) goto login;
                        _sle(10);
                        continue;
                    }
                    
                    if (!empty($get['body'])) {
                        #_put('get.html', $get['body']); 
                        
                        $get = checkCF($bakk, $api, $get);
                        
                        
                        $_s = scraper::_jP($get, "/var\s+HAS_PENDING\s*=\s*(true|false);/i");
                        $_p = filter_var($_s[1][0] ?? false, FILTER_VALIDATE_BOOLEAN);

                        if (stripos($get, 'captchaModal') && $_p) {
                            $json = Scraper::_jP($get, '/var CFG\s*=\s*(\{.*?\});/s')[1][0] ?? null;
                            
                            if ($json) {
                                $cfg = json_decode($json, true);
                                $cfg['csrfHash'] = $loc['csrf'];
                                $retCap = 0;
                                while (true) {
                                    $cap = onfDrg(json_encode($cfg), $sl);
                                    if (isset($cap['captcha_verify_token'])) break;
                                    $retCap++;
                                    if ($retCap >= 5) break; 
                                    _sle(10);
                                }
                                    
                                if (isset($cap['captcha_verify_token'])) {
                                    $pa = array_merge($cap, ['csrf_token_name' => $loc['csrf']]);
                                    $po = solveUtils::webkitID($pa, $boundary);
                                    $he = ["Content-Type: multipart/form-data; boundary=$boundary"];
                                }
                            }
                            
                        } elseif (stripos($get, 'claimModal')) {
                            $f = scraper::payload($get)[0] ?? [];
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                $retCap = 0;
                                while (true) {
                                    $cap = solve::exec($get, $host, $api, $pa);
                                    if (!empty($cap) && !isset($cap['trouble'])) break;
                                    $retCap++;
                                    if ($retCap >= 5) break; 
                                    _sle(10);
                                }
                                $po = array_merge($cap, ['csrf_token_name' => $loc['csrf']], $cap);
                                $he = $headersCF;
                            }
                        }
                        
                        $po = $po ?? null;
                        if (empty($po)) {
                            $f = scraper::payload($get);
                            if (!empty($f)) {
                                foreach ($f as $fo) {
                                    if (isset($fo['payload']['csrf_token_name'])) {
                                        $po = $fo['payload'];
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if (!empty($po)) {
                            #print_r($po);
                            
                            $ver = Net::X("$host/links/complete_claim", 'POST', $po, inf::$cookie, $he, $sl, inf::$uagent);
                            
                            #_put('ver.html', $ver);
                            
                            if (!empty($ver) && ($ver !== 99)) {
                                $cla = json_decode($ver, true);
                                $sucJ = filter_var($cla['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
                                
                                $_sucH = scraper::_jP($ver, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s") ?? [];
                                
                                $suc = $_sucH[1][0] ?? $sucJ;
                                
                                logx($suc ? 'ok' : 'err', $suc ? "Success " : "error ", false);
                                logg(false, $cla['message'] ?? "{$_sucH[2][0]}" ?? 'no message');
                                if (stripos($ver, 'has been sent to your')) $suc = true;
                                
                                if ($suc) {
                                    $success_in_page = true;
                                    break 3;
                                } else {
                                    _sle(5);
                                    break;
                                }
                            } else {
                                _sle(30);
                                continue 2;
                            }
                        }
                        
                    }
                }
                break;
            }
                
            if (!$found_one) {
                logx('err', 'SL habis atau sisa blacklist.');
                $SLDONE = true;
                break; 
            }
        } while (!$success_in_page);
        
        if ($success_in_page || $curr === "") break; 
    }

    unset($sho, $ver, $fau, $cla); 
    
    if (!$claim && $SLDONE) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'beres') ?: die);
    }
    
}













tes:







function onfSL($linkId, $reff, $curr) {

    $s_1 = Net::X("https://onlyfaucet.com/links/get_csrf_token", 'GET', [], inf::$cookie, [], $reff, inf::$uagent, true);
    if (!$s_1 || $s_1 == 99) return ['trouble' => 'reload'];
    $_1 = json_decode($s_1, true);
    
    $token = $_1['csrf_hash'] ?? null;
    if (!$token) return ['trouble' => 'reload'];

    $payload = [
        'link_id' => $linkId,
        'cur' => strtoupper($curr),
        'csrf_token_name' => $token
    ];
    
    $pa = solveUtils::webkitID($payload, $bon);
    $s_2 = Net::X("https://onlyfaucet.com/links/verify_go", 'POST', $pa, inf::$cookie, ["Content-Type: multipart/form-data; boundary=$bon"], $reff, inf::$uagent);
    
    if (!$s_2 || $s_2 == 99) return ['trouble' => 'reload'];
    
    $_2 = json_decode($s_2, true);
    
    if (isset($_2['success']) && $_2['success'] && isset($_2['url'])) {
        return [
            'url'  => $_2['url'], 
            'csrf' => $token 
        ];
    }

    return false;
}

function onfDrg($json_cfg, $reff) {
    $cfg = json_decode($json_cfg, true);
    #print_r($cfg);
    if (!$cfg) return [];

    $_v = $cfg['verifyUrl'] ?? '';
    $_c = $cfg['csrfHash'] ?? '';
    
    $raw0 = Net::X($_v, 'POST', ['action' => 'generate', 'csrf' => $_c], inf::$cookie, [], $reff, inf::$uagent, true);
    #var_dump($raw0); die;
    if (!$raw0 || $raw0 == 99) return ['trouble' => 'reload'];

    $_0 = json_decode($raw0, true);
    #print_r($_0);
    if (!filter_var($_0['ok'] ?? false, FILTER_VALIDATE_BOOLEAN) || !isset($_0['token'])) {
        return [];
    }

    $_t = explode('.', $_0['token']);
    if (count($_t) < 2) return ['trouble' => 'reload'];
    
    $coord = json_decode(base64_decode($_t[0]), true);
    $_x = $coord['x'] ?? 0;
    $_y = $coord['y'] ?? 0;

    $dur = rand(1800, 2800);
    $lag = rand(150, 350);
    
    $_d = $dur + $lag + rand(50, 150);
    usleep($_d * 1000); 

    $post = [
        'action' => 'verify',
        'token' => $_0['token'],
        'x' => $_x + rand(-1, 1),
        'y' => $_y,
        'csrf' => $_0['csrf_hash'] ?? $_c,
        'motor' => [
            'dur' => $dur, 
            'evts' => rand(45, 85),
            'pvar' => round(10 + (mt_rand() / mt_getrandmax()) * 15, 3),
            'vchg' => rand(1, 4),
            'accel' => round(0.001 + (mt_rand() / mt_getrandmax()) * 0.005, 4),
            'lag' => $lag
        ]
    ];

    $raw1 = Net::X($_v, 'POST', $post, inf::$cookie, [], $reff, inf::$uagent, true);
    
    if (!$raw1 || $raw1 == 99) return ['trouble' => 'reload'];

    $_1 = json_decode($raw1, true);
    #print_r($_1);
    if (filter_var($_1['ok'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return ['captcha_verify_token' => $_1['verify_token']];
    }

    return ['trouble' => 'reload'];
}

function onfOdd($img) {
    if (!getDeps('gd@php')) {
        logx('err', "gd@php is missing");
        exit(9);
    }
    $image = imagecreatefromstring($img);
    if (!$image) return ['trouble' => 'reload'];
    
    $width = imagesx($image);
    $height = imagesy($image);
    $segW = intdiv($width, 5);    
    
    $S_R = [0, 0, 0, 0, 0];
    $S_E = [0, 0, 0, 0, 0];

    for ($i = 0; $i < 5; $i++) {
        $startX = $i * $segW;
        $endX = $startX + $segW;
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = $startX; $x < $endX; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Abaikan background putih dan abu-abu bayangan
                if ($r > 220 && $g > 220 && $b > 220) continue;
                if (abs($r - $g) < 20 && abs($g - $b) < 20) continue;

                // ---- FILTER SPESIFIK WARNA TARGET M-CAPTCHA ----
                
                // 1. Target BIRU (Komponen B wajib dominan mutlak mengalahkan R dan G)
                $is_biru = ($b > $g && ($b - $r) > 30);
                
                // 2. Target HIJAU / KUNING / JINGGA (Komponen G atau R dominan, tapi B lemah)
                $is_hijau_kuning = ($g > $b && ($g - $r) > -40);
                
                // 3. Target UNGU TUA (R and B tinggi tapi gelap, bukan Pink cerah)
                $is_ungu = ($r > $g && $b > $g && abs($r - $b) < 40 && $r < 180);

                if ($is_biru || $is_hijau_kuning || $is_ungu) {
                    // Masuk kategori warna langka yang dicari
                    $S_R[$i]++;
                } else {
                    // Masuk kategori warna mayoritas pengganggu (Pink cerah, Merah menyala, dll)
                    $S_E[$i]++;
                }
            }
        }
    }

    $ans = 0;
    $maxPixels = -1;
    
    for ($i = 0; $i < 5; $i++) {
        if ($S_R[$i] > $maxPixels) {
            $maxPixels = $S_R[$i];
            $ans = $i;
        }
    }

    if ($maxPixels < 15) {
        $ans = array_search(max($S_E), $S_E);
    }
    
    @imagedestroy($image);
    return $ans; 
}





function onfAid($cfg_hex) {
    if (!$cfg_hex || strlen($cfg_hex) !== 64) return ['trouble' => 'reload'];

    $motor = [
        'ev' => 1,
        'sc' => rand(35, 55),
        'yv' => rand(10, 35) / 100,
        'vm' => rand(150, 280) / 100,
        'vs' => rand(100, 190) / 100,
        'as' => rand(70, 95),
        'jk' => rand(15, 45) / 100,
        'dr' => rand(450, 750)
    ];

    $json = json_encode($motor);
    
    $_ke = hex2bin($cfg_hex);
    $_iv = random_bytes(12);
    
    $_ci = openssl_encrypt($json, 'aes-256-gcm', $_ke, OPENSSL_RAW_DATA, $_iv, $_ta);
    
    if ($_ci !== false) return ['_aid' => base64_encode($_iv . $_ci . $_ta)];
    
    return ['trouble' => 'reload'];
}

function onfCap($fau, $host, $api, $payload, $he) {
    
    if (stripos($fau, 'mcaptcha') !== false) {
        $ins = Scraper::_xP($fau, "//p[@id='mcaptcha-instruction']")[0] ?? 
               Scraper::_xP($fau, "//div[@id='mcaptcha-challenge-box']//p[@class='text-muted small mb-0']")[0] ?? '';
        $img_u = "https://onlyfaucet.com/faucet/captcha_image?_t=".round(microtime(true) * 1000);
        
        $res = Net::X($img_u, 'GET', [], inf::$cookie, $he, $host, inf::$uagent, d: true);
        $r_hee = $res['headers'] ?? [];
        $r_pSa = $r_hee['x-pow-salt'][0] ?? $r_hee['X-PoW-Salt'][0] ?? null;
        $r_pDi = (int)($r_hee['x-pow-difficulty'][0] ?? $r_hee['X-PoW-Difficulty'][0] ?? 4);
        $r_bFi = $r_hee['x-pow-bfield'][0] ?? $r_hee['X-PoW-BField'][0] ?? null;
        
        $img = $res['body'] ?? '';
        if (!empty($img) && $img !== 99) {
            
#debug
            $img_d = imagecreatefromstring($img);
            if ($img_d) {
                $w = imagesx($img_d);
                $h = imagesy($img_d);
                $colW = intdiv($w, 5);
                $black = imagecolorallocate($img_d, 0, 0, 0);
                for ($g = 1; $g < 5; $g++) {
                    $lineX = $g * $colW;
                    imageline($img_d, $lineX, 0, $lineX, $h, $black);
                }
                imagepng($img_d, 'imgg.png');
                @imagedestroy($img_d);
            }
#debug

            $m_ans = null;
            $m_idx = 0;
            $ans_idx = onfOdd($img); 
            if ($ans_idx === false) return ['trouble' => 'reload'];
            if ($ans_idx >= 0 && $ans_idx <= 4) {
                $m_ans = (string)$ans_idx;
                $m_idx = $ans_idx;
            }
            if ($m_ans !== null) {
                $m_dta = ['captcha_answer' => $m_ans];
                $dx = rand(180, 450);
                $t1 = rand(700, 1400);
                $mc = rand(12, 28);
                $td = rand(400, 950);
                
                $cv_E = 300;
                $sectorWidth = $cv_E / 5; 
                $cx = rand(($m_idx * $sectorWidth) + 8, (($m_idx + 1) * $sectorWidth) - 8);
                $cy = rand(40, 180);
                if ($r_bFi) $m_dta[$r_bFi] = (string)$dx;
                
                if ($r_pSa) {
                    $m_slt = $r_pSa . $dx; 
                    $m_nnc = SolveUtils::Pow($m_slt, $r_pDi);
                    $m_dta['pow_nonce'] = (string)$m_nnc;
                }
                
                $cur_ua = inf::$uagent;
                $hc_val = 8;
                $dm_val = 8;
                if (stripos($cur_ua, 'Android') !== false) {
                    $low_ua = ["Redmi 9A", "SM-A015F", "CPH1909", "Vivo 1906"];
                    foreach ($low_ua as $ld) {
                        if (stripos($cur_ua, $ld) !== false) {
                            $hc_val = 4;
                            $dm_val = 4;
                            break;
                        }
                    }
                }
                
                $telemetry = [
                    '_t1' => $t1,
                    '_mc' => $mc,
                    '_cx' => (int)$cx,
                    '_cy' => (int)$cy,
                    '_wd' => 0,
                    '_hc' => $hc_val,
                    '_dm' => $dm_val, 
                    '_sg' => 1,
                    '_td' => $td
                ];
                $json_str = json_encode($telemetry);
                
                $key = $r_pSa; 
                $encrypted_str = '';
                $key_len = strlen($key);
                $str_len = strlen($json_str);
                
                for ($k = 0; $k < $str_len; $k++) {
                    $encrypted_str .= chr(ord($json_str[$k]) ^ ord($key[$k % $key_len]));
                }
                
                $m_dta['x_tz'] = base64_encode($encrypted_str);
                return $m_dta; 
            }
        }
        return ['trouble' => 'reload'];
    }

    $need_c = Scraper::_jP($fau, '/(?:captchaNeeded|captchaRequired)\s*=\s*(true|false)/')[1][0] ?? '';
    if ($need_c === 'true') {
        if (stripos($fau, 'rag the piece to the slot')) {
            $cfg_c = Scraper::_jP($fau, '/var CFG\s*=\s*(\{.*?\});/s')[1][0] ?? null;
            if ($cfg_c) return onfDrg($cfg_c, $host);
        }
        
        if (stripos($fau, 'lect the correct answer')) {
            $ans = '';
            $img_u = Scraper::_xP($fau, "//img[@id='captcha-img']/@src")[0] ?? '';
            $img_o = Scraper::_xP($fau, "//button[contains(@class, 'captcha-opt-btn')]/@data-value");
            
            if ($img_o && $img_u) {
                $img_math = Net::X($img_u, 'GET', [], inf::$cookie, $he, $host, inf::$uagent);
                if (empty($img_math) || $img_math === 99) return ['trouble' => 'proxy'];
                $ans = Solve::img($api, $host, 'math', $img_math);
                if (isset($ans['trouble'])) return $ans;
            }
            if ($ans) {
                $clean = str_replace(['=', '?', ' '], '', $ans);
                $n = Scraper::_jP($ans, '/\d+/')[0] ?? [];
                $_final_ans = null;
                
                if (count($n) >= 2) {
                    $q1 = (int)$n[0];
                    $q2 = (int)$n[1];
                    $op = null;
                    if (stripos($ans, '+') !== false) $op = '+';
                    elseif (stripos($ans, '-') !== false) $op = '-';
                    elseif (stripos($ans, '*') !== false || stripos($ans, 'x') !== false) $op = '*';
                    
                    if ($op) {
                        $res_math = SolveUtils::math($q1, $q2, $op);
                        if (in_array((string)$res_math, $img_o)) $_final_ans = $res_math;
                    }
                    
                    if ($_final_ans === null) {
                        foreach (['+', '-', '*'] as $type) {
                            $res_math = SolveUtils::math($q1, $q2, $type);
                            if (in_array((string)$res_math, $img_o)) {
                                $_final_ans = $res_math;
                                break;
                            }
                        }
                    }
                }
                
                if ($_final_ans === null) {
                    $maa = filter_var($clean, FILTER_SANITIZE_NUMBER_INT);
                    if (in_array((string)$maa, $img_o)) {
                        $_final_ans = $maa;
                    }
                }
                
                if ($_final_ans !== null) {
                    return ['captcha_answer' => (string)$_final_ans];
                }
                return ['trouble' => 'reload'];
            }
        }
    }
    
    return [];
}







function isBan($html) {
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

function checkCF($url, $api, $body = null) {
    
    $html = $body['body'] ?? null;
    $code = $body['http_code'] ?? null;
    
    if (!$html || !$code) return null;
    
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
                        if ($fix['http_code'] === 200) {
                            config::credential()['ua'] = $ua;
                            return $fix['body'];
                        }
                    }
                    logx('info', "try-{$try} fail, reloading");
                }
            }
        }
    } else {
        return $html;
    }
    
    return null;
    
}


/*
POST /faucet/verify/ltc HTTP/2
host: onlyfaucet.com
content-length: 286
cache-control: max-age=0
sec-ch-ua: "Chromium";v="127", "Not)A;Brand";v="99", "Microsoft Edge Simulate";v="127", "Lemur";v="127"
sec-ch-ua-mobile: ?1
sec-ch-ua-full-version: "127.0.6533.144"
sec-ch-ua-arch: ""
sec-ch-ua-platform: "Android"
sec-ch-ua-platform-version: "15.0.0"
sec-ch-ua-model: "RMX3933"
sec-ch-ua-bitness: ""
sec-ch-ua-full-version-list: "Chromium";v="127.0.6533.144", "Not)A;Brand";v="99.0.0.0", "Microsoft Edge Simulate";v="127.0.6533.144", "Lemur";v="127.0.6533.144"
accept-language: id-ID
upgrade-insecure-requests: 1
origin: https://onlyfaucet.com
content-type: application/x-www-form-urlencoded
user-agent: Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Mobile Safari/537.36
accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,;q=0.8,application/signed-exchange;v=b3;q=0.7
sec-fetch-site: same-origin
sec-fetch-mode: navigate
sec-fetch-dest: document
referer: https://onlyfaucet.com/faucet/currency/ltc
accept-encoding: gzip, deflate, br, zstd
cookie: _ga=GA1.1.1890233853.1777586807
cookie: bitmedia_fid=eyJmaWQiOiIxMzBlY2JiMDAxMGIwMGMwNzI4MDgxZWNkYjA5ZmMyZCIsImZpZG5vdWEiOiJkZTg5NDM4ODFmYWNlNTI3ZGQxMDVhYTI2YTYwZGEzOCJ9
cookie: csrf_cookie_name=6760d8f5807d8dd4bfd6867d4be2174a
cookie: ci_session=2no75vv8hfan5ufgdjf0v6co6g61a3v5
cookie: cf_clearance=gCYPAOvsnlAyG9Wn8wRWLIhdLYy1GDRkekzFELtXNRo-1779371114-1.2.1.1-KFPhxR71nW6C1FLzQo2.lodN1Dy38BMB_MUnwaRGH3WGS18Zas_k.8jhw_nBLl8izNrUuKh_BwLphBMGT7jsSn_717V3jW9Cms9s7g9lcX2ILlJf1ylEN.joQ9vEnkX7AEOV3MI2UnldrKcYcFK3YTSOHKkg_qwVkF4CL7UE6ha03khKv3UWLkEnEd13J9k6lOGvga_tF9rGlG8t7lXHPAmG9f4qBtUmR1F8Wn7wf.yYiDEbgT0jfcILjn3gIoiZYSPPVooAYP.fcKoFqk6o8FkR_OvAoP11d8UXtt0YPcoF4i2ecOZhs80.JKrPZCHGEGSRUvrPJ_Ei.iirqhbnMiMM3CLPIUphQdv9DPS3zdKpRLIPLhUXQo.nKgY9Iyq1RgpRtrfIFw31Et2Dfwu1FfZ9ieCBEkRa7wExTxouQGs
cookie: _ga_8MW4PHBZKX=GS2.1.s1779370213$o36$g1$t1779371345$j37$l0$h0
priority: u=0, i

csrf_token_name=6760d8f5807d8dd4bfd6867d4be2174a&claim_token=668bba9ca114f79b428d5eb9dad62c5c&captcha_answer=1&pow_nonce=27844&b_tick=0&human_stamp=0&bx_3c7ad1cef2b8=90&x_tz=SBBnRgBGDAwFARgbbAxWQVsHHBo6UkEWXgQDTkE8ARsRCA0FHUZpT1MTDgkfQ2oLAhMKAEkTZlAJEQlWT0E9EVQQAgIdRmlMUxMOAQdWSA%3D%bx_3c7ad1cef2b8


HTTP/2 200
date: Thu, 21 May 2026 13:48:52 GMT
content-type: image/webp
content-length: 6338
set-cookie: csrf_cookie_name=6760d8f5807d8dd4bfd6867d4be2174a; expires=Thu, 21-May-2026 15:48:52 GMT; Max-Age=7200; path=/; domain=.onlyfaucet.com; secure; HttpOnly
expires: Thu, 19 Nov 1981 08:52:00 GMT
pragma: no-cache
x-pow-salt: 32821d6871493a5ca108e194d33bccbb
x-pow-difficulty: 4
x-pow-bfield: bx_3c7ad1cef2b8
access-control-expose-headers: X-PoW-Salt, X-PoW-Difficulty, X-PoW-BField
cache-control: no-store, no-cache, must-revalidate, max-age=0
server: cloudflare
x-turbo-charged-by: LiteSpeed
cf-cache-status: DYNAMIC
nel: {"report_to":"cf-nel","success_fraction":0.0,"max_age":604800}
strict-transport-security: max-age=2592000
x-content-type-options: nosniff
speculation-rules: "/cdn-cgi/speculation"
report-to: {"group":"cf-nel","max_age":604800,"endpoints":[{"url":"https://a.nel.cloudflare.com/report/v4?s=%2B6n1OdN86MNujO%2BaBoJH0FF9eQD5PV1cPvZTdh%2FPha0fPvb90gkGj7m6Yj01ahnawFugZZN31LpmGbljr72g%2FSe2GLFMqgpVWancaWBYe5Up2gPMzGPd9m%2FyEI0btHN%2FKw%3D%3D"}]}
cf-ray: 9ff40a8c6854eb9e-SIN
alt-svc: h3=":443"; ma=86400

<binary body>

*/