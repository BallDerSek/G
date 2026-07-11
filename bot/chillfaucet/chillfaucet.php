<?php

class chillfaucet {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://chillfaucet.in';
    private string $r = '/?r=31169&xpost=true';
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
        $curr = '';
        $skipped = [];
        $curr_id = '';
        
        $hhh = Inf::netHead(['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()]);
        
        login:
            Proxy::load();
            Check::Geo();
            
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", array_merge($hhh, $this->headersCF), '/auth/validation');
                
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
                
                $_0 = Net::X($this->host.$this->r, 'GET', null, Inf::$cookie, $hhh, '', Inf::$uagent, d: true);
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
                    $ve = json_decode(Net::X($f['url'], 'POST', $po, Inf::$cookie, array_merge($hhh, $this->headersCF), '', Inf::$uagent)?: '', 1);
                    #_put('ve.html', $ve); die;
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash);
            
            $_fa = [];
            $xpath = Scraper::dom($dash);
            $allLi = $xpath->query("//li[.//span[text()='Faucet']]//ul[@class='pc-submenu']/li");
            $stop = false;
            foreach ($allLi as $li) {
                $text = trim($li->textContent);
                
                if ($text === 'Cwallet') break;
                
                if ($text === 'FaucetPay') continue;
                
                $link = $xpath->query(".//a", $li)->item(0);
                if (!$link) continue;
                
                $url = $link->getAttribute('href');
                
                if (preg_match('/Claim\s+([A-Z]+)/', $text, $m)) {
                    $_fa[] = [
                        'url' => $url,
                        'coin' => $m[1]
                    ];
                }
            }
            #print_r($_fa);
            
            $setF = 0;
            if ($this->claim) {
                foreach ($_fa as $data) {
                    $fa = $data['url'];
                    $_c = $data['coin'];
                    
                    if (!empty($curr) && stripos($_c, $curr) === false) continue;
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue 2;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::X($fa, 'GET', null, Inf::$cookie, array_merge($hhh, $this->headersCF), $this->host, Inf::$uagent, d: true);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        
                        $fau = $this->checkCF($this->headersCF, $fa, $fau);
                        
                        $po = null;
                        if (!empty($fau) && $fau !== 99) {
                            $lft = Scraper::_xP($fau, "//p[contains(text(), 'Claim Left')]/preceding-sibling::h3")[0]?? null;
                            if ($lft !== null) {
                                list($current, $total) = explode('/', $lft);
                                if ($current === '0') {
                                    $habis[$fa] = true;
                                    break;
                                }
                            }
                            
                            $exp = Scraper::_xP($fau, "//p[contains(text(), 'Faucet Exp')]/preceding-sibling::h3")[0]?? null;
                            
                            if (($exp !== null) && ($exp === '0')) {
                                #var_dump($exp); die;
                                $curr_id = basename(parse_url($fa)['path']);
                                $curr = $_c;
                                $setF = microtime(true);
                                break 2;
                            }
                            
                            $f = Scraper::payload($fau)[0] ?? null;
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()];
                                
                                $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                                
                                if (isset($cap['trouble'])) continue;
                                
                                if (isset($cap['headers'])) {
                                    $extra = array_diff_key($cap, ['solution' => 1, 'headers' => 1]);
                                    $po = array_merge($pa, $extra, $cap['solution'], $cre);
                                    $he = $cap['headers'];
                                } else {
                                    $po = array_merge($pa, $cap, $cre);
                                    $he = '';
                                }
                                
                            } else {
                                    
                                if (str_contains($fau, 'Verification Required')) {
                                    $telegramUrl = Scraper::_xP($fau, "//a[contains(@href, 't.me/')]/@href")[0] ?? null;
                                    if ($telegramUrl) $this->logger('err', "verify ur account", $telegramUrl, 1);
                                    
                                }
                                    
                                if (str_contains($fau, '/auth/validation')) continue 3;
                                
                            }
                            
                        }
                        
                        if (!empty($po)) {
                            #print_r($po); die;
                            
                            _sle(2);
                            $bo = '';
                            $body = SolveUtils::webkitID($po, $bo);
                            $head = [$he, "Content-Type: multipart/form-data; boundary=$bo"];
                            $cla = json_decode(Net::X($f['url'], 'POST', $body, Inf::$cookie, array_merge($hhh, $head, $this->headersCF), $fa, Inf::$uagent, foll: false)?: '', 1);
                            if (!empty($cla) && isset($cla['status'])) {
                                
                                $stt = $cla['status'];
                                $msg = trim(strip_tags($cla['msg'])) ?? 'unknown';
                                $this->logger($stt, 'fct', $msg);
                                
                                
                                if (stripos($msg, 'No Faucet EXP left')) {
                                    $curr_id = basename(parse_url($fa)['path']);
                                    $curr = $_c;
                                    $setF = microtime(true);
                                    break 2;
                                    
                                }
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg) || (stripos($msg, 'link your Cwallet') !== false)) {
                                    $habis[$fa] = true;
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned/i', $msg)) {
                                    die;
                                }
                                
                                
                            }
                            
                            styler("waiting for next claim", fn() => _sle(10));
                        }
                        
                    }
                    
                }
                
            }
            
            if (!empty($curr_id)) Net::X("{$this->host}/account/change_currency",'GET',['method' => $curr_id],Inf::$cookie,$hhh,"{$this->host}/ptc",Inf::$uagent);
            
            $ads = Net::X("{$this->host}/ptc", 'GET', null, Inf::$cookie, $hhh, $this->host, Inf::$uagent);
            if (!empty($ads) && $ads !== 99) {
                $ptcList = $this->parsePtcAds($ads);
                $ptcNumb = $ptcList['total'];
                #var_dump($ptcNumb); #die;
        
                if ($ptcNumb == 0) {
                    $this->ADDONE = true;
                } else {
                    #print_r($ptcList); #die;
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
                                if ($balik >= 4 * 60) continue 2;
                            }
                            
                        }
                    }
                    
                }
                
            }
            
            $off_B = Net::C("{$this->host}/offerwall/bitcotasks", 'GET', null, Inf::$cookie, $hhh, $this->host, Inf::$uagent);
            $bctt_u = Scraper::_jP($off_B, '/<iframe[^>]*src=["\']([^"\']*bitcotask[^"\']*)["\'][^>]*>/i')[1][0] ?? null;
            
            if (!empty($bctt_u)) {
                $bctt = new bctt($this->host, $this->api, $this->mail);
                $bcttwl = $bctt->wall($bctt_u);
                if (($bcttwl === 'claim') && $this->claim) $bcttwl->cleanup();
                if (($bcttwl === 'habis')) $this->BCDONE = true;
                
            }
            
            if ($this->SLDONE && $this->ADDONE && $this->BCDONE) styler('cooldown', fn() => _sle(600));
            
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
    
}

(new chillfaucet())->exec();
