<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://claimfreetrx.online';
    private string $r = '';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = false;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], false, /*['login', 'PROXY']*/);
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
        $curr_id = '4';
        
        $hhh = Inf::netHead(array_merge(['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()], $this->adcookie()));
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", $hhh, '/auth/validation');
                
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
                @unlink(Inf::$cookie);
                $po = null;
                
                $_0 = Net::X($this->host.'/?r=25299&xpost=true', 'GET', null, Inf::$cookie, $hhh, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    #var_dump($f); die;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $this->mail];
                        #$cap = $this->_cp($_0);
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        #var_dump($cap); die;
                        $po = array_merge($pa, $cap, $cre);
                        if (isset($cap['headers'])) {
                            $extra = array_diff_key($cap, ['solution' => 1, 'headers' => 1]);
                            $po = array_merge($pa, $extra, $cap['solution'], $cre);
                            $he = $cap['headers'];
                        } else {
                            $po = array_merge($pa, $cap, $cre);
                            $he = '';
                        }
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = json_decode(Net::X($f['url'], 'POST', $po, Inf::$cookie, $hhh, $this->host.'/?r=25299&xpost=true', Inf::$uagent)?: '', 1);
                    #var_dump($ve); die;
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); #die;
            
            $setF = 0;
            if ($this->claim) {
                /*
                gaada faucet di webnya 
                fokus ke ptc
                */
            }
            
            if (!empty($curr_id)) Net::X("{$this->host}/account/change_currency",'GET',['method' => $curr_id],Inf::$cookie,$hhh,"{$this->host}/dashboard",Inf::$uagent);
            
            $ads = Net::X("{$this->host}/ptc", 'GET', null, Inf::$cookie, $hhh, "{$this->host}/dashboard", Inf::$uagent);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                var_dump($ptcNumb); #die;
                
                if ($ptcNumb == 0) {
                    $this->ADDONE = true;
                } else {
                    
                    if (!empty($ptcList['local'])) {
                        
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
                                if ($balik >= 2 * 60) continue 2;
                            }
                            
                        }
                    }
                    
                }
                
            }
            
            
            
            
            #if ($this->SLDONE && $this->ADDONE && !$this->claim) styler('cooldown', fn() => _sle(60));
            
        }
        
        
    }
    
    private function parsePtcAds($html) {
        $host = $this->host;
        if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $xp = Scraper::dom($html);
        if (!$xp) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        
        $result = ['local' => [], 'bctt' => [], 'owme' => [], 'external' => []];
        $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
        $baseUrl = rtrim((parse_url($host, PHP_URL_SCHEME) ? $host : 'https://' . $host), '/');
        
        $onclicks = Scraper::_xP($html, "//button[contains(@onclick, 'go_btn')]/@onclick");
        
        $timers = Scraper::_xP($html, "//span[contains(@class, 'badge-custom') and contains(text(), 'seconds')]");
        
        foreach ($onclicks as $i => $onclick) {
            preg_match("/go_btn\s*\(\s*'([^']+)'/", $onclick, $m);
            if (empty($m[1])) continue;
            
            $url = $m[1];
            
            if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
                $url = (strpos($url, '/') === 0) ? $baseUrl . $url : $baseUrl . '/' . $url;
            } elseif (strpos($url, '//') === 0) {
                $url = 'https:' . $url;
            }
            
            $timer = 5;
            if (isset($timers[$i])) {
                $text = trim($timers[$i]);
                if (preg_match('/(\d+)\s*seconds?/', $text, $tm)) {
                    $timer = (int)$tm[1];
                }
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
    
    
    
    
    
})->exec();