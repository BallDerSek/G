<?php

trait Base {
    
    protected function logger($info, $state = null, $msg = null, $fatal = false, $mail = null) {
        
        $email = $mail ?? $this->mail;
        
        $state ??= static::class;
        Logger::M($email, (!$fatal || !empty($mail)));
        Logger::X($info, "$state ", false, true);
        
        if ($msg) Logger::G(0, "$msg");
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
    
    private function _djb2($str) {
        $hash = 5381;
        for ($i = (strlen($str) - 1); $i >= 0; $i--) $hash = ((($hash * 33) & 0xFFFFFFFF) ^ ord($str[$i])) & 0xFFFFFFFF;
        return base_convert(sprintf('%u', $hash & 0xFFFFFFFF), 10, 16);
    }
    
    private function genFPS($ua) {
        $isMobile = $this->_uaMobile($ua);
        
        if ($isMobile) {
            $s_W = [360, 375, 390, 393, 414, 428];
            $s_H = [664, 667, 740, 780, 844, 852, 896, 926];
        } else {
            $s_W = [1024, 1280, 1366, 1440, 1536, 1600, 1920, 2560];
            $s_H = [768, 820, 834, 900, 1024, 1080, 1180, 1194, 1200, 1366, 1440, 1600];
        }
        
        $sw = $s_W[array_rand($s_W)];
        $sh = $s_H[array_rand($s_H)];
        
        if ($isMobile) {
            $sw = min($sw, 428);
            $sh = min($sh, 926);
        }
        
        $glRenderers = $this->_glRender($ua, $isMobile);
        $selectedGl = $glRenderers[array_rand($glRenderers)];
        
        $platform = $this->_uaPlatform($ua, $isMobile);
        $deviceMemory = $this->_uaMemory($ua, $isMobile);
        $hardwareConcurrency = $this->_hwConcur($ua, $isMobile);
        
        $this->fingerprintData = [
            'isMobile' => $isMobile,
            'screenWidth' => $sw,
            'screenHeight' => $sh,
            'innerWidth' => $isMobile ? $sw : $sw - rand(0, 100),
            'innerHeight' => $isMobile ? $sh - rand(50, 150) : $sh - rand(50, 200),
            'glRenderer' => $selectedGl,
            'hardwareHash' => $this->_hwHashed($isMobile, $sw, $sh, $selectedGl, $ua, $platform, $deviceMemory, $hardwareConcurrency),
            'webdriver' => 0,
            'timezone' => TIMEZONE(),
            'languages' => LANGUAGE(),
            'platform' => $platform,
            'deviceMemory' => $deviceMemory,
            'hardwareConcurrency' => $hardwareConcurrency
        ];
        
    }
    
    private function _uaMobile($ua) {
        if (stripos($ua, 'iPad') !== false || stripos($ua, 'Tablet') !== false || stripos($ua, 'PlayBook') !== false) return true;
        
        $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPod', 'BlackBerry', 'Windows Phone', 'webOS'];
        
        foreach ($mobileKeywords as $keyword) if (stripos($ua, $keyword) !== false) return true;
        
        return false;
    }
    
    private function _uaPlatform($ua, $isMobile) {
        if (stripos($ua, 'Windows') !== false) {
            return 'Win32';
        } elseif (stripos($ua, 'Mac') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            return 'MacIntel';
        } elseif (stripos($ua, 'Linux') !== false || stripos($ua, 'Android') !== false) {
            return $isMobile ? 'Linux armv8l' : 'Linux x86_64';
        } elseif (stripos($ua, 'CrOS') !== false) {
            return 'Chrome OS';
        }
        return $isMobile ? 'Linux armv8l' : 'Win32';
    }
    
    private function _uaMemory($ua, $isMobile) {
        if ($isMobile) $memoryOptions = [2, 3, 4, 6, 8, 12];
        else $memoryOptions = [4, 8, 16, 24, 32, 64];
        
        return $memoryOptions[array_rand($memoryOptions)];
    }
    
    private function _glRender($ua, $isMobile) {
        if ($isMobile) {
            if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
                return [
                    'ANGLE (Apple, Apple M1, OpenGL 4.1)',
                    'ANGLE (Apple, Apple M2, OpenGL 4.1)',
                    'ANGLE (Apple, Apple A15, OpenGL ES 3.2)'
                ];
            } elseif (stripos($ua, 'Android') !== false) {
                return [
                    'ANGLE (ARM, Mali-G57, OpenGL ES 3.2)',
                    'ANGLE (ARM, Mali-G78, OpenGL ES 3.2)',
                    'ANGLE (ARM, Mali-G710, OpenGL ES 3.2)',
                    'ANGLE (Qualcomm, Adreno 650, OpenGL ES 3.2)',
                    'ANGLE (Qualcomm, Adreno 660, OpenGL ES 3.2)',
                    'ANGLE (Qualcomm, Adreno 730, OpenGL ES 3.2)'
                ];
            }
            return ['ANGLE (ARM, Mali-G57, OpenGL ES 3.2)'];
        }
        
        if (stripos($ua, 'Mac') !== false) {
            return [
                'ANGLE (Apple, Apple M1, OpenGL 4.1)',
                'ANGLE (Apple, Apple M2, OpenGL 4.1)',
                'ANGLE (Apple, Apple M3, OpenGL 4.1)'
            ];
        }
        
        return [
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3060, OpenGL 4.5)',
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3070, OpenGL 4.5)',
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3080, OpenGL 4.6)',
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 3090, OpenGL 4.6)',
            'ANGLE (NVIDIA, NVIDIA GeForce RTX 4070, OpenGL 4.6)',
            'ANGLE (AMD, AMD Radeon RX 6800, OpenGL 4.6)',
            'ANGLE (AMD, AMD Radeon RX 6900 XT, OpenGL 4.6)',
            'ANGLE (AMD, AMD Radeon RX 7900 XT, OpenGL 4.6)',
            'ANGLE (Intel, Intel Iris Xe Graphics, OpenGL 4.6)',
            'ANGLE (Intel, Intel UHD Graphics, OpenGL 4.6)'
        ];
    }
    
    private function _hwConcur($ua, $isMobile) {
        if ($isMobile) $cores = [4, 6, 8, 10];
        else $cores = [4, 6, 8, 10, 12, 16, 20, 24];
        
        return $cores[array_rand($cores)];
    }
    
    private function _hwHashed($i, $w, $h, $gl, $ua, $pf, $m, $hw) {
        $hwDetails = [
            'isMobile' => $i,
            'gl' => $gl,
            'sw' => $w,
            'sh' => $h,
            'wd' => false,
            'chr' => true,
            'ua' => $ua,
            'platform' => $pf,
            'deviceMemory' => $m,
            'hardwareConcurrency' => $hw
        ];
        
        return $this->_djb2(json_encode($hwDetails, JSON_UNESCAPED_SLASHES));
    }
    
    private function getFPS($ua) {
        if (empty($this->fingerprintData)) $this->genFPS($ua);
        
        return [
            'fingerprint' => [
                'hardware_hash' => $this->fingerprintData['hardwareHash'] ?? '',
                'webdriver' => $this->fingerprintData['webdriver'] ?? 0,
                'screen_width' => $this->fingerprintData['screenWidth'] ?? 1920,
                'screen_height' => $this->fingerprintData['screenHeight'] ?? 1080,
                'inner_width' => $this->fingerprintData['innerWidth'] ?? 1920,
                'inner_height' => $this->fingerprintData['innerHeight'] ?? 1080,
                'timezone' => TIMEZONE(),
                'languages' => LANGUAGE(),
                'platform' => $this->fingerprintData['platform'] ?? 'Win32',
                'device_memory' => $this->fingerprintData['deviceMemory'] ?? 8,
                'hardware_concurrency' => $this->fingerprintData['hardwareConcurrency'] ?? 8,
                'gl_renderer' => $this->fingerprintData['glRenderer'] ?? '',
                'is_mobile' => $this->fingerprintData['isMobile'] ?? false
            ]
        ];
    }
    
    private function retFPS() {
        $this->genFPS($this->acc['ua'] ?? Inf::$uagent);
        
        if (!empty($this->headersCF)) {
            $this->headersCF['X-Fingerprint'] = $this->fingerprintData['hardwareHash'] ?? '';
            $this->headersCF['X-Screen-Info'] = "{$this->fingerprintData['screenWidth']}x{$this->fingerprintData['screenHeight']}";
        }
    }
    
}
