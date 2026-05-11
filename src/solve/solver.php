<?php
/** @class Solve
 * @method exec
 * @param string $html
 * @param string $host
 * @param Provider|null $api
 * @param bool $ins
 * @return array|string|null
 *
 * @method tkn
 * @param Provider $api
 * @param string $host
 * @param string $key
 * @param string $type
 * @param array $Params
 * @return string|null
 * 
 * @method img
 * @param Provider $api
 * @param string $host
 * @param string $type
 * @param string $img
 * @return string|null
 */
class Solve {
    
    public static function exec($html, $host, ?Provider $api, $pa = null, $ins = false) {
        $ua = inf::$uagent;
        $cookie = inf::$cookie;
        $ip = inf::$ip;
        
        $solution = [];
        $_cap = Capt::cha($html);
#var_dump($_cap); #die;
        if (!empty($_cap['antibot'])) {
            $resAtb = locally::ATB($_cap['antibot']['type'], $api, $html);
            if ($resAtb === 77) return ['trouble' => 'reload'];
            if ($resAtb) $solution['antibotlinks'] = $resAtb;
        }

        if (!empty($pa['captcha'])) {
            switch ($pa['captcha']) {
                case 'shield':
                    if (isset($pa['shield_answer'])) {
                        $resShi = locally::shiCaptcha($html);
                        if ($resShi) $solution = array_merge($solution, $resShi);
                    }
                    break;
                
                case 'rot_captcha':
                    if (isset($pa['rot_captcha_val'])) {
                        $resRot = locally::rotCaptcha($html);
                        if ($resRot) $solution = array_merge($solution, $resRot);
                    }
                    break;
                
                case 'smart_captcha':
                    if (isset($pa['smart_token'])) {
                        $resSmt = locally::smartFP($html);
                        if ($resSmt) $solution['smart_token'] = $resSmt;
                    }
                    break;
                    
            }
        }

        if (isset($_cap['ic_fw'])) {
            $ic = null;
            $attempt = 0;
            while (!$ic && $attempt < 5) {
                $ic = locally::iCaptcha($html, $host);
                if ($ic === 99) return ['trouble' => 'proxy'];
                $attempt++;
            }
            if ($ic) $solution = array_merge($solution, $ic);
        }

        $mainSolved = count(array_diff(array_keys($solution), ['antibotlinks'])) > 0;

        if ($api) {
            if (!$mainSolved) {
                $priority = ['cft', 'rc3', 'rc2', 'hc'];
                foreach ($priority as $t) {
                    if (!isset($_cap[$t])) continue;
                    
                    $_ty = $_cap[$t]['type'] ?? $t; 
                    $_ke = $_cap[$t]['keys'] ?? null;
                    $_ex = array_filter($_cap[$t]['extra'] ?? [], fn($v) => !is_null($v));

                    if (!$_ke) continue;
                    
                    $token = self::tkn($api, $host, $_ke, $_ty, $_ex);
                    if ($token === 471) continue; 
                    
                    if ($token === 404) {
                        logx('err', "api bermasalah");
                        return ['trouble' => 'reload']; 
                    }
                    
                    if (is_string($token) && !empty($token)) {
                        $solution = array_merge($solution, [
                            'g-recaptcha-response' => $token,
                            'cf-turnstile-response' => $token,
                            'h-captcha-response' => $token,
                            'g-recaptcha-response-v3' => $token
                        ]);
                        break; 
                    }
                }
            }
        } else {
            if (!$mainSolved && (!empty($_cap['cft']) || !empty($_cap['rc2']) || !empty($_cap['rc3']) || !empty($_cap['hc']))) {
                logx('err', 'wajib solver provider untuk captcha ini');
                die;
            }
        }
        
        if (empty($solution) && empty($_cap)) {
            logx('info', 'no captcha detected');
            return ['nocaptcha' => true];
        }

        return !empty($solution) ? $solution : null;
    }
    
