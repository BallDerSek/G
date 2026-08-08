<?php

trait Mimic {
    protected array $browserFingerprint = [];
    protected array $navigatorFingerprint = [];
    protected array $screenFingerprint = [];
    protected array $timezoneFingerprint = [];
    protected array $canvasFingerprint = [];
    protected array $webglFingerprint = [];
    protected array $audioFingerprint = [];
    protected array $fontFingerprint = [];
    protected array $behaviorFingerprint = [];
    
    protected function generateFingerprint($userAgent) {
        $this->_nvgator($userAgent);
        $this->_screen($userAgent);
        $this->_tzone();
        $this->_canvas();
        $this->_webGL($userAgent);
        $this->_audio();
        $this->_fonts($userAgent);
        $this->_bhvior();
        $this->_basic($userAgent);
    }
    
    protected function _nvgator($ua) {
        $isMobile = $this->_mobile($ua);
        $isChrome = stripos($ua, 'Chrome') !== false;
        $isFirefox = stripos($ua, 'Firefox') !== false;
        $isSafari = stripos($ua, 'Safari') !== false && !$isChrome;
        
        // ✅ Pakai LANGUAGE() helper
        $lang = LANGUAGE();
        $langs = [$lang];
        $extra = ['en-US', 'en-GB'];
        shuffle($extra);
        $langs = array_merge($langs, array_slice($extra, 0, rand(0, 1)));
        
        $this->navigatorFingerprint = [
            'userAgent' => $ua,
            'platform' => $this->rand_pltform($ua),
            'vendor' => $this->rand_vendor($ua),
            'vendorSub' => '',
            'productSub' => $isChrome ? '20030107' : ($isFirefox ? '20100101' : ''),
            'product' => 'Gecko',
            'appName' => $isFirefox ? 'Netscape' : 'Netscape',
            'appVersion' => $this->rand_appver($ua),
            'appCodeName' => 'Mozilla',
            'language' => $lang,  // ← Dari helper
            'languages' => $langs, // ← Dari helper
            'cookieEnabled' => true,
            'doNotTrack' => $this->get_DNT(),
            'hardwareConcurrency' => $this->get_concur(),
            'deviceMemory' => $this->get_Dmem(),
            'maxTouchPoints' => $isMobile ? $this->get_Touch() : 0,
            'onLine' => true,
            'webdriver' => false,
            'pdfViewerEnabled' => true,
            'connection' => [
                'effectiveType' => $this->get_NET(),
                'rtt' => $this->getRTT(),
                'downlink' => $this->get_Dlink(),
                'saveData' => false
            ]
        ];
    }
    
    protected function _screen($ua) {
        $isMobile = $this->_mobile($ua);
        
        $res = $isMobile ? $this->res_mobiles() : $this->res_desktop();
        
        $this->screenFingerprint = [
            'width' => $res['width'],
            'height' => $res['height'],
            'availWidth' => $res['availWidth'],
            'availHeight' => $res['availHeight'],
            'colorDepth' => 24,
            'pixelDepth' => 24,
            'orientation' => [
                'type' => $isMobile ? 'portrait-primary' : 'landscape-primary',
                'angle' => 0
            ]
        ];
    }
    
    protected function _tzone() {
        $tz = TIMEZONE();
        $dt = new DateTime('now', new DateTimeZone($tz));
        
        $this->timezoneFingerprint = [
            'timezone' => $tz,
            'timezoneOffset' => $dt->getOffset() / 60,
            'dstOffset' => $this->get_tzone($tz),
            'timezoneName' => $dt->format('T'),
            'daylightSaving' => (bool) $dt->format('I')
        ];
    }
    
    protected function _canvas() {
        $this->canvasFingerprint = [
            'hash' => $this->gen_canvas(),
            'winding' => true,
            'geometry' => [
                'points' => 30,
                'complexity' => 3,
                'winding' => true
            ]
        ];
    }
    
