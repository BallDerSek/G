<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent('mobile');

$host = 'http://satoshifaucet.io';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=124158';
$ip = '173.249.41.150';

inf::setup($userAgent, $cookieFile, $ip);

banner();
login:

$_0 = Net::C($host, 'GET', null, inf::$cookie, ['detail-hints:false'], '', inf::$uagent, ip: $ip);
if (empty($_0)) goto login;

$skipped = [];
$SLDONE = false;
$curr = '';
while (true) {
    $max = 7;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', ['detail-hints:false'], '/auth/login');
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break;
        }
        
        if ($ret >= $max) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            exit; 
        }
        
        @unlink(inf::$cookie);
        taskPrintCenter('logging in', 'err');
        $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, ['detail-hints:false'], '', inf::$uagent, ip: $ip);
        
        if (!empty($_0)) {
            $po = null;
            $cap = null;
            $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $login];
            $f = scraper::payload($_0)[0] ?? [];
            if (!empty($f)) {
                $pa = $f['payload'];
                $cap = Solve::exec($_0, 'https://'.$domain, $api, $pa);
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    if ($tro === 'reload') {
                        _sle(10); 
                        continue; 
                    }
                    if ($tro === 'proxy') {
                        _sle(1);
                        continue; 
                    }
                }
                $po = array_merge($pa, $cap, $cre);
            }
            
            if (!empty($po)) Net::C($host.'/auth/login', 'POST', $po, inf::$cookie, ['detail-hints:false'], $host.$r, inf::$uagent, ip: $ip, foll: false);
        }
        _sle(15);

    } while (empty($dash));

