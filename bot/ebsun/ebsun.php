<?php

return (new class {
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://earnbitsun.club';
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
    private bool $can_withdraw = true;
    
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
        $wall = true;
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0; 
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/api/account/tokens/Coins");
                
                if (!empty($l['html']) && str_contains($l['html'], 'balance')) $l['ok'] = 1;
                
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
                $_0 = Net::C("{$this->host}", 'GET', null, Inf::$cookie, [], '', Inf::$uagent, false, false, $this->ip);
                
                if (!empty($_0) && $_0 !== 99) {
                    
                    $cre = ['email' => $this->mail, 'password' => $this->pass];
                    $cap = $this->tkn();
                    if (isset($cap['trouble'])) continue;
                    
                    $po = array_merge($cap, $cre);
                    
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = json_decode(Net::X($this->host.'/api/auth/signin', 'POST', $po, Inf::$cookie, [], $this->host, Inf::$uagent, ip: $this->ip, json: 1)?: '', 1);
                    #var_dump($ve); die;
                    if (isset($ve['error'])) {
                        $msg_d = $ve['error'];
                        if (stripos($msg_d, 'token invalid')) continue;
                        $this->logger('err', '', $msg_d, 1);
                    }
                    
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); die;
            $akun = json_decode($dash, 1)['data'] ?? '';
            
            if ($dash && str_contains($dash, 'confirm your email')) $this->can_withdraw = false;
            
            if (isset($akun['balance'])) {
                $_bal = $akun['balance'];
                $this->logger('', "balance", "$_bal");
                $bal = ((int)$_bal);
                
            }
            
            $setF = 0;
            if (!$this->limit && $this->claim) {
                $ret99 = 0;
                while (true) {
                    $ret99++;
                    $fau = Net::C("{$this->host}/api/faucet", 'GET', null, Inf::$cookie, [], "{$this->host}/", Inf::$uagent, ip: $this->ip);
                    #_put('fau.html', $fau); 
                    if ($fau === 99) {
                        if ($ret99 >= 5) goto login;
                        _sle(40);
                        continue;
                    }
                    
                    $fauu = json_decode($fau, 1)['data'] ?? null;
                    $po = null;
                    if (!empty($fauu) && $fauu !== 99) {
                        
                        $c_end = strtotime($fauu['cycle_ended_at']);
                        $now = microtime(1);
                        if ($now >= $c_end) {
                            $cap = $this->tkn(); 
                            if (isset($cap['trouble'])) continue;
                            $po = $cap;
                            
                        } else {
                            $wait = $c_end - $now;
                            styler('Waiting for faucet', fn() => _sle((int)$wait));
                            
                        }
                        
                    } else {
                        
                        if (str_contains($fau, 'not found')) continue 2;
                        
                        
                    }
                    
                    if (!empty($po)) {
                        #print_r($po); die;
                        $cla = json_decode(Net::X($this->host.'/api/faucet', 'POST', $po, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent, ip: $this->ip, json: 1)?: '', 1)['data'] ?? null;
                        #var_dump($cla); #die;
                        
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        if (!empty($cla)) {
                            
                            $msg =  " claimed ".($cla['claimed_amount']. ' coins' ?? '') ?? 'unknown';
                            
                            $this->logger('ok', 'fct', $msg);
                            if (stripos($msg, 'claimed') && !$wall) {
                                $setF = microtime(1);
                                $endF = strtotime($cla['cycle_ended_at']);;
                                break;
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
            $off_T = Net::C("{$this->host}/surveys", 'GET', null, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent, ip: $this->ip);
            $scc = Scraper::_sC($off_T)['inline'] ?? null;
            if (!empty($scc)) {
                
                foreach ($scc as $i => $_sc) {
                    if (stripos($_sc, 'timewall') && str_contains($_sc, 'https://timewall.io')) {
                        
                        $cfg = Scraper::_jP($_sc, '/https?:\/\/[^"]*timewall[^"]*/')[0][0] ?? null;
                        
                        if (!empty($cfg)) break;
                        
                    }
                }
                
                if (!empty($cfg)) {
                    $url = Scraper::_jP($cfg, '/https?:\/\/[^"]*timewall[^"]*/')[0][0] ?? null;
                    
                    if ($url) {
                        $url = str_replace('\u0026', '&', trim($url, '"'));
                        $timw_I = stripslashes($url);
                    }
                }
                
                if ($timw_I) {
                    $tmwl = new Twall($this->host, $this->api, $this->mail);
                    $tmwwl = $tmwl->exec($timw_I, $setF, $endF);
                    if ($tmwwl == 'habis') $wall = true;
                }
                
            }
            
        }
        
        
        
    }
    
    
    
    private function tkn() {
        $cft = 'token';
        $cft = Solve::tkn($this->api, $this->host, '0x4AAAAAADXP0YCJj-kEWRBh', 'cft')['done'] ?? null;
        
        if ($cft) return ["captcha_token" => "turnstile:$cft"];
        return ['trouble' => 1];
        
    }
    
})->exec();