    protected function _webGL($ua) {
        $this->webglFingerprint = $this->_mobile($ua) ? $this->wGL_mobiles() : $this->wGL_desktop();
    }
    
    protected function _audio() {
        $this->audioFingerprint = [
            'hash' => $this->gen_Audio(),
            'sampleRate' => 44100,
            'channelCount' => 2,
            'fftSize' => 2048,
            'audioContext' => [
                'latencyHint' => 'interactive',
                'sampleRate' => 44100,
                'state' => 'running'
            ]
        ];
    }
    
    protected function _fonts($ua) {
        $this->fontFingerprint = [
            'fonts' => $this->get_Fonts($ua),
            'hash' => $this->gen_Fonts($ua)
        ];
    }
    
    protected function _bhvior() {
        $this->behaviorFingerprint = [
            'mouseMovement' => $this->gen_Mouse(),
            'typingSpeed' => $this->gen_Type(),
            'scrollBehavior' => $this->gen_Scroll(),
            'clickPattern' => $this->gen_Click(),
            'keystrokeDelay' => $this->gen_Kstroke()
        ];
    }
    
    protected function _basic($ua) {
        $this->browserFingerprint = [
            'navigator' => $this->navigatorFingerprint,
            'screen' => $this->screenFingerprint,
            'timezone' => $this->timezoneFingerprint,
            'canvas' => $this->canvasFingerprint,
            'webgl' => $this->webglFingerprint,
            'audio' => $this->audioFingerprint,
            'fonts' => $this->fontFingerprint,
            'behavior' => $this->behaviorFingerprint,
            'device' => $this->_device($ua),
            'network' => $this->_ntwork(),
            'permissions' => $this->_permss(),
            'storage' => $this->_strage(),
            'media' => $this->_media()
        ];
        
        $this->headersCF = array_merge($this->headersCF, [
            'X-Fingerprint' => base64_encode(json_encode($this->browserFingerprint)),
            'X-FP-Hash' => $this->gen_fphash(),
            'X-FP-Data' => $this->gen_fpdata()
        ]);
    }
    
    protected function _device($ua): array {
        $isMobile = $this->_mobile($ua);
        
        return [
            'deviceType' => $isMobile ? 'mobile' : 'desktop',
            'deviceMemory' => $this->get_Dmem(),
            'hardwareConcurrency' => $this->get_concur(),
            'maxTouchPoints' => $isMobile ? $this->get_Touch() : 0,
            'userAgentData' => [
                'brands' => $this->gen_Browser($ua),
                'mobile' => $isMobile,
                'platform' => $this->rand_pltform($ua)
            ]
        ];
    }
    
    protected function _ntwork(): array {
        return [
            'connectionType' => $this->get_NET(),
            'rtt' => $this->getRTT(),
            'downlink' => $this->get_Dlink(),
            'saveData' => false,
            'effectiveType' => $this->get_NET()
        ];
    }
    
    protected function _permss(): array {
        return [
            'geolocation' => 'prompt',
            'notifications' => 'prompt',
            'microphone' => 'prompt',
            'camera' => 'prompt',
            'clipboard-read' => 'prompt',
            'clipboard-write' => 'prompt'
        ];
    }
    
    protected function _strage(): array {
        return [
            'localStorage' => true,
            'sessionStorage' => true,
            'indexedDB' => true,
            'cookieEnabled' => true
        ];
    }
    
    protected function _media(): array {
        return [
            'audioinput' => 1,
            'audiooutput' => 1,
            'videoinput' => 0
        ];
    }
    
    protected function _mobile($ua) {
        return (strpos($ua, 'Mobile') !== false || strpos($ua, 'Android') !== false || strpos($ua, 'iPhone') !== false);
    }
    
    protected function get_concur() {
        $ua = $this->navigatorFingerprint['userAgent'] ?? '';
        if ($this->_mobile($ua)) return 4;
        return 8;
    }
    
