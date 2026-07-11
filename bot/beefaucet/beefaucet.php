<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = Config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

$userAgent = Config::uagent();
$cookieFile = Config::cookie($login);

(function ($login) {
    Proxy::load();
    Check::Geo();
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$login");
    $b->task2('ok', "site: https://beefaucet.org");
    
} ) ($login);

$ip = '162.213.248.69';
$r = '/?r=gamamoch@gmail.com';

$FAST_CLAIM = true; # <-- change true if u want to use single token for all sites, false to use single token per sites.

$sites = [
    'https://beefaucet.org' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4',
    'https://claimfreecoins.io' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4'
];

while (true) {

    if (empty($sites)) {
        @unlink(CREDIR.'/'.$GLOBALS['_CTX']['current_bot']);
        die(Logger::X('err', "ALL SITES REACHED LIMIT"));
    }
    
    if ($api instanceof Provider) $api->getInfo();
    
    $parsed_sites = [];
    foreach ($sites as $host => $key) {
        $domain = parse_url($host)['host'];
        
        $_coo = $cookieFile . '/' . $domain;
        if (!is_dir($_coo)) mkdir($_coo, 0755, true);
        
        $rett0 = 0;
        $parsed_urls = null;
        
        while (true) {
            $rett0++;
            $_0 = Net::C($host, 'GET', null, $_coo . '/main', [], '', $userAgent, ip: $ip, ins: true);
            
            if (!empty($_0) && $_0 !== 99) {
                $_u = Scraper::_xP($_0, "//div[contains(@class, 'dropdown-menu')]//a/@href");
                
                if (!empty($_u) && is_array($_u) && count($_u) > 0) {
                    $parsed_urls = $_u;
                    break;
                }
            }
            
            if ($rett0 >= 9) {
                unset($sites[$host]);
                continue 2;
            }
        }
        
        $parsed_sites[$host] = [
            'key' => $key,
            'domain' => $domain,
            'urls' => $parsed_urls,
            'cookie' => $_coo
        ];
    }
    
    if ($FAST_CLAIM) {
        $token = _tK('https://beefaucet.org', '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4', $api);
        if (empty($token) || $token === null) continue;
    }
    
    foreach ($parsed_sites as $host => $data) {
        $domain = $data['domain'];
        $_u = $data['urls'];
        $_coo = $data['cookie'];
        
        if (!$FAST_CLAIM) {
            $token = _tK($host, $data['key'], $api);
            if (empty($token) || $token === null) {
                unset($sites[$host]);
                continue;
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
                Logger::X('warn', "  Skip $u_nam: Empty page response");
                continue;
            }

            $forms = Scraper::payload($page);
            if (empty($forms)) {
                Logger::X('warn', "  Skip $u_nam: Form not found");
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
                    print(FGo['RED']."".str_pad($u_nam, 15) .RSET);
                    Logger::X('err', "  Empty response on blast", true, true);
                    continue;
                }

                $_suc = Scraper::_xP($res_html, "//div[contains(@class, 'alert')]");
                if (isset($_suc[0])) {
                    $msg = trim(str_replace('×', '', $_suc[0]));
                    $lowMsg = strtolower($msg);
                    
                    print(FGo['BLU'].str_pad($u_nam, 16) .RSET);
                    
                    if (stripos($lowMsg, 'sent')) {
                        Logger::X('ok', " ".$msg, true, true);
                    } else {
                        Logger::X('err', " ".$msg, true, true);
                        
                        if (stripos($msg, 'has been blacklisted')) die;
                        
                        if (stripos($lowMsg, 'claim limit') || stripos($lowMsg, 'sufficient') || stripos($lowMsg, 'safety')) {
                            $limitReached++;
                        }
                    }
                }
            }
            
            foreach ($prep_queue as $args) @unlink($args[3]);
            
            if ($limitReached >= $totalCoins) {
                Logger::X('err', "\n  [!] ".strtoupper($domain)." IS FULLY LIMITED", true, true);
                unset($sites[$host]); 
            }
        }
    }
    if (!empty($sites)) styler("waiting", fn() => _sle(30));
    
}

function _tK($rc_U, $rc_K, $api) {
    return solve::tkn($api, $rc_U, $rc_K, 'rc2')['done'] ?? null;
}