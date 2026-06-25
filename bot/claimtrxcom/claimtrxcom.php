<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, ['mail', 'pass', 'PROXY']);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://claimtrx.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '148.251.78.240';
$ip = '';

(function ($mail, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($mail);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, false, $mail);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$mail");
    $b->task2('ok', "site: $host");
    
} ) ($mail, $ip, $host);

$limit = false;
$claim = true;
$SLDONE = false;
$ADDONE = false;
$skipped = [];
$can_withdraw = true;
while (true) {
    $dash = null;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check("$host/dashboard", [], '/register');
        #var_dump($l); _rl('lanjut:  ');
        
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
        $_0 = Net::C("$host/login", 'GET', null, inf::$cookie, [], '', inf::$uagent, false, false, $ip);
        
        if ($_0 === 99) {
            logx('warn', 'Proxy issue, wait 30s');
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;

        $f = scraper::payload($_0)[0] ?? null;
        #_put('0.html', $_0);
        $po = null;
        if (!empty($f)) {
            #print_r($f); die;
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
            #print_r($po);
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, [], "$host/login", inf::$uagent, ip: $ip);
            
            #_put('ve.html', $ve); #die;
            if ($ve === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
            
            $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
            if (!empty($alert_d)) {
                $msg = $alert_d[0];
                logx('', $msg);
                if (stripos($msg, 'nvalid Captcha')) continue;
                die;
            }
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    if ($dash && str_contains($dash, 'confirm your email')) {
        $can_withdraw = false;
    }
    
    $_bal = Scraper::_xP($dash, "//h5[contains(text(), 'Balance')]/following-sibling::h2/text()")[0] ?? '';
    if ($_bal) {
        Logger::M($mail);
        logx('info', "[ $_bal ]", true, true);
        $bal = ((float)$_bal);
        
        if ($can_withdraw && ($bal >= 0.05)) {
            $po = null;
            $jjn = [];
            $wd = Net::C("$host/withdraw", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            $jjn = _wd($wd);
            
            if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                $pa = $jjn['payload'];
                $cap = solve::exec($wd, $host, $api, $pa);
                
                if (isset($cap['trouble'])) $can_withdraw = false;
                $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                if (empty($pa[$walletKey])) $pa[$walletKey] = $mail;
                if (isset($pa['amount'])) {
                    $amount = (float)$pa['amount'];
                    $pa['amount'] = number_format($amount * 0.99, 8, '.', '');
                }
                $po = array_merge($pa, $cap);
                
                Logger::G(true, '  tes ilmu: '.$jjn['info']['coin'], false);
                Logger::X('info', ' [ '.$po[$walletKey].' ]');
                
                $wdd = Net::C($jjn['url'], 'POST', $po, inf::$cookie, [], "$host/withdraw", inf::$uagent, false, false, $ip);
                #_put('wd.html', $wdd);
                $m = scraper::_jP($wdd, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                if (isset($m[2][0])) {
                    Logger::M($mail);
                    Logger::X('info', $m[2][0]);
                }
                
            } else logx('err', 'gak bisa wd kayaknya');
            
        }
        
    }
    
    $setF = 0; 
    if (!$limit && $claim) {
        $ret99 = 0; 
        while (true) {
            $fau = Net::C("$host/faucet", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
            
            #_put('fau.html', $fau); die;
            
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
            
            $f = scraper::payload($fau)[0] ?? [];
            #print_r($f); #die;
            
            $po = null;
            if (!empty($f)) {
                $pa = $f['payload'];
                $cap = [];
                if (isset($pa['captcha'])) {
                    $_ca = $pa['captcha'];
                    if (($_ca === 'hcaptcha') || ($_ca === 'faucetcaptcha')) {
                        /* comment ini kalo mau lanjut solve*/
                        $claim = false; break;
                    }
                    $cap = solve::exec($fau, $host, $api, $pa);
                    if (isset($cap['trouble'])) {
                        _sle(60);
                        continue;
                    }
                }
                
                $po = array_merge($pa, $cap);
                
                $t_text = null;
                if (stripos($fau, 'Write what you see in the picture')) {
                    $_cu = null;
                    foreach (scraper::_pP($fau, 'src') as $_u) {
                        if (str_contains($_u, '/images/captcha')) {
                            $_cu = trim($_u);
                            break;
                        }
                    }
                    
                    if ($_cu) {
                        $img = Net::C($_cu, 'GET', null, inf::$cookie, [], "$host/faucet", inf::$uagent);
                        
                        if (!empty($img) && ($img !== 99)) $t_text = _text($img, $host, $mail);
                    }
                    
                    if (!$t_text) continue;
                    
                    if ($t_text !== null) {
                        $xp = Scraper::dom($fau);
                        $nodes = $xp->query("//input[@pattern='[0-9]*'] | //input[@inputmode='numeric']");
                        
                        $_Tfield = null;
                        if ($nodes->length > 0) $_Tfield = $nodes->item(0)->getAttribute('name');
                        
                        if (!empty($_Tfield)) {
                            $po[$_Tfield] = $t_text;
                            
                            #logx('info', " text-captcha: [ $_Tfield ]");
                        }
                    }
                }
                
                
            } else {
                if (str_contains($fau, '/register')) continue 2;
                
                if (str_contains($fau, 'Daily limit reached, claim Shortlink Wall')) {
                    $limit = true;
                    logx('err', 'daily limit');
                    break;
                }
                
                if (!$SLDONE || !$ADDONE) {
                    $setF = microtime(true);
                    break;
                }
                
                styler('Waiting for faucet', fn() => _sle(30));
                continue;
                
            }
            
            if (!empty($po)) {
                $cla = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/faucet", inf::$uagent, false, false, $ip);
                if (empty($cla) || ($cla === 99)) continue;
                #_put('cla.html', $cla); die;
                
                $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                if (isset($m[2][0])) {
                    print(FGd['CYN'].maskEmail($mail).RSET." ");
                    logg(true, $m[2][0]);
                    
                    if (stripos($m[2][0], 'has been added')) {
                        $setF = microtime(true);
                        break;
                    }
                }
            }
            
        }
    }
    
    $ads = Net::C("$host/ptc", 'GET', null, inf::$cookie, [], "$host/dashboard", inf::$uagent, false, false, $ip);
    #_put('ads.html', $ads);
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        
        if ($ptcNumb <= 1) {
            $ADDONE = true;
        } else {
            
            if (!empty($ptcList['local']) && !$ADDONE) {
                foreach ($ptcList['local'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    $cla = null;
                    $view = null;
                    
                    $view = Net::C($ad_u, 'GET', null, inf::$cookie, [], "$host/ptc", inf::$uagent, false, false, $ip);
                    #_put('view.html', $view);
                    if ($view === 99) continue 2;
                    if (!empty($view) && $view !== 99) {
                        $po = null;
                        $f = scraper::payload($view)[0] ?? [];
                        
                        if (!empty($f)) {
                            $pa = $f['payload'];
                            
                            $_ca = $pa['captcha'];
                            if (($_ca === 'hcaptcha') || ($_ca === 'faucetcaptcha')) {
                                $ADDONE = true;
                                continue;
                            }
                            
                            $cap = solve::exec($view, $ad_u, $api, $pa);
                            if (isset($cap['trouble'])) {
                                _sle(60);
                                continue;
                            }
                            $po = array_merge($pa, $cap);
                            
                        }
                        
                        if (!empty($po)) {
                            styler("waiting for ads: $ad_t", fn() => _sle($ad_t));
                            $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, [], $ad_u, inf::$uagent, false, true, $ip);
                            #_put('cla.html', $cla); die;
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                            #print_r($m);
                            if (isset($m[2][0])) {
                                Logger::M($mail);
                                Logger::G(0, $m[2][0]);
                                
                                $endF = microtime(true);
                                if ($setF > 0 && $claim) {
                                    $balik = $endF - $setF;
                                    if ($balik >= 4 * 60) continue 2;
                                }
                                
                            }
                            
                        }
                        
                        
                    }
                    
                }
            }
            
            if (!empty($ptcList['bctt'])) {
                foreach ($ptcList['bctt'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    $bctt = new Bctt($host, $api, $mail);
                    $ch = $bctt->exec($ad_u, $ad_t);
                    if ($ch === 99) goto login;
                    
                    $endF = microtime(true);
                    if ($setF > 0 && $claim) {
                        $balik = $endF - $setF;
                        if ($balik >= 4 * 60) continue 2;
                    }
                    
                }
            }
            
        }
        
    }

    sl:
    $ret99 = 0;
    if (!$SLDONE) {
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
            #print_r($short);
            if (empty($short)) {
                logx('info', "sl abis");
                $SLDONE = true;
                break;
            }
            $up = ['earnow','shortano', 'shortino', 'fc-lc'];
            
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
                #var_dump($bakk); #die;
                
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    _sle(5);
                    break 2;
                }
                
                styler("waiting for SL", fn() => _sle(70));
                
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
    }
    
    if (!$claim && $SLDONE && $ADDONE) {
        print(FGd['CYN'].maskEmail($mail).RSET." ");
        (logx('err', 'beres') ?: die);
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
        return 300;
    }

    $width  = imagesx($img);
    $height = imagesy($img);

    $scale = 3;
    $newWidth  = $width * $scale;
    $newHeight = $height * $scale;
    $clean = imagecreatetruecolor($newWidth, $newHeight);
    
    imagecopyresampled($clean, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    imagefilter($clean, IMG_FILTER_GRAYSCALE);
    
    imagefilter($clean, IMG_FILTER_CONTRAST, 40); 

    for ($y = 0; $y < $newHeight; $y++) {
        for ($x = 0; $x < $newWidth; $x++) {
            $rgb = imagecolorat($clean, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            if ($r < $threshold) {
                $color = imagecolorallocate($clean, 0, 0, 0);
            } else {
                $color = imagecolorallocate($clean, 255, 255, 255);
            }
            imagesetpixel($clean, $x, $y, $color);
        }
    }

    $topLeft = imagecolorat($clean, 0, 0);
    if (($topLeft & 0xFF) < 128) imagefilter($clean, IMG_FILTER_NEGATE);

    imagepng($clean, $put_in);
    @imagedestroy($img);

    return $put_in;
}

function _text($imgData, $host, $mail) {
    if (empty($imgData)) return null;

    $tmpDir = _lib('ocr', $host, $mail); 
    $originalImg = $tmpDir . '/raw.png';

    _put($originalImg, $imgData);
    
    $t_vote = [];
    $_th = [80, 90, 100, 110, 120, 140, 160];
    $_psms = [6, 8, 11];

    try {
        foreach ($_th as $th) {
            $preFile = pre($originalImg, $th, 3); 
            
            if ($preFile === 300) return null;
            
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

function parsePtcAds($html, $host) {
    if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $xp = Scraper::dom($html);
    if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
    $baseUrl = (parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host);
    $baseUrl = rtrim($baseUrl, '/');
    
    // Cari card yang ada button onclick
    $cards = $xp->query("//div[contains(@class, 'card')]//button[@onclick]/ancestor::div[contains(@class, 'card')]");
    
    foreach ($cards as $card) {
        $btn = $xp->query(".//button/@onclick", $card);
        if ($btn->length === 0) continue;
        
        preg_match("/(?:window\.location\s*=\s*|window\.open\s*\(\s*|location\.href=')'([^']+)'/", $btn->item(0)->value, $m);
        if (empty($m[1])) continue;
        
        $url = $m[1];
        
        if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
            $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        
        // Ambil timer dari badge-primary (format: "15 Seconds")
        $timer = 5;
        $badge = $xp->query(".//span[contains(@class, 'badge-primary')]", $card);
        if ($badge->length > 0 && preg_match('/(\d+)\s*Seconds?/', $badge->item(0)->textContent, $tm)) {
            $timer = (int)$tm[1];
        }
        
        $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
        
        if ($uHost === $host) $result['local'][] = [$url, $timer];
        elseif (strpos($url, 'bitcotasks.com') !== false) $result['bctt'][] = [$url, $timer];
        elseif (strpos($url, 'offerwall.me') !== false) $result['owme'][] = [$url, $timer];
        else $result['external'][] = [$url, $timer];
    }
    
    $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
    
    return $result;
}