#_put("dash.html", $dash); die;
    
    $_fa = Scraper::_xP($dash, "//div[normalize-space()='Faucets']/ancestor::li//div[@class='sub-menu-two']/a/@href");
    
    foreach ($_fa as $fa) {
        $fa = str_replace('https://', 'http://', $fa);
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".UNDR.$login.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        while (true) {
            _sle(3);
            $fau = Net::C($fa, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);
            if (empty($fau)) continue;

            if ($ban = isBan($fau)) {
                logx('err', " kena ban: " . $ban['ti'], false);
                _sle($ban['sleep']);
                continue; 
            }

            $f = scraper::payload($fau)[0] ?? [];
            if (!empty($f)) {
                $pa = $f['payload'];
                
                $cap = Solve::exec($fau, 'https://'.$domain, $api, $pa);
                
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    ($tro === 'proxy') ? _sle(30) : _sle(10);
                    continue; 
                }

                $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()];
                
                $cleanCap = array_filter((array)$cap, fn($k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_KEY);
                $po = array_merge($pa, $cleanCap, $cre);
            } else {
                if (stripos($fau, 'claim limit') !== false) continue 2;
                styler("waiting for CLAIM", fn() => _sle(10));
                continue;
            }

            _sle(2); 
            $ve = str_replace('https://', 'http://', $f['url']);
            Net::X($ve, 'POST', $po, $cookieFile, [], $fa, $userAgent, ip: $ip, foll: false);
            
            $cla = Net::X($fa, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);
            
            if (stripos($cla, 'rate limited') !== false) goto login;
            
            $_suc = scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
            
            if (!empty($_suc[1][0])) {
                $status = $_suc[1][0]; 
                logx($status === 'success' ? 'ok' : 'err', "{$_suc[2][0]} ", false);
                logg(false, "{$_suc[3][0]}");
                
                if (stripos($_suc[3][0], 'sufficient') !== false) break;
                
                if (stripos($_suc[3][0], 'Shortlink') !== false) {
                    if ($SLDONE) {
                        logx('err', 'Gada jatah SL lagi');
                        die;
                    }
                    $curr = $_c; 
                    break 2;
                }
            }
            
            styler("waiting for next claim", fn() => _sle(15));
        }
    }
    
    $_sl = Scraper::_xP($dash, "//div[normalize-space()='Shortlinks']/parent::div/following-sibling::div[@class='sub-menu-two']/a/@href");

    $valid = [];
    $success_in_page = false;
    foreach ($_sl as $sl) {
        $sl = str_replace('https://', 'http://', $sl);
        $_c = basename($sl);
        
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) continue;
        $up = ['earnow','shortano', 'shortino', 'fc-lc'];

        do {
            logx("info", 'start sl ');
            $sho = Net::C($sl, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);
            if (empty($sho)) { _sle(5); continue; }
            
            if ($ban = isBan($sho)) {
                logx('err', " kena ban: " . $ban['ti'], false);
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue;
            }

            $f = scraper::payload($sho)[0] ?? [];
            if (empty($f)) { _sle(3); continue; }
            
            $pa = $f['payload'];
            $short = sScraper::extract($sho);
            $success_in_page = false; 
            $found_one = false;
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt)) continue;
                if (isset($skipped[$idd])) continue; 
                
                $found_one = true;
                $valid[$links] = [$idd, $lmt];
                
                $go = str_replace("/currency/$_c", "", $sl);
                $ud = $go."/go/{$idd}/".strtoupper($_c);
                
                $cap = Solve::exec($sho, 'https://'.$domain, $api, $pa);
                
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    if ($tro === 'proxy') { _sle(30); continue 2; } 
                    if ($tro === 'reload') { _sle(10); break; }
                }

                $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()];
                // Bersihkan nocaptcha
                $cleanCap = array_filter((array)$cap, fn($k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_KEY);
                $po = array_merge($pa, $cleanCap, $cre);
                
                $get = Net::X($ud, 'POST', $po, $cookieFile, [], $sl, $userAgent, ip: $ip, foll: false);
                
                preg_match('/location\.href\s*=\s*["\']([^"\']+)["\']/', $get, $match);
                $loc = $match[1] ?? '';
                
                if (!$loc) {
                    $skipped[$idd] = true;
                    break;
                }
                
                logx('info', "Redirect: ".parse_url($loc)['host'], true, true);

                $loc_u = parse_url($loc)['host'];
                $is_bl = false;
                foreach ($up as $blacklisted) {
                    if (str_contains($loc_u, $blacklisted)) {
                        logx('warn', "Domain $blacklisted Skipping..");
                        $skipped[$idd] = true;
                        $is_bl = true;
                        break; 
                    }
                }
                if ($is_bl) { _sle(2); break; }
                
                $bak = links($api, $loc);
                if (!$bak) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                styler("waiting for SL", fn() => _sle(5));
                
                $b1 = preg_replace('/^https:/i', 'http:', $bak);
                #logx('', $b1, true, true);
                Net::C($b1, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip, foll: false);
                $b2 = str_replace('/back/', '/verify/', $b1);
                #logx('', $b2, true, true);
                Net::C($b2, 'GET', null, $cookieFile, [], $b1, $userAgent, ip: $ip, foll: false);
                
                $ver = Net::C($sl, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);

                if (!empty($ver)) {
                    $_suc = scraper::_jP($ver, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                    if (!empty($_suc[1][0])) {
                        logx('err', "{$_suc[2][0]} ", false, true);
                        logg(false, "{$_suc[3][0]}");

                        if ($_suc[1][0] === 'success') {
                            $success_in_page = true;
                            break 2; 
                        }
                    }
                } else {
                    $skipped[$idd] = true;
                }
                break; 
            }

            if (!$found_one) {
                logx('err', 'Semua SL habis');
                $SLDONE = true;
                break; 
            }

        } while (!$success_in_page);
        
        if ($success_in_page) break;
    }
    
    unset($sho, $ver, $fau, $cla); 

}







tes:
















