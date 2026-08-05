<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://limefaucet.com';
    private string $r = '/ref/jSzXvkh0oy0M2Xn0';
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
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], true, ['login', 'PROXY']);
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
        $curr = 'ltc';
        $skipped = [];
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/api/auth/me");
                
                if (!empty($l['html']) && str_contains($l['html'], $this->mail)) $l['ok'] = 1;
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
                    
                    $cre = ['email' => $this->mail, 'referral_code' => 'jSzXvkh0oy0M2Xn0'];
                    $cap = [];
                    if (isset($cap['trouble'])) continue;
                    
                    $po = array_merge($cap, $cre);
                }
                
                if ($po) {
                    #print_r($po); die;
                    $ve = Net::X($this->host.'/api/auth/login', 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent, json: 1);
                    #var_dump($ve);
                    
                    
                }
                
            } while (empty($dash));
            #Authentication required
            
            $_fa = json_decode(Net::X($this->host.'/api/config', 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent)?: '', 1)["supported_currencies"] ?? null;
            #print_r($_fa);
            
            if ($this->claim && $_fa) {
                
                foreach ($_fa as $fa) {
                    $_c = basename(parse_url($fa)['path']);
                    
                    if (!empty($curr) && !str_contains(strtolower($_c), $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue;
                    }
                    
                    Net::X(
                        $this->host.'/api/auth/change-currency', 
                        'POST', ['currency' => $fa],Inf::$cookie,
                        $this->headersCF, $this->host,
                        Inf::$uagent, json: 1
                    );
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::C("{$this->host}/api/faucet/info", 'GET', null, Inf::$cookie, [], "{$this->host}/", Inf::$uagent, ip: $this->ip);
                        #_put('fau.html', $fau); 
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            _sle(40);
                            continue;
                        }
                        
                        $fauu = json_decode($fau, 1);
                        $po = null;
                        if (!empty($fauu) && $fauu !== 99) {
                            #var_dump($fauu);
                            
                            $c_end = strtotime($fauu['next_claim_at'] ?? '0');
                            $now = microtime(1);
                            
                            if ($now >= $c_end) {
                                
                                $cap = $this->limCAP(); 
                                if (isset($cap['trouble'])) continue;
                                $po = $cap;
                                
                                
                            } else {
                                $wait = $c_end - $now;
                                styler('Waiting for faucet', fn() => _sle((int)$wait));
                            }
                            
                        } else {
                            
                            if (str_contains($fau, 'Authentication required')) continue 3;
                        }
                        
                        if (!empty($po)) {
                            #print_r($po); die;
                            $cla = json_decode(Net::X($this->host.'/api/faucet/claim', 'POST', $po, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent, ip: $this->ip, json: 1)?: '', 1);
                            
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            if (!empty($cla)) {
                                #var_dump($cla);
                                
                                $stt = $cla['payout_status'] ?? 'err';
                                $msg = $cla['error'] ?? " claimed ".($cla['reward_usd'].' (usd) '.$cla['currency'] ?? '') ?? 'unknown';
                                
                                $this->logger($stt, 'fct', $msg);
                                
                                if (preg_match('/sufficient|could not be processed/i', $msg)) {
                                    $habis[$fa] = true;
                                    break;
                                }
                                
                                if (preg_match('/blacklist|flagged|banned|nti fraud/i', $msg)) die;
                                
                                if (stripos($msg, 'Shortlink')) {
                                    if ($this->SLDONE) die;
                                    $curr = $_c;
                                    break 2;
                                }
                                
                            }
                            
                        }
                        
                    }
                    
                }
                
                
            }
            
            die;
        }
        
        
        
        
        
    }
    
    
    private function tkn() {
        $cft = 'token';
        $cft = Solve::tkn($this->api, $this->host, '0x4AAAAAAD_JMYUykdNqm47T', 'cft')['done'] ?? null;
        var_dump($cft); die;
        
        
        return ['trouble' => 1];
        
    }
    
    private function limCAP($param = 'faucet') {
        
        # nanti buat debug 
        # $stt = Net::C("{$this->host}/api/captcha/status", 'GET', null, Inf::$cookie, [], "{$this->host}/faucet", Inf::$uagent);
        
        $solution = Retry::until(function() use ($param) {
            $tsk = json_decode(Net::X(
                "{$this->host}/api/captcha/challenge", 'POST', 
                ['action' => $param], Inf::$cookie, [],
                "{$this->host}/faucet", Inf::$uagent, json: 1
            )?: '', 1);
            if (!empty($tsk) && isset($tsk['challenge'])) {
                $sooo = _task($tsk['challenge']);
            }
            if ($sooo) {
                return [
                    'challenge_id' => $tsk['challenge_id'],
                    'selection' => is_array($sooo) ? $sooo : [$sooo]
                ];
            }
        }, 2, 1);
        
        if ($solution) {
            $vv = array_merge($solution, ['action' => $param]);
            $ver = json_decode(Net::X(
                "{$this->host}/api/captcha/verify",
                'POST', $vv, Inf::$cookie, [],
                "{$this->host}/faucet", Inf::$uagent, json: 1
            )?: '', 1)['token'] ?? null;
            if ($ver) return ['captcha_token' => $ver];
            #if ($ver) return ['captcha_token' => md5('kntl')];
        }
        
        return ['trouble' => 1];
    }
    
})->exec();



function _task($tc) {
    #var_dump($tc);
    
    switch ($tc['type']) {

        case 'sequence':
            return $tc['target'];
        
        case 'drag-order':
            return $tc['correctOrder'];
        
        case 'count':
            $grd = array_map('trim', $tc['grid']);
            $cnt = array_count_values($grd);
            
            if (($tc['mode'] ?? 'least') === 'least') asort($cnt);
            else arsort($cnt);
            
            return array_key_first($cnt);
        
        case 'pattern':
            $seq = $tc['sequence'];
            $null = array_search(null, $seq, true);
            
            if ($null === false) return null;

            $seq = array_slice($seq, 0, $null);
            $len = count($seq);
            
            $pttrn = 1;
            for ($k = 1; $k <= floor($len / 2); $k++) {
                $see = true;
                for ($i = 0; $i < $len - $k; $i++) {
                    if ($seq[$i] !== $seq[$i + $k]) {
                        $see = false;
                        break;
                    }
                }
                if ($see) {
                    $pttrn = $k;
                    break;
                }
            }
            
            return $seq[$null % $pttrn];

        default:
            return null;
    }
    
}