    protected function get_tzone($timezone) {
        $now = new DateTime('now', new DateTimeZone($timezone));
        $future = clone $now;
        $future->modify('+6 months');
        
        return ($future->getOffset() - $now->getOffset()) / 60;
    }
    
    protected function get_Dmem() {
        $ua = $this->navigatorFingerprint['userAgent'] ?? '';
        if ($this->_mobile($ua)) return 4;
        return 16;
    }
    
    protected function get_Touch() {
        return 5;
    }
    
    protected function get_NET() {
        $country = COUNTRY_CODE();
        if (in_array($country, ['ID', 'IN', 'VN', 'PH', 'TH'])) return '4g';
        if (in_array($country, ['US', 'UK', 'DE', 'FR', 'JP'])) return 'wifi';
        return '4g';
    }
    
    protected function getRTT() {
        $country = COUNTRY_CODE();
        if (in_array($country, ['ID', 'IN', 'VN', 'PH', 'TH'])) return rand(150, 300);
        if (in_array($country, ['US', 'UK', 'DE', 'FR', 'JP'])) return rand(50, 150);
        return rand(100, 200);
    }
    
    protected function get_DNT() {
        return ['unspecified', '1', '0'][array_rand(['unspecified', '1', '0'])];
    }
    
    protected function get_Dlink() {
        $country = COUNTRY_CODE();
        if (in_array($country, ['ID', 'IN', 'VN', 'PH', 'TH'])) return rand(10, 50) / 10;
        if (in_array($country, ['US', 'UK', 'DE', 'FR', 'JP'])) return rand(50, 100) / 10;
        return rand(30, 70) / 10;
    }
    
    protected function res_desktop(): array {
        $resolutions = [
            ['width' => 1920, 'height' => 1080, 'availWidth' => 1920, 'availHeight' => 1040],
            ['width' => 1366, 'height' => 768, 'availWidth' => 1366, 'availHeight' => 728],
            ['width' => 1536, 'height' => 864, 'availWidth' => 1536, 'availHeight' => 824],
            ['width' => 1440, 'height' => 900, 'availWidth' => 1440, 'availHeight' => 860],
            ['width' => 2560, 'height' => 1440, 'availWidth' => 2560, 'availHeight' => 1400],
        ];
        return $resolutions[array_rand($resolutions)];
    }
    
    protected function res_mobiles(): array {
        $resolutions = [
            ['width' => 375, 'height' => 667, 'availWidth' => 375, 'availHeight' => 647],
            ['width' => 390, 'height' => 844, 'availWidth' => 390, 'availHeight' => 824],
            ['width' => 393, 'height' => 852, 'availWidth' => 393, 'availHeight' => 832],
            ['width' => 428, 'height' => 926, 'availWidth' => 428, 'availHeight' => 906],
            ['width' => 360, 'height' => 780, 'availWidth' => 360, 'availHeight' => 760],
        ];
        return $resolutions[array_rand($resolutions)];
    }
    
    protected function wGL_desktop(): array {
        $gpus = [
            ['renderer' => 'NVIDIA GeForce RTX 3060', 'vendor' => 'NVIDIA', 'version' => 'OpenGL 4.6'],
            ['renderer' => 'NVIDIA GeForce RTX 3070', 'vendor' => 'NVIDIA', 'version' => 'OpenGL 4.6'],
            ['renderer' => 'AMD Radeon RX 6700 XT', 'vendor' => 'AMD', 'version' => 'OpenGL 4.6'],
            ['renderer' => 'Intel Iris Xe Graphics', 'vendor' => 'Intel', 'version' => 'OpenGL 4.6'],
        ];
        $gpu = $gpus[array_rand($gpus)];
        
        return [
            'renderer' => $gpu['renderer'],
            'vendor' => $gpu['vendor'],
            'version' => $gpu['version'],
            'shadingLanguageVersion' => 'OpenGL GLSL 4.60',
            'extensions' => $this->get_extGL(false)
        ];
    }
    
