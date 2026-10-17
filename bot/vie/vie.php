<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://viefaucet.com';
    private string $r = '';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    private string $salt = "d!tc0nmeth@ngn@0h@ckt@o";
    
    private bool $limit = false;
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    private bool $atbforce = false;
    private int $atbfail = 0;
    private bool $can_withdraw = true;
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], false, ['mail', 'pass', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['mail'];
        $this->pass = $this->acc['pass'];
        
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
        
        login:
            Proxy::load();
            Check::Geo();
        
        #$this->_init();
        $this->_ck();
        
        while (true) {
            $dash = null;
            $ret = 0; 
            $ptcHabis = 0;
            
            do {
                $ret++;
                
                #$this->headersCF = ['Authorization: Bearer '.$this->acc[$this->mail]];
                $this->headersCF = [
                    'vie-version: 1',
                    'token: '.md5($this->mail),
                    'ts: '.TIMEZONE()
                ];
                $l = Inf::check("{$this->host}/api/user/me", $this->headersCF);
                #var_dump($l);
                
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
                
                $_0 = Net::X($this->host, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                
                $_getC = json_decode(Net::X($this->host.'/api/ct', 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent)?: '', 1);
                #var_dump($_getC);
                
                if (!empty($_getC) && !empty($_getC['captcha'])) {
                    $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $_getC['captcha']);
                    
                    $cre = [
                        'email' => $this->mail,
                        'password' => $this->pass
                    ];
                    $cap = Solve::img($this->api, $this->host, 'vie_upside', $base64);
                    
                    if (!isset($cap['trouble'])) {
                        $tokenC = $this->vieUPS($_getC, $cap);
                        $cap = [
                            'captcha' => [
                                'type' => 'rocket-captcha',
                                'token' => $tokenC,
                                'id' => $_getC['id']
                            ]
                        ];
                        
                        $po = array_merge($cre, $cap);
                        
                    }
                    
                }
                
                if ($po) {
                    #var_dump($po);
                    
                    $ve = json_decode(Net::X($this->host.'/api/auth/login', 'POST', $po, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, json: 1)?: '', 1);
                    #var_dump($ve);
                    
                    $msg_d = $ve['msg'] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'nvalid Captcha')) continue;
                        $this->logger('err', '', $msg_d, 1);
                    }
                    
                }
                
                
            } while (empty($dash));
            $dash = json_decode($dash, 1);
            #var_dump($dash);
            
            Net::X($this->host.'/api/news', 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent);
            
            $this->can_withdraw = (bool)$dash['user']["verified"]?? false;
            
            $_bal = $dash['user']['balance'] ?? null;
            if ($_bal) {
                $this->logger('', "balance", "$_bal");
                $bal = ((float)$_bal);
                
                
            }
            
            $lock = $dash['user']['lockedUntil'] ?? 0;
            
            if ($lock && $lock !== 0) {
                $sleepTime = max(0, strtotime($lock) - time());
                styler("waiting for unlocked", fn() => _sle($sleepTime));
                continue;
            } 
            
            $setF = 0;
            if (!$this->limit && $this->claim) {
                
                $ret99 = 0; 
                while (true) {
                    $ret99++;
                    
                    $cfg = json_decode(Net::C("{$this->host}/api/user/me", 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent)?: '', 1);
                    
                    $me = $cfg['user']['faucetClaim'] ?? 0;
                    #var_dump($me); #die;
                    
                    $fau = json_decode(Net::C("{$this->host}/api/faucet/info", 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent)?: '', 1); 
                    #var_dump($fau); #die;
                    if (empty($fau) || ($fau === 99)) {
                        if ($ret99 >= 5) goto login;
                        _sle(40);
                        continue;
                    }
                    
                    $atb = null;
                    $po = [
                        "captcha" => null,
                        "antibot" => null,
                        "key" => null,
                        "duration" => null,
                        "captchaDuration" => null,
                        "botd" => null,
                        "b2" => false,
                        "idle" => null,
                        "clickedT" => false,
                        "closedOfferwall" => false,
                        "isUsingUBlock" => false,
                        "isUsingMultibotExtension" => false
                    ];
                    $this->atbforce = $this->atbfail >= 1;
                    if ($fau['wait'] >= 1) {
                        styler("waiting for next claim", fn() => _sle($fau['wait']));
                        continue;
                    } elseif ($me >= $fau['settings']["faucet_limit"]) {
                        $this->limit = true;
                        continue 2;
                    }
                    
                    if ($dash['user']["captcha"]) {
                        $setATB = microtime(true) * 1000;
                        $atbC = json_decode(Net::C("{$this->host}/api/antibot", 'GET', null, Inf::$cookie, $this->headersCF, "{$this->host}/app/faucet", Inf::$uagent)?: '', 1)['antibot'] ?? null;
                        
                        if ($atbC) {
                            #Logger::X('info', "ATB: fail => {$this->atbfail}, force => {$this->atbforce}");
                            #_put("$me.atb.json", json_encode($atbC));
                            $atbD = $this->vieATB($atbC);
                            $resAtb = AntibotLinks::exec(
                                'image', $this->api,
                                $atbD, $this->atbforce
                            );
                            #var_dump($resAtb);
                            
                            if ($resAtb === 77) continue;
                            
                            $atb = str_replace(' ', '-', (trim($resAtb) ?: ' '));
                            #var_dump($atb);
                            
                            usleep(random_int(1500000, 4000000));
                            $endATB = microtime(true) * 1000;
                            $durATB = $endATB - $setATB;
                        }
                        
                    }
                    
                    $upsC = json_decode(Net::X(
                        $this->host.'/api/ct', 'GET', null,
                        Inf::$cookie, $this->headersCF,
                        "{$this->host}/app/faucet", Inf::$uagent
                    )?: '', 1);
                    if (!empty($upsC) && !empty($upsC['captcha'])) {
                        $setUPS = microtime(true) * 1000;
                        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $upsC['captcha']);
                        
                        $cap = Solve::img($this->api, $this->host, 'vie_upside', $base64);
                        if (!isset($cap['trouble'])) {
                            
                            $tokenC = $this->vieUPS($upsC, $cap);
                            $po['captcha'] = [
                                'type' => 'rocket-captcha',
                                'token' => $tokenC,
                                'id' => $upsC['id']
                            ];
                            $endUPS = microtime(true) * 1000;
                            $durUPS = $endUPS - $setUPS;
                            
                            if ($atb) $po['antibot'] = $atb;
                            
                        }
                        
                    }
                    
                    $po = (function ($fr, $me) {
                        
                        if ($me == 5) {
                            $fr['key'] = 7 * random_int(10, 10000);
                            $fr['duration'] = $durATB;
                            $fr['captchaDuration'] = $durUPS;
                        }
                        if ($me == 10) $fr['botd'] = base64_encode(json_encode(["bot" => false, "botKind" => "unknown"]));
                        if (($me == 7) || ($me == 25)) $fr['clickedT'] = true;
                        if ($me == 11) $fr['b2'] = true;
                        if ($me > 0 && $me % 10 == 0) $fr['idle'] = $me;
                        $fr = array_filter($fr, fn($v) => $v !== null && $v !== false);
                        return $fr;
                        
                    })($po, $me);
                    
                    if ($po) {
                        #var_dump($po);
                        $cla = json_decode(Net::X($this->host.'/api/faucet', 'POST', $po, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, json: 1)?: '', 1);
                        #var_dump($cla);
                        
                        if (empty($cla) || ($cla === 99)) continue;
                        
                        $msg = $cla['msg'] ?? null;
                        $this->checkATB($this->atbfail, $msg);
                        if (!empty($msg)) {
                            $stt = (stripos($msg, 'have claimed') ? 'ok' : 'err');
                            $this->atbfail = 0;
                            $this->logger($stt, 'fct', $msg);
                            if ($stt == 'ok') {
                                $setF = microtime(true);
                                
                                #break;
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
            /*
            $ads = json_decode(Net::C("{$this->host}/api/ptc", 'GET', null, Inf::$cookie, $this->headersCF, "{$this->host}/app/ptc/all", Inf::$uagent)?: '', 1)['statistic'] ?? [];
            #var_dump($ads);
            #_put('ads.json', json_encode($ads, JSON_PRETTY_PRINT));
            var_dump(count($ads));
            if (!empty($ads) && $ads !== 99) {
                
                foreach ($ads as $ptc => $task) {
                    #var_dump($ptc);
                    var_dump($task);
                    if (strtolower($ptc) != 'window') continue;
                    
                    if ($task['count'] <= 1) {
                        $ptcHabis++;
                        continue;
                    }
                    
                    $ptcList = json_decode(Net::C(
                        "{$this->host}/api/ptc", 'GET',
                        ['type' => $ptc], Inf::$cookie,
                        $this->headersCF,
                        "{$this->host}/app/ptc/all",
                        Inf::$uagent
                    )?: '', 1);
                    
                    if (!empty($ptcList) && !empty($ptcList['ads'])) {
                        $cla = null;
                        foreach ($ptcList['ads'] as $data) {
                            $cla = $this->viePTC($data, $ptc);
                            
                            
                            
                            die;
                        }
                        
                        
                        
                        
                        
                        
                    }
                    
                    
                    die;
                }
                
                
                
                
                
                
            }
            */
            
            
            
            
        }
        
        
        
        
        
        
    }
    
    private function _init($token = '') {
        if ($token !== '') {
            $this->acc[$this->mail] = $token;
        } else {
            $this->acc = Config::credential([$this->mail => md5($this->mail)]);
        }
    }
    
    private function _ck() {
        $ccc = $this->adcookie(true);
        foreach ($ccc as $nn => $vv) Inf::injectCookie(Inf::$cookie, $vv, $this->host, $nn);
    }
    
    private function parseCoords($str) {
        $x = $y = '';
        
        if (preg_match('/x=(\d+),y=(\d+)/', $str, $m)) {
            $x = (int)$m[1]; $y = (int)$m[2];
        } elseif (preg_match('/^(\d+):(\d+)$/', trim($str), $m)) {
            $x = (int)$m[1]; $y = (int)$m[2];
        } elseif (preg_match('/x:(\d+),y:(\d+)/', $str, $m)) {
            $x = (int)$m[1]; $y = (int)$m[2];
        }
        
        return [$x, $y];
    }
    
    private function vieUPS($data, $solution) {
        
        [$x, $y] = $this->parseCoords($solution);
        
        $width = $data['width'];
        $height = $data['height'];
        $id = $data['id'];
        
        
        $percentX = (int)floor(($x / $width) * 100);
        $percentY = (int)floor(($y / $height) * 100);
        
        $po = [
            'percentX' => $percentX,
            'percentY' => $percentY,
            'id' => $id,
            'x' => $x,
            'y' => $y
        ];
        
        $json = json_encode($po, JSON_UNESCAPED_SLASHES);
        
        return base64_encode($json);
    }
    
    private function vieATB($data) {
        $ret = ['main' => null, 'rels' => []];
    
        $ret['main'] = Capt::cleanB64($data['instruction']);
        
        $links = $data['antibotlinks'];
        $count = min(3, count($links));
        for ($i = 0; $i < $count; $i++) {
            $t = (float)$links[$i]['key'];
            
            $_id = (string)round(pow(($t - 5) / 2, 0.25));
            
            $_b64 = Capt::cleanB64($links[$i]['image']);
            if ($_b64) $ret['rels'][$_id] = $_b64;
        }
    
        return $ret;
    }
    
    private function viePTC($data, $type) {
        var_dump($data);
        var_dump($type);
        
        $adIds = $data["_id"] ?? '';
        $adTmr = $data["timer"] ?? 10;
        $adUrl = $data["url"] ?? $this->host;
        
        Net::X($adUrl, 'GET', null, Inf::$cookie, [], '', Inf::$uagent);
        styler("waiting {$adTmr}.s for $type ads", fn() => _sle(($adTmr)));
        
        $upsC = json_decode(Net::X(
            $this->host.'/api/ct', 'GET', null, Inf::$cookie,
            $this->headersCF, "{$this->host}/app/ptc/$type",
            Inf::$uagent
        )?: '', 1);
        
        $po = null;
        if (!empty($upsC) && !empty($upsC['captcha'])) {
            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $upsC['captcha']);
            $cap = Solve::img($this->api, $this->host, 'vie_upside', $base64);
            if (!isset($cap['trouble'])) {
                $tokenC = $this->vieUPS($upsC, $cap);
                $po['token'] = md5($adIds.$this->salt);
                $po['captcha'] = [
                    'type' => 'rocket-captcha',
                    'token' => $tokenC,
                    'id' => $upsC['id']
                ];
            }
        }
        
        if ($po) {
            var_dump($po); #die;
            $cla = json_decode(Net::X(
                "{$this->host}/api/ptc/$adIds/verify", 'POST',
                $po, Inf::$cookie, $this->headersCF, $this->host,
                Inf::$uagent, json: 1
            )?: '', 1);
            var_dump($cla);
            
            if ($cla) {
                $msg = $cla['msg'] ?? null;
                $stt = (stripos($msg, 'have claimed') ? 'ok' : 'err');
                $this->logger($stt, 'fct', $msg);
            }
            
        }
            
        
        
        
        
        
        die;
        
    }
    
    
    
    
    
    
    
    
})->exec();


    


