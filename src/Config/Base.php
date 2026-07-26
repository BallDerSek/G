<?php

trait Base {
    
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
        if (stripos($html, 'account has been banned')) {
            $this->logger('err', "BANNED", 'Yahhh... Akun Banned Permanen!', 1);
        }
        
        if (!stripos($html, 'Temporarily Blocked') && !stripos($html, 'Temporary Ban') && !stripos($html, 'temporarily locked')) {
            return false;
        }
    
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
    
    protected function checkCF(&$hh, $url = '', $body = null, $ads = false) {
        $html = $body['body'] ?? null;
        $code = $body['http_code'] ?? null;
        
        if (!$html || !$code) return null;
        
        if ($code === 200 || stripos($html, 'Just a moment') === false) return $html;
        
        $result = $this->_cf($hh, $url, $html, false, $ads);
        
        if (!$result) $result = $this->_cf($hh, $url, $html, true, $ads);
        
        return $result;
    }
    
    private function _cf(&$cookieHeader, $url, $html, $force, $ads = false) {
        
        $cf = Cloudflare::exec($this->api, $url, Inf::$cookie, Inf::$uagent, ['html' => $html], $force ? 1 : 0);
        
        if (!$cf) return null;
        [$hhh, $ua] = $cf;
        
        if ($ads) {
            $cookies = $this->adcookie(true);
            foreach ($cookies as $name => $value) {
                Inf::injectCookie(Inf::$cookie, $value, $this->host, $name);
            }
            
            foreach ($cookies as $key => $value) {
                $hh[$key] = $value;
            }
        }
        
        foreach ($hhh as $key => $value) {
            $hh[$key] = $value;
        }
        
        Inf::setup($ua, Inf::$cookie);
        if (empty($hh)) return null;
        
        $cookieHeader = Inf::netHead($hh);
        
        for ($try = 1; $try <= 3; $try++) {
            _sle(3);
            $fix = Net::X($url, 'GET', null, Inf::$cookie, $cookieHeader, $url, Inf::$uagent, d: true);
            
            if (!empty($fix) && isset($fix['http_code'])) {
                $_c = $fix['http_code'];
                $_b = $fix['body'];
                if ($_c === 200 || (stripos($_b, 'Just a moment') === false && stripos($_b, 'Attention Required!') === false)) {
                    $this->acc['ua'] = $ua;
                    return $_b;
                }
            }
            $this->logger('err', 'Cloudflare', "try-{$try} fail");
        }
        
        return null;
    }
    
    public function adcookie($refresh = false) {
        static $cached = null;
        
        if ($cached && !$refresh) return $cached;
        
        $now = time();
        
        static $clientId = null;
        if (!$clientId) $clientId = 896547868;
        
        static $gaSession = null;
        if (!$gaSession) $gaSession = 's' . $now . '$o26$g1$t' . $now . '$j' . rand(40, 60) . '$l0$h0';
        
        $popAds = ['992-1-' . ($now + rand(0, 300)), '994-1-' . ($now + rand(0, 300)), '995-1-' . ($now + rand(0, 300))];
        $data_pop = implode('_', $popAds);
        
        $cpcAds = [
            '894-2-' . ($now + rand(0, 300)),
            '1001-1-' . ($now + rand(0, 300)),
            '1067-4-' . ($now + rand(0, 300)),
            '1068-2-' . ($now + rand(0, 300)),
            '1174-3-' . ($now + rand(0, 300))
        ];
        $data_cpc = implode('_', $cpcAds);
        
        static $cc_pu = null;
        if (!$cc_pu) $cc_pu = '9a36387661f7b638';
        
        $cached = [
            '_ga' => 'GA1.1.' . $clientId . '.' . $now,
            '_ga_8MW4PHBZKX' => 'GS2.1.' . $gaSession,
            '_data_pop' => $data_pop,
            '_data_cpc' => $data_cpc,
            'cc_pu' => $cc_pu,
            'bitmedia_fid' => 'eyJmaWQiOiIxMzBlY2JiMDAxMGIwMGMwNzI4MDgxZWNkYjA5ZmMyZCIsImZpZG5vdWEiOiJkZTg5NDM4ODFmYWNlNTI3ZGQxMDVhYTI2YTYwZGEzOCJ9'
        ];
        
        return $cached;
    }
    
    
}
