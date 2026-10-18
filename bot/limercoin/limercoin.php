<?php

return (new class {
    
    use Base, Mimic;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://limercoin.com';
    private string $app = 'https://api.limercoin.com';
    private string $r = '';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $limit = false;
    private bool $claim = true;
    private array $headersCF = [];
    private bool $can_withdraw = true;
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], false, /*['mail', 'pass', 'PROXY']*/);
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
        
        $this->_init();
        $this->_ck();
        
        while (true) {
            $this->banner->show();
            $this->banner->task1('ok', $this->mail);
            $this->banner->task2('ok', "site: ".$this->host);
            
            
            $dash = null;
            $ret = 0; 
            
            do {
                $ret++;
                
                $this->headersCF = ['Authorization: Bearer '.$this->acc[$this->mail]];
                $l = Inf::check("{$this->app}/profile/info", $this->headersCF);
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
                
                if (!empty($_0) && $_0 !== 99) {
                    $cre = [
                        'email' => $this->mail,
                        'password' => $this->pass
                    ];
                    
                    $tkn = Solve::tkn($this->api, $this->host, 'f44df123-e75f-4c2d-a318-4311cdd360a7', 'hc');
                    #$tkn['done'] = _rl('token: ');
                    if (!isset($tkn['fail']) && isset($tkn['done'])) {
                        
                        $cap = ['captchaToken' => $tkn['done']];
                        $po = array_merge($cap, $cre);
                        
                    }
                    
                    
                    
                }
                
                
                if ($po) {
                    #var_dump($po); die;
                    
                    $ve = json_decode(Net::X($this->app.'/auth/login', 'POST', $po, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, json: 1)?: '', 1);
                    #var_dump($ve);
                    
                    $msg_d = $ve['messages'][0] ?? null;
                    if (!empty($msg_d)) {
                        if (stripos($msg_d, 'Captcha verification failed')) continue;
                        $this->logger('err', '', $msg_d, 1);
                    }
                    
                    if (isset($ve['accessToken'])) {
                        #var_dump($ve);
                        $this->_init($ve['accessToken']);
                    }
                    
                }
                
            } while (empty($dash));
            $dash = json_decode($dash, 1);
            #var_dump($dash);
            
            $this->can_withdraw = (bool)$dash['data']["isConfirmed"]?? false;
            
            $cfg = json_decode(Net::X(
                $this->app.'/profile/balances', 'GET',
                null, Inf::$cookie, $this->headersCF,
                $this->host, Inf::$uagent
            )?: '', 1)['data'] ?? [];
            
            $this->claim = !$dash['data']['isBanned'] ?? false;
            $this->limit = (!$this->claim && ($dash['data']['isBot'] ?? false));
            
            if (!empty($cfg)) {
                $pwr = json_decode(Net::X(
                    $this->app.'/game/user-pools-power', 'GET',
                    null, Inf::$cookie, $this->headersCF,
                    $this->host, Inf::$uagent
                )?: '', 1)['data'] ?? [];
                $total = current(array_filter($pwr, fn($p) => $p['miningPoolKey'] === 'total'))['value'] ?? 0;
                $this->logger("info", 'TOTAL POWER :', $total);
                
                #var_dump($cfg);
                foreach ($cfg as $i => $b) {
                    
                    $juml = $b['amount'];
                    $curr = $b['currencyName'];
                    
                    $this->logger("info", str_pad($curr, 12).':', str_pad($juml, 10));
                    
                }
                
            }
            
            /*
            TODO:
            absen dan task nanti
            
            
            
            
            */
            
            
            
            
            
            if (!$this->limit && $this->claim) {
                
                $games = json_decode(Net::X(
                    $this->app.'/game/games-data', 'GET',
                    null, Inf::$cookie, $this->headersCF,
                    $this->host, Inf::$uagent
                )?: '', 1)['data'] ?? [];
                #var_dump($games);
                
                foreach ($games as $g => $data) {
                    #var_dump($data);
                    
                    $payload = null;
                    if ($data["cooldown"] > 0) continue;
                    
                    $setG = json_decode(Net::X(
                        $this->app.'/game/start', 'POST', ['gameId' => $data["gameId"]],
                        Inf::$cookie, $this->headersCF,
                        $this->host, Inf::$uagent, json: 1
                    )?: '', 1);
                    #var_dump($setG);
                    
                    $po = $this->lmrGame($data, $setG, $dash['data']["id"]);
                    #var_dump($po);
                    
                    if ($po) {
                        $payload['data'] = $po;
                        #var_dump($payload);
                        
                        $endG = json_decode(Net::X(
                            $this->app.'/game/finish', 'POST',
                            $payload, Inf::$cookie,
                            $this->headersCF, $this->host,
                            Inf::$uagent, json: 1
                        )?: '', 1);
                        #var_dump($endG);
                        
                        if (!empty($endG)) {
                            
                            $stt = ($endG['success'] ?? false) ? 'ok' : 'err';
                            $rwr = $endG['data']['reward'] ?? null;
                            $cla = $endG['data']['power'] ?? null;
                            $msg = $cla ? "claimed $cla POWER" : ($endG['messages'][0] ?? 'unknown message');
                            
                            $this->logger($stt, "from game {$data['gameId']}", $msg);
                            if ($rwr) {
                                $this->logger('info', "ADDITIONAL REWARD OBTAINED", '');
                                print_r($endG);
                            }
                            
                            
                        }
                        
                        
                    }
                    styler("waiting for next games", fn() => _sle(rand(10, 13)));
                    
                }
                
                
                
                
                
                
                
                
            } else {
                var_dump($dash); die;
            }
            
            
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
    
    private function lmrGame($data, $cfg, $user) {
        
        #var_dump($cfg);
        #var_dump($data);
        
        $gameId = (int)$data['gameId'];
        $maxLvl = ($gameId === 6) ? 10 : 30;
        $level = min($maxLvl, max(1, (int)$data['level']));
        $userGameId = $cfg['data']['userGameId'];
        $userId = (string)$user;
    
        #if ($level >= $maxLvl) return null;
    
        $durG = [1 => 60, 2 => 40, 3 => 120, 4 => 120, 5 => 40, 6 => 60];
        $baseTime = $durG[$gameId] ?? 60;
        
        $isWin = rand(1, 100) > 15;
        $waitTime = $isWin ? (int)($baseTime * (rand(60, 75) / 100)) : $baseTime;
        
        styler("Completing game $gameId @ $level/$maxLvl ($waitTime.s)... ", fn() => _sle($waitTime));
        
        $maxPower = (function ($gid, $lvl) {
            switch ($gid) {
                case 1:
                    return min(316, 200 + ($lvl - 1) * 4);
                case 2:
                    return min(345, 316 + ($lvl - 1) * 1);
                case 3:
                    return min(610, (int)ceil(240 + ($lvl - 1) * (290 / 30)));
                case 4:
                    return min(400, 150 + ($lvl - 1) * 8);
                case 5:
                    return min(300, 180 + ($lvl - 1) * 4);
                case 6:
                    return min(416, 220 + ($lvl - 1) * 6);
                default:
                    return 200;
            }
        })($gameId, $level);
        
        $power = $isWin ? (int)($maxPower * (rand(95, 99) / 100)) : 0;
        
        $os = $this->get_OPS(Inf::$uagent);
        $tkn = '';
        if (!empty($cfg['data']['isWithCaptcha'])) {
            $tkn = Solve::tkn($this->api, $this->host, 'f44df123-e75f-4c2d-a318-4311cdd360a7', 'hc')['done'] ?? '';
        }
        
        $payload = [
            'userGameId' => $userGameId,
            'power' => $power,
            'winStatus' => $isWin ? 'WIN' : 'LOSE',
            'captchaToken' => $tkn,
            'operatingSystem' => $os,
            'possibleScriptedMovements' => rand(0, 3)
        ];
        #var_dump($payload);
        
        return $this->_cjs($userId, json_encode($payload));
    }
    
    private function _cjs($pass, $data) {
        $salt = openssl_random_pseudo_bytes(8);
        $derived = $this->_evp($pass, $salt);
        
        $encrypted = openssl_encrypt(
            $data, 
            'AES-256-CBC', 
            $derived['key'], 
            OPENSSL_RAW_DATA, 
            $derived['iv']
        );
        
        return base64_encode("Salted__" . $salt . $encrypted);
    }
    
    private function _evp($pass, $salt, $keyLen = 32, $ivLen = 16) {
        $derivedKey = '';
        $lastHash = '';
        
        while (strlen($derivedKey) < $keyLen + $ivLen) {
            $lastHash = md5($lastHash . $pass . $salt, true);
            $derivedKey .= $lastHash;
        }
        
        return [
            'key' => substr($derivedKey, 0, $keyLen),
            'iv' => substr($derivedKey, $keyLen, $ivLen)
        ];
    }

    
    
    
})->exec();


