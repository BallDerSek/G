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
while (true) { @unlink(inf::$cookie);
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
            _put('ve.html', $ve);

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
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while (true) {
            $fau = null;
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent);
            
            #_put('fau.html', $fau); die;
            
            if ($fau === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            if (stripos($fau, 'Just a moment') !== false || stripos($fau, 'Attention Required!') !== false) {
                if ($cf = execCF($api, $fa, inf::$cookie, inf::$uagent)) {
                    [$headersCF, $ua] = $cf;
                    inf::setup($ua, inf::$cookie);
                    _sle(3);
                    continue;
                }
            }
            if ($ban = isBan($fau)) {
                logx('err', " kena ban: " . $ban['ti']);
                if (!$SLDONE) {
                    $curr = $_c; 
                    break 2;
                }
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue;
            }

            $po = null;
            $f = scraper::payload($fau)[0] ?? null;
            if ($f) {
                $cap = [];
                $pa = $f['payload'];
                $cap = onfCap($fau, $host, $api, $pa, $headersCF);

                if (empty($cap)) {
                    $cap = Solve::exec($fau, $host, $api, $pa);
                }
                
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    ($tro === 'proxy') ? _sle(30) : _sle(10);
                    continue;
                }
                $po = array_merge($pa, $cap);
            }
            #_put('fau.html', $fau); die;
            
            if (!empty($po)) {
                #print_r($po); #die;
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
                        /*
                        if ($successCount >= 100) {
                            $successCount = 0; 
                            $curr = $_c; 
                            break 2; 
                        }
                        */
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
    
                styler("waiting for next claim", fn() => _sle(10));
            }
        }
    }
