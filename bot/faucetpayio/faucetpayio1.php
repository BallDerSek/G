<?php

class faucetpay {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = "https://faucetpay.io";
    private string $app = "https://api.faucetpay.io";
    private string $ip = '';
    private string $domain;
    
    private string $x_Key, $y_Key;
    
    public function __construct() {
        $this->api = onKeys();
        if (!($this->api instanceof skibidixxx)) die(Logger::X('err', 'pilih api skibidixxx'));
        
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        logx('err', "\nneed detailed Info to prevent suspicious session, and email otp if possible");
        logx('err', "gmxch api is also can get this digital key, (not recommended for prevent soft ban (ip binding))");
        
        $this->acc = config::Credential([], true);
        $this->x_Key = $this->acc['x_digital_key'];
        $this->y_Key = $this->acc['y_digital_key'];
        
        Inf::setup(
            $this->acc['user_agent'],
            Config::cookie($this->mail)
        );
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', "use with caution");
        $b->task2('ok', "");
    }
    
    public function exec() {
        
    }
    
    private function sendB($akun, $app) {
        
        $b = $this->banner;
        $b->show();
        $bal = $this->_getBal($akun, $this->host, $this->app, true);
        $b->task1('Info', 'INPUT RECEIVER EMAIL');
        $b->task2('err', 'USE WITH CAUTION, ALWAYS CHECK ADDRESS');
        
        if (!empty($bal)) {
        
            $tf = _rl('INPUT RECEIVER: ');
            foreach ($bal as $acc) {
                $b->show();
                $_M = $acc['mail'];
                $_P = $acc['pass'];
                $_A = $acc['auth'];
                $_E = $acc['etag'];
                $_B = $acc['balances'];
                
                if ($_M === $tf) continue;
                
                foreach ($_B as $_C => $_J) {
                    $b->task1('Info', "from $_M to $tf");
                    
                    $jmlh = rtrim(rtrim(sprintf("%.10f", (float)$_J), '0'), '.');
                    
                    $b->task2('ok', "amount $jmlh ($_C)");
                    
                    $_H = ["authorization: Bearer $_A"];
                    $_P = [
                        'coin' => $_C,
                        'amount' => $jmlh,
                        '2fa_code' => '',
                        'user' => $tf
                    ];
                    
                    $send = json_decode(Net::X($app.'/transfer/send', 'POST', $_P, Inf::$cookie, $_H, $host, Inf::$uagent, json: true)?: '', 1)['message'] ?? null;
                    
                    if (!empty($send)) {
                        $this->logger('ok', null, $send, 0, $acc['mail']);
                        $this->logger('ok', '',  $lo['message']);
                        Logger::M($acc['mail'], false);
                        Logger::X('Info', $send, true, true);
                    }
                    _sle(5);
                    
                }
                
            }
        
        }
        
    }
    
    private function _getBer($akun, $api, $host) {
        
        $payload = [
            'user_email' => $akun['mail'],
            'password' => $akun['pass'],
            'captcha_response' => $this->_getTKN($api)['token'] ?? '',
            'x_digital_key' => $this->x_Key,
            'y_digital_key' => $this->y_Key,
        ];
        
        $lo = json_decode(Net::X($host.'/account/login', 'POST', $payload, Inf::$cookie, reff: $host.'/login', ua: Inf::$uagent, json: true)?: '', 1);
        
        #var_dump($lo);
        
        if ($lo && isset($lo['token'])) {
            $this->logger('ok', null, $lo['message'], 0, $akun['mail']);
            return [$lo['token'], $lo['etag']];
        } else {
            var_dump($lo);
            die;
        }
        
        
        
    die;
    }
    
    private function _getTKN($api) {
        
        $cap = $this->api->run('faucetpay', [
            'sitekey' => 'a3760bfe5cf4254b2759c19fb2601667',
            'domain' => $this->host,
        ])['done'] ?? '';
        
        if (str_starts_with($cap, 'cap')) {
            $token = trim(str_replace('cap_res:', '', $cap));
            return ['token' => $token];
        }
        
        return [];
    }
    
    private function _getBal($akun, $coinsOnly = false) {
        $b = Banner::getInstance();
        $b->task1('ok', '');
        $b->task2('ok', '');
        
        $available = [];
        $filteredAkun = [];
        
        foreach ($akun as &$acc) {
            $b->task1('Info', 'fetching balance, please wait');
            
            $bearer = ['authorization: Bearer '.$acc['auth']];
            $Info = json_decode(Net::X($this->app.'/wallet/get-Information', 'GET', null, Inf::$cookie, $bearer, $this->host, Inf::$uagent)?: '', 1)['data'] ?? null;
            
            if (empty($Info)) continue;
            
            if ($coinsOnly) {
                $userBalances = [];
                foreach ($Info['coin_balances'] as $coin) {
                    $bal = (float)$coin['balance'];
                    if ($bal > 0.00000100) {
                        $userBalances[$coin['coin']] = $bal;
                    }
                }
                
                if (!empty($userBalances)) {
                    $acc['balances'] = $userBalances;
                    $acc['total_balance'] = array_sum($userBalances);
                    $filteredAkun[] = $acc;
                }
            } else {
                #print_r($Info);
                $saldo = (float)($Info['statistics']['portfolio_value'] ?? 0);
                if ($saldo > 0) {
                    $acc['balance'] = $saldo;
                    $filteredAkun[] = $acc;
                    
                    $emailLen = strlen($acc['mail']);
                    $padding = 23 - $emailLen;
                    if ($padding < 0) $padding = 0;
                    
                    Logger::M(" ".$acc['mail'], false);
                    Logger::X('Info', sprintf(str_repeat(' ', $padding) . "[ balance: %-10.8f USD ]", $saldo), true, true);
                }
            }
        }
        unset($acc);
        
        $akun = $filteredAkun;
        
        if ($coinsOnly) return $akun;
    
        $b->task1('ok', 'all accounts fetched');
        _rl('    enter to continue...');
        
        return $akun;
    }

    
}