    public static function tkn($api, $host, $key, $type, array $Params = []) {
        
        $solver = config::getKeys($api, $type);
        print(DIMM.BOLD.ITAL.FGo['MAG']."solving  ".RSET);
        $set = microtime(true);
        $t = null;
        
        for ($retry = 0; $retry < 2; $retry++) {
            $t = $solver->token($key, $host, $type, $Params);
            if ($t === 777) {
                
                if (!isset(Api::TKN[get_class($api)][$type]))                    return 471; 
                logx('ok', "Switching to ".get_class($api));
                $t = $api->token($key, $host, $type, $Params);
                if ($t === 71) return 471;
                if ($t === null) exit();
                if ($t === false) return 404;
                if ($t) break;
            }
            
            if ($t === 71) return 471; 
            if ($t === null) exit();
            if ($t && $t !== 777) break;
            _sle(1);
        }
        
        if ($t === false) return 404; 
        
        $api->getInfo();
        logg(false, 'elapsed: ' . number_format(microtime(true) - $set, 3).'s');
        return $t;
    }

    public static function img($api, $host, $type, $img) {
        if (!$api) (logx('err', 'undefined provider') ?: die);
        
        $solver = config::getKeys($api, $type, 'b64');
        if (!isset(Api::B64[get_class($solver)][$type])) (logx('err', 'unsupported provider') ?: die);
        $res = $solver->base64($img, $type);
        
        if ($res === 777) {
            logx('warn', "Switching to Direct API provider", false);
            _clr();
            $res = $api->base64($img, $type);
            if ($res && $res !== 777) $api->getInfo();
        }
        
        if ($res === 77) return ['trouble' => 'reload'];
        return $res ?: null;
    }
    
}

/** @class SolveUtils
 * @method oddCaptcha
 * @param string $base64
 * @return int|false
 *
 * @method aHash
 * @param string $input
 * @return string|null
 *
 * @method dHash
 * @param string $input
 * @return string|null
 *
 * @method hamming
 * @param string $h1
 * @param string $h2
 * @return int
 *
 * @method createImg
 * @param string $input
 * @return resource|false|null
 *
 * @method getGray
 * @param int $rgb
 * @return float|int
 *
 * @method widgetID
 * @return string
 *
 * @method webkitID
 * @param array $fo
 * @param string $boundary
 * @return string
 *
 * @method hcdn
 * @param string $cjs
 * @return string
 */
class SolveUtils {
    
    public static function widgetID() {
        $uuid = '';
        for ($n = 0; $n < 32; $n++) {
            if (in_array($n, [8, 12, 16, 20])) $uuid .= '-';
            $e = mt_rand(0, 15);
            if ($n == 12) $e = 4;
            elseif ($n == 16) $e = ($e & 0x3) | 0x8;
            $uuid .= dechex($e);
        }
        return $uuid;
    }

    public static function webkitID(array $fo, &$boundary) {
        if (empty($boundary)) {
            $boundary = '----WebKitFormBoundary' . bin2hex(random_bytes(8));
        }
        
        $body = '';
        foreach ($fo as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= $value . "\r\n"; 
        }
        $body .= "--{$boundary}--\r\n";
        return $body;
    }
    