    protected function wGL_mobiles(): array {
        $gpus = [
            ['renderer' => 'Mali-G57', 'vendor' => 'ARM', 'version' => 'OpenGL ES 3.2'],
            ['renderer' => 'Adreno (TM) 640', 'vendor' => 'Qualcomm', 'version' => 'OpenGL ES 3.2'],
            ['renderer' => 'Apple A14 GPU', 'vendor' => 'Apple', 'version' => 'OpenGL ES 3.0'],
        ];
        $gpu = $gpus[array_rand($gpus)];
        
        return [
            'renderer' => $gpu['renderer'],
            'vendor' => $gpu['vendor'],
            'version' => $gpu['version'],
            'shadingLanguageVersion' => 'OpenGL ES GLSL ES 3.20',
            'extensions' => $this->get_extGL(true)
        ];
    }
    
    protected function get_extGL($isMobile): array {
        $extensions = [
            'EXT_texture_filter_anisotropic',
            'WEBGL_compressed_texture_s3tc',
            'WEBGL_compressed_texture_astc',
            'WEBGL_compressed_texture_etc1',
            'WEBGL_debug_renderer_info',
            'WEBGL_debug_shaders',
            'WEBGL_depth_texture',
            'WEBGL_draw_buffers',
            'WEBGL_lose_context',
            'WEBGL_multi_draw'
        ];
        
        if (!$isMobile) {
            $extensions = array_merge($extensions, [
                'ANGLE_instanced_arrays',
                'OES_element_index_uint',
                'OES_fbo_render_mipmap',
                'OES_standard_derivatives',
                'OES_texture_float',
                'OES_texture_float_linear',
                'OES_texture_half_float',
                'OES_texture_half_float_linear'
            ]);
        }
        
        return $extensions;
    }
    
    protected function get_Fonts($ua): array {
        $fonts = [];
        
        if (stripos($ua, 'Windows') !== false) {
            $fonts = [
                'Arial', 'Arial Black', 'Calibri', 'Cambria', 'Candara',
                'Comic Sans MS', 'Consolas', 'Constantia', 'Corbel',
                'Courier New', 'Georgia', 'Impact', 'Lucida Console',
                'Lucida Sans', 'Lucida Sans Unicode', 'Microsoft Sans Serif',
                'Palatino Linotype', 'Segoe UI', 'Symbol', 'Tahoma',
                'Times New Roman', 'Trebuchet MS', 'Verdana'
            ];
        } elseif (stripos($ua, 'Mac') !== false) {
            $fonts = [
                'Apple Color Emoji', 'Apple Symbols', 'Arial', 'Arial Black',
                'Avenir', 'Comic Sans MS', 'Courier New', 'Georgia',
                'Helvetica', 'Helvetica Neue', 'Impact', 'Lucida Grande',
                'Symbol', 'Tahoma', 'Times New Roman', 'Trebuchet MS',
                'Verdana'
            ];
        } elseif (stripos($ua, 'Linux') !== false) {
            $fonts = [
                'DejaVu Sans', 'DejaVu Serif', 'Droid Sans', 'Droid Sans Mono',
                'Ubuntu', 'Arial', 'Courier New', 'Georgia', 'Impact',
                'Times New Roman', 'Tahoma', 'Verdana'
            ];
        } else {
            $fonts = [
                'Arial', 'Courier New', 'Georgia', 'Times New Roman',
                'Tahoma', 'Verdana', 'Comic Sans MS', 'Impact'
            ];
        }
        
        return $fonts;
    }
    
    protected function rand_pltform($ua) {
        if (stripos($ua, 'Windows') !== false) return 'Win32';
        if (stripos($ua, 'Mac') !== false) return 'MacIntel';
        if (stripos($ua, 'Linux') !== false) return 'Linux x86_64';
        if (stripos($ua, 'Android') !== false) return 'Android';
        if (stripos($ua, 'iPhone') !== false) return 'iPhone';
        return 'Win32';
    }
    
