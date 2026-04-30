<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent('mobile');

$host = 'https://satoshifaucet.io';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=124158';
$ip = null;

inf::setup($userAgent, $cookieFile, $ip);

banner();
login:


if (empty($GLOBALS['_CTX']['proxy']['src'])) {
    logx('err', '  MANA PROXY NYA !!!');
    die;
}

$headersCF = [];
$skipped = [];
$SLDONE = false;
while (true) {
    $max = 7;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', $headersCF, '/auth/login');
        #_put('l.html', $l['html']);
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break; 
        }
        if ($ret >= $max) {
            logx('warn', 'ADA YANG SALAH, COBA CEK KE WEB');
            exit; 
        }
        @unlink(inf::$cookie);
        taskPrintCenter('logging in', 'err');
        
        $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        if ($_0 === 99) exit;
        if (empty($_0)) continue;
        
        #_put('0.html', $_0);
        $f = scraper::payload($_0)[0];
        $pa = $f['payload'];
        $cap = solve::exec($_0, $host, $api);
        
        $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $login];
        $po = array_merge($pa, $cap, $cre);
        
        $lo = Net::C($f['url'], 'POST', $po, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
        if ($lo === 99) exit;
        #_put('lo.html', $lo);
#die;
        _sle(5);
    } while (empty($dash));

    #_put('dash.html', $dash);
    
    $_fa = Scraper::_xP($dash, "//div[normalize-space()='Faucets']/ancestor::li//div[@class='sub-menu-two']/a/@href");
    
    foreach ($_fa as $fa) {
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".UNDR.maskEmail($login).RSET."  ");
        logx('err', strtoupper($_c));
        
        while (true) {
            #print_r($headersCF);
            $fau = Net::C($fa, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent, ip: $ip);
            if ($fau === 99) exit;
            if (empty($fau)) continue;
            #_put('stffau.html', $fau);
            
            /*
            if (!empty($headersCF)) {
                _put('fau.html', $fau);
                logx('info', inf::$uagent);
            }
            */
            
            $f = scraper::payload($fau)[0] ?? [];
            if (!empty($f)) {
                $pa = $f['payload'];
                #print_r($pa);
                $cap = solve::exec($fau, 'https://'.$domain, $api);
                if ($cap['nocaptcha'] === true) {
                    if (($pa['captcha'] === 'shield') && isset($pa['shield_answer'])) {
                        $cap = solveShield($fau);
                    }
                }
                $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()];
                $po = array_merge($pa, $cap, $cre);
            } else {
                if (stripos($fau, 'claim limit for this coin reached')) continue 2;
                if ((stripos($fau, 'Just a moment') !== false || stripos($fau, 'Attention Required!') !== false)) {
                    logx('warn', 'Cloudflare Detected');
                    if ($cf = stfCF($api, $fa)) {
                        [$he, $ua] = $cf;
                        inf::setup($ua, inf::$cookie);
                        $headersCF = $he;
                        
                        #$fauuu = Net::C($fa, 'GET', null, inf::$cookie, $he, $fa, inf::$uagent);
                        #_put('faa.html', $fauuu); die;
                    }
                }
                _sle(10);
                continue;
            }
            print_r($po);
            
            _sle(2);
            
            $cla = Net::X($f['url'], 'POST', $po, inf::$cookie, $headersCF, '', inf::$uagent);
            if ($cla === 99) exit;
            if (empty($cla)) continue;
            #_put('cla.html', $cla);
            var_dump($cla); die;
            if (stripos($cla, 'rate limited')) goto login;
            if ($ban = isBan($cla)) {
                logx('err', " kena ban: " . $ban['ti'], false);
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue; 
            }
            $_suc = scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
            if (!empty($_suc[1][0])) {
                logx('err', " {$_suc[2][0]} ", false, true);
                logg(false, "{$_suc[3][0]}");
                if (stripos($_suc[3][0], 'sufficient')) break;
                if (stripos($_suc[3][0], 'Shortlink must be completed')) {
                    if ($SLDONE) {
                        logx('err', 'dah kelar gada sl+jatah faucet'); die;
                    }
                    $curr = $_c;
                    break 2;
                }
            }
            styler("waiting for CLAIM", fn() => _sle(10));
        }
    }

    $_sl = Scraper::_xP($dash, "//div[normalize-space()='Shortlinks']/parent::div/following-sibling::div[@class='sub-menu-two']/a/@href");
