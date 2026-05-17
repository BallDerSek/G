<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['mail', 'pass', 'PROXY']);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://feyorra.top';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '148.251.78.240';

(function ($mail, $ip) {
    Proxy::load();
    $cookieFile = config::cookie($mail);
    $userAgent = config::uagent('mobile');

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($mail, 'info');
})($mail, $ip);

$dash = null;
$limit = false;
$shortlink = false;
$SLDONE = false;
$skipped = [];
$claim = false;
$can_withdraw = true;
while (true) {
    $max = 7;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check("$host/dashboard", [], '/register');
        
        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        
        if ($ret >= $max) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            exit; 
        }
        
        @unlink(inf::$cookie);
        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::C("$host/login", 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip);
        
        if ($_0 === 99) {
            logx('warn', 'Proxy issue, wait 30s');
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        #var_dump($_0); die;
        $f = scraper::payload($_0)[0] ?? null;
        
        $po = null;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['email' => $mail, 'password' => $pass];
            
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
            $ve = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/login", inf::$uagent, false, false, $ip);
            
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
            if (!empty($alert_d)) {
                $msg = $alert_d[0];
                if (stripos($msg, 'nvalid Captcha')) continue;
                logx('', $msg);
                die;
            }
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash); 
    
    if ($dash && str_contains($dash, 'confirm your email')) {
        $can_withdraw = false;
    }

    do {
        $ads = Net::C("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
        if ($ads === 99) { _sle(60); continue 2; }
        $_tim = scraper::_xP($ads, "//div[@class='ptc_cards']//span[i[contains(@class, 'fa-clock')]]");
        
        $_url = scraper::_xP($ads, "//button[not(contains(@onclick, 'bitcotask'))]/@onclick");
        $url = array_map(fn($u) => explode("'", $u)[1], $_url);
        $vurl = $url[0] ?? null;
        
        if ($vurl) {
            $cla = null;
            $tim = isset($_tim[0]) ? (int)preg_replace('/[^0-9]/', '', $_tim[0]) : 0;
            logx('info', "[ $vurl ]: ", false);
            logx('', $tim);
            #die;
            $ret99 = 0;
            while (true) {
                $view = Net::C($vurl, 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip);
                if ($view === 99) {
                    $ret99++;
                    logx('warn', "masalah proxy, warm up dulu");
                    if ($ret99 >= 5) {
                        goto login;
                    }
                    _sle(30);
                    continue;
                }
                $ret99 = 0; 
                if (!empty($view)) {
                    $set = microtime(true);
                    $f = scraper::payload($view) ?? [];
                    if (!empty($f)) {
                        $pa = $f[0]['payload'] ?? [];

                        $cap = [];
                        $cap = solve::exec($view, $host, $api);
                        if (isset($cap['trouble'])) {
                            _sle(60);
                            continue;
                        }
                        $po = array_merge($pa, $cap);
                        #print_r($po);
                        
                        if (!empty($po)) {
                            $end = microtime(true) - $set;
                            $wait = (int)($tim - $end);
                            if ($wait > 0) {
                                styler("waiting for ads: $wait", fn() => _sle($wait));
                            }
                            claim:
                            $cla = Net::C($f[0]['url'], 'POST', $po, inf::$cookie, [], $vurl, inf::$uagent, false, false, $ip);
                            if (empty($cla)) goto claim;
                            if ($cla === 99) goto login;
                            break;
                        }
                    }
                }
            }
            if (!empty($cla)) {
                $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                }
            }
        } else {
            logx('err', 'ptc habis');
            $claim = true;
            break;
        }
    } while (!$claim);

    $box = false;
    if ($claim && !$limit) {
        $ret99 = 0; 
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            #_put('fau.html', $fau);
            if ($fau === 99) {
                $ret99++;
                logx('warn', "masalah proxy, warm up dulu");
                if ($ret99 >= 5) {
                    goto login;
                }
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            $fo = scraper::payload($fau) ?? [];
            if (empty($fo)) {
                $alert_d = scraper::_xP($fau, "//div[contains(@class, 'alert-danger')]");
                if (!empty($alert_d)) {
                    $msg = $alert_d[0]; 
                    if (str_contains($msg, 'Pickabox game(s) required')) {
                        preg_match('/\d+/', $msg, $num);
                        $co = $num[0] ?? 1;
                        logx('err', "$co Pickabox game(s) detected!");
                        $box = true;
                        break;
                    }
                }
                
                if (str_contains($fau, 'Daily limit reached, claim Shortlink Wall')) {
                    $limit = true;
                    logx('err', 'daily limit');
                    break;
                }
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
            }
            
            $f = $fo[0];
            $pa = $f['payload'];
            
            $t_text = null;
            if (str_contains($fau, 'Write what you see in the picture')) {
                $_cu = null;
                foreach (scraper::_pP($fau, 'src') as $_u) {
                    if (str_contains($_u, '/images/captcha')) {
                        $_cu = trim($_u);
                        break;
                    }
                }
                
                if ($_cu) {
                    $img = Net::C($_cu, 'GET', null, inf::$cookie, [], "$host/faucet", inf::$uagent);
                    if (!empty($img) && ($img !== 99)) {
                        $t_text = _text($img, $host, $mail);
                    }
                }
                
                if (!$t_text) {
                    _sle(3);
                    continue; 
                }
            }
            
            $cap = [];
            if (isset($pa['captcha'])) {
                if ($pa['captcha'] === 'hcaptcha') {
                    /* comment ini kalo mau lanjut solve*/
                    #$limit = true; break;
                }
                $cap = solve::exec($fau, $host, $api);
                if (isset($cap['trouble'])) {
                    _sle(60);
                    continue;
                }
            }
            
            $po = array_merge($pa, $cap);
            if ($t_text !== null) {
                foreach ($po as $key => $val) {
                    if ($val === '' || $val === null) {
                        $po[$key] = $t_text;
                    }
                }
            }
            
            $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, false, false, $ip);
            if (empty($cla) || ($cla === 99)) continue;
            $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                
            if (isset($m[2][0])) {
                print(FGd['CYN'].maskEmail($mail).RSET." ");
                logg(true, $m[2][0], false);
                $pttr = '/<h3>([^<]+)<\/h3>\s*<p>Balance<\/p>/';
                $_bal = scraper::_jP($cla, $pttr)[1];
                logx('ok', '[ '.$_bal[0].' ]', true, true);
                if (stripos($m[2][0], 'has been added')) break;
            }
            
        }
    }

    if ($box) {
        for ($i = 1; $i <= $co; $i++) {
            logx('info', "box $i/$co.");
            
            $box = Net::C("$host/pickabox", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            $f = scraper::payload($box);
            if (!empty($f)) {
                $pa = $f[0]['payload'];
                $pa['selected_box'] = rand(1,3);
                $bet = Net::C($f[0]['url'], 'POST', $pa, inf::$cookie, [], "$host/faucet", inf::$uagent, false, false, $ip);
                $_aa = scraper::_xP($bet, "//div[contains(@class, 'alert')]");
                if (!empty($_aa)) {
                    $resMsg = preg_replace('/\s+/', ' ', trim(strip_tags($_aa[0])));
                    logx('info', "Result: $resMsg");
                }
                _sle(2); 
            }
        }
    }
    sl:
    $ret99 = 0;
    do {
        $sho = Net::C("$host/links", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
        if ($sho === 99) {
            $ret99++;
            logx('warn', "masalah proxy, warm up dulu");
            if ($ret99 >= 7) {
                goto login;
            }
            _sle(30);
            continue;
        }
        $ret99 = 0; 
        if (empty($sho)) continue;
        
        $f = scraper::payload($sho)[0] ?? [];
        $short = sScraper::extract($sho);
        if (empty($short)) {
            logx('info', "sl abis");
            $SLDONE = true;
            break;
        }
        #print_r($short);
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
        
        if (!empty($f)) {
            $po = $f['payload'];
            
            if (str_contains($sho, 'Write what you see in the picture')) {
                $t_text = null;
                $_cu = null;
                foreach (scraper::_pP($sho, 'src') as $_u) {
                    if (str_contains($_u, '/images/captcha')) {
                        $_cu = trim($_u);
                        break;
                    }
                }
                if ($_cu) {
                    $img = Net::C($_cu, 'GET', null, inf::$cookie, [], "$host/links", inf::$uagent);
                    $t_text = _text($img, $host, $mail);
                }
                if ($t_text) {
                    foreach ($po as $key => $val) {
                        if ($val === '' || $val === null) {
                            $po[$key] = $t_text;
                        }
                    }
                }
            }
        } 

        $can_process = false; 
        foreach ($short as $links => [$idd, $lmt]) {
            
            if (!limit($lmt) || isset($skipped[$idd])) continue;
            
            $can_process = true;
            
            $ud = $host.'/links/go/'.$idd;
            $getVer = 0;
            while (true) {
                $get = Net::X($ud, 'POST', $po, inf::$cookie, [], $host.'/links', inf::$uagent, ip: $ip, foll: false);
                if ($get === 99) {
                    $getVer++;
                    if ($getVer >= 5) goto login;
                    _sle(30);
                    continue;
                }
                if (!empty($get)) break;
            }
            
            preg_match('/location\.href\s*=\s*["\']([^"\']+)["\']/', $get, $match);
            $loc = $match[1] ?? '';
            
            if (!$loc) {
                $skipped[$idd] = true;
                continue; 
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
            if ($is_bl) {
                _sle(5);
                continue; 
            }
            
            logx('info', "Bypass: $loc", true, true);
            $bakk = links($api, $loc);
            #var_dump($bakk);
            
            if (!$bakk) {
                $skipped[$idd] = true; 
                _sle(5);
                continue; 
            }
            
            styler("waiting for SL", fn() => _sle(50));
            
            $retVer = 0;
            while (true) {
                $ver = Net::C($bakk, 'GET', null, inf::$cookie, [], $loc, inf::$uagent);
                if ($ver === 99) {
                    $retVer++;
                    if ($retVer >= 5) goto login;
                    _sle(30);
                    continue;
                }
                break;
            }
            
            if (!empty($ver)) {
                $m = scraper::_jP($ver, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                    break 2;
                }
            }
            
            #break; 
            break 2; 
        }

        if (!$can_process) {
            logx('info', "sl abis");
            $SLDONE = true;
        }
        
    } while (!$SLDONE);

    if ($limit) {
        if (!$can_withdraw) die;
        $wd = Net::C("$host/withdraw", 'GET', null, inf::$cookie, [], "$host/faucet", inf::$uagent, false, false, $ip);
        if (empty($wd) || ($wd === 99)) continue;
        $jajan = _wd($wd);
        if (!$jajan) {
            logx('err', 'gak bisa wd kayaknya');
            exit;
        }
        if ($jajan['payload']['amount'] > 2000) {
            $po = $jajan['payload'];
            $walletKey = isset($po['address']) ? 'address' : (isset($po['wallet']) ? 'wallet' : 'email');
            if (empty($po[$walletKey])) $po[$walletKey] = $mail;
            logg(true, '  tes ilmu: '. $jajan['info']['coin'], false);
            logx('info', ' [ '.$po['wallet'].' ]');
            $wd = Net::C($jajan['url'], 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, false, false, $ip);
            #_put('wd.html', $wd);
            if (!empty($wd)) {
                $m = scraper::_jP($wd, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                if (isset($m[2][0])) {
                    logx('info', $m[2][0], true, true);
                    $pttr = '/<h3>([^<]+)<\/h3>\s*<p>Balance<\/p>/';
                    die;
                }
            }
        } else {
            logx('err', 'gak cukup minimum wd');
            exit;
        }
    }
    
}






function pre($in_put, $threshold = 128) {
    if (!getDeps('gd@php')) {
        logx('err', 'gd@php missing');
        exit;
    }

    $put_in = dirname($in_put) . DIRECTORY_SEPARATOR . 'pre_' . basename($in_put);

    $img = @imagecreatefromstring(_get($in_put));
    if (!$img) {
        logx('err', "Unknown image format");
        exit;
    }

    $width  = imagesx($img);
    $height = imagesy($img);

    $scale = 3;
    $newWidth  = $width * $scale;
    $newHeight = $height * $scale;
    $clean = imagecreatetruecolor($newWidth, $newHeight);
    
    imagecopyresampled($clean, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    imagefilter($clean, IMG_FILTER_GRAYSCALE);
    imagefilter($clean, IMG_FILTER_CONTRAST, -100); 
    imagefilter($clean, IMG_FILTER_BRIGHTNESS, -10);

    for ($y = 0; $y < $newHeight; $y++) {
        for ($x = 0; $x < $newWidth; $x++) {
            $rgb = imagecolorat($clean, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            $gray = ($r + $g + $b) / 3;

            if ($gray < $threshold) {
                $color = imagecolorallocate($clean, 0, 0, 0);
            } else {
                $color = imagecolorallocate($clean, 255, 255, 255);
            }
            imagesetpixel($clean, $x, $y, $color);
        }
    }

    $topLeft = imagecolorat($clean, 0, 0);
    if (($topLeft & 0xFF) < 128) {
        imagefilter($clean, IMG_FILTER_NEGATE);
    }

    imagepng($clean, $put_in);
    #imagedestroy($img);
    #imagedestroy($clean);

    return $put_in;
}

function _text($imgData, $host, $mail) {
    if (empty($imgData)) return null;

    $tmpDir = _lib($host, $mail); 
    $originalImg = $tmpDir . '/raw.png';

    _put($originalImg, $imgData);
    
    $t_vote = [];
    $_th = [80, 90, 100, 110, 120, 140, 160];
    $_psms = [6, 8, 11];

    try {
        foreach ($_th as $th) {
            $preFile = pre($originalImg, $th, 3); 
            
            if (!$preFile || !file_exists($preFile)) continue;

            foreach ($_psms as $psm) {
                $output = [];
                $cmd = "tesseract " . escapeshellarg($preFile) . " stdout --psm $psm -c tessedit_char_whitelist=0123456789 2>/dev/null";
                @exec($cmd, $output);
                
                $resText = trim(implode('', $output));
                
                if (ctype_digit($resText) && strlen($resText) === 4) {
                    $t_vote[] = $resText;
                }
            }
            if (file_exists($preFile)) @unlink($preFile);
        }
    } finally {
        if (file_exists($originalImg)) @unlink($originalImg);
        if (is_dir($tmpDir)) @rmdir($tmpDir);
    }

    if (!empty($t_vote)) {
        $counts = array_count_values($t_vote);
        arsort($counts); 
        $t_text = (string)key($counts); 
        logx('ok', "OCR: $t_text (" . reset($counts) . "/" . count($t_vote) . ")");
        return $t_text;
    }

    return null;
}

function _wd($html) {
    $res = Scraper::payload($html)[0] ?? null;
    if (!$res) return false;

    $names  = Scraper::_xP($html, "//input[@name='method']/@data-coincode");
    $values = Scraper::_xP($html, "//input[@name='method']/@value");
    $stocks = Scraper::_xP($html, "//div[contains(@class, 'col-2') and contains(text(), '%')]");

    foreach ($names as $i => $name) {
        if (stripos($name, 'btc') !== false || stripos($name, 'bitcoin') !== false) continue;

        $stokValue = (int) ($stocks[$i] ?? 0);
        
        if ($stokValue > 20) {
            $res['payload']['method'] = $values[$i];
            
            $res['info'] = [
                'coin'  => $name,
                'stock' => $stokValue . '%'
            ];
            return $res;
        }
    }
    return false;
}
