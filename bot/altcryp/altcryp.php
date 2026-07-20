<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://altcryp.com';
    private string $r = '/?r=45909';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential([], false, ['login', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['login'];
        
        Inf::setup(
            Config::uagent('mobile'),
            Config::cookie($this->mail),
            $this->ip,
            false, 
            $this->mail
        );
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', $this->mail);
        $b->task2('ok', "site: " . $this->host);
    }
    
    
    public function exec() {
        
        $habis = [];
        $curr = '';
        $skipped = [];
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            
            $ret = 0;
            do {
                $ret++;
                $l = Inf::check("{$this->host}", $this->headersCF, '/auth/login');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    logx('Info', "logged in", false); 
                    _sle(3); _clr();
                    #var_dump($dash); die;
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                
                $_0 = Net::X($this->host.$this->r, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    #var_dump($f); die;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['wallet' => $this->mail];
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                    
                    $msg_d = Scraper::_jP($ve, "/Swal\.fire\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'<]+)/i")[2][0] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'nvalid captcha')) continue;
                        #if (preg_match('/registered|success/i', $msg_d)) continue;
                        #$this->logger('err', '', $msg_d, 1);
                    }
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash);
            
            $_fa = Scraper::_xP($dash, "//ul[@id='faucet'][contains(@class,'submenu')]//a/@href");
            #print_r($_fa);
            
            $setF = 0;
            if ($this->claim) {
                static $afp = null;
                foreach ($_fa as $fa) {
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue 2;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::C($fa, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, d: true);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        
                        $fau = $this->checkCF($this->headersCF, $fa, $fau);
                        #_put('fau.html', $fau);
                        $po = null;
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau)[0] ?? null;
                            
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                
                                $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                                
                                if (isset($pa['fp_os_name'])) {
                                    if ($afp === null) {
                                        $afp = _altcryptoken(Inf::$uagent, $this->mail);
                                    }
                                    $capp = $afp;
                                }
                                if (isset($cap['trouble'])) continue;
                                
                                $po = array_merge($pa, $cap, $capp ?? []);
                                
                            } else {
                                if (stripos($fau, 'firewall')) {
                                    $ff = Scraper::payload($fau)[0] ?? [];
                                    
                                    if (!empty($ff)) {
                                        $cap = Solve::exec($fau, $host, $api, $ff['payload'] ?? []);
                                        $pp = array_merge($ff['payload'], $cap);
                                        Net::C($ff['url'], 'POST', $pp, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                                        continue;
                                    }
                                    
                                }
                                
                                if (str_contains($fau, 'claim limit')) {
                                    #_put('fau.html', $fau); 
                                    $habis[$fa] = true;
                                    break;
                                }
                            }
                            
                        }
                        
                        if (!empty($po)) {
                            _sle(7);
                            #print_r($po);
                            
                            $cla = Net::C($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                            
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            $mf = Scraper::_jP($cla, "/Swal\.fire\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'<]+)/i");
                            if (!empty($mf[2][0])) {
                                $stt = $mf[1][0];
                                $msg = $mf[2][0];
                                $this->logger($stt, 'fct', $msg);
                                
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                    $habis[$fa] = true;
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned|anti-fraud/i', $msg)) {
                                    die;
                                }
                                
                                if (stripos($msg, 'Shortlink')) {
                                    if ($this->SLDONE) die;
                                    $curr = $_c;
                                    break 2;
                                }
                                
                            }
                            styler("waiting for next claim", fn() => _sle(5));
                        }
                        
                    }
                    
                }
                
                
            }
            
            if (count($habis) === count($_fa)) $this->logger('ok', '', 'beres', 1);
            
            $ads = Net::C("{$this->host}/ptc", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, ip: $this->ip);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                #print_r($ptcList); die;
                
                if ($ptcNumb <= 1) {
                    $this->ADDONE = true;
                } else {
                    if (!empty($ptcList['local'])) {
                        
                    } 
                    
                    if (!empty($ptcList['bctt'])) {
                        #print_r($ptcList['bctt']); die;
                        foreach ($ptcList['bctt'] as $ptc) {
                            [$ad_u, $ad_t] = $ptc;
                            $bctt = new Bctt($this->host, $this->api, $this->mail);
                            $ch = $bctt->exec($ad_u, $ad_t);
                            if ($ch === 99) goto login;
                            if ($ch === 'forbidden') break;
                            $endF = microtime(true);
                            if ($setF > 0 && $this->claim) {
                                $balik = $endF - $setF;
                                if ($balik >= 4 * 60) continue 2;
                            }
                            
                        }
                    }
                    
                }
                
            }
            
            /*
            $wd = Net::C("{$this->host}/withdraw", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, ip: $this->ip);
            $_bal = Scraper::_xP($wd, "//div[contains(@class, 'balance-hero')]//h2/text()")[0] ?? '';
            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                
                $bal = (float) substr($_bal, 1);
                if ($bal >= 0.0001) {
                    $po = null;
                    $jjn = [];
                    $jjn = $this->_wd($wd);
                    
                    if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                        $pa = $jjn['payload'];
                        $cap = Solve::exec($wd, $this->host, $this->api, $pa);
                        
                        $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                        if (empty($pa[$walletKey])) $pa[$walletKey] = $this->mail;
                        
                        $po = array_merge($pa, $cap);
                        
                        
                        $this->logger('', "{$po[$walletKey]}", "tes ilmu: ".$jjn['info']['coin']);
                        $wdd = Net::X($jjn['url'], 'POST', $po, Inf::$cookie, [], "{$this->host}/withdraw", Inf::$uagent);
                        _put('wdd.html', $wdd);
                        
                        
                        
                    } else Logger::X('err', 'gak bisa wd kayaknya');
                }
                
            }
            */
            
        }
        
        
    }
    
    
    private function parsePtcAds($html) {
        
        if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
        
        $xp = Scraper::dom($html);
        if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
        
        $result = ['local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
        $host = str_replace('www.', '', parse_url($this->host, PHP_URL_HOST) ?: $this->host);
        $baseUrl = rtrim((parse_url($this->host, PHP_URL_SCHEME) ? $this->host : 'https://' . $this->host), '/');
        
        $cards = $xp->query("//div[contains(@class, 'card')][.//button[contains(@onclick, 'go_btn')]]");
        
        foreach ($cards as $card) {
            $btn = $xp->query(".//button/@onclick", $card);
            if ($btn->length === 0) continue;
            
            $onclick = $btn->item(0)->value;
            $url = '';
            if (preg_match("/go_btn\s*\(\s*'([^']+)'/", $onclick, $m)) $url = $m[1];
            
            if (empty($url)) continue;
            
            if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
            elseif (strpos($url, '//') === 0) $url = 'https:' . $url;
            
            $timer = 5;
            $timerEl = $xp->query(".//span[contains(@class, 'badge-custom')]//i[contains(@class, 'fa-clock')]/parent::span", $card);
            if ($timerEl->length === 0) $timerEl = $xp->query(".//span[contains(@class, 'badge-custom') and contains(text(), 'seconds')]", $card);
            if ($timerEl->length > 0 && preg_match('/(\d+)\s*seconds?/', trim($timerEl->item(0)->textContent), $tm)) $timer = (int)$tm[1];
            
            $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
            if ($uHost === $host) $result['local'][] = [$url, $timer];
            elseif (strpos($url, 'bitcotasks.com') !== false) $result['bctt'][] = [$url, $timer];
            elseif (strpos($url, 'offerwall.me') !== false) $result['owme'][] = [$url, $timer];
            elseif (strpos($url, 'offerzono.com') !== false) $result['zono'][] = [$url, $timer];
            else $result['external'][] = [$url, $timer];
        }
        
        $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['zono']) + count($result['external']);
        return $result;
        
    }
    
    private function _wd($html) {
        
        $res = Scraper::payload($html)[0] ?? null;
        if (!$res) return false;
        
        $balance = 0;
        $xp = Scraper::dom($html);
        if ($xp) {
            $nodes = $xp->query("//div[contains(@class, 'balance-hero')]//h2");
            if ($nodes->length > 0 && preg_match('/\$([\d.]+)/', trim($nodes->item(0)->textContent), $m)) $balance = (float)$m[1];
        }
        
        $cards = explode('class="coin-card"', $html);
        array_shift($cards);
        foreach ($cards as $card) {
            if (stripos($card, 'BTC') !== false || stripos($card, 'Bitcoin') !== false) continue;
            if (strpos($card, 'READY TO WITHDRAW') !== false) {
                preg_match('/name="method"\s+value="([^"]+)"/i', $card, $valMatch);
                $method = $valMatch[1] ?? null;
                
                if ($method) {
                    $res['payload']['method'] = $method;
                    $res['payload']['amount'] = $balance;
                    $res['url'] = "https://altcryp.com/withdraw/withdraw/" . $method;
                    preg_match('/<span class="coin-title">([^<]+)<\/span>/', $card, $nameMatch);
                    $coin = trim($nameMatch[1] ?? '');
                    
                    $res['info'] = [
                        'coin' => $coin,
                        'balance' => $balance,
                        'status' => 'READY TO WITHDRAW'
                    ];
                    return $res;
                }
            }
        }
        return false;
        
    }
    
})->exec();

