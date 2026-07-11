<?php

class faucetpayz {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://faucetpayz.com';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $limit = false;
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $skipped = [];
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
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0; 
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/account", [], '/register');
                
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
                        $cre = ['username' => $this->mail, 'password' => $this->pass];
                        
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                    
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::C($this->host.'/login', 'POST', $po, Inf::$cookie, [], $this->host.'/login', Inf::$uagent, ip: $this->ip);
                    
                    $msg_d = Scraper::_jP($ve, '/type:\s*["\']([^"\']+)["\'],\s*message:\s*["\']([^"\']+)["\']/s')[2][0] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'nvalid Captcha')) continue;
                        $this->logger('err', '', $msg_d, true);
                        
                    }
                    
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); die;
            
            if ($dash && str_contains($dash, 'confirm your email')) $this->can_withdraw = false;
            
            $setF = 0;
            if (!$this->limit && $this->claim) {
                $ret99 = 0; 
                while (true) {
                    $ret99++;
                    $fau = Net::C("{$this->host}/faucet", 'GET', null, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    
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
                        #var_dump($f);
                        
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
                        $cla = Net::C("{$this->host}/faucet", 'POST', $po, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent, ip: $this->ip);
                        
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        if (checkATB($this->atbfail, $cla)) continue;
                        
                        $mf = Scraper::_jP($cla, '/type:\s*["\']([^"\']+)["\'],\s*message:\s*["\']([^"\']+)["\']/s');
                        if (!empty($mf[2][0])) {
                            
                            $stt = $mf[1][0];
                            $msg = $mf[2][0];
                            
                            $this->logger($stt, 'fct', $msg);
                            
                            if (stripos($msg, 'has been added')) {
                                
                                $this->atbforce = false;
                                $this->atbfail = 0;
                                $setF = microtime(true);
                                break;
                                
                                
                            }
                            
                            if (stripos($msg, 'get back tomorrow')) {
                                $this->limit = true;
                                $this->claim = false;
                                break;
                            }
                            
                        }
                    }
                    
                }
            }
            
            $ads = Net::C("{$this->host}/surf", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, ip: $this->ip);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                #print_r($ptcList); die;
                
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
                                    $uid = Scraper::find($view, 'uid', key: 'id')[0] ?? null;
                                    $iid = Scraper::_pP($view, 'let id')[0] ?? null;
                                    preg_match("/let count = (\d+)/", $view, $cnt);
                                    
                                    if (isset($cnt[1])) $ad_t = (int)$cnt[1];
                                    
                                    $go = ['uid' => $uid,'c' => $iid.rand(1, 9999)];
                                    
                                    Net::C("{$this->host}/surf", 'GET', $go, Inf::$cookie, [], $ad_u, Inf::$uagent);
                                    
                                    $cap = Solve::exec($view, $ad_u, $this->api, $pa);
                                    
                                    if (isset($cap['trouble'])) continue;
                                    
                                    $po = array_merge($go, $cap);
                                    
                                }
                                
                            }
                            
                            if (!empty($po)) {
                                
                                $ma = json_decode(Net::X("{$this->host}/ajax/surf", 'POST', $po, Inf::$cookie, [], $ad_u, Inf::$uagent)?: '', 1)['message'] ?? null;
                                
                                if (!empty($ma)) {
                                    
                                    $this->logger('info', 'ptc', $ma);
                                    $endF = microtime(true);
                                    if ($setF > 0 && $this->claim) {
                                        $balik = $endF - $setF;
                                        if ($balik >= 4 * 60) continue 2;
                                    }
                                }
                                
                            }
                            
                        }
                    } else {
                        if (!empty($ptcList['bctt'])) {
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
                            die;
                            }
                            
                            
                        }
                    }
                    
                    
                }
                
            }
            
            if ($this->limit && $this->ADDONE) {
                
                $wd = Net::C("{$this->host}/account", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, ip: $this->ip);
                #_put('wd.html', $wd); die;
                $po = null;
                if (!empty($wd) && $wd !== 99) {
                    $pa = Scraper::payload($wd, 'makeWithdrawForm')[0]['payload'] ?? [];
                    $cre = ['address' => $this->mail];
                    $po = array_merge($pa, $cre);
                    
                }
                if ($po) {
                    $jjn = json_decode(Net::X("{$this->host}/ajax/withdraw", 'POST', $po, Inf::$cookie, [], "{$this->host}/account", Inf::$uagent)?: '', 1)["notify"] ?? null;
                    #var_dump($jjn); die;
                    if (!empty($jjn['success'])) $this->logger('info', ' wd  ', $jjn['success']);
                }
                
                
                
            }
            
            if (!$this->claim && $this->SLDONE && $this->ADDONE) $this->logger('ok', '', 'beres', 1);
            
        }
        
        
        
        
    }
    
    
    
    private function parsePtcAds($html) {
        
        if (empty($html) || $html === 99) {
            return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        }
        
        $xp = Scraper::dom($html);
        if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $host = str_replace('www.', '', parse_url($this->host, PHP_URL_HOST) ?: $this->host);
        $baseUrl = rtrim((parse_url($this->host, PHP_URL_SCHEME) ? $this->host : 'https://' . $this->host), '/');
        
        $urls = Scraper::_xP($html, "//a[contains(@href, '/surf/') and not(contains(@class, 'd-none'))]/@href");
        $timers = Scraper::_xP($html, "//div[contains(@class, 'pill sec')]");
        
        foreach ($urls as $i => $href) {
            if (strpos($href, 'http') !== 0 && strpos($href, '//') !== 0) $url = (strpos($href, '/') === 0) ? $baseUrl . $href : $baseUrl . '/' . $href;
            elseif (strpos($href, '//') === 0)  $url = 'https:' . $href;
            else $url = $href;
            
            $timer = 5;
            if (isset($timers[$i])) {
                $text = trim($timers[$i]);
                if (preg_match('/(\d+)\s*s/', $text, $tm)) $timer = (int)$tm[1];
            }
            
            $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
            
            if ($uHost === $host) $result['local'][] = [$url, $timer];
            elseif (strpos($url, 'bitcotasks.com') !== false) $result['bctt'][] = [$url, $timer];
            elseif (strpos($url, 'offerwall.me') !== false) $result['owme'][] = [$url, $timer];
            else $result['external'][] = [$url, $timer];
        }
        
        $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['external']);
        
        return $result;
    }
    
}

$BOTEXEC = new faucetpayz();
$BOTEXEC->exec();