    public static function oddCaptcha($base64) {
        if (!getDeps('gd@php')) {
            logx('err', "gd@php is missing");
            exit(9);
        }
        
        $image = imagecreatefromstring(base64_decode($base64));
        if (!$image) return false;

        $width  = imagesx($image);
        $height = imagesy($image);
        $segW   = intdiv($width, 5);
        $angles = [];

        for ($idx = 0; $idx < 5; $idx++) {
            $xCoords = []; $yCoords = [];
            $start = $idx * $segW;

            for ($y = 0; $y < $height; $y++) {
                for ($x = $start; $x < $start + $segW; $x++) {
                    $rgb = imagecolorat($image, $x, $y);
                    if ((($rgb >> 16) & 0xFF) < 220) { // Dark pixel detect
                        $xCoords[] = $x - $start; $yCoords[] = $y;
                    }
                }
            }

            if (count($xCoords) < 10) { $angles[$idx] = 0.0; continue; }

            $mX = array_sum($xCoords) / count($xCoords);
            $mY = array_sum($yCoords) / count($yCoords);
            $vX = $vY = $cXY = 0.0;

            for ($i = 0; $i < count($xCoords); $i++) {
                $dx = $xCoords[$i] - $mX; $dy = $yCoords[$i] - $mY;
                $vX += $dx * $dx; $vY += $dy * $dy; $cXY += $dx * $dy;
            }
            $angles[$idx] = ($vY == $vX) ? 0.0 : 0.5 * rad2deg(atan2(2 * $cXY, $vY - $vX));
        }

        $sorted = $angles; sort($sorted);
        $median = $sorted[2];
        $maxDev = -1; $res = 0;

        foreach ($angles as $idx => $angle) {
            $dev = abs($angle - $median);
            if ($dev > 90) $dev = 180 - $dev;
            if ($dev > $maxDev) { $maxDev = $dev; $res = $idx; }
        }
        return $res;
    }

    public static function aHash($input) {
        if (!getDeps('gd@php')) {
            logx('err', "gd@php is missing");
            exit(9);
        }
        
        $im = self::createImg($input);
        if (!$im) return null;

        $w = 16; $h = 16;
        $thumb = imagecreatetruecolor($w, $h);
        imagecopyresampled($thumb, $im, 0,0,0,0, $w,$h, imagesx($im), imagesy($im));
        
        $pixels = []; $sum = 0;
        for ($y=0; $y<$h; $y++) {
            for ($x=0; $x<$w; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $gray = self::getGray($rgb);
                $pixels[] = $gray; $sum += $gray;
            }
        }
        $avg = $sum / 256; $hash = '';
        foreach ($pixels as $p) $hash .= ($p >= $avg) ? '1' : '0';
        
        imagedestroy($thumb); imagedestroy($im);
        return $hash;
    }

    public static function dHash($input) {
        if (!getDeps('gd@php')) {
            logx('err', "gd@php is missing");
            exit(9);
        }
        
        $im = self::createImg($input);
        if (!$im) return null;

        $w = 16; $h = 16;
        $thumb = imagecreatetruecolor($w, $h);
        imagecopyresampled($thumb, $im, 0,0,0,0, $w,$h, imagesx($im), imagesy($im));
        
        $hash = '';
        for ($y=0; $y<$h; $y++) {
            for ($x=0; $x<$w-1; $x++) {
                $g1 = self::getGray(imagecolorat($thumb, $x, $y));
                $g2 = self::getGray(imagecolorat($thumb, $x+1, $y));
                $hash .= ($g1 > $g2) ? '1' : '0';
            }
        }
        imagedestroy($thumb); imagedestroy($im);
        return $hash;
    }

    public static function hamming($h1, $h2) {
        $dist = 0; 
        $len = strlen($h1);
        for ($i = 0; $i < $len; $i++) if ($h1[$i] !== $h2[$i]) $dist++;
        return $dist;
    }

    private static function createImg($input) {
        if (!getDeps('gd@php')) {
            logx('err', "gd@php is missing");
            exit(9);
        }
        
        $data = (is_string($input) && file_exists($input)) ? _get($input) : $input;
        return @imagecreatefromstring($data);
    }

    private static function getGray($rgb) {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        return 0.299*$r + 0.587*$g + 0.114*$b;
    }
    
