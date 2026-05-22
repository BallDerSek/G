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
$claim = true;
$curr = '';
$habis = [];
$dash = null;
$needSL = false;
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
            $_sucT = scraper::_jP($ve, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)/s") ?? [];
            $msg = $_sucS[3][0] ?? $_sucT[2][0] ?? '';
            
            if (!empty($msg)) {
                print(FGd['CYN'] . maskEmail($login) . RSET . " ");
                logx('info', $msg);
                if (stripos($msg, 'denied')) die;
                if (stripos($msg, 'emporarily blocked')) {
                    _sle(100);
                    goto login;
                }
                
            }
            
        }
        _rl('lanjut: ');
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
            
            if (stripos($fau, 'After every') && stripos($fau, '1 Shortlink') && stripos($fau, 'must be completed')) $needSL = true;
            
            if (!empty($po)) {
                #print_r($po); #die;
                _sle(1);
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
                        
                        if ($successCount >= 100 && !$needSL) {
                            $successCount = 0; 
                            $curr = $_c; 
                            break 2; 
                        }
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) {
                            $habis[] = $fa;
                            break;
                        }
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
    
    if (count($habis) === count($_fa)) {
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
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
                
                $wait = 60 - (int)(microtime(true) - $start);
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
                            #var_dump($ver);
                            
                            if (!empty($ver) && ($ver !== 99)) {
                                $cla = json_decode($ver, true);
                                $sucJ = filter_var($cla['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
                                
                                $_sucH = scraper::_jP($ver, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s") ?? [];
                                
                                // DI SINI PERBAIKANNYA (Hanya cek array, sisa logika ke bawah tetap 100% kode asli Anda)
                                $suc = (!empty($_sucH[1]) && isset($_sucH[1][0])) ? $_sucH[1][0] : $sucJ;
                                
                                logx($suc ? 'ok' : 'err', $suc ? "Success " : "error ", false);
                                
                                $err_msg = (!empty($_sucH[2]) && isset($_sucH[2][0])) ? $_sucH[2][0] : 'no message';
                                logg(false, $cla['message'] ?? $err_msg);
                                
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

function onfOdd($img) {
    
    if (!getDeps('gd@php')) {
        logx('err', "gd@php is missing");
        exit(9);
    }
    
    $image = imagecreatefromstring($img);
    if (!$image) return false;
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    $binary = [];
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            if ($r > 225 && $g > 225 && $b > 225) {
                $binary[$y][$x] = 0;
            } else {
                $binary[$y][$x] = 1;
            }
        }
    }

    $visited = array_fill(0, $height, array_fill(0, $width, false));
    $blobs = [];

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ($binary[$y][$x] == 1 && !$visited[$y][$x]) {
                $queue = [[$x, $y]];
                $visited[$y][$x] = true;
                $blob_pixels = [];
                
                $head = 0;
                while ($head < count($queue)) {
                    $curr = $queue[$head++];
                    $cx = $curr[0];
                    $cy = $curr[1];
                    $blob_pixels[] = [$cx, $cy];
                    
                    $neighbors = [[$cx+1, $cy], [$cx-1, $cy], [$cx, $cy+1], [$cx, $cy-1]];
                    foreach ($neighbors as $n) {
                        $nx = $n[0]; $ny = $n[1];
                        if ($nx >= 0 && $nx < $width && $ny >= 0 && $ny < $height) {
                            if ($binary[$ny][$nx] == 1 && !$visited[$ny][$nx]) {
                                $visited[$ny][$nx] = true;
                                $queue[] = [$nx, $ny];
                            }
                        }
                    }
                }
                
                $area = count($blob_pixels);
                if ($area > 150) {
                    $blobs[] = $blob_pixels;
                }
            }
        }
    }

    if (count($blobs) < 2) {
        @imagedestroy($image);
        return false;
    }

    $shapes_data = [];
    foreach ($blobs as $blob) {
        $totalX = 0; $totalY = 0;
        $totalR = 0; $totalG = 0; $totalB = 0;
        $count = count($blob);
        
        foreach ($blob as $pixel) {
            $px = $pixel[0]; $py = $pixel[1];
            $totalX += $px;  $totalY += $py;
            
            $rgb = imagecolorat($image, $px, $py);
            $totalR += ($rgb >> 16) & 0xFF;
            $totalG += ($rgb >> 8) & 0xFF;
            $totalB += $rgb & 0xFF;
        }
        
        $shapes_data[] = [
            'cx' => $totalX / $count,
            'cy' => $totalY / $count,
            'r'  => $totalR / $count,
            'g'  => $totalG / $count,
            'b'  => $totalB / $count
        ];
    }

    $avg_global_r = array_sum(array_column($shapes_data, 'r')) / count($shapes_data);
    $avg_global_g = array_sum(array_column($shapes_data, 'g')) / count($shapes_data);
    $avg_global_b = array_sum(array_column($shapes_data, 'b')) / count($shapes_data);

    $odd_shape = $shapes_data[0];
    $max_distance = -1;

    foreach ($shapes_data as $shape) {
        $diffR = $shape['r'] - $avg_global_r;
        $diffG = $shape['g'] - $avg_global_g;
        $diffB = $shape['b'] - $avg_global_b;
        
        $distance = sqrt(($diffR * $diffR) + ($diffG * $diffG) + ($diffB * $diffB));
        
        if ($distance > $max_distance) {
            $max_distance = $distance;
            $odd_shape = $shape;
        }
    }

    if ($max_distance < 15) {
        @imagedestroy($image);
        return false;
    }
/*
    try {
        if (!is_dir('.pre')) {
            @mkdir('.pre', 0777, true);
        }

        $debug_img = imagecreatetruecolor(250, 250);
        
        imagecopyresampled($debug_img, $image, 0, 0, 0, 0, 250, 250, $width, $height);
        
        $red_color = imagecolorallocate($debug_img, 255, 0, 0);
        
        $tar_X = (int)round(($odd_shape['cx'] / $width) * 250);
        $tar_Y = (int)round(($odd_shape['cy'] / $height) * 250);

        imagefilledellipse($debug_img, $tar_X, $tar_Y, 10, 10, $red_color);
        
        imagepng($debug_img, '.pre/pre.png');
        
        @imagedestroy($debug_img);
    } catch (\Exception $e) {
    }
*/
    @imagedestroy($image);

    return [
        'cx' => (int)round(($odd_shape['cx'] / $width) * 250),
        'cy' => (int)round(($odd_shape['cy'] / $height) * 250)
    ];
}

function onfCap($fau, $host, $api, $payload, $he) {
    
    if (stripos($fau, 'mcaptcha') !== false) {
        $img_u = "https://onlyfaucet.com/faucet/captcha_image?_t=".round(microtime(true) * 1000);
        
        $res = Net::X($img_u, 'GET', [], inf::$cookie, $he, $host, inf::$uagent, d: true);
        $r_hee = $res['headers'] ?? [];
        $r_pSa = $r_hee['x-pow-salt'][0] ?? $r_hee['X-PoW-Salt'][0] ?? null;
        $r_pDi = (int)($r_hee['x-pow-difficulty'][0] ?? $r_hee['X-PoW-Difficulty'][0] ?? 4);
        $r_bFi = $r_hee['x-pow-bfield'][0] ?? $r_hee['X-PoW-BField'][0] ?? null;
        
        $img = $res['body'] ?? '';
        if (empty($img) || $img === 99 || strpos($img, '<!DOCTYPE') !== false || strpos($img, '<html') !== false) {
            return ['trouble' => 'reload'];
        }
        
        $coords = onfOdd($img); 
        if ($coords === false) return ['trouble' => 'reload'];
        
        // KEMBALI KE INT (Bulat Murni): Menghindari bug koma/titik lokal regional PHP
        $cx = (int)round($coords['cx'] + rand(-2, 2));
        $cy = (int)round(($coords['cy'] + rand(-2, 2)));
        
        $cx = max(5, min(245, $cx));
        $cy = max(5, min(245, $cy));

        $m_dta = ['captcha_answer' => '1']; 
        
        // Ambil range tengah yang stabil berdasarkan log sukses lu sebelumnya
        $dx = rand(190, 290);     
        $t1 = rand(800, 1300);   
        $mc = rand(12, 22);      
        $td = rand(450, 850);    
        
        if ($r_bFi) $m_dta[$r_bFi] = (string)$dx;
        
        if ($r_pSa) {
            $m_slt = $r_pSa . $dx; 
            $m_nnc = SolveUtils::Pow($m_slt, $r_pDi);
            $m_dta['pow_nonce'] = (string)$m_nnc;
        }
        
        $cur_ua = inf::$uagent;
        $hc_val = 8; $dm_val = 8;
        if (stripos($cur_ua, 'Android') !== false) {
            $hc_val = 4; $dm_val = 4;
        }
        
        $telemetry = [
            '_t1' => $t1,
            '_mc' => $mc,
            '_cx' => $cx, // Integer murni
            '_cy' => $cy, // Integer murni
            '_wd' => 0,
            '_hc' => $hc_val,
            '_dm' => $dm_val, 
            '_sg' => 1,
            '_td' => $td
        ];
        
        $json_str = json_encode($telemetry);
        $key = !empty($r_pSa) ? $r_pSa : "safekey_mcaptcha"; 
        
        $encrypted_str = '';
        $key_len = strlen($key);
        $str_len = strlen($json_str);
        
        for ($k = 0; $k < $str_len; $k++) {
            $encrypted_str .= chr(ord($json_str[$k]) ^ ord($key[$k % $key_len]));
        }
        
        $m_dta['x_tz'] = base64_encode($encrypted_str);
        
        // Tahan waktu tunggu wajar seolah proses hitung asli
        usleep(rand(1200000, 2200000)); 
        
        return $m_dta; 
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


