<?php

class gptfaucet {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://gptfaucet.bitcotasks.com';
    private string $r = '/?ref=3230';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private bool $BCDONE = false;
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
            $dash = null;
            
            $ret = 0;
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", $this->headersCF, 'loginModalLabel');
                
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
                        $cre = ['email' => $this->mail];
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po);
                    $ve = Net::X("{$this->host}/login", 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                }
                
                
            } while (empty($dash));
            #_put('dash.html', $dash);
            
            $_bal = Scraper::_xP($dash, "//div[contains(@class, 'card-body')][.//h6[contains(text(), 'Balance')]]//h5[contains(text(), 'Coins')]/text()")[0] ?? null;
            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                $bal = ((int)$_bal);
                
                if ($bal >= 30) {
                    $po = null;
                    $jjn = [];
                    $jjn = $this->_wd($dash);
                    
                    if (!empty($jjn['payload']) && !empty($jjn['url'])) {
                        $pa = $jjn['payload'];
                        $cap = Solve::exec($dash, $this->host, $this->api, $pa);
                        
                        $walletKey = isset($pa['address']) ? 'address' : (isset($pa['wallet']) ? 'wallet' : 'email');
                        if (empty($pa[$walletKey])) $pa[$walletKey] = $this->mail;
                        
                        $po = array_merge($pa, $cap);
                        
                        
                        $this->logger('', "", "tes ilmu: ".$jjn['info']['coin']);
                        $wdd = json_decode(Net::C($jjn['url'], 'POST', $po, Inf::$cookie, [], "{$this->host}/dashboard", Inf::$uagent)?: '', 1)['message'] ?? null;
                        if (!empty($wdd)) $this->logger('info', "withdraw", "$wdd");
                        
                        
                    } else Logger::X('err', 'gak bisa wd kayaknya');
                    
                }
                
            }
            
            $setF = 0;
            $ads = Net::X("$this->host/ptc", 'GET', null, Inf::$cookie, $this->headersCF, "$this->host/offers", Inf::$uagent);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                #print_r($ptcList); die;
                
                if ($ptcNumb <= 1) {
                    $this->ADDONE = true;
                } else {
                    if (!empty($ptcList['local'])) {
                        
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
                                
                            }
                            
                            
                        }
                    }
                    
                }
                
            }
            
            $off_B = Net::X("{$this->host}/offers", 'GET', null, Inf::$cookie, $this->headersCF, "{$this->host}/offers", Inf::$uagent);
            $bctt_u = Scraper::_xP($off_B, "//a[contains(text(), 'Earn More') and contains(@href, 'bitcotasks.com')]/@href")[0] ?? null;
            
            if (!empty($bctt_u)) {
                $bctt = new bctt($this->host, $this->api, $this->mail);
                $bcttwl = $bctt->wall($bctt_u);
                if (($bcttwl === 'claim') && $this->claim) $bcttwl->cleanup();
                if (($bcttwl === 'habis')) $this->BCDONE = true;
                
            }
            
            if ($this->SLDONE && $this->ADDONE && $this->BCDONE) styler('cooldown', fn() => _sle(600));
            
        }
        
        
        
        
    }
    
    function parsePtcAds($html) {
        $host = $this->host;
        if (empty($html) || $html === 99) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
        
        $result = ['local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
        $host = str_replace('www.', '', parse_url($host, PHP_URL_HOST) ?: $host);
        
        $json = json_decode($html, true);
        if (!$json || empty($json['data'])) return ['total' => 0, 'local' => [], 'bctt' => [], 'owme' => [], 'zono' => [], 'external' => []];
        
        foreach ($json['data'] as $item) {
            $url = $item['url'] ?? '';
            $timer = (int)($item['duration'] ?? 5);
            
            if (empty($url)) continue;
            
            $uHost = str_replace('www.', '', parse_url($url, PHP_URL_HOST) ?: '');
            
            if ($uHost === $host) $result['local'][] = [$url, $timer];
            elseif (strpos($url, 'bitcotasks.com') !== false) $result['bctt'][] = [$url, $timer];
            elseif (strpos($url, 'offerwall.me') !== false) $result['owme'][] = [$url, $timer];
            elseif (strpos($url, 'offerzono.com') !== false) $result['zono'][] = [$url, $timer];
            else $result['external'][] = [$url, $timer];
            
        }
        
        $result['total'] = count($result['local']) + count($result['bctt']) + count($result['owme']) + count($result['zono']) + count($result['external']);
        
        return $result;
    }
    
    function _wd($html) {
        preg_match('/const currencies = (\{.*?\});/s', $html, $match);
        if (empty($match[1])) return false;
        
        $currencies = json_decode($match[1], true);
        if (empty($currencies)) return false;
        
        $balance = 0;
        $xp = Scraper::dom($html);
        if ($xp) {
            $nodes = $xp->query("//h5[contains(text(), 'Coins')]");
            if ($nodes->length > 0) {
                $text = trim($nodes->item(0)->textContent);
                if (preg_match('/([\d.]+)\s*Coins/', $text, $m)) {
                    $balance = (float)$m[1];
                }
            }
        }
        
        $selectedCurrency = null;
        foreach ($currencies as $key => $curr) {
            if (isset($curr['balance_coin']) && $curr['balance_coin'] > 0) {
                $selectedCurrency = $key;
                break;
            }
        }
        
        if (!$selectedCurrency) return false;
        
        $currency = $currencies[$selectedCurrency];
        
        $payload = [
            'amount' => $balance,
            'currency' => $selectedCurrency
        ];
        
        return [
            'url' => $this->host.'/withdraw/submit',
            'method' => 'POST',
            'payload' => $payload,
            'info' => [
                'coin' => $currency['name'],
                'symbol' => $currency['symbol'],
                'balance' => $balance,
                'usd_price' => $currency['usd_price'],
                'balance_coin' => $currency['balance_coin']
            ]
        ];
    }
    
}

new gptfaucet()->exec();