    public static function hcdn($cjs) {
        $h = [
            0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a,
            0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19
        ];

        $k = [
            0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
            0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
            0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
            0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
            0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
            0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
            0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
            0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
        ];

        // Closure 32-bit 
        $u32  = function($n) { return $n & 0xFFFFFFFF; };
        $rotr = function($x, $n) use ($u32) {
            return $u32(($x >> $n) | ($x << (32 - $n)));
        };

        // Padding
        $z = $cjs . "\x80";
        while ((strlen($z) % 64) !== 56) $z .= "\x00";
        $z .= pack('N2', 0, strlen($cjs) * 8);

        $chunks = str_split($z, 64);
        foreach ($chunks as $chunk) {
            $w = array_values(unpack('N16', $chunk));
            for ($i = 16; $i < 64; $i++) {
                $s0 = $rotr($w[$i-15], 7) ^ $rotr($w[$i-15], 18) ^ ($w[$i-15] >> 3);
                $s1 = $rotr($w[$i-2], 17) ^ $rotr($w[$i-2], 19) ^ ($w[$i-2] >> 10);
                $w[$i] = $u32($w[$i-16] + $s0 + $w[$i-7] + $s1);
            }

            list($a, $b, $c, $d, $e, $f, $g, $h_temp) = $h;

            for ($i = 0; $i < 64; $i++) {
                $S1 = $rotr($e, 6) ^ $rotr($e, 11) ^ $rotr($e, 25);
                $ch = ($e & $f) ^ ((~$e) & $g);
                $temp1 = $u32($h_temp + $S1 + $ch + $k[$i] + $w[$i]);
                $S0 = $rotr($a, 2) ^ $rotr($a, 13) ^ $rotr($a, 22);
                $maj = ($a & $b) ^ ($a & $c) ^ ($b & $c);
                $temp2 = $u32($S0 + $maj);

                $h_temp = $g;
                $g = $f;
                $f = $e;
                $e = $u32($d + $temp1);
                $d = $c;
                $c = $b;
                $b = $a;
                $a = $u32($temp1 + $temp2);
            }

            $h[0] = $u32($h[0] + $a); $h[1] = $u32($h[1] + $b);
            $h[2] = $u32($h[2] + $c); $h[3] = $u32($h[3] + $d);
            $h[4] = $u32($h[4] + $e); $h[5] = $u32($h[5] + $f);
            $h[6] = $u32($h[6] + $g); $h[7] = $u32($h[7] + $h_temp);
        }

        $res = '';
        foreach ($h as $val) $res .= sprintf('%08x', $val);
        return $res;
    }
    
}

/** @class locally
 * @method smartFP
 * @param string $html
 * @return string
 *
 * @method iCaptcha
 * @param string $html
 * @param string $host
 * @return array|false
 *
 * @method eCaptcha
 * @param string $host
 * @return array|false
 *
 * @method ATB
 * @param string $type
 * @param Provider|null $apii
 * @param string $html
 * @return mixed
 *
 * @method atb_E
 * @param string $html
 * @return string|null
 *
 * @method atb_I
 * @param object $api
 * @param string $html
 * @return mixed 
 * 
 * 
 */
class locally {
    
