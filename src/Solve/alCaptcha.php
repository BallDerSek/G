<?php

final class alCaptcha {
    
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
        
        $_H = 'https://adslab.me/api/'.$_T;
        
        /*
        $wdgt = $_W ?? $_H."/alcaptcha/widget.js?v=".intval(microtime(true));
        $wjs = Net::C($wdgt, 'GET', null, $this->ck, [], $this->host, $this->ua);
        _put('w.js', $wjs); die;
        */
        
        $cc_po = [
            'sitekey' => $_K,
            'domain' => parse_url($this->host)['host'] ?? '',
            'subid' => $_I
        ];
        $cc_0 = json_decode(Net::S(
            $_H."/alcaptcha/init", 'POST', $cc_po, json: 1
        )?: '', 1);
        #var_dump($cc_0);
        
        #$wwjs = Net::S('https://adslab.me', 'GET', $cc_po);
        #_put('ww.html', $wwjs);
        
        $soll = null;
        if ($cc_0 && isset($cc_0['captchaUrl'])) {
            $sett = microtime(1);
            #var_dump($cc_0); #die;
            
            #$img = Net::C($cc_0['captchaUrl'], 'GET', null, $this->ck, [], $this->host, $this->ua);
            
            $cc_po = null;
            $img = _get($cc_0['captchaUrl']);
            if ($img !== null) {
                #_put('img.png', $img);
                $jawaban = Solve::img($api, $this->host, 'adslab', $img);
                if (isset($jawaban['trouble'])) return $soll;
                
                if (is_string($jawaban) && str_contains($jawaban, 'idx=')) {
                    $matches = Scraper::_jP($jawaban, '/idx=(\d+)/');
                    $angka = array_map('intval', $matches[1] ?? []);
                } else {
                    $ans = Scraper::_jP($jawaban, '/\d+/');
                    $angka = array_map('intval', ($ans[0] ?? []));
                }
            
                #var_dump($jawaban, $angka);
            
                $endd = microtime(1);
            
                $cc_po = [
                    'token' => $cc_0['token'],
                    'answer' => $angka,
                    'hp_field' => '',
                    'hp_time' => intval(($endd - $sett) * 1000),
                    'deviceTimezone' => TIMEZONE()
                ];
            }
            
            if ($cc_po) {
                $cc_1 = json_decode(Net::S(
                    $_H."/alcaptcha/verify-click", 'POST', $cc_po, json: 1
                ) ?: '', 1);
                if ($cc_1 && $cc_1['success']) {
                    #var_dump($cc_1);
                    
                    $soll = [
                        'alcaptcha-response' => $cc_1['token'] ?? $cc_0['token']
                    ];
                    
                    
                }
                
            }
            
        } else {
            Logger::X('info', "\rSolve [ ".static::class.' ] ', false, 1);
            Logger::X('err', $cc_0['message'] ?? 'unknown error');
        }
        
        #var_dump($soll);
        return $soll;
        
    }
    
}
