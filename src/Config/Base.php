<?php

trait Base {
    
    protected function logger($info, $state = null, $msg = null, $fatal = false, $mail = null) {
        
        $email = $mail ?? $this->mail;
        
        $state ??= static::class;
        Logger::M($email, (!$fatal || !empty($mail)));
        Logger::X($info, "$state ", false, true);
        
        if ($msg) Logger::G(0, "$msg");
        if ($fatal) die;
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

#legacy
    protected function checkCF0(&$hh, $url = '', $body = null) {
        
        $html = $body['body'] ?? null;
        $code = $body['http_code'] ?? null;
        
        if (!$html || !$code) return null;
        
        if ($code !== 200 && (stripos($html, 'Just a moment') !== false)) {
            
            $cf = Cloudflare::exec($this->api, $url, Inf::$cookie, Inf::$uagent, ['html' => $html], 1);
            #var_dump($cf);
            
            if ($cf) {
                [$hh, $ua] = $cf;
                Inf::setup($ua, Inf::$cookie);
                
                if (!empty($hh)) {
                    for ($try = 1; $try <= 3; $try++) {
                        _sle(3);
                        $fix = Net::X($url, 'GET', null, Inf::$cookie, $hh, $url, Inf::$uagent, d: true);
                        #var_dump($fix);
                        
                        if (!empty($fix) && isset($fix['http_code'])) {
                            $_c = $fix['http_code'];
                            $_b = $fix['body'];
                            
                            if ($_c === 200 && stripos($_b, 'Just a moment') === false && stripos($_b, 'Attention Required!') === false) {
                                $this->acc['ua'] = $ua;
                                return $_b;
                            }
                        }
                        $this->logger('err', 'Cloudflare', "try-{$try} fail, reloading");
                    }
                }
            }
        } 
        
        return $html ?? null;
    }
#legacy
    
    protected function checkCF(&$hh, $url = '', $body = null) {
        $html = $body['body'] ?? null;
        $code = $body['http_code'] ?? null;
        
        if (!$html || !$code) return null;
        
        if ($code === 200 || stripos($html, 'Just a moment') === false) return $html;
        
        $result = $this->_cf($hh, $url, $html, false);
        
        if (!$result) $result = $this->_cf($hh, $url, $html, true);
        
        return $result;
    }
    
    private function _cf(&$hh, $url, $html, $fallback) {
        $cf = Cloudflare::exec($this->api, $url, Inf::$cookie, Inf::$uagent, ['html' => $html], $fallback ? 1 : 0);
        
        if (!$cf) return null;
        #var_dump($cf);
        
        [$hh, $ua] = $cf;
        Inf::setup($ua, Inf::$cookie);
        
        if (empty($hh)) return null;
        
        for ($try = 1; $try <= 3; $try++) {
            _sle(3);
            $fix = Net::X($url, 'GET', null, Inf::$cookie, $hh, $url, Inf::$uagent, d: true);
            #var_dump($fix);
            
            if (!empty($fix) && isset($fix['http_code'])) {
                $_c = $fix['http_code'];
                $_b = $fix['body'];
                
                if ($_c === 200 && stripos($_b, 'Just a moment') === false && stripos($_b, 'Attention Required!') === false) {
                    $this->acc['ua'] = $ua;
                    return $_b;
                }
            }
            $this->logger('err', 'Cloudflare', "try-{$try} fail");
        }
        
        return null;
    }
    
}