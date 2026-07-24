<?php
_die();
class bitfaucetcj {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://bitfaucet.cryptojobss.com';
    private string $app = 'https://cryptojobss.com';
    private string $r = '/r/i9HZBvO42I';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = false;
    private bool $ADDONE = false;
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
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", $this->headersCF, 'loginForm');
                
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
                
                $_0 = Net::C($this->host.$this->r, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0, 'loginForm')[0] ?? null;
                    #var_dump($f); die;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['email' => $this->mail];
                        #$cap = $this->_cp($_0);
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::C($this->host, 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent);
                    #_put('ve.html', $ve); die;
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash);
            
            $_fa = Scraper::_xP($dash, "//a[contains(@class, 'btn-claim')]/@href");
            #print_r($_fa); die;
            if (empty($curr)) shuffle($_fa);
            if ($this->claim) {
                foreach ($_fa as $fa) {
                    $fa = str_replace('/faucet/', '/go/', $fa);
                    
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue 2;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::X($this->host.$fa, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        #_put('fau.html', $fau);
                        
                        $met = Scraper::_pP($fau, 'location.replace')[0] ?? null;
                        
                        if (!empty($met)) {
                            $bfcj = $this->bfcj($met, $this->host.$fa);
                            if ($bfcj === 'proxy') continue 3;
                            if ($bfcj === 'habis') {
                                $habis[$fa] = true;
                                break;
                            }
                            
                        } else continue 3;
                        
                    }
                }
            }
            
            if (count($habis) === count($_fa)) $this->logger('ok', '', 'beres', 1);
            
            
            $_sl = Scraper::_xP($dash, "//a[contains(@class, 'btn-links')]/@href");
            #print_r($_sl);
            
            
        }
        
        
        
        
    }
    
    
    
    private function bfcj($meta, $reff) {
        if (!$meta) return false;
        
        $mett = 'https://'.(parse_url($meta)['host'] ?? '');
        
        $con = null;
        $cnt = json_decode(Net::C(
            "{$this->app}/api/post/", 'GET', null, Inf::$cookie,
            $this->headersCF, $reff, Inf::$uagent
            )?: '', 1);
        if (!empty($cnt) && is_array($cnt)) $con = $cnt[array_rand($cnt)]['link'];
        
        $fpp_id = ['fp_id' => md5($this->mail)];
        $fcj = json_decode(Net::X(
            "{$this->app}/api/fcj/", 'GET', $fpp_id,
            Inf::$cookie, $this->headersCF,
            $mett, Inf::$uagent)?: '', 1
        )['fcj_id'] ?? null;
        
        $data = null;
        if (!empty($fcj)) {
            $fcj_id = ['fcj_id' => $fcj];
            $data = json_decode(Net::X(
                "{$this->app}/api/fcj/", 'POST', $fcj_id,
                Inf::$cookie, $this->headersCF, $mett,
                Inf::$uagent, json: 1
            )?: '', 1)['data'] ?? null;
            if (!empty($data)) if ($data['daily_count'] == $data['daily_limit']) return 'habis';
        }
        
        if ($con && $fcj && $data) {
            #var_dump($con, $fcj, $data);
            
            while (true) {
                _sle(3);
                $view = Net::C($con, 'GET', null, Inf::$cookie, [], $mett, Inf::$uagent);
                if ($view === 99) return "proxy";
                
                $fcap = $this->_cap($mett, $data);
                
                if (isset($fcap['trouble'])) continue;
                
                if (isset(($fcap['capdata']))) {
                    
                    $po = array_merge($fpp_id, $fcj_id, $fcap);
                    
                    $cla = json_decode(Net::X(
                        "{$this->app}/api/fcj/claim.php", 
                        'POST', $po, Inf::$cookie, 
                        $this->headersCF, $mett,
                        Inf::$uagent, json: true
                    )?: '', 1);
                    #var_dump($cla);
                    
                    if (!empty($cla)) {
                        $claa = $cla['data'] ?? null;
                        $rwd =  ($claa['rewards'] ?? '')." ".($claa['coin_name'] ?? '');
                        
                        $stt = $cla['status'];
                        $msg = trim(strip_tags($cla['message'])) ?? 'unknown';
                        
                        $this->logger($stt, 'fct', "$rwd $msg");
                        
                        if (preg_match('/sufficient|could not be processed/i', $msg)) return 'habis';
                        
                        if (preg_match('/blacklisted|flagged|banned/i', $msg)) die;
                        
                        if (preg_match('/went wron|new session/i', $msg)) { 
                            continue;
                            @unlink(Inf::$cookie);
                            return 'proxy';
                        }
                        
                        if (!empty($claa)) {
                            if ($claa['daily_count'] == $claa['daily_limit']) return 'habis';
                        }
                        
                    }
                    
                    styler("waiting for next claim", fn() => _sle((int)$data['faucet_time']));
                    
                }
                
            }
            
        }
        
        return 'proxy';
        
    }
    
    private function _cap($reff, $data) {
        $bon = null;
        $pa_0 = SolveUtils::webkitID(['action' => 'generate'], $bon);
        $he_0 = ["Content-Type: multipart/form-data; boundary=$bon"];
        
        $req = json_decode(Net::X(
            "{$this->app}/api/captcha/", 'POST', $pa_0,
            Inf::$cookie, $he_0, $reff, Inf::$uagent
        )?: '', 1);
        
        if (!empty($req) && isset($req['captcha_id'])) {
            $img = explode(',', $req['image'])[1];
            
            $solution = Solve::img($this->api, $this->host, 'upside', $img);
            
            if (isset($solution['trouble'])) return ['trouble' => 'reload'];
            
            $coords = scraper::_jP($solution, '/\d+/');
            $coo = $coords[0] ?? $coords; 
            if (is_array($coo) && count($coo) >= 2) {
                $bon = null;
                [$x, $y] = $coo;
                
                $pa_1 = SolveUtils::webkitID(['action' => 'verify', 'captcha_id' => $req['captcha_id'], 'click_x' => $x, 'click_y' => $y], $bon);
                $he_1 = ["Content-Type: multipart/form-data; boundary=$bon"];
                
                $res = json_decode(Net::X(
                    "{$this->app}/api/captcha/", 'POST', $pa_1,
                    Inf::$cookie, $he_1, $reff, Inf::$uagent
                )?: '', 1);
                
                if (isset($res['verified']) && filter_var($res['verified'], FILTER_VALIDATE_BOOLEAN)) return ['captcha_id' => ($res['captcha_id']?? $req['captcha_id']), 'capdata' => 'ok'];
                
            }
            
        }
        
        if (isset($req['error']) && stripos($req["message"], 'wait')) {
            $this->logger('err', 'fct', $req['message']);
            $wait = (int)filter_var($req['message'], FILTER_SANITIZE_NUMBER_INT);
            styler("waiting for unlocked", fn() => _sle((int)$wait * 60));
            
        }
        
        return ['trouble' => 'reload'];
        
    }
    
    
}

(new bitfaucetcj())->exec();

