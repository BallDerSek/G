<?php
if (!defined('ROOT')) { die; }

$api = onKeys();
$login = _rl('email/address: ');

$userAgent = config::uagent();
$cookieFile = config::cookie($login);

$ip = '162.213.248.69';
$r = '/?r=gamamoch@gmail.com';

$sites = [
    'https://beefaucet.org' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4',
    'https://claimfreecoins.io' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4'
];

banner();
logx('info', 'using: '.$login, true, true);

while (true) {

    if (empty($sites)) {
        logx('err', "\nALL SITES REACHED LIMIT", true, true);
        exit;
    }
    
    print(DIMM.BOLD.ITAL.FGo['MAG']."solving  ".RSET);
    $main_host = array_key_first($sites);
    $main_key = $sites[$main_host];
    
    $token = solve::tkn($api, $main_host, $main_key, 'rc2');

    foreach ($sites as $host => $key) {
        $domain = parse_url($host)['host'];
        logx('info', 'SITE: ', false, false);
        logx('info', $domain, true, true);
        
        $_coo = $cookieFile . '/' . $domain;
        if (!is_dir($_coo)) mkdir($_coo, 0755, true);
        
        while (true) {
            $_0 = Net::C($host, 'GET', null, $_coo . '/main', [], '', $userAgent, ip: $ip, ins: true);
            if (!empty($_0)) {
                $_u = scraper::_xP($_0, "//div[contains(@class, 'dropdown-menu')]//a/@href");
                break;
            }
        }

        $prep_queue = [];
        foreach ($_u as $f_u) {
            $u_nam = basename(parse_url($f_u)['path']);
            $u_coo = $_coo . '/' . $u_nam;
            
            $_url = (strpos($f_u, 'http') === 0) ? $f_u : $host . $f_u;
            $_url .= $r;

            if (file_exists($u_coo)) @unlink($u_coo); 
            
            $prep_queue[$_url] = [$_url, 'GET', null, $u_coo, [], $host, $userAgent, $ip];
        }

        $pages = styler("Preparing " . count($prep_queue) . " coins", function() use ($prep_queue) {
            return Mux::C(...array_values($prep_queue));
        });

        $multi_calls = [];
        $coin_map = [];
        $idx = 0;

        foreach ($prep_queue as $_url => $args) {
            $page = $pages[$idx++] ?? null;
            $u_nam = basename(parse_url($_url)['path']);

            if (empty($page)) {
                logx('warn', "  Skip $u_nam: Empty page response");
                continue;
            }

            $forms = scraper::payload($page);
            if (empty($forms)) {
                logx('warn', "  Skip $u_nam: Form not found");
                continue;
            }

            $f = $forms[0];
            $pa = $f['payload'];
            $pa['address'] = $login;
            $pa['g-recaptcha-response'] = $token;
            $post_url = (strpos($f['url'], 'http') === 0) ? $f['url'] : $host . $f['url'];

            $multi_calls[] = [$post_url, 'POST', $pa, $args[3], [], $_url, $userAgent, $ip];
            $coin_map[] = $u_nam;
        }

        if (!empty($multi_calls)) {
            $results = styler("Claiming " . count($multi_calls) . " coins", function() use ($multi_calls) {
                return Mux::C(...$multi_calls);
            });

            $totalCoins = count($results);
            $limitReached = 0;

            foreach ($results as $i => $res_html) {
                $u_nam = $coin_map[$i] ?? 'unknown';
                
                if (empty($res_html)) {
                    print(FGo['RED']."  ".str_pad($u_nam, 15) .RSET);
                    logx('err', "  Empty response on blast", true, true);
                    continue;
                }

                $_suc = scraper::_xP($res_html, "//div[contains(@class, 'alert')]");
                if (isset($_suc[0])) {
                    $msg = trim(str_replace('×', '', $_suc[0]));
                    $lowMsg = strtolower($msg);
                    
                    print(FGo['BLU']."  ".str_pad($u_nam, 15) .RSET);
                    
                    if (stripos($lowMsg, 'sent')) {
                        logx('ok', " ".$msg, true, true);
                    } else {
                        logx('warn', " ".$msg, true, true);
                        
                        if (stripos($lowMsg, 'claim limit') || stripos($lowMsg, 'sufficient') || stripos($lowMsg, 'safety')) {
                            $limitReached++;
                        }
                    }
                }
            }
            
            foreach ($prep_queue as $args) {
                @unlink($args[3]);
            }
            
            if ($limitReached >= $totalCoins) {
                logx('err', "\n  [!] ".strtoupper($domain)." IS FULLY LIMITED", true, true);
                unset($sites[$host]); 
            }
        }
    }
    
    if (!empty($sites)) {
        styler("waiting", fn() => _sle(30));
    }
}
