<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], true, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://satoshifaucet.io';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=124158';
$ip = null;

(function ($login, $ip) {
    Proxy::load();
    $cookieFile = config::cookie($login);
    $creds = config::credential(['uagent' => fn() => config::uagent('desktop')], true);
    
    $userAgent = $creds['uagent'];

    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
})($login, $ip);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$curr = '';
while (true) {
    $max = 7;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', $headersCF, '/auth/login');
        
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

        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        
        if ($_0 === 99) {
            logx('warn', 'Proxy issue, wait 30s');
            _sle(30);
            continue; 
        }
        
        if (empty($_0)) continue;
        
        $f = scraper::payload($_0)[0] ?? null;
        
        $po = null;
        if (!empty($f)) {
            $pa = $f['payload'];
            $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $login];
            
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
            $lo = Net::C($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
            #_put('lo.html', $lo);
            if ($lo === 99) {
                logx('warn', 'Proxy issue, wait 30s');
                _sle(30);
                continue;
            }
        }
        
        _sle(5);
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    $_fa = Scraper::_xP($dash, "//div[normalize-space()='Faucets']/ancestor::li//div[@class='sub-menu-two']/a/@href");
    foreach ($_fa as $fa) {
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;

        while (true) {
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent, ip: $ip);
            #_put('fau.html', $fau); die;
            if ($fau === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 

            if (empty($fau)) continue;

            if (stripos($fau, 'Just a moment') !== false || stripos($fau, 'Attention Required!') !== false) {
                if ($cf = execCF($api, $fa, inf::$cookie, inf::$uagent)) {
                    [$headersCF, $ua] = $cf;
                    config::credential()['uagent'] = $ua; 
                    inf::setup($ua, inf::$cookie);
                    _sle(3);
                    continue;
                }
            }
            
            if ($ban = isBan($fau)) {
                logx('err', " Kena ban: " . $ban['ti']);
                _sle($ban['sleep']);
                continue; 
            }

            $f = scraper::payload($fau)[0] ?? null;
            if ($f) {
                $pa = $f['payload'];
                
                if (($pa['captcha'] === 'math_captcha') && isset($pa['math_answer'])) {
                    
                    $img_u = Scraper::_xP($fau, "//div[@class='mc-img-wrap']/img/@src");
                    if (!empty($img_u) && isset($img_u[0])) {
                        $img = explode(',', $img_u[0])[1] ?? '';
                        if (!empty($img)) {
                            #_put('img.png', base64_decode($img));
                            $cap = stfM($api, $host, $img);
                        }
                    }
                } else {
                    $cap = Solve::exec($fau, 'https://'.$domain, $api, $pa);
                }
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    ($tro === 'proxy') ? _sle(30) : _sle(10);
                    continue;
                }

                $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $login];
                
                $cleanCap = array_filter((array)$cap, fn($v, $k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_BOTH);
                $po = array_merge($pa, $cleanCap, $cre);
            } else {
                if (stripos($fau, 'claim limit') !== false) continue 2; 
                styler("waiting for CLAIM", fn() => _sle(10));
                continue;
            }
            
            _sle(2);
            $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, $fa, inf::$uagent);
            if ($cla === 99) {
                _sle(60);
                continue;
            }
            if (empty($cla)) continue;

            if (stripos($cla, 'rate limited') !== false) goto login;
            
            if ($ban = isBan($cla)) {
                logx('err', " Kena ban: " . $ban['ti']);
                _sle($ban['sleep']);
                continue; 
            }

            $_suc = scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
            
            if (!empty($_suc[1][0])) {
                $status = $_suc[1][0]; 
                print(FGd['CYN'].maskEmail($login).RSET." ");
                logx($status === 'success' ? 'ok' : 'err', " {$_suc[2][0]} ", false);
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
    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) continue;
        
        $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
        $ret99 = 0;

        do {
            $sho = Net::C($sl, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
            
            if ($sho === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 

            if (empty($sho)) { _sle(5); continue; }

            if ($ban = isBan($sho)) {
                logx('err', " Kena ban: " . $ban['ti']);
                _sle($ban['sleep']);
                continue; 
            }

            $f = scraper::payload($sho)[0] ?? null;
            if (!$f) { _sle(3); continue; }
            
            $pa = $f['payload'];
            $short = sScraper::extract($sho);
            $success_in_page = false; 
            $found_one = false; 
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt) || isset($skipped[$idd])) continue;
                
                $found_one = true;
                $ud = str_replace("/currency/$_c", "", $sl)."/go/{$idd}/".strtoupper($_c);
                
                $cap = solve::exec($sho, $host, $api, $pa);
                
                if (isset($cap['trouble'])) {
                    $tro = $cap['trouble'];
                    ($tro === 'proxy') ? _sle(30) : _sle(10);
                    break; 
                }

                $cleanCap = array_filter((array)$cap, fn($v, $k) => $k !== 'nocaptcha', ARRAY_FILTER_USE_BOTH);
                $po = array_merge($pa, $cleanCap, ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
                
                $get = Net::X($ud, 'POST', $po, inf::$cookie, $headersCF, $sl, inf::$uagent, ip: $ip, foll: false);
                
                if ($get === 99) {
                    logx('warn', 'Proxy issue, wait 30s');
                    _sle(30);
                    break; 
                }

                preg_match('/(?:location|location\.href|location\.replace)\s*=\s*["\']([^"\']+)["\']/', $get, $match);
                $loc = $match[1] ?? '';
                
                if (!$loc) {
                    $skipped[$idd] = true;
                    break; 
                }

                $loc_u = parse_url($loc)['host'] ?? '';
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
                
                logx('info', "Bypassing SL: $loc_u", true, true);
                
                $start = microtime(true);
                $bakk = links($api, $loc);
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                $wait = 100 - (int)(microtime(true) - $start);
                if ($wait > 0) styler("waiting for SL", fn() => _sle((int)ceil($wait)));
                
                $retVer = 0;
                while (true) {
                    $ver = Net::C($bakk, 'GET', null, inf::$cookie, $headersCF, $loc, inf::$uagent);
                    if ($ver === 99) {
                        $retVer++;
                        logx('warn', 'Proxy issue, wait 30s');
                        if ($ret99 >= 7) goto login;
                        _sle(30);
                        continue;
                    }
                    break;
                }
                
                if (!empty($ver)) {
                    $_suc = scraper::_jP($ver, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                    if (!empty($_suc[1][0])) {
                        $status = $_suc[1][0];
                        logx($status === 'success' ? 'ok' : 'err', " {$_suc[2][0]} ");
                        logg(false, "{$_suc[3][0]}");

                        if ($status === 'success') {
                            $success_in_page = true;
                            break 2; 
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



function stfM($api, $host, $img) {
    
    $ans = Solve::img($api, $host, 'math', $img);
    
    if (isset($ans['trouble'])) return $ans;
    
    if ($ans) {
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
