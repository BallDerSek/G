<?php

return (new class {
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    
    private string $host = 'https://claimpepe.xyz';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $limit = false;
    private bool $claim = true;
    private bool $SLDONE = false;
    private bool $ADDONE = false;
    private bool $BCDONE = false;
    private array $skipped = [];
    private array $headersCF = [];
    private bool $can_withdraw = false;
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        // Load tài khoản từ cấu hình mặc định của tool (không dùng mail.json)
        $this->acc = Config::credential([], false, ['mail', 'pass', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['mail'];
        $this->pass = $this->acc['pass'];
        
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
        $skipped = [];
        
        login:
            Proxy::load();
            Check::Geo();
        
        // Vòng lặp vô hạn cho 1 tài khoản duy nhất
        while (true) {
            $dash = null;
            $ret = 0; 
            
            // --- QUÁ TRÌNH ĐĂNG NHẬP ---
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", [], '/register');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    Logger::X('Info', "logged in", false); 
                    _sle(3); _clr();
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                
                $_0 = Net::C("{$this->host}/auth/login", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, false, false, $this->ip);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        // Sửa trường email thành login_email cho claimpepe
                        $cre = ['login_email' => $this->mail, 'password' => $this->pass];
                        
                        // Vẫn gọi Solve::exec phòng trường hợp có IconCaptcha
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                    }
                }
                
                if ($po) {
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, [], $this->host.'/auth/login', Inf::$uagent, ip: $this->ip);
                    
                    $msg_d = Scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]")[0] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'nvalid Captcha') !== false) continue;
                        $this->logger('err', '', $msg_d, 1);
                    }
                }
                
            } while (empty($dash));
            
            if ($dash && str_contains($dash, 'confirm your email')) $this->can_withdraw = false;
            
            // Lấy số dư
            $_bal = Scraper::_xP($dash, "//div[contains(@class, 'topStat_card')]//p[contains(text(), 'Coins')]/text()")[0] ?? '';
            if (!$_bal) {
                // Fallback regex cho Claimpepe
                $_bal = Scraper::_jP($dash, '/Balance.*?>([0-9,.]+)<\/.*?Coins/is')[1][0] ?? '';
            }

            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                $bal = ((int)$_bal);
                
                // Rút tiền
                if ($this->can_withdraw && ($bal >= 10000)) {
                    $po = null;
                    $jjn = [];
                    $wd = Net::C("{$this->host}/withdraw", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    $jjn = $this->_wd($wd);
                    
                    if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                        $pa = $jjn['payload'];
                        $cap = Solve::exec($wd, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) $this->can_withdraw = false;
                        
                        $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                        if (empty($pa[$walletKey])) $pa[$walletKey] = $this->mail;
                        
                        $po = array_merge($pa, $cap);
                        
                        $this->logger('', "", "tes ilmu: ".$jjn['info']['coin']);
                        $wdd = Net::C($jjn['url'], 'POST', $po, Inf::$cookie, [], "{$this->host}/withdraw", Inf::$uagent, ip: $this->ip);
                        $mwd = Scraper::_jP($wdd, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                        if (isset($mwd[2][0])) {
                            $msg = $mwd[2][0];
                            $this->logger('ok', 'withdraw', $msg);
                        }
                        
                    } else Logger::X('err', 'gak bisa wd kayaknya');
                }
            }
            
            // --- QUÁ TRÌNH CLAIM FAUCET ---
            $setF = 0;
            if (!$this->limit && $this->claim) {
                $ret99 = 0; 
                while (true) {
                    Inf::injectCookie(Inf::$cookie, '0', $this->host, 'faucet_link_url_cookie');
                    
                    $ret99++;
                    $fau = Net::C("{$this->host}/faucet", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    
                    if ($fau === 99) {
                        if ($ret99 >= 5) goto login;
                        _sle(40);
                        continue;
                    }
                    
                    $po = null;
                    if (!empty($fau) && $fau !== 99) {
                        $ret99 = 0;
                        $f = Scraper::payload($fau)[0] ?? [];
                        
                        if (!empty($f['payload'])) {
                            $pa = $f['payload'];
                            
                            // Bỏ hoàn toàn logic Captcha vì Claimpepe không có captcha
                            $po = $pa;
                            
                        } else {
                            if (str_contains($fau, '/register')) continue 2;
                            
                            if (str_contains($fau, 'Daily limit reached')) {
                                $this->logger('err', 'fct', 'Daily limit reached');
                                $this->claim = false;
                                break;
                            }
                            
                            styler('Waiting for faucet', fn() => _sle(15));
                            continue;
                        }
                    }
                    
                    if (!empty($po)) {
                        $cla = Net::C($f['url'], 'POST', $po, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent, ip: $this->ip);
                        
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        $this->checkATB($this->atbfail, $cla);
                        
                        $mf = Scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s")[2][0] ?? Scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]")[0] ?? null;
                        
                        if (!empty($mf)) {
                            $msg = $mf;
                            $stt = (stripos($msg, 'has been added') ? 'ok' : 'err');
                            
                            $this->logger($stt, 'fct', $msg);
                            if (stripos($msg, 'has been added')) {
                                $setF = microtime(true);
                                break;
                            }
                        }
                    }
                }
            }
            
            // PTC
            $ads = Net::C("{$this->host}/ptc", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                
                if ($ptcNumb <= 1) {
                    $this->ADDONE = true;
                } else {
                    if (!empty($ptcList['local'])) {
                        foreach ($ptcList['local'] as $ptc) {
                            break;
                            [$ad_u, $ad_t] = $ptc;
                            $cla = null;
                            $view = null;
                            
                            $view = Net::C($ad_u, 'GET', null, Inf::$cookie, [], "{$this->host}/ptc", Inf::$uagent, ip: $this->ip);
                            
                            $po = null;
                            if (!empty($view) && $view !== 99) {
                                styler("waiting for ads: $ad_t", fn() => _sle($ad_t));
                                $f = Scraper::payload($view)[0] ?? [];
                                
                                if (!empty($f)) {
                                    $paa = $f['payload'];
                                    $cap = Solve::exec($view, $ad_u, $this->api, $paa);
                                    if (isset($cap['trouble'])) continue;
                                    $po = array_merge($paa, $cap);
                                }
                            }
                            
                            if (!empty($po)) {
                                $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, [], $ad_u, Inf::$uagent, ip: $this->ip);
                                $ma = Scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s")[2][0] ?? null;
                                if (!empty($ma)) $this->logger('info', 'ptc', $ma);
                                
                                $endF = microtime(true);
                                if ($setF > 0 && $this->claim) {
                                    $balik = $endF - $setF;
                                    if ($balik >= 2 * 60) continue 2;
                                }
                            }
                        }
                    }
                }
            }
            
            // Shortlink
            if (!$this->SLDONE) {
                $ret99 = 0;
                $up = ['earnow','shortano', 'shortino', 'fc-lc'];
                
                do {
                    $ret99++;
                    $sho = Net::C("{$this->host}/links", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    if ($sho === 99) {
                        if ($ret99 >= 5) goto login;
                        continue;
                    }
                    $ret99 = 0;
                    
                    $short = Shortlinks::extract($sho);
                    if (empty($short)) $this->SLDONE = true;
                    
                    $f = Scraper::payload($sho)[0] ?? [];
                    
                    if (!empty($f)) {
                        $po = $f['payload'];
                    } 
                    
                    $can_process = false; 
                    foreach ($short as $links => [$idd, $lmt]) {
                        if (!Shortlinks::limit($lmt) || isset($skipped[$idd])) continue;
                        $can_process = true;
                        
                        $ud = $this->host.'/links/go/'.$idd;
                        $loc = $this->parseShortL($ud, "{$this->host}/links", $po);
                        
                        if (!$loc) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        $loc_u = parse_url($loc)['host'];
                        $is_bl = false;
                        foreach ($up as $blacklisted) {
                            if (str_contains($loc_u, $blacklisted)) {
                                Logger::X('warn', "Domain $blacklisted Skipping..");
                                $skipped[$idd] = true;
                                $is_bl = true;
                                break; 
                            }
                        }
                        if ($is_bl) continue;
                        
                        $start = microtime(true);
                        $bakk = Shortlinks::exec($this->api, $loc);
                        $wait = 130 - (int)(microtime(true) - $start);
                        
                        if (!$bakk) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                        
                        $retVer = 0;
                        while ($retVer <= 3) {
                            $retVer++;
                            $ver = Net::C($bakk, 'GET', null, Inf::$cookie, [], $loc, Inf::$uagent);
                            
                            if (!empty($ver) && $ver !== 99) {
                                $msh = Scraper::_jP($ver, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
                                if (!empty($msh[2][0])) {
                                    $msg = $msh[2][0];
                                    $this->logger('ok', 'sho', $msg);
                                }
                                break 3;
                            }
                        }
                    }
                    
                    if (!$can_process) {
                        $this->logger('err', 'sho', 'SL habis atau sisa blacklist');
                        $this->SLDONE = true;
                    }
                    
                } while (!$this->SLDONE);
            }
            
            // Offerwall
            $off_B = Net::C("{$this->host}/offerwall/bitcotasks", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
            $bctt_u = Scraper::_jP($off_B, '/<iframe[^>]*src=["\']([^"\']*bitcotask[^"\']*)["\'][^>]*>/i')[1][0] ?? null;
            
            if (!empty($bctt_u)) {
                $bctt = new Bctt($this->host, $this->api, $this->mail);
                $bcttwl = $bctt->wall($bctt_u);
                if (($bcttwl === 'claim') && $this->claim) $bcttwl->cleanup();
                if (($bcttwl === 'habis')) $this->BCDONE = true;
            }
            
            if ($this->SLDONE && $this->ADDONE && !$this->claim && $this->BCDONE) {
                styler('cooldown', fn() => _sle(600));
            }
        }
    }
    
    private function parseShortL($ud, $sl, $po = null) {
        $getLok = 0;
        while ($getLok <= 3) {
            $getLok++;
            $lok = Net::X($ud, 'POST', $po, Inf::$cookie, $this->headersCF, $sl, Inf::$uagent);
            if (!empty($lok) && $lok !== 99) {
                return Scraper::_pP($lok, 'location.href')[0] ?? null;
            }
        }
        return null;
    }
    
    private function parsePtcAds($html) {
        $host = $this->host;
        if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $xp = Scraper::dom($html);
        if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
        $baseUrl = (parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host);
        $baseUrl = rtrim($baseUrl, '/');
        
        foreach ($xp->query("//div[contains(@class,'ptc_cards')]") as $card) {
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
            
            $timer = 5;
            $spans = $xp->query(".//span", $card);
            foreach ($spans as $span) {
                $text = trim($span->textContent);
                if (preg_match('/(\d+)\s*s/', $text, $tm)) {
                    $timer = (int)$tm[1];
                    break;
                }
            }
            
            $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
            
            if ($uHost === $host) $result['local'][] = [$url, $timer];
            elseif (strpos($url, 'bitcotasks.com') !== false) $result['bctt'][] = [$url, $timer];
            else $result['external'][] = [$url, $timer];
        }
        
        $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
        return $result;
    }
    
    private function _wd($html) {
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
    
})->exec();