<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://gamefaucet.fun';
    private string $r = '/?r=10275';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], 0, ['login', 'PROXY']);
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
                $l = Inf::check("{$this->host}/dashboard", $this->headersCF, '/auth/login', true);
                
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
                        $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'email' => $this->mail];
                        #$cap = $this->_cp($_0);
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                    #_put('ve.html', $ve); die;
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash);
            
            $_fa = Scraper::_xP($dash, "//div[contains(normalize-space(), 'Faucets')]/following-sibling::div[@class='sub-menu-two']/a/@href");
            #print_r($_fa);
            
            if ($this->claim) {
                
                foreach ($_fa as $fa) {
                    
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
                        
                        $po = null;
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau)[0] ?? null;
                            
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE()];
                                
                                #$cap = $this->_cp($fau);
                                $cap = Solve::exec($fau, $this->host, $this->api, $pa);
                                if (isset($cap['trouble'])) continue;
                                $po = array_merge($pa, $cap, $cre);
                                
                            }
                        }
                        
                        if (!empty($po)) {
                            $cla = Net::C($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                            #_put('cla.html', $cla);
                            
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            $mf = Scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?title:\s*'([^']+)'.*?text:\s*'([^']+)'.*?icon:\s*'([^']+)'/s") ?? [];
                            
                            if (!empty($mf[2][0])) {
                                
                                $stt = $mf[1][0];
                                $msg = $mf[2][0];
                                $this->logger($stt, 'fct', $msg);
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                    $habis[$fa] = true;
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned/i', $msg)) {
                                    die;
                                }
                                
                                if (stripos($msg, 'Shortlink')) {
                                    if ($this->SLDONE) die;
                                    $curr = $_c;
                                    break 2;
                                }
                                
                            }
                            
                            $lf = Scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]")[0] ?? null;
                            if (!empty($lf)) {
                                if (str_contains($lf, 'too many wrong captcha')) break;
                                $this->logger('err', 'fct', $lf);
                            }
                            
                        }
                        styler("waiting for next claim", fn() => _sle(10));
                        
                    }
                    
                }
            }
            
            if (count($habis) === count($_fa)) $this->logger('ok', '', 'beres', 1);
            
            $_sl = Scraper::_xP($dash, "//div[contains(normalize-space(), 'Shortlinks')]/following-sibling::div[@class='sub-menu-two']/a/@href");
            #print_r($_sl);
            foreach ($_sl as $sl) {
                $_c = basename($sl);
                if (!empty($curr) && !str_contains($_c, $curr)) continue;
                
                $up = ['earnow','shortano', 'shortino', 'fc-lc', 'coinclix'];
                $ret99 = 0;
                
                do {
                    $ret99++;
                    $sho = null;
                    $sho = Net::X($sl, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent);
                    #_put('sho.html', $sho);
                    if ($sho === 99) {
                        if ($ret99 >= 5) goto login;
                        continue;
                    }
                    $ret99 = 0;
                    
                    $short = Shortlinks::extract($sho);
                    if (empty($short)) continue 3;
                    #print_r($short); die;
                    
                    $success_in_page = false;
                    $found_one = false;
                    
                    foreach ($short as $links => [$idd, $lmt]) {
                        if (!Shortlinks::limit($lmt) || isset($skipped[$idd])) continue;
                        
                        $found_one = true;
                        $ud = str_replace("/$_c", "/go/$idd/$_c", $sl);
                        $loc = $this->parseShortL($ud, $sl);
                        
                        if (!$loc) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        $loc_u = parse_url($loc)['host'];
                        $is_bl = false;
                        foreach ($up as $blacklisted) {
                            if (str_contains($loc_u, $blacklisted)) {
                                logx('warn', "Domain $blacklisted Skipping..");
                                $skipped[$idd] = true;
                                $is_bl = true;
                                break; 
                            }
                        }
                        if ($is_bl) continue;
                        
                        $start = microtime(true);
                        $bakk = Shortlinks::exec($this->api, $loc);
                        $wait = 130 - (int)(microtime(true) - $start);
                        
                        if (!$bakk) {
                            $skipped[$idd] = true; 
                            continue;
                        }
                        
                        if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                        
                        $retVer = 0;
                        while ($retVer <= 3) {
                            $retVer++;
                            $ver = Net::C($bakk, 'GET', null, Inf::$cookie, $this->headersCF, $loc, Inf::$uagent);
                            #_put('ver.html', $ver);
                            
                            if (!empty($ver) && $ver !== 99) {
                                
                                $msh = Scraper::_jP($ver, "/Swal\.fire\(\s*\{.*?title:\s*'([^']+)'.*?text:\s*'([^']+)'.*?icon:\s*'([^']+)'/s") ?? [];
                                if (!empty($msh[2][0])) {
                                    
                                    $stt = $msh[1][0];
                                    $msg = $msh[2][0];
                                    $this->logger($stt, 'sho', $msg);
                                    
                                    if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                        $sidx = array_search($sl, $_sl);
                                        
                                        if ($sidx !== false && isset($_sl[$sidx + 1])) $curr = basename($_sl[$sidx + 1]);
                                            
                                        else $curr = '';
                                        
                                    }
                                }
                                
                                if (stripos($ver, 'has been sent to your')) $success_in_page = true;
                                
                                break 2;
                            }
                            
                        }
                        
                    }
                    if (!$found_one) {
                        $this->logger('err', 'sho', 'SL habis atau sisa blacklist');
                        $this->SLDONE = true;
                        break; 
                    }
                    
                } while (!$success_in_page);
                
                if ($success_in_page || $curr === "") break; 
                
            }
            
            
        }
        
        
    }
    
    private function parseShortL($ud, $sl) {
        
        $getLok = 0;
        while ($getLok <= 3) {
            $getLok++;
            $lok = Net::X($ud, 'GET', null, Inf::$cookie, $this->headersCF, $sl, Inf::$uagent);
            if (!empty($lok) && $lok !== 99) {
                $f = Scraper::payload($lok)[0] ?? [];
                if (!empty($f)) {
                    $pa = $f['payload'];
                    #$cap = $this->_cp($lok);
                    $cap = Solve::exec($lok, $this->host, $this->api, $pa);
                    if (isset($cap['trouble'])) continue;
                    $po = array_merge($pa, $cap);
                }
            }
            
            if (!empty($po)) {
                $get = Net::C($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $sl, Inf::$uagent, foll: false);
                return Scraper::_pP($get, 'location.href')[0] ?? null;
                
            }
            
        }
        
        return null;
        
    }
    
})->exec();