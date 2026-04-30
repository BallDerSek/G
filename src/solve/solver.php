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
 * @method widgetID
 * @return string
 *
 * @method webkitID
 * @param array $fo
 * @param string $boundary
 * @return string
 */
class Solve {
    
    public static function exec($html, $host, ?Provider $api, $ins = false) {
        $ua = inf::$uagent;
        $cookie = inf::$cookie;
        $ip = inf::$ip;
        
        if (!$_cap = Capt::cha($html)) {
            logx('info', 'no captcha detected');
            return [ 'nocaptcha' => true];
        }
        
        #var_dump($_cap); die;
        $solution = [];

        // 1. ANTIBOT
        if (!empty($_cap['antibot'])) {
            $resAtb = self::ATB($_cap['antibot']['type'], $api, $html);
            if ($resAtb === 77) return 'reload';
            if ($resAtb) $solution['antibotlinks'] = $resAtb;
        }

        // 2. ICONCAPTCHA (iCaptcha)
        if (isset($_cap['ic_fw'])) {
            $ic = null;
            $attempt = 0;
            while (!$ic && $attempt < 3) {
                $ic = self::iCaptcha($html, $host);
                $attempt++;
            }
            if ($ic) return array_merge($solution, $ic);
        }
        
        /*/ RSCAPTCHA (rss) 
        if (!empty($_cap['rss'])) {
            $imgData = Net::C($_cap['rss']['keys'], 'GET', null, $cookie, [], $host, $ua);
            $rss = $api->base64($imgData, $_cap['rss']['type']);
            #if ($rss === false) return 'reload';
            if (!empty($rss)) {
                return $solution['rscaptcha_response'] = $rss;
            } 
        }
        */
        
        // 3. API BASED (Recaptcha, Turnstile, Hcaptcha)
        if ($api) {
            $priority = ['cft', 'rc3', 'rc2', 'hc'];
            foreach ($priority as $t) {
                if (!isset($_cap[$t])) continue;
                
                $_ty = $_cap[$t]['type'] ?? $t; 
                $_ke = $_cap[$t]['keys'] ?? null;
                $_ex = array_filter($_cap[$t]['extra'] ?? [], fn($v) => !is_null($v));

                if (!$_ke) continue;
                $solver = config::getKeys($api, $t);
                if (!isset(Api::TKN[get_class($solver)][$t])) continue;
                
                $token = self::tkn($api, $host, $_ke, $_ty, $_ex);
                if ($token) {
                    return array_merge($solution, [
                        'g-recaptcha-response' => $token,
                        'cf-turnstile-response' => $token,
                        'h-captcha-response' => $token,
                        'g-recaptcha-response-v3' => $token
                    ]);
                }
            }
        } else {
            logx('err', 'wajib solver provider');
            die;
        }
        
        return !empty($solution) ? $solution : null;
    }
    
    public static function tkn($api, $host, $key, $type, array $Params = []) {
        $solver = config::getKeys($api, $type);
        print(DIMM.BOLD.ITAL.FGo['MAG']."solving  ".RSET);
        
        tes:
        $set = microtime(true);
        while (true) {
            $t = $solver->token($key, $host, $type, $Params);
            if ($t === 777) {
                logx('warn', "Switching to Direct API");
                $t = $api->token($key, $host, $type, $Params);
            }
            if ($t === false) {
                _sle(1);
                continue;
            }
            break; 
        }
        if ($t === null) exit(1);
        $end = microtime(true);
        $api->getInfo();
        logx('err', '[ '.get_class($solver).' ] ', false);
        logg(false, 'elapsed: ' . number_format($end - $set, 3).'s');
        #die;
        return $t;
    }

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
                    $endpoint = $m[1]; break;
                }
            } 
            $token = Scraper::find($html, '_iconcaptcha-token')[0] ?? null;
            if (!$endpoint || !$token) return false;

            $widgetID = self::widgetID();
            $ts = round(microtime(true) * 1000);
            $json = ["payload" => base64_encode(json_encode([
                "widgetId" => $widgetID, "action" => "LOAD", "theme" => "light",
                "token" => $token, "timestamp" => $ts, "initTimestamp" => $ts - 2000
            ]))];

            $res = Net::X($endpoint, 'POST', $json, $cookie, ["x-iconcaptcha-token: $token"], $host, $ua, false, false, $ip);
            if ($res === 99) return 99;
            $r = json_decode(base64_decode($res), true);
            $challengeId = $r['identifier'] ?? null;
            if (!$challengeId) return false;

            for ($i = 0; $i < 5; $i++) {
                $x = (int)(($i * 64) + 32); 
                $ts = round(microtime(true) * 1000);
                $payload = base64_encode(json_encode([
                    "x" => $x, "y" => rand(22,30), "width" => 320, "token" => $token,
                    "action" => "SELECTION", "widgetId" => $widgetID,
                    "timestamp" => $ts, "challengeId" => $challengeId, "initTimestamp" => $ts - 2000
                ]));

                $boundary = '';
                $body = self::webkitID(["payload" => $payload], $boundary);
                $s = Net::X($endpoint, 'POST', $body, $cookie, ["x-iconcaptcha-token: $token", "Content-Type: multipart/form-data; boundary=$boundary"], $host, $ua, false, false, $ip);
                if ($s === 99) return 99;
                $r = json_decode(base64_decode($s), true);
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
    
}

/** @class locally
 * @method smartFP
 * @param string $html
 * @return string
 *
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
 * @method hcdn
 * @param string $cjs
 * @return string
 */
class locally {
    
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
        return "";
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
        $dist = 0; $len = strlen($h1);
        for ($i=0; $i<$len; $i++) if ($h1[$i] !== $h2[$i]) $dist++;
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