    protected function rand_vendor($ua) {
        if (stripos($ua, 'Chrome') !== false) return 'Google Inc.';
        if (stripos($ua, 'Firefox') !== false) return '';
        if (stripos($ua, 'Safari') !== false) return 'Apple Computer, Inc.';
        return 'Google Inc.';
    }
    
    protected function rand_appver($ua) {
        if (preg_match('/(Chrome|Firefox|Safari)\/(\d+\.\d+)/', $ua, $matches)) {
            return $matches[0];
        }
        return '5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    }
    
    protected function rand_grphic() {
        $elements = ['rect', 'circle', 'text', 'arc', 'bezier'];
        $data = '';
        for ($i = 0; $i < rand(3, 8); $i++) {
            $data .= $elements[array_rand($elements)] . rand(1, 100) . ',';
        }
        return substr($data, 0, -1);
    }
    
    protected function gen_Fonts($ua) {
        return md5(implode('|', $this->get_Fonts($ua)));
    }
    
    protected function gen_canvas() {
        $data = [
            'g' => $this->rand_grphic(),
            't' => time(),
            'w' => $this->screenFingerprint['width'] ?? 1920,
            'h' => $this->screenFingerprint['height'] ?? 1080
        ];
        return md5(json_encode($data));
    }
    
    protected function gen_Audio(): string {
        return md5('audio' . time() . rand(1000, 9999));
    }
    
    protected function gen_Mouse(): array {
        $pattern = [];
        $steps = rand(20, 50);
        $x = rand(100, 500);
        $y = rand(100, 300);
        
        for ($i = 0; $i < $steps; $i++) {
            $x += rand(-10, 10);
            $y += rand(-5, 5);
            $x = max(0, min(1920, $x));
            $y = max(0, min(1080, $y));
            $pattern[] = ['x' => $x, 'y' => $y, 't' => $i * rand(10, 30)];
        }
        
        return $pattern;
    }
    
    protected function gen_Type(): array {
        return [
            'speed' => rand(100, 300),
            'variation' => rand(10, 50),
            'backspaceFrequency' => rand(0, 5) / 100
        ];
    }
    
    protected function gen_Scroll(): array {
        return [
            'smoothness' => rand(1, 5),
            'speed' => rand(10, 50),
            'randomness' => rand(1, 3)
        ];
    }
    
    protected function gen_Click(): array {
        return [
            'doubleClickSpeed' => rand(200, 500),
            'clickVariation' => rand(10, 50),
            'dragDuration' => rand(100, 300)
        ];
    }
    
    protected function gen_Kstroke(): array {
        return [
            'min' => rand(30, 80),
            'max' => rand(150, 300),
            'average' => rand(80, 150)
        ];
    }
    
    protected function gen_Browser($ua): array {
        if (stripos($ua, 'Chrome') !== false) {
            return [
                ['brand' => 'Chromium', 'version' => '120'],
                ['brand' => 'Google Chrome', 'version' => '120'],
                ['brand' => 'Not=A?Brand', 'version' => '99']
            ];
        } elseif (stripos($ua, 'Firefox') !== false) {
            return [
                ['brand' => 'Mozilla Firefox', 'version' => '120']
            ];
        } elseif (stripos($ua, 'Safari') !== false) {
            return [
                ['brand' => 'Apple Safari', 'version' => '17.2']
            ];
        }
        return [];
    }
    
    protected function gen_fphash() {
        $data = json_encode($this->browserFingerprint);
        return hash('sha256', $data);
    }
    
    protected function gen_fpdata() {
        $data = [
            'timestamp' => microtime(true),
            'version' => '2.0',
            'fp' => $this->browserFingerprint
        ];
        return base64_encode(json_encode($data));
    }
}