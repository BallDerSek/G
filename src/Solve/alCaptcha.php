<?php

final class alCaptcha {
    
    private static $attempts = [];
    
    private string $host, $ua, $ck, $ip;
    private bool $in;
    private string $html, $id;
    
    public function __construct(array $ctx) {
        
        $this->host = $ctx['host'] ?? '';
        $this->html = $ctx['html'] ?? '';
        $this->ua = $ctx['uagent'] ?? '';
        $this->ck = $ctx['cookie'] ?? null;
        $this->in = $ctx['ins'] ?? false;
        $this->ip = $ctx['ip'] ?? '';
        $this->id = $ctx['id'] ?? '';
        
    }
    
    public function exec($alc, $api, $html = null) {
        #var_dump($alc);
        
        $_I = $alc['sid'] ?? 'widget_user';
        $_T = $alc['version'] ?? 'v1';
        $_D = $alc['extra'] ?? null;
        $_K = $alc['keys'] ?? null;
        $_W = $_D['js'] ?? null;
        if (!$_K) return false;
        
        $cacheKey = md5($_K . $this->host);
        self::$attempts[$cacheKey] = (self::$attempts[$cacheKey] ?? 0) + 1;
        $force = self::$attempts[$cacheKey] >= 3;
        
        // Logger::X('info', "Attempt #" . self::$attempts[$cacheKey] . " Force: " . ($force ? 'YES' : 'NO'));
        
        $_H = 'https://adslab.me/api/'.$_T;
        
        $cc_po = [
            'sitekey' => $_K,
            'domain' => parse_url($this->host)['host'] ?? '',
            'subid' => $_I
        ];
        $cc_0 = json_decode(Net::X(
            $_H."/alcaptcha/init", 'POST', $cc_po,
            null, [], $this->host, $this->ua, json: 1
        )?: '', 1);
        
        $soll = null;
        if ($cc_0 && isset($cc_0['captchaUrl'])) {
            $sett = microtime(1);
            
            $cc_po = null;
            $img = _get($cc_0['captchaUrl']);
            if ($img !== null) {
                $jawaban = $this->_solve($api, $img, $force);
                #var_dump($jawaban);
                
                if (isset($jawaban['trouble'])) {
                    if (self::$attempts[$cacheKey] >= 3) {
                        unset(self::$attempts[$cacheKey]);
                    }
                    return $soll;
                }
                
                if (is_string($jawaban) && str_contains($jawaban, 'idx=')) {
                    $matches = Scraper::_jP($jawaban, '/idx=(\d+)/');
                    $angka = array_map('intval', $matches[1] ?? []);
                } else {
                    $ans = Scraper::_jP($jawaban, '/\d+/');
                    $angka = array_map('intval', ($ans[0] ?? []));
                }
                
                $clk = [];
                $total = count($angka);
                for ($i = 0; $i < $total; $i++) {
                    if ($i === 0) {
                        $clk[] = intval(microtime(true) * 1000);
                    } else {
                        usleep(rand(1200, 1800) * 1000);
                        $clk[] = intval(microtime(true) * 1000);
                    }
                }
                
                $endd = microtime(true);
                
                $cc_po = [
                    'token' => $cc_0['token'],
                    'answer' => $angka,
                    'hp_field' => '',
                    'hp_time' => intval(($endd - $sett) * 1000),
                    'deviceTimezone' => TIMEZONE(),
                    'mouseMoves' => count($angka) + rand(0, 3),
                    'clickTimes' => $clk
                ];
                
            }
            
            if ($cc_po) {
                $cc_1 = json_decode(Net::X(
                    $_H."/alcaptcha/verify-click", 'POST',
                    $cc_po, null, [],
                    $this->host, $this->ua, json: 1
                ) ?: '', 1);
                #var_dump($cc_1);
                if ($cc_1 && $cc_1['success']) {
                    $soll = [
                        'alcaptcha-response' => $cc_1['token'] ?? $cc_0['token']
                    ];
                    
                    unset(self::$attempts[$cacheKey]);
                }
            }
            
        } else {
            Logger::X('info', "\rSolve [ ".static::class.' ] ', false, 1);
            Logger::X('err', $cc_0['message'] ?? 'unknown error');
            
            unset(self::$attempts[$cacheKey]);
        }
        
        if (isset(self::$attempts[$cacheKey]) && self::$attempts[$cacheKey] >= 3) {
            unset(self::$attempts[$cacheKey]);
        }
        
        return $soll;
        
    }
    
    private function _solve($api, $img, $force = false) {
        return Solve::img($api, $this->host, 'adslab', $img, [], $force);
    }
    
}