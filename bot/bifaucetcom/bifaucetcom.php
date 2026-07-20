<?php

class bifaucet {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://bifaucet.com';
    private string $ip = '159.198.47.130';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $limit = false;
    private bool $claim = true;
    private bool $SLDONE = false;
    private bool $ADDONE = false;
    private bool $BCDONE = false;
    private array $skipped = [];
    private array $headersCF = [];
    private bool $can_withdraw = true;
    
    private bool $atbforce = false;
    private int $atbfail = 0;
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
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
            
        while (true) {
            $dash = null;
            $ret = 0; 
            
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
                $_0 = Net::C("{$this->host}/login", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, false, false, $this->ip);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    #var_dump($f); die;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['email' => $this->mail, 'password' => $this->pass];
                        
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                    
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, [], $this->host.'/login', Inf::$uagent, ip: $this->ip);
                    
                    $msg_d = Scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]")[0] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'nvalid Captcha')) continue;
                        $this->logger('err', '', $msg_d, 1);
                        
                    }
                    
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); die;
            
            if ($dash && str_contains($dash, 'confirm your email')) $this->can_withdraw = false;
            
            $_bal = Scraper::_xP($dash, "//small[text()='Main Balance']/preceding-sibling::h6/text()")[0] ?? '';
            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                $bal = ((int)$_bal);
                
                if ($this->can_withdraw && ($bal >= 5000)) {
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
                        $mwd = scraper::_jP($wdd, "/Swal\.fire\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'<]+)/i");
                        
                        if (isset($mwd[2][0])) {
                            $msg = $mwd[2][0];
                            $this->logger('ok', 'withdraw', $msg);
                        }
                        
                    } else logx('err', 'gak bisa wd kayaknya');
                    
                    
                }
                
            }
            
            $setF = 0;
            if (!$this->limit && $this->claim) {
                $ret99 = 0; 
                while (true) {
                    $ret99++;
                    $fau = Net::C("{$this->host}/faucet", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    #_put('fau.html', $fau); 
                    if ($fau === 99) {
                        if ($ret99 >= 5) goto login;
                        _sle(40);
                        continue;
                    }
                    
                    $po = null;
                    $this->atbforce = $this->atbfail >= 3;
                    if (!empty($fau) && $fau !== 99) {
                        $ret99 = 0;
                        $f = Scraper::payload($fau)[0] ?? [];
                        
                        if (!empty($f['payload'])) {
                            $pa = $f['payload'];
                            
                            $cap = Solve::exec($fau, $this->host, $this->api, $pa, $this->atbforce);
                            if (isset($cap['trouble'])) continue;
                            $po = array_merge($pa, $cap);
                        } else {
                            if (str_contains($fau, '/register')) continue 2;
                            
                            if (!$this->SLDONE || !$this->ADDONE) {
                                $setF = microtime(true);
                                break;
                            }
                            
                            styler('Waiting for faucet', fn() => _sle(30));
                            continue;
                        }
                        
                    }
                    
                    if (!empty($po)) {
                        #print_r($po); die;
                        $cla = Net::C($f['url'], 'POST', $po, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent, ip: $this->ip);
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        if (checkATB($this->atbfail, $cla)) continue;
                        
                        $mf = scraper::_jP($cla, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                        if (!empty($mf[2][0])) {
                            $stt = $mf[3][0];
                            $msg = $mf[2][0];
                            
                            $this->logger($stt, 'fct', $msg);
                            
                            if (stripos($msg, 'has been added')) {
                                
                                $this->atbforce = false;
                                $this->atbfail = 0;
                                $setF = microtime(true);
                                break;
                                
                                
                            }
                            
                        }
                        
                    }
                    
                }
                
                
            }
            
            $ads = Net::C("{$this->host}/ptc", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                #print_r($ptcList); #die;
                
                if ($ptcNumb <= 1) {
                    $this->ADDONE = true;
                } else {
                    if (!empty($ptcList['local'])) {
                        foreach ($ptcList['local'] as $ptc) {
                            [$ad_u, $ad_t] = $ptc;
                            $cla = null;
                            $view = null;
                            
                            $view = Net::C($ad_u, 'GET', null, Inf::$cookie, [], "{$this->host}/ptc", Inf::$uagent, ip: $this->ip);
                            
                            $po = null;
                            if (!empty($view) && $view !== 99) {
                                _sle(1);
                                styler("waiting for ads: $ad_t", fn() => _sle($ad_t));
                                
                                $f = Scraper::payload($view)[0] ?? [];
                                if (!empty($f)) {
                                    $pa = $f['payload'];
                                    
                                    $cap = Solve::exec($view, $ad_u, $this->api, $pa);
                                    
                                    if (isset($cap['trouble'])) continue;
                                    
                                    $po = array_merge($pa, $cap);
                                    
                                }
                            }
                            
                            if (!empty($po)) {
                                #print_r($po); die;
                                $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, [], $ad_u, Inf::$uagent, ip: $this->ip);
                                
                                $ma = scraper::_jP($cla, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/")[2][0] ?? null;
                                
                                if (!empty($ma)) $this->logger('info', 'ptc', $ma);
                                
                                $endF = microtime(true);
                                if ($setF > 0 && $this->claim) {
                                    $balik = $endF - $setF;
                                    if ($balik >= 4 * 60) continue 2;
                                }
                                
                            }
                            
                        }
                        
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
                    if (empty($short) || stripos($sho, '/register')) break;
                    #print_r($short); die;
                    
                    $can_process = false; 
                    foreach ($short as $links => [$idd, $lmt]) {
                        if (!Shortlinks::limit($lmt) || isset($skipped[$idd])) continue;
                        $can_process = true;
                        
                        $ud = $this->host.'/links/go/'.$idd;
                        $loc = $this->parseShortL($ud, "{$this->host}/links");
                        
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
                                $msh = Scraper::_jP($ver, "/Swal\.fire\s*\(\s*'([^']+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*\)/");
                                
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
            
            $off_B = Net::C("{$this->host}/offerwall/bitcotasks", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
            $bctt_u = Scraper::_jP($off_B, '/<iframe[^>]*src=["\']([^"\']*bitcotask[^"\']*)["\'][^>]*>/i')[1][0] ?? null;
            
            if (!empty($bctt_u)) {
                $bctt = new Bctt($this->host, $this->api, $this->mail);
                $bcttwl = $bctt->wall($bctt_u);
                if (($bcttwl === 'claim') && $this->claim) $bcttwl->cleanup();
                if (($bcttwl === 'habis')) $this->BCDONE = true;
                
            }
            
            if ($this->SLDONE && $this->ADDONE && !$this->claim && $this->BCDONE) styler('cooldown', fn() => _sle(600));
            
            
        }
    }
    
    private function parseShortL($ud, $sl, $po = null) {
        
        $getLok = 0;
        while ($getLok <= 3) {
            $getLok++;
            $lok = Net::X($ud, 'GET', $po, Inf::$cookie, $this->headersCF, $sl, Inf::$uagent, foll: false);
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
        $baseUrl = rtrim((parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host), '/');
        
        $cards = $xp->query("//div[@id='local']//div[contains(@class, 'card')]");
        foreach ($cards as $card) {
            $btn = $xp->query(".//button[@onclick]", $card);
            if ($btn->length === 0) continue;
            
            $onclick = $btn->item(0)->getAttribute('onclick');
            if (!preg_match("/window\.location\s*=\s*'([^']+)'/", $onclick, $m)) continue;
            
            $url = $m[1];
            if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
                $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
            } elseif (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            }
            
            $timer = 5;
            $timerEl = $xp->query(".//div[contains(@class, 'fw-semibold') and contains(text(), 'sec')]", $card);
            if ($timerEl->length > 0 && preg_match('/(\d+)\s*sec/', $timerEl->item(0)->textContent, $tm)) {
                $timer = (int)$tm[1];
            }
            $result['local'][] = [$url, $timer];
        }
        
        $urls = Scraper::_xP($html, "//div[@id='bitcotasks']//div[contains(@class, 'card')]//div[contains(@class, 'mt-auto')]/a/@href");
        $tmrs = Scraper::_xP($html, "//div[@id='bitcotasks']//div[contains(@class, 'card')]//div[contains(@class, 'fw-semibold') and contains(text(), 'sec')]/text()");
        foreach ($urls as $i => $url) {
            $timer = 5;
            if (isset($tmrs[$i]) && preg_match('/(\d+)\s*sec/', trim($tmrs[$i]), $m)) {
                $timer = (int)$m[1];
            }
            $result['bctt'][] = [$url, $timer];
        }
        
        
        
        $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
        
        return $result;
    }
    
    private function _wd($html) {
        $res = Scraper::payload($html)[0] ?? null;
        if (!$res) return false;
    
        $fp_area = explode('data-group="cwallet"', $html)[0] ?? $html;
        
        $cards = explode('class="currency-card', $fp_area);
        array_shift($cards); 
    
        foreach ($cards as $card) {
            preg_match('/data-name="([^"]+)"/i', $card, $nameMatch);
            $name = $nameMatch[1] ?? '';
            
            if (stripos($name, 'btc') !== false || stripos($name, 'bitcoin') !== false) {
                continue;
            }
    
            preg_match('/aria-valuenow="(\d+)"/i', $card, $stockMatch);
            $stock = (int)($stockMatch[1] ?? 0);
    
            if ($stock >= 5) {
                preg_match('/name="method"\s+value="(\d+)"/i', $card, $valMatch);
                $value = $valMatch[1] ?? null;
    
                if ($value !== null) {
                    $res['payload']['method'] = $value;
                    
                    $res['info'] = [
                        'coin'  => $name,
                        'stock' => $stock . '%'
                    ];
                    
                    return $res;
                }
            }
        }
        
        return false;
    }
    
}

(new bifaucet())->exec();