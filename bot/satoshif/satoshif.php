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
$ip = null;
$ip = '173.249.41.150';

inf::setup($userAgent, $cookieFile, $ip);

banner();
login:

$_0 = Net::C($host, 'GET', null, inf::$cookie, ['detail-hints:false'], '', inf::$uagent, ip: $ip);
if (empty($_0)) goto login;

while (true) {
    $max = 7;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check($host.'/dashboard', ['detail-hints:false'], '/auth/login');
        #_put('l.html', $l['html']);
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break; 
        }
        if ($ret >= $max) {
            logx('err', 'gak tau');
            exit; 
        }
        @unlink(inf::$cookie);
        taskPrintCenter('logging in', 'err');
        
        $_0 = Net::C($host.$r, 'GET', null, inf::$cookie, ['detail-hints:false'], '', inf::$uagent, ip: $ip);
        if (empty($_0)) continue;
        
        #_put('0.html', $_0);
        $f = scraper::payload($_0)[0];
        $pa = $f['payload'];
        $cap = solve::exec($_0, 'https://'.$domain, $api);
        
        $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $login];
        $po = array_merge($pa, $cap, $cre);
        
        Net::C($host.'/auth/login', 'POST', $po, inf::$cookie, ['detail-hints:false'], $host.$r, inf::$uagent, ip: $ip, foll: false);
#die;
        _sle(5);
    } while (empty($dash));

    #_put('dash.html', $dash);

    $_fa = Scraper::_xP($dash, "//div[normalize-space()='Faucets']/ancestor::li//div[@class='sub-menu-two']/a/@href");
    
    foreach ($_fa as $fa) {
        $fa = str_replace('https://', 'http://', $fa);
        $_c = basename(parse_url($fa)['path']);
        
        print(FGd['CYN']." ".UNDR.$login.RSET."  ");
        logx('err', strtoupper($_c));
        
        while (true) {
            _sle(3);
            $fau = Net::C($fa, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);
            if (empty($fau)) continue;
            
            _put('fau.html', $fau);
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
                styler("waiting for CLAIM", fn() => _sle(10));
                continue;
            }
            #print_r($po);
            
            _sle(2);
            $ve = str_replace('https://', 'http://', $f['url']);
            Net::X($ve, 'POST', $po, $cookieFile, [], $fa, $userAgent, ip: $ip, foll: false);
            $cla = Net::X($fa, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);
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
                    $curr = $_c;
                    break 2;
                }
            }
            styler("waiting for CLAIM", fn() => _sle(10));
        }

    }
    
    $_sl = Scraper::_xP($dash, "//div[normalize-space()='Shortlinks']/parent::div/following-sibling::div[@class='sub-menu-two']/a/@href");

    $skipped = [];
    foreach ($_sl as $sl) {
        $sl = str_replace('https://', 'http://', $sl);
        $_c = basename($sl);
        
        if (trim(strtoupper($_c)) !== trim(strtoupper($curr))) {
            continue;
        }
        
        do {
            $sho = Net::C($sl, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);
            if (empty($sho)) {
                _sle(5); 
                continue;
            }
            
            if ($ban = isBan($sho)) {
                logx('err', " kena ban: " . $ban['ti'], false);
                logx('ok', " tunggu {$ban['tmr']}");
                styler("waiting for unlocked", fn() => _sle($ban['sleep']));
                continue; 
            }
            
            $f = scraper::payload($sho)[0] ?? [];
            if (empty($f)) {
                _sle(3);
                continue; 
            }
            
            $pa = $f['payload'];
            $short = sScraper::extract($sho);
            $success_in_page = false; 
            
            foreach ($short as $links => [$idd, $lmt]) {
                if (!limit($lmt)) continue;
                if (isset($skipped[$idd])) continue; 
                
                $go = str_replace("/currency/$_c", "", $sl);
                $ud = $go."/go/{$idd}/".strtoupper($_c);
                
                $cap = solve::exec($sho, 'https://'.$domain, $api);
                $cre = ['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()];
                $po = array_merge($pa, $cap, $cre);
                
                $get = Net::X($ud, 'POST', $po, $cookieFile, [], $sl, $userAgent, ip: $ip, foll: false);
                preg_match('/location\.href\s*=\s*["\']([^"\']+)["\']/', $get, $match);
                $loc = $match[1] ?? '';
                logx('info', $loc, true, true);
                
                if (!$loc) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                $bakk = links($api, $loc);
                if (!$bakk) {
                    $skipped[$idd] = true; 
                    break; 
                }
                
                styler("waiting for SL", fn() => _sle(60));
                $bak = preg_replace('/^https:/i', 'http:', $bakk);
                Net::C($bak, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip, foll: false);
                $baak = str_replace('/back/', '/verify/', $bak);
                
                $res_claim = Net::C($baak, 'GET', null, $cookieFile, [], $bakk, $userAgent, ip: $ip, foll: false);
                
                $ver = Net::C($sl, 'GET', null, $cookieFile, [], '', $userAgent, ip: $ip);
                _put('ver.html', $ver); 

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
                logx('info', "All links for $_c are limit.");
                $curr = ""; 
                break; 
            }
        } while (true);
    }



}








tes:







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
