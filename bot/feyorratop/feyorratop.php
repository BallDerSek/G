<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$cookieFile = getCookie();
$userAgent  = getUagent();
$acc = credential([], true);
$mail = $acc['mail'];
$pass = $acc['pass'];

$host = 'https://feyorra.top';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '148.251.78.240';

banner(); 
login:

$dash = null;
while (true) {
    
    do {
        $l = checkLogin("$host/dashboard", headers('', $host, $domain), $ip, '/register');
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break;
        }
        
        @unlink($cookieFile);
        taskPrintCenter('logging in', 'err');
        $_0 = Net::C("$host/login", 'GET', null, $cookieFile, [], '', $userAgent);
        if (empty($_0)) continue;
        $f = xScraper::payload($_0)[0];
        $pa = $f['payload'];
        
        if (str_contains($_0, 'iconcaptcha-widget')) {
            $ic = null;
            while(!$ic) {
                $ic = solveICAPTCHA($_0, $host);
            }
            $ca = $ic;
        } elseif (str_contains($pa['captcha'], 'turnstile')) {
            $c = capt::cha($_0);
            #print_r($c); die;
            $t = tK(getKeys($api), $host, $c['keys'][0], $c['type'], $userAgent);
            $ca = ['cf-turnstile-response' => $t, 'g-recaptcha-response' => $t];
        } else {
            logx('err', 'unknown captcha');
            die;
        }
        
        $cre = ['email' => $mail, 'password' => $pass];
        $po = array_merge($pa, $ca, $cre);
        
        $ve = Net::C($f['url'], 'POST', $po, $cookieFile, [], "$host/login", $userAgent);
        _put('ver.html', $ve);
        die;
        
    } while (empty($dash));
    
    $claim = false;
    do {
        $ads = Net::C("$host/ptc", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
        
        $_tim = xScraper::xPath($ads, "//div[@class='ptc_cards']//span[i[contains(@class, 'fa-clock')]]");
        
        $_url = xScraper::xPath($ads, "//button/@onclick");
        $url = array_map(fn($u) => explode("'", $u)[1], $_url);
        $vurl = $url[0] ?? null;
        
        if ($vurl) {
            $cla = null;
            $tim = isset($_tim[0]) ? (int)preg_replace('/[^0-9]/', '', $_tim[0]) : 0;
            logx('info', "[ $vurl ]: ", false);
            logx('', $tim, true);
            while (true) {
                $view = Net::C($vurl, 'GET', null, $cookieFile, [], '', $userAgent, false, false, $ip);
                if (!empty($view)) {
                    $set = microtime(true);
                    $f = xScraper::payload($view) ?? [];
                    if (!empty($f)) {
                        $pa = $f[0]['payload'] ?? [];
                        $ca = [];
                        
                        if (str_contains($view, 'iconcaptcha-widget')) {
                            $ic = null;
                            while(!$ic) {
                                $ic = solveICAPTCHA($view, $host, $ip);
                            }
                            $ca = $ic;
                        } else {
                            logx('err', 'unknown captcha.');
                            die;
                        }
                        $po = array_merge($pa, $ca);
                        if (!empty($po)) {
                            $end = microtime(true) - $set;
                            $wait = (int)($tim - $end);
                            if ($wait > 0) {
                                styler("waiting for ads: $wait", fn() => _sle($wait));
                            }
                            claim:
                            $cla = Net::C($f[0]['url'], 'POST', $po, $cookieFile, headers('', $vurl), $userAgent, false, false, $ip);
                            if (empty($cla)) goto claim;
                            break;
                        }
                    }
                }
            }
            if (!empty($cla)) {
                $m = rScraper::jPath($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                if (isset($m[2][0])) {
                    logx('info', $m[2][0], true, true);
                }
            }
        } else {
            logx('err', 'ptc habis');
            $claim = true;
            break;
        }
    } while (!$claim);
    
    $box = false;
    if ($claim) {
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
            if (empty($fau)) continue;
            
            $fo = xScraper::payload($fau) ?? [];
            if (empty($fo)) {
                $alertt = xScraper::xpath($fau, "//div[contains(@class, 'alert-danger')]");
                if (!empty($alertt)) {
                    $msg = $alertt[0]; 
                    if (str_contains($msg, 'Pickabox game(s) required')) {
                        preg_match('/\d+/', $msg, $num);
                        $co = $num[0] ?? 1;
                        logx('err', "$co Pickabox game(s) detected!");
                        $box = true;
                        break;
                    }
                }
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
            }
            
            $f = $fo[0];
            $pa = $f['payload'];
            
            $t_text = null;
            if (str_contains($fau, 'Write what you see in the picture')) {
                $src = rScraper::pPath($fau, 'src')[1];
                $_cu = null;
                
                foreach ($src as $_u) {
                    if (str_contains($_u, '/images/captcha')) {
                        $_cu = trim($_u);
                        break;
                    }
                }
                
                if ($_cu) {
                    $img = Net::C($_cu, 'GET', null, $cookieFile, [], "$host/faucet", $userAgent);
                    if (!getDeps('tesseract')) {
                        logx('err', 'GD extension missing');
                        exit;
                    }
                    
                    if (!empty($img)) {
                        _put(__DIR__.'/img.png', $img);
                        $t_vote = [];
                        $psm_stats = [6 => [], 8 => [], 11 => []];
                        $_th = [80, 90, 100, 110, 120, 140, 160];
                        $_psms = [6, 8, 11];
                        foreach ($_th as $th) {
                            $pre = pre(__DIR__.'/img.png', $th, 3);
                            foreach ($_psms as $psm) {
                                $output = [];
                                $cmd = "tesseract " . escapeshellarg($pre) . " stdout --psm $psm -c tessedit_char_whitelist=0123456789 2>/dev/null";
                                @exec($cmd, $output);
                                $resText = trim(implode('', $output));
                                if (ctype_digit($resText) && strlen($resText) === 4) {
                                    $t_vote[] = $resText;
                                    $psm_stats[$psm][] = $resText; 
                                }
                            }
                            @unlink($pre);
                        }
                        @unlink(__DIR__.'/img.png');
                        if (!empty($t_vote)) {
                            foreach ($psm_stats as $p_num => $p_results) {
                                if (!empty($p_results)) {
                                    $p_counts = array_count_values($p_results);
                                    arsort($p_counts);
                                    $p_best = key($p_counts);
                                    $p_score = reset($p_counts);
                                    logx('info', "PSM $p_num: $p_best ($p_score/".count($_th).")");
                                }
                            }
                            $counts = array_count_values($t_vote);
                            arsort($counts); 
                            $t_text = key($counts); 
                            $t_ocr = reset($counts);
                            $t_vocr = count($t_vote); 
                            logx('ok', "OCR: $t_text ( $t_ocr/$t_vocr )");
                        }
                    }
                }
                if (!$t_text) {
                    _sle(3);
                    continue; 
                }
            }
            
            $ca = [];
            $c = capt::cha($fau);
            if (isset($pa['captcha'])) {
                $field = match (true) {
                    str_contains($pa['captcha'], 'turnstile') => 'cf-turnstile-response',
                    str_contains($pa['captcha'], 'hcaptcha')  => 'h-captcha-response',
                    default => null
                };
                
                if ($field) {
                    $t = tK(getKeys($api), $host, $c['keys'][0], $c['type'], $userAgent);
                    $ca = [$field => $t, 'g-recaptcha-response' => $t];
                }
                
            }
            
            $po = array_merge($pa, $ca);
            if ($t_text !== null) {
                foreach ($po as $key => $val) {
                    if ($val === '' || $val === null) {
                        $po[$key] = $t_text;
                    }
                }
            }
            
            $cla = Net::C($f['url'], 'POST', $po, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
            $m = rScraper::jPath($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
            
            if (isset($m[2][0])) {
                logx('info', $m[2][0], false, true);
                $pttr = '/<h3>([^<]+)<\/h3>\s*<p>Balance<\/p>/';
                $_bal = rScraper::jPath($cla, $pttr)[1];
                logx('ok', ' [ '.$_bal[0].' ]', true, true);
            }
            
        }
    }

    if ($box) {
        for ($i = 1; $i <= $co; $i++) {
            logx('info', "box $i/$co.");
            
            $box = Net::C("$host/pickabox", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
            $f = xScraper::payload($box);
            if (!empty($f)) {
                $pa = $f[0]['payload'];
                $pa['selected_box'] = rand(1,3);
                $bet = Net::C($f[0]['url'], 'POST', $pa, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
                $_aa = xScraper::xpath($bet, "//div[contains(@class, 'alert')]");
                if (!empty($_aa)) {
                    $resMsg = preg_replace('/\s+/', ' ', trim(strip_tags($_aa[0])));
                    logx('info', "Result: $resMsg");
                }
                _sle(2); 
            }
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

function tK($api, $host, $key, $type, $ua) {
    if (!$api) {
        logx('err', 'undefined provider'); exit(1);
    }
    
    while (($t = $api->token($key, $host, $type, ['userAgent' => $ua])) === false);
    if ($t === null) exit(1);
    
    return $t;
}