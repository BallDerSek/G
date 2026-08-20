<?php

return (new class {
    
    use Base;
    
    private $acc;
    private $api;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private array $sites = [
        'https://beefaucet.org' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4',
        'https://claimfreecoins.io' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4'
    ];
    
    private string $r = '/?r=gamamoch@gmail.com';
    private string $ip = '162.213.248.69';
    
    private $mail;
    private $prox;
    
    public function __construct() {
        $this->api = onKeys();
        $this->acc = Config::credential([], false, ['login', 'PROXY']);
        putenv("PROXY=" . ($this->prox = $this->acc['PROXY']));
        
        $this->mail = $this->acc['login'];
        
        Proxy::load();
        Check::Geo();
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', "claiming for {$this->mail}");
        $b->task2('ok', "auto multi-site");
        
    }
    
    public function exec() {
        $_ua = Config::uagent();
        $_ck = Config::cookie($this->mail);
        
        $sites = [
            'https://beefaucet.org' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4',
            'https://claimfreecoins.io' => '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4'
        ];
        
        $FAST_CLAIM = true;
        # [ true ] if u want to use single token for all sites
        # [ false ] to use single token per sites.
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            
            if (empty($sites)) {
                @unlink(CREDIR.'/'.$GLOBALS['_CTX']['current_bot']);
                $this->logger('err', "limit!!!", 'ALL SITES REACHED LIMIT', true);
            }
            
            if ($this->api instanceof Provider) $this->api->getInfo();
            
            $parsed_sites = [];
            foreach ($this->sites as $host => $key) {
                $domain = parse_url($host)['host'];
                $_coo = $_ck . '/' . $domain;
                if (!is_dir($_coo)) mkdir($_coo, 0755, true);
                
                $rett0 = 0;
                $parsed_urls = null;
                
                while (true) {
                    $rett0++;
                    $_0 = Net::C($host, 'GET', null, $_coo . '/main', [], '', $_ua, ip: $this->ip, ins: true);
                    if (!empty($_0) && $_0 !== 99) {
                        $_u = Scraper::_xP($_0, "//div[contains(@class, 'dropdown-menu')]//a/@href");
                        
                        if (!empty($_u) && is_array($_u) && count($_u) > 0) {
                            $parsed_urls = $_u;
                            break;
                        }
                        
                    }
                    if ($rett0 >= 9) {
                        unset($sites[$host]);
                        continue 2;
                    }
                }
                
                $parsed_sites[$host] = [
                    'key' => $key,
                    'domain' => $domain,
                    'urls' => $parsed_urls,
                    'cookie' => $_coo
                ];
                
                
                
            }
            
            if ($FAST_CLAIM) {
                $token = $this->_tK('https://beefaucet.org', '6LfwaSgTAAAAAJJNz6oAdimVHmIe3s4fHj4D0at4');
                if (empty($token) || $token === null) continue;
            }
            
            foreach ($parsed_sites as $host => $data) {
                $domain = $data['domain'];
                $_u = $data['urls'];
                $_coo = $data['cookie'];
                
                $prep_queue = [];
                foreach ($_u as $f_u) {
                    $u_nam = basename(parse_url($f_u)['path']);
                    $u_coo = $_coo . '/' . $u_nam;
                    
                    $_url = (strpos($f_u, 'http') === 0) ? $f_u : $host . $f_u;
                    $_url .= $this->r;
        
                    if (file_exists($u_coo)) @unlink($u_coo); 
                    
                    $prep_queue[$_url] = [
                        $_url, 'GET', null, $u_coo,
                        [], $host, $_ua, $this->ip,
                        false, #$this->prox['PROXY']
                    ];
                    
                }
                
                $pages = styler("Preparing " . count($prep_queue) . " coins", function() use ($prep_queue) {
                    return Mux::C(...array_values($prep_queue));
                });
                
                if (!$FAST_CLAIM) {
                    $token = $this->_tK($host, $data['key']);
                    if (empty($token) || $token === null) {
                        unset($sites[$host]);
                        continue;
                    }
                }
                
                $multi_calls = [];
                $coin_map = [];
                $idx = 0;
                
                foreach ($prep_queue as $_url => $args) {
                    
                    $page = $pages[$idx++] ?? null;
                    $u_nam = basename(parse_url($_url)['path']);
                    
                    if (empty($page)) {
                        $this->logger('warn', str_pad($u_nam, 16), 'Empty page response');
                        continue;
                    }
                    
                    $forms = Scraper::payload($page)[0] ?? [];
                    
                    if (empty($forms)) {
                        $this->logger('warn', str_pad($u_nam, 16), 'Form not found');
                        continue;
                    }
                    
                    $pa = $forms['payload'];
                    $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                    if (empty($pa[$walletKey])) $pa[$walletKey] = $this->mail;
                    $pa['g-recaptcha-response'] = $token;
                    $post_url = (strpos($forms['url'], 'http') === 0) ? $forms['url'] : $host . $forms['url'];
                    
                    $multi_calls[] = [
                        $post_url, 'POST', $pa, $args[3],
                        [], $_url, $_ua, $this->ip,
                        false, #$this->prox['PROXY']
                    ];
                    $coin_map[] = $u_nam;
                    
                }
                
                if (!empty($multi_calls)) {
                    $results = styler("Claiming " . count($multi_calls) . " coins", function() use ($multi_calls) {
                        return Mux::C(...$multi_calls);
                    });
                    
                    $totalCoins = count($results);
                    $limitReached = 0;
                    
                    foreach ($results as $i => $res_html) {
                        $u_nam = $coin_map[$i] ?? 'unknown';
                        
                        if (empty($res_html)) {
                            $this->logger('err', str_pad($u_nam, 16), 'Empty response on blast');
                            continue;
                        }
                        
                        $_suc = Scraper::_xP($res_html, "//div[contains(@class, 'alert')]");
                        if (isset($_suc[0])) {
                            $msg = trim(str_replace('×', '', $_suc[0]));
                            $lowMsg = strtolower($msg);
                            
                            $stt = (stripos($lowMsg, 'sent') ? 'ok' : 'err');
                            
                            $this->logger($stt, str_pad($u_nam, 16), "$msg");
                            
                            if (stripos($msg, 'has been blacklisted')) die;
                            
                            if (stripos($lowMsg, 'claim limit') || stripos($lowMsg, 'sufficient') || stripos($lowMsg, 'safety')) {
                                $limitReached++;
                            }
                            
                        }
                        
                    }
                    
                    foreach ($prep_queue as $args) @unlink($args[3]);
                    
                    if ($limitReached >= $totalCoins) {
                        $this->logger('err', "limit!!!", strtoupper($domain)." IS FULLY LIMITED");
                        unset($sites[$host]); 
                    }
                    
                }
                
            }
            
            if (!empty($sites)) styler("waiting", fn() => _sle(30));
            
        }
        
    }
    
    public function _tK($rc_U, $rc_K) {
        return Solve::tkn($this->api, $rc_U, $rc_K, 'rc2')['done'] ?? null;
    }
    
    
})->exec();
