<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://earnsolana.xyz';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $limit = false;
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $skipped = [];
    private array $headersCF = [];
    private bool $can_withdraw = false;
    private bool $atbforce = false;
    private int $atbfail = 0;
    
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], true, /*['mail', 'pass', 'PROXY']*/);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['mail'];
        $this->pass = $this->acc['pass'];
        
        Inf::setup(
            $this->acc['ua'],
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
            $madd = false;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", $this->headersCF, '/register', 1);
                
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
                $_0 = Net::X("{$this->host}/auth/login", 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, ip: $this->ip, d: 1);
                $_0 = $this->checkCF($this->headersCF, "{$this->host}/auth/login", $_0);
                
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
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.'/login', Inf::$uagent, ip: $this->ip);
                    
                    $msg_d = Scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]")[0] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'nvalid Captcha')) continue;
                        $this->logger('err', '', $msg_d, 1);
                        
                    }
                    
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); #die;
            
            if ($dash && str_contains($dash, 'confirm your email')) $this->can_withdraw = false;
            
            $_bal = Scraper::_xP($dash, "//div[contains(@class, 'card-body')]//strong[contains(text(), 'tokens')]/text()")[0] ?? '';
            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                $bal = ((int)$_bal);
                
                if ($this->can_withdraw && ($bal >= 2000)) {
                    $po = null;
                    $jjn = [];
                    $wd = Net::C("{$this->host}/withdraw", 'GET', null, Inf::$cookie, $this->headersCF, "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    
                    $jjn = Scraper::payload($wd)[0] ?? null;
                    if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                        $pa = $jjn['payload'];
                        $cap = Solve::exec($wd, $this->host, $this->api, $pa);
                        
                        $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                        if (empty($pa[$walletKey])) $pa[$walletKey] = $this->mail;
                        
                        $po = array_merge($pa, $cap);
                        
                        $wdd = Net::C($jjn['url'], 'POST', $po, Inf::$cookie, $this->headersCF, "{$this->host}/withdraw", Inf::$uagent, ip: $this->ip);
                        $mwd = Scraper::_jP($wdd, "/Swal\.fire\(\s*'[^']+'\s*,\s*'([^']+)'/");
                        if (isset($mwd[1][0])) {
                            $msg = $mwd[1][0];
                            $this->logger('ok', 'withdraw', $msg);
                        }
                        
                    } else Logger::X('err', 'gak bisa wd kayaknya');
                }
            }
            
            $setF = 0;
            if (!$this->limit && $this->claim) {
                $ret99 = 0; 
                while (true) {
                    $ret99++;
                    $fau = Net::C("{$this->host}/faucet", 'GET', null, Inf::$cookie, $this->headersCF, "{$this->host}/dashboard", Inf::$uagent, ip: $this->ip);
                    
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
                        #var_dump($f); die;
                        
                        if (!empty($f['payload'])) {
                            $this->esolCF($f['url'], "{$this->host}/faucet");
                            $pa = $f['payload'];
                            
                            $cap = Solve::exec($fau, $this->host, $this->api, $pa, $this->atbforce);
                            if (isset($cap['trouble'])) continue;
                            $po = array_merge($pa, $cap);
                        } else {
                            if (str_contains($fau, '/register')) continue 2;
                            
                            if (str_contains($fau, 'Daily limit reached')) {
                                $this->limit = true;
                                break;
                            }
                            /*
                            if (!$this->SLDONE || !$this->ADDONE) {
                                $setF = microtime(true);
                                break;
                            }
                            */
                            styler('Waiting for faucet', fn() => _sle(30));
                            continue;
                        }
                        
                    }
                    
                    if (!empty($po)) {
                        #print_r($po); die;
                        $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, "{$this->host}/faucet", Inf::$uagent, ip: $this->ip);
                        
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        if (checkATB($this->atbfail, $cla)) continue;
                        
                        $mf = Scraper::_xP($cla, "//div[contains(@class, 'alert-success')]");
                        
                        if (!empty($mf[0])) {
                            $msg = $mf[0];
                            $this->logger('ok', 'fct', $msg);
                            
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
            
            while(!$madd) {
                $po = null;
                
                $mad = Net::C("{$this->host}/madfaucet", 'GET', null, Inf::$cookie, $this->headersCF, "{$this->host}/faucet", Inf::$uagent, ip: $this->ip);
                if ($mad === 99) goto login;
                
                if (!empty($mad) && $mad !== 99) {
                    $f = Scraper::payload($mad)[0] ?? [];
                    #var_dump($f); die;
                    
                    $this->atbforce = $this->atbfail >= 3;
                    if (!empty($f['payload'])) {
                        $this->esolCF($f['url'], "{$this->host}/madfaucet");
                        $pa = $f['payload'];
                        
                        $cap = Solve::exec($mad, $this->host, $this->api, $pa, $this->atbforce);
                        if (isset($cap['trouble'])) continue;
                        $po = array_merge($pa, $cap);
                    } else {
                        if (str_contains($mad, 'Daily limit reached')) $madd = true;
                    }
                    
                }
                
                if (!empty($po)) {
                    $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, "{$this->host}/madfaucet", Inf::$uagent, ip: $this->ip);
                    
                    if (checkATB($this->atbfail, $cla)) continue;
                    
                    if (!empty($cla) && $cla !== 99) {
                        $msg = Scraper::_xP($cla, "//div[contains(@class, 'alert-success')]")[0] ?? null;
                        if ($msg) {
                            $this->atbforce = false;
                            $this->atbfail = 0;
                            $this->logger('ok', 'mad', $msg);
                        }
                        $endF = microtime(true);
                        if ($setF > 0 && $this->claim) {
                            $balik = $endF - $setF;
                            if ($balik >= 3 * 50) continue 2;
                        }
                        
                        styler("waiting for next claim", fn() => _sle(15));
                    }
                    
                    
                }
                
            } 
            
            
        }
        
        
        
    }
    
    private function esolCF($url, $reff) {
        $_0 = Net::C($url, 'GET', null, Inf::$cookie, $this->headersCF, $reff, Inf::$uagent, ip: $this->ip, d: 1);
        return $this->checkCF($this->headersCF, $url, $_0);
    }
    
    
})->exec();