function _altcryptoken($ua, $mail) {
    $os = 'Windows';
    if (str_contains($ua, 'Android')) $os = 'Android';
    elseif (str_contains($ua, 'Macintosh')) $os = 'MacOS';
    elseif (str_contains($ua, 'Linux') && !str_contains($ua, 'Android')) $os = 'Linux';
    
    $_B = 'Chrome';
    if (str_contains($ua, 'Firefox')) $_B = 'Firefox';
    elseif (str_contains($ua, 'Edg')) $_B = 'Edge';
    elseif (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) $_B = 'Safari';
    
    $_M = str_contains($ua, 'Android') || str_contains($ua, 'Mobile');
    
    $resos_desktop = ['1920x1080', '1366x768', '1536x864'];
    $resos_mobile = ['360x800', '412x915', '1080x2400'];
    $cores_desktop = ['4', '6', '8', '12', '16'];
    $cores_mobile = ['4', '6', '8'];
    
    $_reso = $_M ? $resos_mobile[array_rand($resos_mobile)] : $resos_desktop[array_rand($resos_desktop)];
    $_core = $_M ? $cores_mobile[array_rand($cores_mobile)] : $cores_desktop[array_rand($cores_desktop)];
    
    return [
        'fp_device_token' => 'FP_' . abs(crc32($ua . $mail)),
        'fp_os_name' => $os,
        'fp_browser_name' => $_B,
        'fp_screen_res' => $_reso,
        'fp_user_timezone' => TIMEZONE(),
        'fp_browser_lang' => LANGUAGE(),
        'fp_cpu_cores' => $_core,
        'fp_adblocker' => 'Disabled',
    ];
}
