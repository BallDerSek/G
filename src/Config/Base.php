<?php

trait Base {
    #protected bool $fetched = true;
    
    protected function logger($info, $state = null, $msg = null, $fatal = false, $mail = null) {
        
        $email = $mail ?? $this->mail;
        
        $state ??= static::class;
        Logger::M($email, (!$fatal || !empty($mail)));
        Logger::X($info, "$state ", false, true);
        
        if ($msg) Logger::G(0, strlen($msg) > 40 ? substr($msg, 0, 40) . '...' : "$msg");
        if ($fatal) die;
        
        #if ($this->api instanceof Provider) ($this->api)->getInfo();
        
    }
    
    protected function checkATB(&$err, $html) {
        if ($html && (stripos($html, 'nvalid Anti-Bot') !== false || stripos($html, 'Invalid AntiBot') !== false)) {
            $err++;
            return true;
        }
        return false;
    }
    
    protected function parseHtml($html) {
        $alert_d = Scraper::_jP($ve, '/type:\s*["\']([^"\']+)["\'],\s*message:\s*["\']([^"\']+)["\']/s');
        $m = Scraper::_jP($cla, '/type:\s*["\']([^"\']+)["\'],\s*message:\s*["\']([^"\']+)["\']/s');
        
        $m = Scraper::_jP($ve, "/Swal\.fire\(\s*\{.*?title:\s*'([^']+)'.*?text:\s*'([^']+)'.*?icon:\s*'([^']+)'/s");
        $_ald = Scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
        $_sucS = Scraper::_jP($ve, "/Swal\.fire\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'<]+)/i");
        $_suc = Scraper::_jP($cla, "/Toast\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
        
        $alert_d = scraper::_xP($ve, "//div[contains(@class, 'alert-danger')]");
        $m = scraper::_jP($cla, "/Swal\.fire\(\{.*?title\s*:\s*(['\"])(.*?)\\1.*?\}\)/s");
        
        $msg_d = Scraper::_jP($ve, '/icon:\s*[\'"]([^\'"]+)[\'"]\s*,\s*title:\s*[\'"]([^\'"]+)[\'"]\s*,\s*text:\s*[\'"]([^\'"]+)[\'"]/s');
        
    }
    
    protected function _cp($html = null) {
        
        if ($html) {
            $kyy = Capt::cha($html); 
            var_dump($kyy); 
        }
        
        $token = _rl('token: ');
        return [
            'g-recaptcha-response' => $token,
            'cf-turnstile-response' => $token,
            'h-captcha-response' => $token,
            'hcaptcha-response' => $token,
            'g-recaptcha-response-v3' => $token
        ];
    }
    
    protected function isBan($html) {
        if (!$html) return false;
        if (stripos($html, 'account has been banned')) $this->logger('err', "BANNED", 'Yahhh... Akun Banned Permanen!', 1);
        
        if (!stripos($html, 'Temporarily Blocked') && !stripos($html, 'Temporary Ban') && !stripos($html, 'temporarily locked')) return false;
    
        $countdownText = Scraper::_xP($html, "//*[@id='block-countdown']")[0] ?? '';
        
        $m = 0; 
        $s = 0;
        if (preg_match('/(\d+)\s*minute/', $countdownText, $matchM)) $m = (int)$matchM[1];
        if (preg_match('/(\d+)\s*second/', $countdownText, $matchS)) $s = (int)$matchS[1];
    
        $r = Scraper::_xP($html, "//div[contains(@class, 'alert-danger')]//p[1]")[0] ?? 'CAPTCHA failed';
    
        return [
            'ti' => trim($r),
            'tmr' => sprintf('%02d:%02d', $m, $s),
            'sleep' => ($m * 60) + $s + 5 
        ];
    }
    
    protected function checkCF(&$hh, $url = '', $body = null, $ads = false, $payload = null) {
        
        $html = $body['body'] ?? null;
        $code = $body['http_code'] ?? null;
        
        if (!$html || !$code) return null;
        
        if ($code === 200 || (stripos($html, 'Just a moment') === false)) return $html;
        
        $result = $this->_cf($hh, $url, $html, false, $ads, $payload);
        
        if (!$result) $result = $this->_cf($hh, $url, $html, true, $ads, $payload);
        
        return $result;
    }
    
    private function _cf(&$cookieHeader, $url, $html, $force, $ads, $payload) {
        
        $method = (!empty($payload) ? 'POST' : 'GET');
        
        $cf = Cloudflare::exec($this->api, $url, Inf::$cookie, Inf::$uagent, ['html' => $html, 'payload' => $payload], $force ? 1 : 0);
        
        if (!$cf) return null;
        [$hhh, $ua] = $cf;
        
        if ($ads) {
            $cookies = $this->adcookie(true);
            foreach ($cookies as $name => $value) Inf::injectCookie(Inf::$cookie, $value, $this->host, $name);
            foreach ($cookies as $key => $value) $hh[$key] = $value;
        }
        
        foreach ($hhh as $key => $value) $hh[$key] = $value;
        
        Config::resetUA();
        Inf::setup($ua, Inf::$cookie);
        if (empty($hh)) return null;
        
        $cookieHeader = Inf::netHead($hh);
        
        for ($try = 1; $try <= 3; $try++) {
            _sle(3);
            $fix = Net::X($url, $method, null, Inf::$cookie, $cookieHeader, $url, Inf::$uagent, d: true);
            #var_dump($fix); #die;
            
            if (!empty($fix) && isset($fix['http_code'])) {
                $_c = $fix['http_code'];
                $_b = $fix['body'];
                if ($_c === 200 || (stripos($_b, 'Just a moment') === false && stripos($_b, 'Attention Required!') === false)) {
                    Config::resetUA();
                    $this->acc['ua'] = $ua;
                    $this->refreshFingerprint();
                    return $_b;
                }
            }
            $this->logger('err', 'Cloudflare', "try-{$try} fail");
        }
        
        return null;
    }
    
    public function adcookie($refresh = false) {
        _sle(3);
        static $cached = null;
        if ($cached && !$refresh) return $cached;
        
        $now = time();
        
        static $clientId = null;
        if (!$clientId) $clientId = 691235932;
        
        static $sessionStart = null;
        if (!$sessionStart) $sessionStart = $now;
        
        static $sessionCount = 1;
        $gaSession = 's' . $sessionStart . '$o' . $sessionCount++ . '$g1$t' . $now . '$j' . rand(10, 30) . '$l0$h0';
        
        $popTime = $now + rand(3600, 3660);
        $data_pop = '992-1-' . $popTime;
        
        $cpcTime = $now + rand(3600, 3660);
        $data_cpc = '632-1-' . $cpcTime;
        
        static $cc_pu = null;
        if (!$cc_pu) $cc_pu = '9a36387661f7b638';
        
        static $bitmedia_fid = null;
        if (!$bitmedia_fid) {
            $fid = bin2hex(random_bytes(15));
            $fidnoua = bin2hex(random_bytes(16));
            $payload = ['fid' => $fid, 'fidnoua' => $fidnoua];
            $bitmedia_fid = base64_encode(json_encode($payload));
        }
        
        $cached = [
            'clever-counter-105662' => '0-1',
            'ads-counter-105739' => '0-1',
            '_ga' => 'GA1.1.' . $clientId . '.' . $now,
            '_ga_8MW4PHBZKX' => 'GS2.1.' . $gaSession,
            '_data_pop' => $data_pop,
            '_data_cpc' => $data_cpc,
            'pop_delay_12321' => '1',
            'cc_pu' => $cc_pu,
            'bitmedia_fid' => $bitmedia_fid
        ];
        
        return $cached;
    }

    private function fetch() {
        
        if ($this->fetched) {
            Proxy::load();
            Check::Geo();
            $this->fetched = false;
        }
        
    }
    
    protected function refreshFingerprint() {
        $traits = class_uses($this);
        if (!isset($traits['Mimic'])) return;
        
        if (!isset($this->acc['ua'])) return;
        
        if (method_exists($this, 'generateFingerprint')) {
            $this->generateFingerprint($this->acc['ua']);
        }
        
        if (method_exists($this, 'gen_fphash')) {
            $this->headersCF = array_merge($this->headersCF, [
                'X-Fingerprint' => base64_encode(json_encode($this->browserFingerprint ?? [])),
                'X-FP-Hash' => $this->gen_fphash(),
                'X-FP-Data' => $this->gen_fpdata()
            ]);
        }
    }
    
}