sl:
    $valid = [];
    $success_in_page = false;
    $_sl = Scraper::_xP($dash, "//ul[@id='links']//a/@href");
    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) continue;
        
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
                    
                    $get = Net::X($bakk, 'GET', null, inf::$cookie, $headersCF, $loc['url'], inf::$uagent);
                    
                    if ($get === 99) {
                        $retGet++;
                        logx('warn', 'Proxy issue, wait 30s');
                        if ($retGet >= 7) goto login;
                        _sle(10);
                        continue;
                    }
                    
                    if (!empty($get)) {
                        #_put('get.html', $get); 
                        
                        if (stripos($get, 'Just a moment') !== false || stripos($get, 'Attention Required!') !== false) {
                            if ($cf = execCF($api, $bakk, inf::$cookie, inf::$uagent)) {
                                [$he, $ua] = $cf;
                                inf::setup($ua, inf::$cookie);
                                $headersCF = $he;
                                _sle(3);
                                continue;
                            }
                        }
                        
                        $_s = scraper::_jP($get, "/var\s+HAS_PENDING\s*=\s*(true|false);/i");
                        $_p = filter_var($_s[1][0] ?? false, FILTER_VALIDATE_BOOLEAN);

                        if (stripos($get, 'captchaModal') && $_p) {
                            $json = Scraper::_jP($get, '/var CFG\s*=\s*(\{.*?\});/s')[1][0] ?? null;
                            
                            if ($json) {
                                $cfg = json_decode($json, true);
                                $cfg['csrfHash'] = $loc['csrf'];
                                $retCap = 0;
                                while (true) {
                                    $cap = onlyFans(json_encode($cfg), $sl);
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

function onlyFans($json_cfg, $reff) {
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

function onfCap($fau, $host, $api, $payload, $he) {
    
    $need_c = Scraper::_jP($fau, '/(?:captchaNeeded|captchaRequired)\s*=\s*(true|false)/')[1][0] ?? '';
    #var_dump($need_c); die;
    if ($need_c === 'true') {
        if (stripos($fau, 'rag the piece to the slot')) {
            $cfg_c = Scraper::_jP($fau, '/var CFG\s*=\s*(\{.*?\});/s')[1][0] ?? null;
            if ($cfg_c) return onlyfans($cfg_c, $fa);
        }
        
        if (stripos($fau, 'lect the correct answer')) {
            $ans = '';
            $img_u = Scraper::_xP($fau, "//img[@id='captcha-img']/@src")[0] ?? '';
            #var_dump($img_u);
            $img_o = Scraper::_xP($fau, "//button[contains(@class, 'captcha-opt-btn')]/@data-value");
            #var_dump($img_o);
            if ($img_o && $img_u) {
                $img = Net::X($img_u, 'GET', [], inf::$cookie, $he, $host, inf::$uagent);
                if (empty($img) || $img === 99) return ['trouble' => 'proxy'];
                $ans = Solve::img($api, $host, 'math', $img);
                #var_dump($ans); #die;
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
                        $res = SolveUtils::math($q1, $q2, $op);
                        if (in_array((string)$res, $img_o)) $_final_ans = $res;
                    }
                    
                    if ($_final_ans === null) {
                        foreach (['+', '-', '*'] as $type) {
                            $res = SolveUtils::math($q1, $q2, $type);
                            if (in_array((string)$res, $img_o)) {
                                $_final_ans = $res;
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
    } elseif (stripos($fau, 'mcaptcha')) {
        $ins = Scraper::_xP($fau, "//div[@id='mcaptcha-challenge-box']//p[@class='text-muted small mb-0']")[0] ?? [];
        #logx('info', "$ins", true, true);
        $s = 5;
        if (preg_match('/(\w+)\s+shapes\s+belong/i', $ins, $_s)) {
            $map = ['two'=>3, 'three'=>4, 'four'=>5, 'five'=>6, 'six'=>7];
            $s = $map[strtolower($_s[1])] ?? 5;
        }
        
        $img_u = "https://onlyfaucet.com/faucet/captcha_image?_t=".round(microtime(true) * 1000);
        $img = Net::X($img_u, 'GET', [], inf::$cookie, $he, $host, inf::$uagent);
        
        if (!empty($img) && $img !== 99) {
            #return ['captcha_answer' => "5"];
            #_put('img.png', $img);
            $ans = SolveUtils::oddCaptcha(base64_encode($img), 'color', $s);
            if ($ans) return ['captcha_answer' => $ans];
        }

        return ['trouble' => 'reload'];



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

function onfOdd($img) {
    $image = imagecreatefromstring($img);
    if (!$image) {
        logx('err', "Gagal load gambar captcha", true);
        return ['trouble' => 'reload'];
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    $segW = intdiv($width, 5); 
    $midY = intdiv($height, 2); 
    $diffData = [];
    for ($i = 0; $i < 5; $i++) {
        $targetX = ($i * $segW) + intdiv($segW, 2);
        $r = $g = $b = 0;
        $foundColor = false;
        
        for ($offset = -5; $offset <= 5; $offset++) {
            $rgb = imagecolorat($image, $targetX, $midY + $offset);
            $tR = ($rgb >> 16) & 0xFF;
            $tG = ($rgb >> 8) & 0xFF;
            $tB = $rgb & 0xFF;
            if ($tR > 50 || $tG > 50 || $tB > 50) {
                $r = $tR;
                $g = $tG; 
                $b = $tB;
                $foundColor = true;
                break;
            }
        }
        $diffData[$i] = ['r' => $r, 'g' => $g, 'b' => $b];
    }
    $avgR = array_sum(array_column($diffData, 'r')) / 5;
    $avgG = array_sum(array_column($diffData, 'g')) / 5;
    $avgB = array_sum(array_column($diffData, 'b')) / 5;
    $maxDist = -1;
    $ans = 0;
    foreach ($diffData as $idx => $val) {
        $dist = sqrt(pow($val['r'] - $avgR, 2) + pow($val['g'] - $avgG, 2) + pow($val['b'] - $avgB, 2));
        if ($dist > $maxDist) {
            $maxDist = $dist;
            $ans = $idx;
        }
    }
    return $ans;

}