<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://vipfaucet.de';
    private string $r = '';
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
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $zer_u = null;
            $ret = 0;
            
            do {
                $ret++;
                @unlink(Inf::$cookie);
                $_0 = Net::C($this->host, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent);
                #var_dump($_0); die;
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    
                    $po = array_merge($f['payload'], ['user' => $this->mail]);
                    $zer_u = $f['url'].'?'.http_build_query($po);
                }
                
                
                
            } while (empty($zer_u));
            
            if (!empty($zer_u)) {
                $setF = microtime(true);
                $zera = new Zera($this->host, $this->api, $this->mail);
                $zerads = $zera->exec($zer_u);
                if (($zerads === 'claim') && $this->claim) $zera->cleanup();
            }
            
        }
        
    }
    
    
})->exec();