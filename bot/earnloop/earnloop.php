<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://earnloop.online';
    private string $r = '';
    private string $ip = '';
    private string $domain;
    
    private string $mail;
    
    private bool $claim = true;
    private array $headersCF = [];
    
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
            $fau = null;
            $ret = 0;
            
            do {
                $ret++;
                @unlink(Inf::$cookie);
                $_0 = Net::C($this->host, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent);
                
                #_put('0.html', $_0); die;
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                if (!empty($_0) && $_0 !== 99) {
                    $meta = Scraper::_xP($_0, "//span[@class='panel__meta']/text()")[0] ?? '';
                    preg_match('/(\d+)/', (trim($meta)?:''), $m);
                    $jumlah = (int)($m[1] ?? 0);
                    
                    if ($jumlah <= 0) $this->logger('err', "can't claim", 'no faucet available', true);
                    else $_fa = Scraper::_xP($_0, "//ul[contains(@class,'coin-grid')]//a[contains(@class,'coin-card')]/@href");
                    
                }
                
            } while (empty($_fa));
            
            #var_dump($_fa);
            if ($this->claim) {
                
                foreach ($_fa as $fa) {
                    $fa = rtrim($this->host, '/') . '/' . ltrim($fa, '/');
                    $_c = basename(parse_url($fa)['path']);
                    
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue;
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
                        _put('fau.html', $fau);
                        
                        
                        $po = null;
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau)[0] ?? null;
                            #var_dump($f);
                            
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                
                                $f_0 = Net::X($fa.'/api/internal.php', 'GET', ['action' => 'stats'], Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                                
                                $p_1 = [
                                    'action' =>'client_signals',
                                    'csrf_token' => $pa['csrf_token'],
                                    'webdriver' => false,
                                    'callPhantom' => false,
                                    '_phantom' => false,
                                    '__nightmare' => false,
                                    'domAutomation' => false,
                                    'selenium' => false,
                                    'headless' => false,
                                    'no_plugins' => true,
                                    'has_canvas' => true,
                                    'has_webgl' => true,
                                    'has_notification_api' => true,
                                    'has_promise' => true,
                                    'has_fetch' => true,
                                    'no_plugins' => true,
                                    'move_count' => rand(5, 15),
                                    'move_entropy' => round(mt_rand(30, 180) / 100, 3),
                                    'canvas_entropy' => round(mt_rand(80, 280) / 1000, 3),
                                ];
                                $f_1 = Net::C($fa.'/api/internal.php', 'POST', $p_1, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                                var_dump($f_1);
                                
                                $f_2 = Net::X($fa.'/api/internal.php', 'GET', ['action' => 'cooldown', 'email' => $this->mail], Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                                var_dump($f_2);
                                
                                $f_3 = json_decode(Net::X($fa.'/api/internal.php', 'POST', ['action' => 'check_email', 'email' => $this->mail, 'csrf_token' => $pa['csrf_token']], Inf::$cookie, $this->headersCF, $fa, Inf::$uagent)?: '', 1);
                                var_dump($f_3);
                                
                                $u_4 = rtrim($this->host, '/') . '/' . ltrim($f['url'], '/');
                                $cre = ['email' => $this->mail];
                                $p_4 = array_merge($pa, $cre);
                                unset($p_4['remember']);
                                $f_4 = Net::X($u_4, 'POST', $p_4, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent);
                                _put('f4.html', $f_4);
                                
                                $err = Scraper::_xP($f_4, "//*[@data-claim-error][contains(@class,'is-shown')]/text()")[0] ?? null;
                                var_dump($err);
                                die;
                                
                                if (stripos($err, 'ssion expired')) {
                                    var_dump(_get(Inf::$cookie));
                                }
                                
                                
                                
                                
                            }
                            
                            
                            
                            
                            
                            
                            
                        }
                        
                        
                        
                        
                        
                        
                        
                        
                        
                        
                        
                        
                        die;
                    }
                    
                    
                    
                    
                    
                    
                    die;
                }
                
                
                
            }
            
            
            
            die;
        }
        
        
    }
    
    
    
    
    
})->exec();