    public static function iCaptcha($html, $host) {
        $cookie = inf::$cookie;
        $ua = inf::$uagent;
        $ip = inf::$ip;

        return styler("SOLVING icaptcha", function() use ($html, $host, $ip, $cookie, $ua) {
            iconcaptcha:
            $endpoint = null;
            $scripts = Scraper::_xP($html, "//script/text()");
            foreach ($scripts as $js) {
                if (preg_match("~IconCaptcha\.init.*?endpoint\s*:\s*['\"]([^'\"]+)['\"]~is", $js, $m)) {
                    $path = $m[1];
                    $endpoint = (str_starts_with($path, 'http')) ? $path : rtrim($host, '/') . '/' . ltrim($path, '/');
                    break;
                }
            }

            $token = Scraper::find($html, '_iconcaptcha-token')[0] ?? null;
            if (!$endpoint || !$token) return false;
            #logx('info', $endpoint);
            #logx('info', $token);

            $widgetID = SolveUtils::widgetID();
            $ts = round(microtime(true) * 1000);
            $json = ["payload" => base64_encode(json_encode([
                "widgetId" => $widgetID, "action" => "LOAD", "theme" => "light",
                "token" => $token, "timestamp" => $ts, "initTimestamp" => $ts - 2000
            ]))];

            $res = Net::X($endpoint, 'POST', $json, $cookie, ["x-iconcaptcha-token: $token"], $host, $ua, false, false, $ip);
            if ($res === 99 || (empty($res))) return 99;
            $r = json_decode(base64_decode($res), true);
            #print_r($r);
            $challengeId = $r['identifier'] ?? null;
            if (!$challengeId) return false;
            _sle(1);

            for ($i = 0; $i < 5; $i++) {
                $x = (int)(($i * 64) + 32); 
                $ts = round(microtime(true) * 1000);
                $payload = base64_encode(json_encode([
                    "x" => $x, "y" => rand(22,30), "width" => 320, "token" => $token,
                    "action" => "SELECTION", "widgetId" => $widgetID,
                    "timestamp" => $ts, "challengeId" => $challengeId, "initTimestamp" => $ts - 2000
                ]));

                $boundary = '';
                $body = SolveUtils::webkitID(["payload" => $payload], $boundary);
                $s = Net::X($endpoint, 'POST', $body, $cookie, ["x-iconcaptcha-token: $token", "Content-Type: multipart/form-data; boundary=$boundary"], $host, $ua, false, false, $ip);
                if ($s === 99 || empty($s)) return 99;
                $r = json_decode(base64_decode($s), true);
                #print_r($r);
                if (!empty($r['completed'])) {
                    return [
                        'captcha' => 'icaptcha',
                        '_iconcaptcha-token' => $token,
                        'ic-rq' => 1,
                        'ic-wid' => $widgetID,
                        'ic-cid' => $challengeId,
                        'ic-hp' => ''
                    ];
                } 
                _sle(1);
            }
            return false;
        });
    }

    public static function eCaptcha($host) {
        $cookie = inf::$cookie;
        $ua = inf::$uagent;
        $ip = inf::$ip;

        return styler("SOLVING ecaptcha", function() use ($host, $cookie, $ua, $ip) {
            $res = Net::X($host.'/ecaptcha/get_token', 'GET', null, $cookie, [], $host, $ua, false, false, $ip);
            if ($res === 99) return 99;
            $json = json_decode($res ?: '', true);
            $token = $json['token'] ?? null;
            if (!$token) return false;

            $res = Net::X($host.'/ecaptcha/get_captcha', 'GET', null, $cookie, [], $host, $ua, false, false, $ip);
            if ($res === 99) return 99;
            $task = json_decode($res ?: '', true);
            #print_r($task); die;
            if (empty($task['captcha_key']) || empty($task['question'])) return false;
            
            // 3. Parsing Answer 
            $sel = explode(':', $task['question']);
            $answer = strtolower(trim(end($sel))) . '.gif';
            
            $payload = [
                'key' => $task['captcha_key'],
                'selected' => $answer,
                'token' => $token
            ];
            
            // 4. Validate
            $res = Net::X($host.'/ecaptcha/validate_icon', 'POST', $payload, $cookie, [], $host, $ua, false, false, $ip);
            #print_r($post); die;
            if ($res === 99) return 99;
            $post = json_decode($res ?: '', true);
            if (($post['status'] ?? '') === 'valid') {
                return [
                    'captcha' => 'emoji_captcha',
                    'captcha_key' => $post['captcha_key'],
                    'captcha_token' => $token,
                    'selected_icon' => $answer
                ];
            } 
            return false;
        });
    }
    
    public static function ATB($type, $apii, $html) {
        
        if (!$apii) { 
            logx('err', 'undefined provider');
            die;
        }
        
        $api = config::getKeys($apii, $type);
        
        if ($type === 'emoji') return self::atb_E($html);
        
        if ($type === 'image') {
            $refl = new ReflectionClass($api);
            
            if (!$refl->hasConstant('ATB_MODE')) {
                logx('err', get_class($api) . " gak support Antibot");
                die;
            }
            
            if (method_exists($api, 'atb')) {
                return self::atb_I($api, $html); 
            }
        }
    }
    
