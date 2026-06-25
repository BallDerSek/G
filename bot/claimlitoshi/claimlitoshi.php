<?php
if (!defined('ROOT')) { die; }
#_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://claimlitoshi.top';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=38637&xpost=true';
$ip = '154.26.138.53';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, false, $login);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$login");
    $b->task2('ok', "site: $host");
    
} ) ($login, $ip, $host);

$hhh = inf::netHead(['uf' => md5($login), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
$headersCF = [];
$skipped = [];
$ADDONE = false;
$SLDONE = true;
$claim = true;
$curr = '';
$curr_id = '';
$habis = [];

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host/dashboard", $headersCF, '/auth/validation');
        #var_dump($l);

        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        if ($ret >= 10) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            
            if (!empty($_0)) _put(__DIR__.'/lo.html', $_0);
            if (isset($ve) && !empty($ve)) _put(__DIR__.'/ve.html', $ve);
            
            exit; 
        }
        
        logx('err', "logging in ", false); 
        _sle(3); _clr();
        @unlink(inf::$cookie);
        Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        $_0 = Net::X($host, 'GET', null, inf::$cookie, $headersCF, $host.$r, inf::$uagent);
        #_put('0.html', $_0); die;
        if ($_0 === 99) {
            logx('warn', "masalah proxy, warm up dulu");
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;
        
        $f = scraper::payload($_0)[0] ?? [];
        $po = null;
        $he = '';
        if (!empty($f)) {
            #print_r($f);
            
            $pa = $f['payload'];
            $cre = ['wallet' => $login];
            $cap = solve::exec($_0, $host, $api, $pa);
            if (isset($cap['trouble'])) {
                _sle(10);
                continue;
            }
            if (isset($cap['headers'])) {
                $po = array_merge($pa, $cap['solution'], $cre);
                $he = $cap['headers'];
            } else {
                
                $po = array_merge($pa, $cap, $cre);
                
                $he = 'x-server-hash: '.getHead($_0, $host)['headers'];
            }
            
        }
        
        if (!empty($po)) {
            
            $bo = '';
            $body = SolveUtils::webkitID($po, $bo);
            $head = [$he, "Content-Type: multipart/form-data; boundary=$bo"];
            
            $headers = array_merge($head, $hhh);
            
            $ve = json_decode(Net::X($f['url'], 'POST', $body, inf::$cookie, $headers, $host.$r, inf::$uagent)?: '', 1);
            #print_r($ve); die;
            
            if (!empty($ve) && isset($ve['msg'])) {
                
                $msg = strtolower(strip_tags($ve['msg']));
                $stt = $ve['status'];
                logx($stt, $msg, false, true);
                
                if (str_contains($msg,'banned') || str_contains($msg, 'blocked') || str_contains($msg, 'denied')) die;
                _clr();
                
            }
            
        }
        
    } while (empty($dash));
    #_put('dash.html', $dash);

    $_fa = [];
    $xpath = Scraper::dom($dash);
    $links = $xpath->query("//li[.//span[text()='Faucet']]//ul[@class='pc-submenu']//a");
    foreach ($links as $link) {
        $text = trim($link->textContent);
        if (!str_contains($text, '~ FP')) continue;
        $url = $link->getAttribute('href');
        if (preg_match('/Claim\s+([A-Z]+)\s+~/', $text, $m)) {
            $_fa[] = [
                'url' => $url,
                'coin' => $m[1]
            ];
        }
    }
    
    $setF = 0;
    foreach ($_fa as $data) {
        if (!$claim) break;
        $fa = $data['url'];
        $_c = $data['coin'];
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while ($claim) {
            $fau = null;
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent);
            
            #_put('fau.html', $fau); #die;
            
            if ($fau === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            if (isset($habis[$fa])) {
                $curr = '';
                continue 2;
            }
            
            $po = null;
            $cap = [];
            $he = '';
            $f = scraper::payload($fau)[0] ?? null;
            
            if (str_contains($fau, 'limit reached')) {
                $curr = '';
                $habis[$fa] = true;
                break;
            }
            
            if (!empty($f) && stripos($f['url'], 'faucet')) {
                #print_r($f); die;
                $pa = $f['payload'];
                $cap = Solve::exec($fau, $fa, $api, $pa);
                
                if (isset($cap['trouble'])) continue;
                if (isset($cap['headers'])) {
                    $po = array_merge($pa, $cap['solution']);
                    $he = $cap['headers'];
                } else {
                    $po = array_merge($pa, $cap);
                    $he = 'x-server-hash: '.getHead($fau, $host)['headers'];
                }
                
            } else {
                
                if (preg_match('/<b id="minute">(\d+)<\/b>:<b id="second">(\d+)<\/b>/', $fau, $m)) {
                    
                    styler("waiting for next claim", fn() => _sle((int)$m['2']));
                    continue;
                }
                
                
                continue 3;
            }
                
            if (!empty($po)) {
                #print_r($po);
                
                $bo = '';
                $body = SolveUtils::webkitID($po, $bo);
                $head = [$he, "Content-Type: multipart/form-data; boundary=$bo"];
                
                $cla = json_decode(Net::X($f['url'], 'POST', $body, inf::$cookie, array_merge($hhh, $head), $fa, inf::$uagent, foll: false)?: '', 1);
                #var_dump($cla);
                if (!empty($cla) && isset($cla['status'])) {
                    $stt = $cla['status'];
                    $msg = $cla['msg'] ?? 'unknown';
                    $is_ok = (stripos($stt, 'success') !== false);
                    logm($login);
                    
                    logx($is_ok ? 'ok' : 'err', "{$stt} ", false);
                    logg(false, $msg);
                    
                    if (stripos($msg, 'No Faucet EXP left') !== false) {
                        $curr_id = basename(parse_url($fa)['path']);
                        $curr = $_c;
                        $setF = microtime(true);
                        break 2;
                    }
                    
                    if (preg_match('/sufficient|could not be processed/i', $msg) || (stripos($msg, 'link your Cwallet') !== false)) {
                        $habis[$fa] = true;
                        break;
                    }
                    if (stripos($msg, 'Shortlink')) {
                        if ($SLDONE) (logx('err', 'Gada SL lagi') ?: die);
                        $curr = $_c;
                        break 2;
                    }
                    
                    if (stripos($msg, 'nvalid Claim') !== false) break;
                    
                    styler("waiting for next claim", fn() => _sle(27));
                } elseif (empty($cla)) $ret99++;
                
                
            }
            
        }
        
    }

    if (count($habis) === count($_fa)) {
        $claim = false;
        /*
        print(FGd['CYN'].maskEmail($login).RSET." ");
        (logx('err', 'gak bisa claim') ?: die);
        */
    }
    
    if (!empty($curr_id)) Net::X($host . '/account/change_currency','GET',['method' => $curr_id],inf::$cookie,$hhh,$host.'/ptc',inf::$uagent);
    $ads = Net::X($host.'/ptc', 'GET', null, inf::$cookie, $hhh, $host, inf::$uagent);
    #_put('ads.html', $ads);
    if (!empty($ads) && $ads !== 99) {
        $ptcList = parsePtcAds($ads ,$host);
        $ptcNumb = $ptcList['total'];
        #print_r($ptcList); #die;
        
        if ($ptcNumb == 0) {
            $ADDONE = true;
        } else {
            #print_r($ptcList); #die;
            
            if (!empty($ptcList['local'])) {
                foreach ($ptcList['local'] as $ptc) {
                    #print_r($ptc);
                    [$ad_u, $ad_t] = $ptc;
                    
                    $view = Net::C($ad_u, 'GET', null, inf::$cookie, [], $host, inf::$uagent);
                    if ($view === 99) goto login;
                    $data = null;
                    $f = scraper::payload($view)[0] ?? [];
                    
                    if (!empty($f)) {
                        styler("waiting for ads", fn() => _sle($ad_t));
                        $he = '';
                        $pa = $f['payload'];
                        $sol = Solve::exec($ads, $host, $api, $pa);
                        if (isset($sol['trouble'])) goto login;
                        
                        if (isset($sol['headers'])) {
                            $data = array_merge($pa, $sol['solution']);
                            $he = $sol['headers'];
                        } else {
                            $data = array_merge($sol, $pa);
                            $he = 'x-server-hash: '.getHead($ads, $host)['headers'];
                        }
                        
                    }
                    
                    if (!empty($data)) {
                        postPTC(
                            SolveUtils::webkitID($data, $bo),
                            $f['url'],
                            [$he, "Content-Type: multipart/form-data; boundary=$bo"]
                        );
                    }
                    
                    #die;
                }
                
            }
            
            if (!empty($ptcList['external'])) {
                #print_r($ptcList['external']);
                foreach ($ptcList['external'] as $ptc) {
                    #print_r($ptc);
                    [$ad_u, $ad_t] = $ptc;
                    styler("waiting for ads", fn() => _sle($ad_t));
                    $data = null;
                    $f = scraper::payload($ads, 'submit_form')[0] ?? [];
                    
                    if ($f) {
                        $he = '';
                        $pa = $f['payload'];
                        $sol = Solve::exec($ads, $host, $api, $pa);
                        if (isset($sol['trouble'])) goto login;
                        
                        if (isset($sol['headers'])) {
                            $data = array_merge($pa, $sol['solution']);
                            $he = $sol['headers'];
                        } else $data = array_merge($sol, $pa);
                        
                    }
                    
                    if (!empty($data)) postPTC($data, $host.'/ptc/verify/'.$ad_u, [$he], true);
                    
                }
                
                
                
            }
            
            if (!empty($ptcList['bctt'])) {
                #print_r($ptcList['bctt']);
                foreach ($ptcList['bctt'] as $ptc) {
                    [$ad_u, $ad_t] = $ptc;
                    $bctt = new Bctt($host, $api, $login);
                    $ch = $bctt->exec($ad_u, $ad_t);
                    if ($ch === 99) goto login;
                    
                    $endF = microtime(true);
                    if ($setF > 0 && $claim) {
                        $balik = $endF - $setF;
                        if ($balik >= 10 * 60) continue 2;
                    }
                    
                }
            }
            
        }
        
    }
    
    if (!$claim && $ADDONE && $SLDONE) die;
    
}





