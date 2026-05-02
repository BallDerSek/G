<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$mail = $acc['mail'];
$pass = $acc['pass'];

$cookieFile = config::cookie($mail);
$userAgent = config::uagent('mobile');

$host = 'https://claimtrx.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '148.251.78.240';

inf::setup($userAgent, $cookieFile, $ip);

banner(); 
login:

$dash = null;
$limit = false;
$shortlink = false;
$SLDONE = false;
$skipped = [];
while (true) {
    $max = 7;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check("$host/dashboard", [], '/register');
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            #var_dump($dash); die;
            break;
        }
        if ($ret >= $max) {
            logx('err', 'gak tau');
            exit; 
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
            logx('', $msg);
            die;
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash); 

    $claim = false;
    do {
        $ads = Net::C("$host/ptc", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);

        $_tim = scraper::_xP($ads, "//div[contains(@class, 'card-badge')]//span[contains(@class, 'badge-primary')]");
        $_onclick = scraper::_xP($ads, "//div[@class='card-body']//button/@onclick");
        $url_list = array_map(fn($u) => explode("'", $u)[1] ?? null, $_onclick);
        $vurl = $url_list[0] ?? null;

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
                    logg(true, $m[2][0], true, true);
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
            $ret99 = 0; 
            $max99 = 5;
            $fau = Net::C("$host/faucet", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
            #_put('fau.html', $fau);
            if ($fau === 99) {
                $ret99++;
                logx('warn', "masalah proxy, warm up dulu");
                if ($ret99 >= $max99) {
                    logx('err', "Proxy beneran mati. Exit.");
                    die(98);
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
                    $img = Net::C($_cu, 'GET', null, $cookieFile, [], "$host/faucet", $userAgent);
                    #_put('img.png', $img);
                    if (!empty($img)) {
                        $resText = $api->base64($img, 'ocr');
                        #var_dump($resText); die;
                        if (ctype_digit($resText) && strlen($resText) === 4) {
                            $t_text = $resText; 
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
            if (empty($cla)) continue;
            $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
            
            if (isset($m[2][0])) {
                logg(true, $m[2][0]);
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
    
    do {
        $ret99 = 0; 
        $max99 = 5;
        $sho = Net::C("$host/links", 'GET', null, $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
        if ($fau === 99) {
            $ret99++;
            logx('warn', "masalah proxy, warm up dulu");
            if ($ret99 >= $max99) {
                logx('err', "Proxy beneran mati. Exit.");
                die(98);
            }
            _sle(30);
            continue;
        }
        $ret99 = 0; 
        if (empty($sho)) continue;
        
        $f = scraper::payload($sho)[0] ?? [];
        $short = sScraper::extract($sho);
        #print_r($short); die;
        $up = ['earnow','shortano', 'shortino', 'coinclix', 'fc-lc'];
        
        if (!empty($f) && !empty($short)) {
            $po = $f['payload'];
            
            if (str_contains($fau, 'Write what you see in the picture')) {
                $_cu = null;
                foreach (scraper::_pP($fau, 'src') as $_u) {
                    if (str_contains($_u, '/images/captcha')) {
                        $_cu = trim($_u);
                        break;
                    }
                }
                
                if ($_cu) {
                    $img = Net::C($_cu, 'GET', null, $cookieFile, [], "$host/links", $userAgent);
                    #_put('img.png', $img);
                    if (!empty($img)) {
                        $resText = $api->base64($img, 'ocr');
                        #var_dump($resText); die;
                        if (ctype_digit($resText) && strlen($resText) === 4) {
                            $t_text = $resText; 
                        }
                    }
                }
                
                if ($t_text) {
                    foreach ($po as $key => $val) {
                        if ($val === '' || $val === null) {
                            $po[$key] = $t_text;
                        }
                    }
                }
            }
        } else {
            continue;
        }

        $can_process = false; 
        foreach ($short as $links => [$idd, $lmt]) {
            
            if (!limit($lmt) || isset($skipped[$idd])) continue;
            
            $can_process = true;
            
            $ud = $host.'/links/go/'.$idd;
            $get = Net::X($ud, 'POST', $po, inf::$cookie, [], $host.'/links', inf::$uagent, ip: $ip, foll: false);
            if ($get === 99) break;
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
            if ($is_bl) continue; 
            
            logx('info', "Bypass: $loc", true, true);
            $bakk = links($api, $loc);
            
            if (!$bakk) {
                $skipped[$idd] = true; 
                continue; 
            }
            
            styler("waiting for SL", fn() => _sle(15));
            
            $retVer = 0;
            while (true) {
                $ver = Net::C($bakk, 'GET', null, inf::$cookie, [], $loc, inf::$uagent);
                if ($ver === 99) {
                    $retVer++;
                    if ($retVer >= 5) die(98);
                    _sle(30);
                    continue;
                }
                break;
            }
            
            if (!empty($ver)) {
                $m = scraper::_jP($ver, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                if (isset($m[2][0])) {
                    logg(true, $m[2][0], true, true);
                }
            }
            
            break; 
        }

        if (!$can_process) {
            logx('info', "sl abis");
            $SLDONE = true;
        }
        
    } while (!$SLDONE);
    
    if ($limit) {
        $wd = Net::C("$host/withdraw", 'GET', null, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
        #_put('wd.html', $wd);
        if (empty($wd)) continue;
        $jajan = _wd($wd);
        #print_r($jajan);
        if (!$jajan) {
            logx('err', 'gak bisa wd kayaknya');
            exit;
        }
        if ($jajan['payload']['amount'] > 0.04) {
            $po = $jajan['payload'];
            $original = $po['amount'];
            if (str_contains($original, '.')) {
                $decimal_count = strlen(substr(strrchr($original, "."), 1));
                $divider = pow(10, $decimal_count);
                $minus = rand(1, 5) / $divider;
                $po['amount'] = number_format($original - $minus, $decimal_count, '.', '');
            } else {
                $po['amount'] = $original - rand(1, 5);
            }
            logg(true, '  tes ilmu: '. $jajan['info']['coin'], false);
            logx('info', ' [ '.$po['wallet'].' ]');
            $wd = Net::C($jajan['url'], 'POST', $po, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
            #_put('wd.html', $wd); die;
            if (!empty($wd)) {
                $m= scraper::_jP($wd, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s") ?? [];
                if (isset($m[2][0])) {
                    logx('info', $m[2][0], true, true);
                    die;
                }
            }
        } else {
            logx('err', 'gak cukup minimum wd');
            exit;
        }
    }
    
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