function solveRotate($html) {
    if (!getDeps('gd@php')) {
        logx('err', "gd@php is missing");
        exit(9);
    }
    
    $_targetText = Scraper::_xP($html, "//div[@id='rc-title']//strong");
    $targetStr = isset($_targetText[0]) ? strtoupper($_targetText[0]) : 'UP';
    
    $targetDegrees = 270; 
    if (strpos($targetStr, 'DOWN') !== false)  $targetDegrees = 90;
    if (strpos($targetStr, 'RIGHT') !== false) $targetDegrees = 0;
    if (strpos($targetStr, 'LEFT') !== false)  $targetDegrees = 180;

    $_b = Scraper::find($html, 'rc-img', 'img', 'src', 'id')[0];
    $b64 = substr($_b, strrpos($_b, ',') + 1);

    $_img = base64_decode($b64);
    if (!$_img) return ['rot_captcha_val' => 0];
    
    $img = imagecreatefromstring($_img);
    $W = imagesx($img);
    $H = imagesy($img);
    $_slc = max(5, (int)round(max($W, $H) * 0.08));

    $_brdr = [];
    for ($x = 0; $x < $W; $x++) {
        $_brdr[] = imagecolorat($img, $x, 0);
        $_brdr[] = imagecolorat($img, $x, $H - 1);
    }
    for ($y = 1; $y < $H - 1; $y++) {
        $_brdr[] = imagecolorat($img, 0, $y);
        $_brdr[] = imagecolorat($img, $W - 1, $y);
    }
    $counts = array_count_values($_brdr);
    arsort($counts);
    $bgRaw = key($counts);
    $bgR = ($bgRaw >> 16) & 0xFF;
    $bgG = ($bgRaw >> 8)  & 0xFF;
    $bgB = $bgRaw & 0xFF;


    $_bnr = [];
    for ($y = 0; $y < $H; $y++) {
        for ($x = 0; $x < $W; $x++) {
            $c = imagecolorat($img, $x, $y);
            $r = ($c >> 16) & 0xFF;
            $g = ($c >> 8)  & 0xFF;
            $b =  $c        & 0xFF;
            $_dst = sqrt(($r-$bgR)**2 + ($g-$bgG)**2 + ($b-$bgB)**2);
            $_bnr[$y][$x] = ($_dst > 50) ? 1 : 0;
        }
    }

    $_vst = [];
    $_bst = [];
    for ($sy = 0; $sy < $H; $sy++) {
        for ($sx = 0; $sx < $W; $sx++) {
            if (!($_bnr[$sy][$sx] ?? 0) || ($_vst[$sy][$sx] ?? false)) continue;
            $p_a = [];
            $q_a = [[$sx, $sy]];
            $_vst[$sy][$sx] = true;
            while (!empty($q_a)) {
                [$cx2, $cy2] = array_pop($q_a);
                $p_a[] = [$cx2, $cy2];
                foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dx2,$dy2]) {
                    $nx2 = $cx2 + $dx2; $ny2 = $cy2 + $dy2;
                    if ($nx2 < 0 || $nx2 >= $W || $ny2 < 0 || $ny2 >= $H) continue;
                    if (!($_bnr[$ny2][$nx2] ?? 0) || ($_vst[$ny2][$nx2] ?? false)) continue;
                    $_vst[$ny2][$nx2] = true;
                    $q_a[] = [$nx2, $ny2];
                }
            }
            if (count($p_a) > count($_bst)) $_bst = $p_a;
        }
    }

    $n = count($_bst);
    if ($n < 10) return ['rot_captcha_val' => 0];

    $sumX = $sumY = 0.0;
    foreach ($_bst as [$px, $py]) { $sumX += $px; $sumY += $py; }
    $cxC = $sumX / $n; $cyC = $sumY / $n;

    $mu20 = $mu02 = $mu11 = 0.0;
    foreach ($_bst as [$px, $py]) {
        $dx2 = $px - $cxC; $dy2 = $py - $cyC;
        $mu20 += $dx2 * $dx2; $mu02 += $dy2 * $dy2; $mu11 += $dx2 * $dy2;
    }
    $mu20 /= $n; $mu02 /= $n; $mu11 /= $n;

    $angle = 0.5 * atan2(2 * $mu11, $mu20 - $mu02);
    $cosA  = cos($angle); $sinA  = sin($angle);

    $t_V = [];
    foreach ($_bst as [$px, $py]) {
        $t_V[] = ($px - $cxC) * $cosA + ($py - $cyC) * $sinA;
    }
    $tMin = min($t_V); $tMax = max($t_V);

    $avgDev = function($t_C) use ($_bst, $cxC, $cyC, $cosA, $sinA, $t_V, $_slc) {
        $sum = 0.0; $cnt = 0;
        foreach ($_bst as $i => [$px, $py]) {
            if (abs($t_V[$i] - $t_C) <= $_slc) {
                $sum += abs(-($px - $cxC) * $sinA + ($py - $cyC) * $cosA);
                $cnt++;
            }
        }
        return $cnt > 0 ? $sum / $cnt : INF;
    };

    $_minn = $avgDev($tMin);
    $_maxx = $avgDev($tMax);
    
    $v_A = ($_minn < $_maxx) ? 'min' : 'max';

    $cntPos = 0; $cntNeg = 0;
    foreach ($t_V as $t) { if ($t >= 0) $cntPos++; else $cntNeg++; }
    
    $v_B = ($cntNeg < $cntPos) ? 'min' : 'max';

    $he_ = $v_A; 

    $he_T = ($he_ === 'min') ? $tMin : $tMax;
    $te_T = ($he_ === 'min') ? $tMax : $tMin;

    $vecDx = ($he_T - $te_T) * $cosA;
    $vecDy = ($he_T - $te_T) * $sinA;

    $arr_D = fmod(rad2deg(atan2($vecDy, $vecDx)) + 360, 360);
    
    $rot_V = (int) round(fmod($targetDegrees - $arr_D + 360, 360));

    #imagedestroy($img);
    return ['rot_captcha_val' => $rot_V];
}


