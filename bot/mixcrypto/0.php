<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $curr = ['/litecoin'];
    private array $coins = ['/litecoin', '/solana', ''];
    
    private string $host = 'https://mix-crypto.com';
    private string $r = '/?r=gamamoch@gmail.com';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private array $headersCF = [];
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], false, ['login', 'PROXY']);
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
        
        login:
            Proxy::load();
            Check::Geo();
        
        if ($this->api instanceof Provider) $this->api->getInfo();
            
        foreach ($this->curr as $coin) {
            
            while (true) {
                
                $faucetUrl = $this->host .$coin. $this->r;
                $fau = Net::X($faucetUrl, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                
                $po = null;
                if (!empty($fau) && $fau !== 99) {
                    #_put('fau.html', $fau);
                    
                    $f = Scraper::payload($fau)[0] ?? null;
                    #var_dump($f);
                    if (!empty($f)) {
                        $fU = (strpos($f['url'], 'http') === 0) ? $f['url'] : $this->host . $f['url'];
                        
                        $pa = $f['payload'];
                        $cap = [];
                        $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                        
                        if (isset($cap['trouble'])) continue;
                        
                        $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                        if (empty($pa[$walletKey])) $pa[$walletKey] = $this->mail;
                        $po = array_merge($pa, $cap);
                        
                    }
                    
                }
                
                if (!empty($po)) {
                    #var_dump($po);
                    
                    $cla = Net::X($fU, 'POST', $po, Inf::$cookie, $this->headersCF, $faucetUrl, Inf::$uagent);
                    #_put('cla.html', $cla);
                    
                    $_suc = Scraper::_xP($cla, "//div[contains(@class, 'alert')]");
                    if (isset($_suc[0])) {
                        $msg = trim(str_replace('×', '', $_suc[0]));
                        $lowMsg = strtolower($msg);
                        $stt = (stripos($lowMsg, 'sent') ? 'ok' : 'err');
                        
                        $curr = (string)$coin;
                        $this->logger($stt, str_pad($curr, 12), "$msg");
                        
                        if (stripos($msg, 'has been blacklisted')) die;
                        
                        if (stripos($lowMsg, 'claim limit') || stripos($lowMsg, 'sufficient') || stripos($lowMsg, 'safety')) break;
                        
                    }
                    
                }
                
                styler("waiting for next claim", fn() => _sle(30));
                
            }
            
            die;
        }
        
    }
    
    
    
    
    
    
    
    
    
})->exec();



