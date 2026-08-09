<?php

return (new class {
    
    use Base, Mimic;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://cryptofuture.co.in';
    private string $r = '/index.php?ref=1349';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = false;
    private bool $ADDONE = false;
    private array $headersCF = [];
    private bool $can_withdraw = true;
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent()], false, ['login', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['login'];
        
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
                $l = Inf::check("{$this->host}/index.php", $this->headersCF, '/auth.php');
                
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
                        $cre = [ 'identifier' => $this->mail];
                        #$cap = $this->_cp($_0);
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($this->host.'/'.$f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                    #_put('ve.html', $ve); die;
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); #die;
            
            $_bal = Scraper::_xP($dash, "//span[@id='header-user-balance']/text()")[0] ?? '';
            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                $bal = str_replace(',', '', $_bal);
                
                if ($this->can_withdraw && ($bal >= 1000)) {
                    $po = null;
                    $jjn = [];
                    $wd = Net::C($this->host."/pages/load_withdraw.php", 'GET', null, Inf::$cookie, [], "{$this->host}/index.php", Inf::$uagent);
                    
                    $pa = ['currency' => 'LTC'];
                    $cap = Solve::exec($wd, $this->host, $this->api, $pa);
                    if (isset($cap['trouble'])) $this->can_withdraw = false;
                    
                    $po = array_merge($pa, $cap);
                    
                    $this->logger('', "", "tes ilmu: LTC");
                    $wdd = json_decode(Net::C("{$this->host}/actions/withdraw_request_ajax.php", 'POST', SolveUtils::webkitID($po, $bon), Inf::$cookie, ["Content-Type: multipart/form-data; boundary=$bon"], "{$this->host}/index.php", Inf::$uagent)?: '', 1);
                    
                    $mwd = $wdd['message'] ?? '';
                    if (!empty($mwd)) {
                        $this->logger('ok', 'withdraw', $mwd);
                    }
                    
                    
                    
                    
                }
                
            }
            
            $setF = 0;
            if ($this->claim) {
                $ret99 = 0; 
                while (true) {
                    $ret99++;
                    $fau = Net::C($this->host."/pages/load_faucet.php", 'GET', null, Inf::$cookie, [], "{$this->host}/index.php", Inf::$uagent);
                    #_put('fau.html', $fau);
                    if ($fau === 99) {
                        if ($ret99 >= 5) goto login;
                        _sle(40);
                        continue;
                    }
                    
                    $po = null;
                    $po = ['nocaptcha' => 1]; #default dulu
                    if (!empty($fau) && $fau !== 99) {
                        $ret99 = 0;
                        $f = Scraper::payload($fau)[0] ?? [];
                        #var_dump($f); die;
                        
                        if (!empty($f['payload'])) {
                            $pa = $f['payload'];
                            
                            $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                            if (isset($cap['trouble'])) continue;
                            
                            $po = array_merge($pa, $cap);
                            
                        }
                        
                        
                    }
                    
                    if (!empty($po)) {
                        #print_r($po); die;
                        $cla = json_decode(Net::C("{$this->host}/api/claim_faucet.php", 'POST', $po, Inf::$cookie, [], "{$this->host}/index.php", Inf::$uagent)?: '', 1);
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        if (!empty($cla)) {
                            #var_dump($cla);
                            
                            $stt = ($cla['status'] == 200) ? 'ok' : 'err';
                            
                            $msg = !empty($cla['reward']) ? (($cla['message'].' '.$cla['reward'].' '.$cla['currency'])) : $cla['message'] ?? 'unknown error';
                            
                            $this->logger($stt, 'fct', $msg);
                            
                            if (preg_match('/blacklist|flagged|banned|nti fraud/i', $msg)) die;
                            
                            if (stripos($msg, 'logged in') !== false) {
                                @unlink(Inf::$cookie);
                                continue 2;
                            }
                            
                            if ($cla['claims_today'] >= $cla['daily_limit']) {
                                $this->limit = true;
                                break;
                            }
                            
                        }
                        
                        styler("waiting for next claim", fn() => _sle(15));
                        
                    }
                    
                }
                
                
                
            }
            
            
        }
    }
    
    
    
    
    
    
    
    
    
    
})->exec();