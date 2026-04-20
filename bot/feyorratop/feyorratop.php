<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$mail = $acc['mail'];
$pass = $acc['pass'];

$cookieFile = config::cookie($mail);
$userAgent = config::uagent('mobile');

$host = 'https://feyorra.top';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '148.251.78.240';

inf::setup($userAgent, $cookieFile, $ip);

banner(); 
login:

$dash = null;
$limit = false;
$register = false;
$shortlink = false;
while (true) {
    
    do {
        $l = inf::check("$host/dashboard", [], '/register');
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            #var_dump($dash); die;
            break;
        }
        
        @unlink($cookieFile);
        taskPrintCenter('logging in', 'err');
        $_0 = Net::C("$host/login", 'GET', null, $cookieFile, [], '', $userAgent, false, false, $ip);
        if (empty($_0)) continue;
        #var_dump($_0); die;
        $f = scraper::payload($_0)[0];
        $pa = $f['payload'];
        
        $cap = null;
        $cap = solve::exec($_0, $host, $api);
        
        $cre = ['email' => $mail, 'password' => $pass];
        $po = array_merge($pa, $cap, $cre);
        #print_r($po);
        
        $ve = Net::C($f['url'], 'POST', $po, $cookieFile, [], "$host/login", $userAgent, false, false, $ip);
        $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
        if (!empty($alert_d)) {
            $msg = $alert_d[0]; 
            if (str_contains($msg, 'nvalid Credential')) {
                logx('err', $msg);
                #$register = true;
                #break 2;
            }
            logx('', $msg);
            die;
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash); 

/*
    if (str_contains($dash, 'confirm your email')) {
        logx('info', 'check email');
        $_re = Net::C("$host/dashboard/resend", 'GET', null, $cookieFile, [], $host.'/dashboard', $userAgent, false, false, $ip);
        Net::C(_cd($mail), 'GET', null, $cookieFile, [], $host.'/dashboard', $userAgent, false, false, $ip);
        continue;
    }
*/
    
    $claim = false;
    do {
        $ads = Net::C("$host/ptc", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
        
        $_tim = scraper::_xP($ads, "//div[@class='ptc_cards']//span[i[contains(@class, 'fa-clock')]]");
        
        $_url = scraper::_xP($ads, "//button/@onclick");
        $url = array_map(fn($u) => explode("'", $u)[1], $_url);
        $vurl = $url[0] ?? null;
        
        if ($vurl) {
            $cla = null;
            $tim = isset($_tim[0]) ? (int)preg_replace('/[^0-9]/', '', $_tim[0]) : 0;
            logx('info', "[ $vurl ]: ", false);
            logx('', $tim);
            #die;
            while (true) {
                $view = Net::C($vurl, 'GET', null, $cookieFile, [], '', $userAgent, false, false, $ip);
                if (!empty($view)) {
                    $set = microtime(true);
                    $f = scraper::payload($view) ?? [];
                    if (!empty($f)) {
                        $pa = $f[0]['payload'] ?? [];

                        $cap = null;
                        $cap = solve::exec($view, $host, $api);
                        $po = array_merge($pa, $cap);
                        #print_r($po);
                        
                        if (!empty($po)) {
                            $end = microtime(true) - $set;
                            $wait = (int)($tim - $end);
                            if ($wait > 0) {
                                styler("waiting for ads: $wait", fn() => _sle($wait));
                            }
                            claim:
                            $cla = Net::C($f[0]['url'], 'POST', $po, $cookieFile, [], $vurl, $userAgent, false, false, $ip);
                            if (empty($cla)) goto claim;
                            break;
                        }
                    }
                }
            }
            if (!empty($cla)) {
                $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                if (isset($m[2][0])) {
                    logx('info','   '. $m[2][0], true, true);
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
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
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
                $src = scraper::_pP($fau, 'src');
                $_cu = null;
                
                foreach ($src as $_u) {
                    if (str_contains($_u, '/images/captcha')) {
                        $_cu = trim($_u);
                        break;
                    }
                }
                
                if ($_cu) {
                    $tmpDir = _lib($host, $mail); 
                    $originalImg = $tmpDir . '/raw.png';

                    $img = Net::C($_cu, 'GET', null, $cookieFile, [], "$host/faucet", $userAgent);
                    
                    if (!getDeps('tesseract')) {
                        logx('err', 'tesseract missing');
                        exit;
                    }
                    
                    if (!empty($img)) {
                        _put($originalImg, $img);
                        
                        $t_vote = [];
                        $psm_stats = [6 => [], 8 => [], 11 => []];
                        $_th = [80, 90, 100, 110, 120, 140, 160];
                        $_psms = [6, 8, 11];

                        foreach ($_th as $th) {
                            $pre = pre($originalImg, $th, 3); 

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
                        
                        @unlink($originalImg); 
                        @rmdir($tmpDir); 

                        if (!empty($t_vote)) {
                            $counts = array_count_values($t_vote);
                            arsort($counts); 
                            $t_text = key($counts); 
                            logx('ok', "OCR: $t_text (" . reset($counts) . "/" . count($t_vote) . ")");
                        }
                    }
                }
                
                if (!$t_text) {
                    _sle(3);
                    continue; 
                }
            }
            
            $cap = [];
            if (isset($pa['captcha'])) {
                $cap = solve::exec($fau, $host, $api);
            }
            
            $po = array_merge($pa, $cap);
            if ($t_text !== null) {
                foreach ($po as $key => $val) {
                    if ($val === '' || $val === null) {
                        $po[$key] = $t_text;
                    }
                }
            }
            
            $cla = Net::C($f['url'], 'POST', $po, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
            $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
            
            if (isset($m[2][0])) {
                logx('info', $m[2][0], false, true);
                $pttr = '/<h3>([^<]+)<\/h3>\s*<p>Balance<\/p>/';
                $_bal = scraper::_jP($cla, $pttr)[1];
                logx('ok', ' [ '.$_bal[0].' ]', true, true);
            }
            
        }
    }

    if ($box) {
        for ($i = 1; $i <= $co; $i++) {
            logx('info', "box $i/$co.");
            
            $box = Net::C("$host/pickabox", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
            $f = scraper::payload($box);
            if (!empty($f)) {
                $pa = $f[0]['payload'];
                $pa['selected_box'] = rand(1,3);
                $bet = Net::C($f[0]['url'], 'POST', $pa, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
                $_aa = scraper::_xP($bet, "//div[contains(@class, 'alert')]");
                if (!empty($_aa)) {
                    $resMsg = preg_replace('/\s+/', ' ', trim(strip_tags($_aa[0])));
                    logx('info', "Result: $resMsg");
                }
                _sle(2); 
            }
        }
    }
    
    if ($limit) {
        $wd = Net::C("$host/withdraw", 'GET', null, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
        if (empty($wd)) continue;
        $jajan = _wd($wd);
        if (!$jajan) {
            logx('err', 'gak bisa wd kayaknya');
            exit;
        }
        if ($jajan['payload']['amount'] > 2000) {
            $po = $jajan['payload'];
            logg(true, '  tes ilmu: '. $jajan['info']['coin'], false);
            logx('info', ' [ '.$po['wallet'].' ]');
            $wd = Net::C($jajan['url'], 'POST', $po, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
            _put('wd.html', $wd);
            if (!empty($wd)) {
                $m= scraper::_jP($wd, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                if (isset($m[2][0])) {
                    logx('info', $m[2][0], true, true);
                    $pttr = '/<h3>([^<]+)<\/h3>\s*<p>Balance<\/p>/';
                }
            }
        } else {
            logx('err', 'gak cukup minimum wd');
            exit;
        }
    }
    
}

if ($register) {
    @unlink($cookieFile);
    $po_ = null;
    while (true) {
        $_0 = Net::C("$host/register", 'GET', null, $cookieFile, [], '', $userAgent, false, false, $ip);
        if (!empty($_0)) {
            #_put('0.html', $_0);
            $f = scraper::payload($_0)[0];
            $pa = $f['payload'];
            #print_r($f);
            $c = capt::cha($_0);
            $t = tK(getKeys($api), $host, $c['keys'][0], $c['type'], $userAgent);
            $ca = ['cf-turnstile-response' => $t, 'g-recaptcha-response' => $t];
            $cre = ['email' => $mail, 'password' => $pass, 'confirm_password' => $pass];
            $po_ = array_merge($pa, $ca, $cre);
            break;
        }
    }
    if (!empty($po_)) {
        #print_r($po_);
        $_1 = Net::C($f['url'], 'POST', $po_, $cookieFile, [], $host.'/register', $userAgent, false, false, $ip);
        #_put('1.html', $_1);
    }
    goto login;
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

function _wd($html) {
    $res = Scraper::payload($html)[0] ?? null;
    if (!$res) return false;

    $names  = Scraper::_xP($html, "//input[@name='method']/@data-coincode");
    $values = Scraper::_xP($html, "//input[@name='method']/@value");
    $stocks = Scraper::_xP($html, "//div[contains(@class, 'col-2') and contains(text(), '%')]");

    foreach ($names as $i => $name) {
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

function _cd($key = 'code') {
    $baseUrl = getenv('DG');
    if (!$baseUrl) return _rl("$key: ");

    $url = rtrim($baseUrl, '/') . '/get';
    $deadline = time() + 320;
    $payload = ['type' => $key];

    while (time() < $deadline) {
        logx('info', "checking $key");
        $html = Net::X($url, 'POST', $payload, null, [], '', null, true);
        
        if ($html) {
            if (preg_match('/.*[=]\s*(\S+)/i', $html, $m)) {
                $result = trim($m[1]);
                logx('ok', "Data found: $result");
                return $result;
            }
            
            if (strlen(trim($html)) > 0 && !str_contains($html, 'error')) {
                return trim($html);
            }
        }
        
        if (str_contains($html, 'error')) {
            logx('warn', "Remote: $html");
        }

        sleep(5);
    }

    logx('err', "Timeout: $key");
    exit(1);
}