<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'http://satoshifaucet.io';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=124158';
$ip = '173.249.41.150';

(function ($login, $ip) {
    Proxy::load();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
})($login, $ip);

$_0 = Net::C($host, 'GET', null, inf::$cookie, ['detail-hints:false'], '', inf::$uagent, ip: $ip);
if (empty($_0)) goto login;

$skipped = [];
$SLDONE = false;
$curr = '';
while (true) {
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', ['detail-hints:false'], '/auth/login');
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
        
        logx('err', "logging in", false); 
        _sle(3); _clr();
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
                    _sle(10);
                    continue;
                }
                $po = array_merge($pa, $cap, $cre);
            }
            
            if (!empty($po)) Net::X($host.'/auth/login', 'POST', $po, inf::$cookie, ['detail-hints:false'], $host.$r, inf::$uagent, ip: $ip, foll: false);
        }
        _sle(15);

    } while (empty($dash));

#_put("dash.html", $dash); die;
    
    $_fa = Scraper::_xP($dash, "//div[normalize-space()='Faucets']/ancestor::li//div[@class='sub-menu-two']/a/@href");
    foreach ($_fa as $fa) {
        $fa = str_replace('https://', 'http://', $fa);
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        while (true) {
            _sle(3);
            $fau = Net::C($fa, 'GET', null, inf::$cookie, [], '', inf::$uagent, ip: $ip);
            #_put('fau.html', $fau);
            if (empty($fau)) continue;

            if ($ban = isBan($fau)) {
                logx('err', " kena ban: " . $ban['ti']);
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue; 
            }

            $f = scraper::payload($fau)[0] ?? [];
            if (!empty($f)) {
                $pa = $f['payload'];
                
                $cha = $pa['captcha'];
                
                if (($cha === 'math_captcha') && isset($pa['math_answer'])) {
                    $img_u = Scraper::_xP($fau, "//div[@class='mc-img-wrap']/img/@src");
                    if (!empty($img_u) && isset($img_u[0])) {
                        $img = explode(',', $img_u[0])[1] ?? '';
                        $cap = stfM($api, $host, $img);
                    }
                } elseif (($cha === 'click_captcha') && isset($pa['cc_tok'])) {
                    $img_p = Scraper::_jP($fau, '/<div class="cc-prompt">(.*?)<\/div>/is')[1][0] ?? '';
                    
                    preg_match('/color:\s*(#[a-f0-9]{6})[^>]*>(.*?)<\/strong>/i', $img_p, $ins);
                    $hex = $ins[1] ?? '';
                    $tex = $ins[2] ?? '';
                    
                    $img_u = Scraper::_xP($fau, "//div[@class='cc-img-wrap']/img/@src");
                    if (isset($img_u[0]) && !empty($hex)) {
                        $img = explode(',', $img_u[0])[1] ?? '';
                        $cap = stfC($img, $hex);
                    }
                    
                } else {
                    $cap = Solve::exec($fau, 'https://'.$domain, $api, $pa);
                }
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
            
            #print_r($po); #die;
            _sle(2); 
            $ve = str_replace('https://', 'http://', $f['url']);
            Net::X($ve, 'POST', $po, inf::$cookie, [], $fa, inf::$uagent, ip: $ip, foll: false);
            
            $cla = Net::X($fa, 'GET', null, inf::$cookie, [], '', inf::$uagent, ip: $ip);
            
            if (stripos($cla, 'rate limited') !== false) goto login;
            
            if ($ban = isBan($cla)) {
                logx('err', " Kena ban: " . $ban['ti']);
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue; 
            }
            
            $_suc = scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
            
            if (!empty($_suc[1][0])) {
                $status = $_suc[1][0]; 
                print(FGd['CYN'].maskEmail($login).RSET." ");
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
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];

        do {
            $sho = Net::C($sl, 'GET', null, inf::$cookie, [], '', inf::$uagent, ip: $ip);
            if (empty($sho)) { _sle(5); continue; }
            
            if ($ban = isBan($sho)) {
                logx('err', " kena ban: " . $ban['ti']);
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
                $cleanCap = array_filter((array)$cap, fn($k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_KEY);
                $po = array_merge($pa, $cleanCap, $cre);
                
                $get = Net::X($ud, 'POST', $po, inf::$cookie, [], $sl, inf::$uagent, ip: $ip, foll: false);
                
                preg_match('/location\.href\s*=\s*["\']([^"\']+)["\']/', $get, $match);
                $loc = $match[1] ?? '';
                
                if (!$loc) {
                    $skipped[$idd] = true;
                    break;
                }
                
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

                logx('info', "Bypassing SL: {$loc}", true, true);
                
                $start = microtime(true);
                $bak = links($api, $loc);
                if (!$bak) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                $wait = 100 - (int)(microtime(true) - $start);
                if ($wait > 0) styler("waiting for SL", fn() => _sle((int)ceil($wait)));
                
                $b1 = preg_replace('/^https:/i', 'http:', $bak);
                #logx('', $b1, true, true);
                Net::C($b1, 'GET', null, inf::$cookie, [], '', inf::$uagent, ip: $ip, foll: false);
                $b2 = str_replace('/back/', '/verify/', $b1);
                #logx('', $b2, true, true);
                Net::C($b2, 'GET', null, inf::$cookie, [], $b1, inf::$uagent, ip: $ip, foll: false);
                
                $ver = Net::C($sl, 'GET', null, inf::$cookie, [], '', inf::$uagent, ip: $ip);

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
















function stfM($api, $host, $img) {
    #_put('img.png', $img); _rl('lanjut: ');
    $ans = Solve::img($api, $host, 'math', $img);
    
    if (isset($ans['trouble'])) return $ans;
    
    if ($ans) {
        #var_dump($ans);
        $ans = strtolower($ans);
        $clean = str_replace(['=', '?', ' '], '', $ans);
        
        $n = Scraper::_jP($ans, '/\d+/')[0] ?? [];
        
        $_final_ans = null;

        if (count($n) >= 2) {
            $q1 = (int)$n[0];
            $q2 = (int)$n[1];
            $op = null;

            if (strpos($ans, '+') !== false) $op = '+';
            elseif (strpos($ans, '-') !== false) $op = '-';
            elseif (strpos($ans, '*') !== false || strpos($ans, 'x') !== false) $op = '*';

            if ($op) {
                $_final_ans = SolveUtils::math($q1, $q2, $op);
            }
        }

        if ($_final_ans === null) {
            $maa = filter_var($clean, FILTER_SANITIZE_NUMBER_INT);
            if ($maa !== '') {
                $_final_ans = $maa;
            }
        }

        if ($_final_ans !== null) {
            return [
                'captcha' => 'math_captcha',
                'math_answer' => (string)$_final_ans,
                'math_mouse'  => '1'
            ];
        }
        
        return ['trouble' => 'reload'];
    }
    
}

function stfC($img, $hex) {
    if (!getDeps('gd@php')) {
        logx('err', "gd@php is missing");
        exit(9);
    }
    
    if (!$_bin = base64_decode($img)) return ['trouble' => 'reload'];
    #_put('img.png', $_bin);

    $img = imagecreatefromstring($_bin);
    if (!$img) return ['trouble' => 'reload'];

    $hex = ltrim($hex, '#');
    $_tR = hexdec(substr($hex, 0, 2));
    $_tG = hexdec(substr($hex, 2, 2));
    $_tB = hexdec(substr($hex, 4, 2));

    $_w = imagesx($img);
    $_h = imagesy($img);

    $tolerance = 30; 

    for ($y = 0; $y < $_h; $y++) {
        for ($x = 0; $x < $_w; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $col = imagecolorsforindex($img, $rgb);

            $diffR = abs($col['red'] - $_tR);
            $diffG = abs($col['green'] - $_tG);
            $diffB = abs($col['blue'] - $_tB);
/*
            var_dump($diffR);
            var_dump($diffG);
            var_dump($diffB);
*/
            if ($diffR < $tolerance && $diffG < $tolerance && $diffB < $tolerance) {
                @imagedestroy($img);
                return [
                    'click_done' => '1',
                    'click_x' => (string)$x, 
                    'click_y' => (string)$y
                ];
            }
        }
    }

    @imagedestroy($img);
    return ['trouble' => 'reload'];
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