    public static function atb_E($html) {
        $ab_ins = Scraper::_xP($html, "//strong[contains(text(),',')]");
        if (empty($ab_ins)) return null;
        
        $_ask = array_map('trim', explode(',', $ab_ins[0]));
        
        $_ab = '/data-token=\\\\"(?<token>[^"\\\\]+)\\\\".*?>(?<emoji>.*?)<\/a>/u';
        $ab_rel = Scraper::_jP($html, $_ab);
        
        if (empty($ab_rel['token'])) return null;

        $ab_t = [];
        foreach ($ab_rel['token'] as $idx => $_rel) {
            $ab_e = $ab_rel['emoji'][$idx];
            $ab_t[$ab_e] = $_rel;
        }

        $db_file = LIBDIR . '/atb_e.json';
        $db = file_exists($db_file) ? json_decode(_get($db_file), true) : [];

        $solution = [];
        $_tokens = $ab_t;

        foreach ($_ask as $e_nam) {
            $e_nam = strtolower($e_nam);
            $ab_e = array_search($e_nam, $db);
            
            if ($ab_e !== false && isset($ab_t[$ab_e])) {
                $solution[$e_nam] = $ab_t[$ab_e];
                unset($_tokens[$ab_e]);
            } else {
                $solution[$e_nam] = null;
            }
        }

        foreach ($solution as $e_nam => $token) {
            if ($token === null && count($_tokens) === 1) {
                $solution[$e_nam] = array_shift($_tokens);
            }
        }

        if (in_array(null, $solution)) {
            logx("err", "Antibot Unknown");
            return null;
        }

        return implode(' ', array_values($solution));
    }
    
    public static function atb_I($api, $html) {
        $data = ATBtest::get($html);
        #print_r($data);
        if (empty($data['main'])) return 77; 
        return $api->atb($data);
    }
    
    public static function rotCaptcha($html) {
        $solution = ['rot_captcha_val' => 0];
        if (!getDeps('gd@php')) {
            logx('err', "gd@php is missing");
            exit(9);
        }
        
        $_targetText = Scraper::_xP($html, "//div[@id='rc-title']//strong");
        $targetStr = isset($_targetText[0]) ? strtoupper($_targetText[0]) : 'UP';
        $targetDegrees = 270; 
        
        if (strpos($targetStr, 'DOWN') !== false)  $targetDegrees = 90;
        if (strpos($targetStr, 'RIGHT') !== false) $targetDegrees = 0;
        if (strpos($targetStr, 'LEFT') !== false)  $targetDegrees = 180;
        
        $_b = Scraper::find($html, 'rc-img', 'img', 'src', 'id')[0];
        $b64 = substr($_b, strrpos($_b, ',') + 1);
        
        $_img = base64_decode($b64);
        if (!$_img) return $solution;
        
        $img = imagecreatefromstring($_img);
        $W = imagesx($img);
        $H = imagesy($img);
        $_slc = max(5, (int)round(max($W, $H) * 0.08));
        
        $_brdr = [];
        for ($x = 0; $x < $W; $x++) {
            $_brdr[] = imagecolorat($img, $x, 0);
            $_brdr[] = imagecolorat($img, $x, $H - 1);
        }
        for ($y = 1; $y < $H - 1; $y++) {
            $_brdr[] = imagecolorat($img, 0, $y);
            $_brdr[] = imagecolorat($img, $W - 1, $y);
        }
        $counts = array_count_values($_brdr);
        arsort($counts);
        $bgRaw = key($counts);
        $bgR = ($bgRaw >> 16) & 0xFF;
        $bgG = ($bgRaw >> 8)  & 0xFF;
        $bgB = $bgRaw & 0xFF;
        
        $_bnr = [];
        for ($y = 0; $y < $H; $y++) {
            for ($x = 0; $x < $W; $x++) {
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8)  & 0xFF;
                $b =  $c        & 0xFF;
                $_dst = sqrt(($r-$bgR)**2 + ($g-$bgG)**2 + ($b-$bgB)**2);
                $_bnr[$y][$x] = ($_dst > 50) ? 1 : 0;
            }
        }
        