tes:






















function parsePtcAds($html, $host) {
    if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $xp = Scraper::dom($html);
    if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    
    $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
    $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
    $baseUrl = rtrim((parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host), '/');
    
    $onclicks = Scraper::_xP($html, "//button[@onclick]/@onclick");
    $values = Scraper::_xP($html, "//button[@onclick]/@value");
    $timers = Scraper::_xP($html, "//span[contains(@class, 'bg-light-warning')]");
    
    foreach ($onclicks as $i => $onclick) {
        $url = '';
        $timer = 5;
        $adId = '';
        
        // startview: URL dari value, timer dari parameter ke-2, adId dari parameter ke-3
        if (preg_match("/startview\s*\(\s*this\.value\s*,\s*(\d+)\s*,\s*(\d+)/", $onclick, $m)) {
            $url = $values[$i] ?? '';
            $timer = (int)$m[1];
            $adId = (int)$m[2];
        }
        // go_btn dengan URL langsung di parameter
        elseif (preg_match("/go_btn\s*\(\s*'([^']+)'/", $onclick, $m)) {
            $url = $m[1];
        }
        // go_btn dengan this.value
        elseif (strpos($onclick, 'go_btn') !== false && strpos($onclick, 'this.value') !== false) {
            $url = $values[$i] ?? '';
        }
        
        if (empty($url)) continue;
        
        if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
            $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
        } elseif (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        
        // Ambil timer dari badge kalau belum dapet
        if ($timer === 5 && isset($timers[$i]) && preg_match('/(\d+)\s*S/i', $timers[$i], $tm)) {
            $timer = (int)$tm[1];
        }
        
        $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
        
        if ($uHost === $host) {
            $result['local'][] = [$url, $timer];
        }
        elseif (strpos($url, 'bitcotasks.com') !== false) {
            $result['bctt'][] = [$url, $timer];
        }
        elseif (strpos($url, 'offerwall.me') !== false) {
            $result['owme'][] = [$url, $timer];
        }
        else {
            // External: return [adId, timer]
            $result['external'][] = [$adId, $timer];
        }
    }
    
    $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
    
    return $result;
}

function getHead($html, $host) {
    $scJs = array_merge(Scraper::_sC($html)['external'], Scraper::_sC($html)['inline']);
    $param['extra']['js'] = $scJs;
    $param['mods'] = 'upside_captcha';
    
    $uCap = array_merge(inf::$context, ['html' => $html, 'host' => $host]);
    return (new uCaptcha($uCap))->exec($param, true);
}

function postPTC($data, $url, $head, $un = false) {
    
    $ver = Net::X($url, 'POST', $data, inf::$cookie, $head, '', inf::$uagent);
    #_put('ver.html', $ver);
    
    if (strpos($ver, 'have been credited') !== false) {
        if (preg_match('/message:\s*"([^"]+ credited to your Faucetpay account)"/', $ver, $m)) logg(true, $m[1]);
        return true;
    }
    
    return false;
    
}
