<?php
_die();
return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://vipcoinfaucet.com';
    private string $r = '/?r=4218';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = true;
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
                $l = Inf::check("{$this->host}/app/dashboard", $this->headersCF, '/auth/validation');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    logx('Info', "logged in", false); 
                    _sle(3); _clr();
                    #var_dump($dash); die;
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                @unlink(Inf::$cookie);
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                
                $_0 = Net::X($this->host.$this->r, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[1] ?? [0] ?? null;
                    
                    if (!empty($f) && !str_contains($f['url'], 'telegram')) {
                        $pa = $f['payload'];
                        $cre = ['wallet' => $this->mail, 'uid' => md5($this->mail), 'private_ip' => IP()];
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                    #_put('ve.html', $ve);
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash);
            
            $_fa = Scraper::_xP($dash, "//a[contains(@href, '/faucet/currency/')]/@href");
            #print_r($_fa);
            #if (empty($curr)) shuffle($_fa);
            $setF = 0;
            if ($this->claim) {
                foreach ($_fa as $fa) {
                    
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa]) || str_contains($fa, 'fey')) {
                        $curr = '';
                        continue 2;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::X($fa, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, d: true);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        
                        $fau = $this->checkCF($this->headersCF, $fa, $fau);
                        
                        $po = null;
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau, "faucetClaimForm")[0] ?? null;
                            #var_dump($f);
                            
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                
                                #$cap = $this->_cp($fau);
                                $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                            $po = array_merge($pa, $cap);
                                
                            } else {
                                if (str_contains($fau, '/auth/validation')) continue 3;
                            }
                            
                        }
                        
                        if (!empty($po)) {
                            #print_r($po); die;
                            $cla = Net::C($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                            #_put('cla.html', $cla); die;
                            
                            $mf = Scraper::_jP($cla, '/icon:\s*[\'"]([^\'"]+)[\'"]\s*,\s*title:\s*[\'"]([^\'"]+)[\'"]\s*,\s*text:\s*[\'"]([^\'"]+)[\'"]/s');
                            if (!empty($mf[1][0])) {
                                
                                $stt = $mf[1][0];
                                $msg = $mf[3][0];
                                $this->logger($stt, 'fct', $msg);
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                    $habis[$fa] = true;
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned/i', $msg)) {
                                    die;
                                }
                                
                                if (stripos($msg, 'nvalid Claim')) break;
                                
                                if (stripos($msg, 'Shortlink')) {
                                    if ($this->SLDONE) die;
                                    $curr = $_c;
                                    break 2;
                                }
                                
                            }
                            
                            styler("waiting for next claim", fn() => _sle(10));
                        }
                        
                    }
                }
            }
            
            if (count($habis) === count($_fa)) $this->logger('ok', '', 'beres', 1);
            
            
        }
        
        
    }
    
})->exec();