function solveShield($fau) {
    $json = json_decode(Scraper::_jP($fau, '/var D=({.*?});/')[1][0] ?? '', true);
    
    if (!$json) return "";

    $grid = $json['grid'];
    #print_r($grid);
    $instruction = strtolower($json['instruction']);
    #logx('ok', " [ $instruction ]", true, true);

    $ans = [];

    if (str_contains($instruction, "belong") || str_contains($instruction, "different")) {
        $shapeCounts = array_count_values(array_column($grid, 'shape'));
        $colorCounts = array_count_values(array_column($grid, 'color'));

        foreach ($grid as $index => $item) {
            if ($shapeCounts[$item['shape']] === 1 || $colorCounts[$item['color']] === 1) {
                $ans[] = $index;
                break;
            }
        }
    } else {
        preg_match('/<b>(.*?)<\/b>/', $instruction, $match);
        $target = $match[1] ?? '';

        $colorMap = [
            'blue' => ['#3b82f6', '#2563eb', '#60a5fa', '#1d4ed8'],
            'red' => ['#ef4444', '#dc2626', '#f87171', '#b91c1c'],
            'green' => ['#22c55e', '#16a34a', '#4ade80', '#15803d'],
            'yellow' => ['#eab308', '#facc15', '#ca8a04'],
            'orange' => ['#f97316', '#ea580c', '#fb923c', '#f59e0b'],
            'pink' => ['#ec4899', '#db2777', '#f472b6'],
            'purple' => ['#a855f7', '#9333ea', '#c084fc'],
            'cyan' => ['#06b6d4', '#0891b2', '#22d3ee'],
            'gray' => ['#64748b', '#475569', '#94a3b8'],
            'indigo' => ['#6366f1', '#4f46e5', '#818cf8'],
            'sky' => ['#0ea5e9', '#0284c7', '#38bdf8']
        ];

        $shapes = ['circle', 'square', 'triangle', 'diamond', 'star', 'hexagon'];

        foreach ($grid as $index => $item) {
            $itemColor = strtolower($item['color']);
            $itemShape = strtolower($item['shape']);

            if (isset($colorMap[$target])) {
                if (in_array($itemColor, $colorMap[$target])) {
                    $ans[] = $index;
                }
            } 
            elseif (in_array($target, $shapes)) {
                if ($itemShape === $target) {
                    $ans[] = $index;
                }
            }
        }
    }

    sort($ans);
    
    return [
        'shield_answer' => implode(',', $ans)
    ];
}

function isBan($html) {
    if (stripos($html, 'account has been banned')) {
        logx('err', 'yahhh ke ban');
        exit;
    }
    
    if (!stripos($html, 'Temporary Ban') && !stripos($html, 'temporarily locked')) {
        return false;
    }
    
    $m = (int)(Scraper::_xP($html, "//*[@id='minute']")[0] ?? 0);
    $s = (int)(Scraper::_xP($html, "//*[@id='second']")[0] ?? 0);
    
    $r = Scraper::_jP($html, '/Reason:\s*([^<]+)/')[1][0] ?? 'Unknown';

    return [
        'ti'    => trim($r),
        'tmr'   => "$m:$s",
        'sleep' => ($m * 60) + $s + 5
    ];
}