        $_vst = [];
        $_bst = [];
        for ($sy = 0; $sy < $H; $sy++) {
            for ($sx = 0; $sx < $W; $sx++) {
                if (!($_bnr[$sy][$sx] ?? 0) || ($_vst[$sy][$sx] ?? false)) continue;
                $p_a = [];
                $q_a = [[$sx, $sy]];
                $_vst[$sy][$sx] = true;
                while (!empty($q_a)) {
                    [$cx2, $cy2] = array_pop($q_a);
                    $p_a[] = [$cx2, $cy2];
                    foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dx2,$dy2]) {
                        $nx2 = $cx2 + $dx2; $ny2 = $cy2 + $dy2;
                        if ($nx2 < 0 || $nx2 >= $W || $ny2 < 0 || $ny2 >= $H) continue;
                        if (!($_bnr[$ny2][$nx2] ?? 0) || ($_vst[$ny2][$nx2] ?? false)) continue;
                        $_vst[$ny2][$nx2] = true;
                        $q_a[] = [$nx2, $ny2];
                    }
                }
                if (count($p_a) > count($_bst)) $_bst = $p_a;
            }
        }
        
        $n = count($_bst);
        if ($n < 10) return $solution;
        
        $sumX = $sumY = 0.0;
        foreach ($_bst as [$px, $py]) {
            $sumX += $px; $sumY += $py; 
        }
        
        $cxC = $sumX / $n; $cyC = $sumY / $n;
        $mu20 = $mu02 = $mu11 = 0.0;
        
        foreach ($_bst as [$px, $py]) {
            $dx2 = $px - $cxC; $dy2 = $py - $cyC;
            $mu20 += $dx2 * $dx2; $mu02 += $dy2 * $dy2; $mu11 += $dx2 * $dy2;
        }
        
        $mu20 /= $n; $mu02 /= $n; $mu11 /= $n;
        $angle = 0.5 * atan2(2 * $mu11, $mu20 - $mu02);
        $cosA = cos($angle); $sinA  = sin($angle);
        
        $t_V = [];
        foreach ($_bst as [$px, $py]) {
            $t_V[] = ($px - $cxC) * $cosA + ($py - $cyC) * $sinA;
        }
        $tMin = min($t_V); $tMax = max($t_V);
        
        $avgDev = function($t_C) use ($_bst, $cxC, $cyC, $cosA, $sinA, $t_V, $_slc) {
            $sum = 0.0; $cnt = 0;
            foreach ($_bst as $i => [$px, $py]) {
                if (abs($t_V[$i] - $t_C) <= $_slc) {
                    $sum += abs(-($px - $cxC) * $sinA + ($py - $cyC) * $cosA);
                    $cnt++;
                }
            }
            return $cnt > 0 ? $sum / $cnt : INF;
        };
        
        $_minn = $avgDev($tMin);
        $_maxx = $avgDev($tMax);
        $v_A = ($_minn < $_maxx) ? 'min' : 'max';
        $cntPos = 0; $cntNeg = 0;
        
        foreach ($t_V as $t) {
            if ($t >= 0) $cntPos++; else $cntNeg++; 
        }
        
        $v_B = ($cntNeg < $cntPos) ? 'min' : 'max';
        $he_ = $v_A; 
        $he_T = ($he_ === 'min') ? $tMin : $tMax;
        $te_T = ($he_ === 'min') ? $tMax : $tMin;
        $vecDx = ($he_T - $te_T) * $cosA;
        $vecDy = ($he_T - $te_T) * $sinA;
        $arr_D = fmod(rad2deg(atan2($vecDy, $vecDx)) + 360, 360);
        $rot_V = (int) round(fmod($targetDegrees - $arr_D + 360, 360));
        return ['rot_captcha_val' => $rot_V];
    }
    
    public static function shiCaptcha($fau) {
        $json = json_decode(Scraper::_jP($fau, '/var D=({.*?});/')[1][0] ?? '', true);
        if (!$json) return ['shield_answer' => ""];
        
        $grid = $json['grid'];
        #print_r($grid);
        $instruction = strtolower($json['instruction']);
        #logx('ok', " [ $instruction ]", true, true);
        
        $ans = [];
        if (str_contains($instruction, "belong") || str_contains($instruction, "different")) {
            $shapeCounts = array_count_values(array_column($grid, 'shape'));
            $colorCounts = array_count_values(array_column($grid, 'color'));
            foreach ($grid as $index => $item) {
                if ($shapeCounts[$item['shape']] === 1 || $colorCounts[$item['color']] === 1) {
                    $ans[] = $index;
                    break;
                }
            }
        } else {
            preg_match('/<b>(.*?)<\/b>/', $instruction, $match);
            $target = $match[1] ?? '';
            $colorMap = [
                'blue' => ['#3b82f6', '#2563eb', '#60a5fa', '#1d4ed8'],
                'red' => ['#ef4444', '#dc2626', '#f87171', '#b91c1c'],
                'green' => ['#22c55e', '#16a34a', '#4ade80', '#15803d'],
                'yellow' => ['#eab308', '#facc15', '#ca8a04'],
                'orange' => ['#f97316', '#ea580c', '#fb923c', '#f59e0b'],
                'pink' => ['#ec4899', '#db2777', '#f472b6'],
                'purple' => ['#a855f7', '#9333ea', '#c084fc'],
                'cyan' => ['#06b6d4', '#0891b2', '#22d3ee'],
                'gray' => ['#64748b', '#475569', '#94a3b8'],
                'indigo' => ['#6366f1', '#4f46e5', '#818cf8'],
                'sky' => ['#0ea5e9', '#0284c7', '#38bdf8']
            ];
            
            $shapes = ['circle', 'square', 'triangle', 'diamond', 'star', 'hexagon'];
            foreach ($grid as $index => $item) {
                $itemColor = strtolower($item['color']);
                $itemShape = strtolower($item['shape']);
                if (isset($colorMap[$target])) {
                    if (in_array($itemColor, $colorMap[$target])) {
                        $ans[] = $index;
                    }
                } elseif (in_array($target, $shapes)) {
                    if ($itemShape === $target) {
                        $ans[] = $index;
                    }
                }
            }
        }
        sort($ans);
        return ['shield_answer' => implode(',', $ans)];
    }
    
    public static function smartFP($html) {
        $xpath = Scraper::dom($html);
        $node = $xpath->query("//input[@name='smart_token']")->item(0);
        if ($node) {
            $hasLogic = false;
            $scripts = $xpath->query("//script");
            $id = $node->getAttribute('id');
            foreach ($scripts as $script) {
                $content = $script->textContent;
                if (strpos($content, 'smart_token') !== false || ($id && strpos($content, $id) !== false)) {
                    $hasLogic = true;
                    break;
                }
            }
            if ($hasLogic) {
                $currentValue = $node->getAttribute('value');
                if (empty($currentValue)) {
                    $data = [
                        'ts' => (int)round(microtime(true) * 1000),
                        'cpu' => 8,
                        'mem' => 4,
                        'w' => 1366,
                        'h' => 768,
                        'touch' => 0,
                        'moves' => rand(1, 5)
                    ];
                    return base64_encode(json_encode($data));
                }
            }
        }
        return [];
    }
    
}