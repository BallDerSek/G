<?php

class Solve {
    
    public static function exec($html, $host, ?Provider $api, $ins = false) {
        $ua = inf::$uagent;
        $cookie = inf::$cookie;
        $ip = inf::$ip;
        
        if (!$_cap = Capt::cha($html)) {
            logx('info', 'no captcha detected');
            return null;
        }

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
            logx('err', 'undefined provider');
            die;
        }
        
        return !empty($solution) ? $solution : null;
    }

    public static function tkn($api, $host, $key, $type, array $Params = []) {
        $solver = config::getKeys($api, $type);
        print(DIMM.BOLD.ITAL.FGo['MAG']."solving  ".RSET);
        $set = microtime(true);
        while (($t = $solver->token($key, $host, $type, $Params)) === false);
        if ($t === null) exit(1);
        $end = microtime(true);
        $solver->getInfo();
        logg(false, '  elapsed: ' . number_format($end - $set, 3).'s');
        return $t;
    }

    public static function iCaptcha($html, $host) {
        $cookie = inf::$cookie;
        $ua = inf::$uagent;
        $ip = inf::$ip;

        return styler("SOLVING icaptcha", function() use ($html, $host, $ip, $cookie, $ua) {
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
                $r = json_decode(base64_decode($s), true);
                if (!empty($r['completed'])) {
                    return ['captcha' => 'icaptcha', '_iconcaptcha-token' => $token, 'ic-rq' => 1, 'ic-wid' => $widgetID, 'ic-cid' => $challengeId, 'ic-hp' => ''];
                } 
                _sle(1);
            }
            return false;
        });
    }

    public static function eCaptcha($host) {
        // Ambil data identitas dari pusat (inf)
        $cookie = inf::$cookie;
        $ua     = inf::$uagent;
        $ip     = inf::$ip;

        return styler("SOLVING ecaptcha", function() use ($host, $cookie, $ua, $ip) {
            // 1. Get Token
            $resToken = Net::X($host.'ecaptcha/get_token', 'GET', null, $cookie, [], $host, $ua, false, false, $ip);
            $json = json_decode($resToken, true);
            $token = $json['token'] ?? null;
            
            // 2. Get Task
            $resTask = Net::X($host.'ecaptcha/get_captcha', 'GET', null, $cookie, [], $host, $ua, false, false, $ip);
            $task = json_decode($resTask, true);
            if (empty($task['captcha_key']) || empty($task['question'])) return false;
            
            // 3. Parsing Answer (Logic: ambil kata terakhir setelah titik dua)
            $sel = explode(':', $task['question']);
            $answer = strtolower(trim(end($sel))) . '.gif';
            
            $payload = [
                'key'      => $task['captcha_key'],
                'selected' => $answer,
                'token'    => $token
            ];
            
            // 4. Validate
            $resValid = Net::X($host.'ecaptcha/validate_icon', 'POST', $payload, $cookie, [], $host, $ua, false, false, $ip);
            $v = json_decode($resValid, true);
            
            if (($v['status'] ?? '') === 'valid') {
                return [
                    'captcha'       => 'emoji_captcha',
                    'captcha_key'   => $task['captcha_key'],
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

class locally {

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
    
}

/*
    if (isset($payload['smart_token'])) {
        $payload['smart_token'] = base64_encode(json_encode([
            'ts'    => time() * 1000,
            'cpu'   => 8,
            'mem'   => 4,
            'w'     => 1366,
            'h'     => 768,
            'touch' => 0,
            'moves' => 0,
        ]));
    }
*/