/*
    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) continue;
        $up = ['earnow','shortano', 'shortino', 'coinclix', 'fc-lc'];

        do {
            $sho = Net::C($sl, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
            if ($sho === 99) exit;
            if (empty($sho)) {
                _sle(5); 
                continue;
            }
            if ($ban = isBan($sho)) {
                logx('err', " kena ban: " . $ban['ti'], false);
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue; 
            }
            $f = scraper::payload($sho)[0] ?? [];
            if (empty($f)) { _sle(3); continue; }
            
            $pa = $f['payload'];
            $short = sScraper::extract($sho);
            #print_r($short); die;
            $success_in_page = false; 
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt) || isset($skipped[$idd])) continue;
                
                $ud = str_replace("/currency/$_c", "", $sl)."/go/{$idd}/".strtoupper($_c);
                $cap = solve::exec($sho, $host, $api);
                $po = array_merge($pa, $cap, ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
                
                $get = Net::X($ud, 'POST', $po, inf::$cookie, $headersCF, $sl, inf::$uagent, ip: $ip, foll: false);
                if ($get === 99) exit;
                preg_match('/location\.href\s*=\s*["\']([^"\']+)["\']/', $get, $match);
                $loc = $match[1] ?? '';
                
                if (!$loc) {
                    $skipped[$idd] = true;
                    break; 
                }
                $loc_u = parse_url($loc)['host'];
                foreach ($up as $blacklisted) {
                    if (str_contains($loc_u, $blacklisted)) {
                        logx('warn', "Domain $blacklisted Skipping..");
                        $skipped[$idd] = true;
                        break 2; 
                    }
                }

                logx('info', "Bypass: $loc", true, true);
                $bakk = links($api, $loc);
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                styler("waiting for SL", fn() => _sle(60));
                $ver = Net::C($bakk, 'GET', null, inf::$cookie, $headersCF, $loc, inf::$uagent);
                if ($ver === 99) exit;
                
                if (!empty($ver)) {
                    $_suc = scraper::_jP($ver, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                    if (!empty($_suc[1][0])) {
                        logx('err', " {$_suc[2][0]} ", false, true);
                        logg(false, "{$_suc[3][0]}");

                        if ($_suc[1][0] === 'success') {
                            $success_in_page = true;
                            $curr = ""; 
                            break 2;
                        }
                        if (stripos($_suc[3][0], 'sufficient')) {
                            break;
                        }
                    }
                }
                $skipped[$idd] = true;
                break;
            }
            if (!$success_in_page && empty($ud)) {
                logx('err', 'sl abis kayaknya');
                $curr = "";
                $SLDONE = true;
                break; 
            }
        } while (!$success_in_page);
        
        if ($success_in_page || $curr === "") {
            break; 
        }
    }
/*/

    foreach ($_sl as $sl) {
        $_c = basename($sl);
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) continue;
        $up = ['earnow','shortano', 'shortino', 'coinclix', 'fc-lc'];

        do {
            $sho = Net::C($sl, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
            if ($sho === 99) exit;
            if (empty($sho)) {
                _sle(5); 
                continue;
            }
            if ($ban = isBan($sho)) {
                logx('err', " kena ban: " . $ban['ti'], false);
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue; 
            }
            $f = scraper::payload($sho)[0] ?? [];
            if (empty($f)) { _sle(3); continue; }
            
            $pa = $f['payload'];
            $short = sScraper::extract($sho);
            #print_r($short) && die;
            $success_in_page = false; 
            $found_one = false; 
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt) || isset($skipped[$idd])) continue;
                
                $found_one = true;
                $ud = str_replace("/currency/$_c", "", $sl)."/go/{$idd}/".strtoupper($_c);
                $cap = solve::exec($sho, $host, $api);
                $po = array_merge($pa, $cap, ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
                
                $get = Net::X($ud, 'POST', $po, inf::$cookie, $headersCF, $sl, inf::$uagent, ip: $ip, foll: false);
                if ($get === 99) exit;
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
                
                if ($is_bl) break; 

                logx('info', "Bypass: $loc", true, true);
                $bakk = links($api, $loc);
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                styler("waiting for SL", fn() => _sle(60));
                $ver = Net::C($bakk, 'GET', null, inf::$cookie, $headersCF, $loc, inf::$uagent);
                if ($ver === 99) exit;
                
                if (!empty($ver)) {
                    $_suc = scraper::_jP($ver, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                    if (!empty($_suc[1][0])) {
                        logx('err', " {$_suc[2][0]} ", false, true);
                        logg(false, "{$_suc[3][0]}");

                        if ($_suc[1][0] === 'success') {
                            $success_in_page = true;
                            $curr = ""; 
                            break 2;
                        }
                        if (stripos($_suc[3][0], 'sufficient')) {
                            break;
                        }
                    }
                }
                break; 
            }

            if (!$found_one) {
                logx('err', 'sl abis kayaknya / sisa yang berat');
                $curr = "";
                $SLDONE = true;
                break; 
            }
        } while (!$success_in_page);
        
        if ($success_in_page || $curr === "") {
            break; 
        }
    }


}



tes:



function stfCF($api, $fa) {
    $res = execCF($api, $fa, inf::$cookie, inf::$uagent, []);
    #print_r($res);
    if (is_array($res) && isset($res['token'])) {
        logx('success', 'Cloudflare Solved!');
        $h = inf::netHead(['cf_clearance' => $res['token']]);

        inf::setup($res['ua'], inf::$cookie, inf::$ip);

        return [$h, $res['ua']];
    }
    
    return false;
}

function solveShield($fau) {
    $json = json_decode(Scraper::_jP($fau, '/var D=({.*?});/')[1][0] ?? '', true);
    
    if (!$json) return [];

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
