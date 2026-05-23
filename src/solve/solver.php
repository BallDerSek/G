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
        
        #return [];
        
        $ua = inf::$uagent;
        $cookie = inf::$cookie;
        $ip = inf::$ip;
        
        $solution = [];
        $_cap = Capt::cha($html);
        
        $_fields = null; 
        $_select = '';
        $captchaFields = [];

        if (is_array($pa)) {
            $_option = null;
            $_foundField = null;
            foreach ($pa as $key => $val) {
                if (str_contains(strtolower($key), 'captcha')) {
                    
                    $captchaFields[] = $key;
                    if (is_array($val)) {
                        $_option = $val;
                    } else {
                        $_foundField = $key;
                    }
                }
            }
            $_fields = $_foundField ?? $_fields;
            if ($_option === null && $_foundField !== null) {
                $_option = [$pa[$_foundField]];
            }
            if (!empty($_option)) {
                $pref = ['shield', 'rot', 'smart', 'turnstile', 'hcaptcha', 'recaptcha'];
                foreach ($pref as $p) {
                    foreach ($_option as $opt) {
                        if (str_contains(str_replace(['-', '_'], '', strtolower($opt)), $p)) {
                            $_select = $opt;
                            break 2;
                        }
                    }
                }
                if (!$_select) $_select = $_option[0];
            }

        } else {
            $_select = (string)$pa;
            $_fields = 'captcha';
            $captchaFields[] = $_fields;
        }

        if ($_fields && $_select) {
            $solution[$_fields] = $_select;
            
            if (is_array($pa)) {
                foreach ($pa as $key => $val) {
                    if (str_contains(strtolower($key), 'captcha')) {
                        if ($key === $_fields) {
                            $solution[$key] = $_select;
                        } else {
                            $solution[$key] = $_select;
                        }
                    }
                }
            }
        }

        if (!empty($_cap['antibot'])) {
            $resAtb = locally::ATB($_cap['antibot']['type'], $api, $html);
            if ($resAtb === 77) return ['trouble' => 'reload'];
            if ($resAtb) $solution['antibotlinks'] = $resAtb;
        }

        if ($_select) {
            $_checks = str_replace(['-', '_'], '', strtolower($_select));
            
            switch ($_checks) {
                case 'shield':
                    if (isset($pa['shield_answer'])) {
                        $resShi = locally::shiCaptcha($html);
                        if ($resShi) $solution = array_merge($solution, $resShi);
                    }
                    break;
                
                case 'rotcaptcha':
                case 'rot':
                    if (isset($pa['rot_captcha_val'])) {
                        $resRot = locally::rotCaptcha($html);
                        if ($resRot) $solution = array_merge($solution, $resRot);
                    }
                    break;
                
                case 'smartcaptcha':
                case 'smart':
                    if (isset($pa['smart_token'])) {
                        $resSmt = locally::smartFP($html);
                        if ($resSmt) $solution['smart_token'] = $resSmt;
                    }
                    break;
            }
        }

        if (isset($_cap['ic_fw'])) {
            $ic = null; $attempt = 0;
            while (!$ic && $attempt < 5) {
                $ic = locally::iCaptcha($html, $host);
                if ($ic === 99) return ['trouble' => 'proxy'];
                $attempt++;
            }
            if ($ic) $solution = array_merge($solution, $ic);
        }
        
        if (isset($_cap['rss'])) {
            $rss_res = self::rss($_cap['rss'], $api, $host, $html);
            if (is_array($rss_res)) {
                $solution = array_merge($solution, $rss_res);
                if (!empty($_fields)) {
                    $solution[$_fields] = 'rscaptcha';
                }
            } else {
                $solution['trouble'] = 'reload';
            }
        }
    
        $ignoreFields = array_merge(['antibotlinks'], $captchaFields);
        $mainSolved = count(array_diff(array_keys($solution), $ignoreFields)) > 0;

        if ($api && !$mainSolved) {
            $priority = [];
            $lowType = str_replace(['-', '_'], '', strtolower($_select));

            if (str_contains($lowType, 'turnstile')) {
                $priority = ['cft'];
            } elseif (str_contains($lowType, 'hcaptcha') || str_contains($lowType, 'hc')) {
                $priority = ['hc'];
            } elseif (str_contains($lowType, 'recaptcha')) {
                $priority = ['rc3', 'rc2'];
            } else {
                $priority = ['cft', 'rc3', 'rc2', 'hc'];
            }

            foreach ($priority as $t) {
                if (!isset($_cap[$t])) continue;
                
                $_ty = $_cap[$t]['type'] ?? $t; 
                $_ke = $_cap[$t]['keys'] ?? null;
                $_ex = array_filter($_cap[$t]['extra'] ?? [], fn($v) => !is_null($v));

                if (!$_ke) continue;
                
                $token = self::tkn($api, $host, $_ke, $_ty, $_ex);
                if ($token === 471) continue; 
                if ($token === 404) return ['trouble' => 'reload']; 
                
                if (is_string($token) && !empty($token)) {
                    $solution = array_merge($solution, [
                        'g-recaptcha-response'    => $token,
                        'cf-turnstile-response'   => $token,
                        'h-captcha-response'      => $token,
                        'hcaptcha-response'       => $token,
                        'g-recaptcha-response-v3' => $token
                    ]);
                    break; 
                }
            }
        } elseif (!$api) {
            if (!$api) (logx('err', 'undefined provider') ?: die);
        }

        if (empty($solution) && empty($_cap)) {
            logx('info', 'no captcha detected');
            return ['nocaptcha' => true];
        }

        return !empty($solution) ? $solution : [];
    }

    public static function tkn($api, $host, $key, $type, array $data = []) {
        $solver = config::getKeys($api, $type); 
        
        print(DIMM.BOLD.ITAL.FGo['MAG']."solving  ".RSET);
        $set = microtime(true);
        $t = null;
        
        $Params = array_merge($data, ['userAgent' => inf::$uagent]);
        for ($retry = 0; $retry < 2; $retry++) {
            $t = $solver->token($key, $host, $type, $Params);
            
            if ($t === 777) {
                if (!isset(Api::TKN[get_class($api)][$type])) return 471; 
                
                logx('ok', "Switching to ".get_class($api));
                $t = $api->token($key, $host, $type, $Params);
                
                if ($t === 71) return 471;
                if ($t === null || $t === false) return 404;
                if ($t) break;
            }
            
            if ($t === 71) return 471; 
            if ($t === null) return 404;
            if ($t && $t !== 777) break;
            _sle(1);
        }
        
        if ($t === false) return 404; 
        
        $api->getInfo();
        logg(false, 'elapsed: ' . number_format(microtime(true) - $set, 3).'s');
        return $t;
    }

    public static function img($api, $host, $type, $img) {
        $solver = config::getKeys($api, $type, 'b64');
        print(DIMM.BOLD.ITAL.FGo['MAG']."solving image... ".RSET);
        $set = microtime(true);
        $res = null;
        
        for ($retry = 0; $retry < 2; $retry++) {
            if (isset(Api::B64[get_class($solver)][$type])) {
                $res = $solver->base64($img, $type);
            } else {
                $res = 777; 
            }
            
            if ($res === 777) {
                if (!isset(Api::B64[get_class($api)][$type])) {
                    return ['trouble' => 'reload']; 
                }
                
                logx('ok', "Switching to " . get_class($api));
                $res = $api->base64($img, $type);
                
                if ($res === 71) {
                    return ['trouble' => 'reload'];
                }
                
                if ($res && $res !== 777) {
                    $api->getInfo();
                    break;
                }
            }
            
            if ($res === 77) return ['trouble' => 'reload'];
            
            if ($res === 71) {
                logx('err', 'unsupported image provider');
                return ['trouble' => 'reload'];
            }
            
            if ($res && $res !== 777) break;
            _sle(1);
        }
        
        if ($res && $res !== 777) {
            logg(false, 'elapsed: ' . number_format(microtime(true) - $set, 3).'s');
            return $res; 
        }
        
        return ['trouble' => 'reload'];
    }

    private static function rss($rss, $api, $host, $html) {
        $solution = [];
        $token = null;
        
        $_M = $rss['type'] ?? null;
        $_K = $rss['keys'] ?? null;
        $_T = $rss['extra']['token'] ?? null;
        $_J = $rss['extra']['js'] ?? null;
        
        if (in_array(null, [$_M, $_K, $_T, $_J], true)) return false;
        
        if (!filter_var($_K, FILTER_VALIDATE_URL)) {
            $_host = rtrim($host, '/');
            $_path = ltrim($_K, '/');
            if (strpos($_host, 'http') !== 0) {
                $_K = "https://$_host/$_path";
            } else {
                $_K = $_host . "/" . $_path;
            }
        }
        
        $img = Net::C($_K, 'GET', null, inf::$cookie, [], $host, inf::$uagent);
        #_put('img.png', $img);
        if (!empty($img) && $img !== 99) {
            $co = self::img($api, $host, $_M, $img);
            if (!isset($co['trouble'])) {
                $_coMatches = scraper::_jP($co, '/\d+/');
                $_co = $_coMatches[0] ?? $_coMatches; 
                
                if (is_array($_co) && count($_co) >= 2) {
                    $x = $_co[0];
                    $y = $_co[1];
                    $utils = ['html' => $html, 'js' => $_J];
                    $token = self::rs($api, $utils, $x, $y, $host);
                    if ($token) {
                        #logx('info', 'token:' . $token);
                        $solution = [
                            'rscaptcha_token' => $_T,
                            'rscaptcha_response' => $token
                        ];
                        # print_r($solution);
                    }
                }
            }
        }
    
    return !empty($solution) ? $solution : false;
}

    private static function rs($api, $utils, $x, $y, $host) {
        $provider = strtolower(get_class($api));
        $token = null;
        
        # if some provider got many invalid
        # u can change to use locally fallback
        
        # uncomment to use by provider, it'll consume few credit
        /*
        if ($provider === 'tertuyul') {
            $data = [
                'clickX' => $x,
                'clickY' => $y,
                'script' => base64_encode($utils['js'])
            ];
            $token = $api->run('rstoken', $data);
        } 
        
        if ($provider === 'skibidixxx') {
            $data = [
                "htmlContent" => $utils['html'],
                "clickX" => $x,
                "clickY" => $y
            ];
            for ($retry = 0; $retry < 3; $retry++) {
                usleep(500000);
                $res = json_decode(Net::S('https://api.waryono.my.id/rspayload.php', 'POST', $data, json: true) ?: '', true);
                #var_dump($res);
                if (isset($res['Payload'])) {
                    $token = $res['Payload'];
                    break;
                }
            }
        }
       */
       
        if (!$token) {
            # this is got 2 method and auto pass
            $rss = new rsResponse(inf::$uagent, $host);
            $token = $rss->exec($utils, $x, $y);
            
        }
        return $token;

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
    
    public static function math($q1, $q2, $op) {
        return match($op) {
            '+' => $q1 + $q2,
            '-' => $q1 - $q2,
            '*' => $q1 * $q2,
            '/' => $q2 != 0 ? (int)($q1 / $q2) : 0,
            default => 0,
        };
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
    
    public static function oddCaptcha($base64, $m = 'angle', $s = 5) {
        if (!getDeps('gd@php')) {
            logx('err', "gd@php is missing");
            exit(9);
        }
        
        $data = (base64_decode($base64, true)) ?: $base64; 
        $image = @imagecreatefromstring($data);
        if (!$image) return false;
        
        $width = imagesx($image);
        $height = imagesy($image);
        $segW = intdiv($width, $s); 
        $scores = [];
        
        for ($idx = 0; $idx < $s; $idx++) {
            $start = $idx * $segW;
            if ($m === 'color') {
                $targetX = $start + intdiv($segW, 2);
                $targetY = intdiv($height, 2);
                
                $totalGray = 0;
                $count = 0;

                for ($ox = -3; $ox <= 3; $ox++) {
                    for ($oy = -3; $oy <= 3; $oy++) {
                        $rgb = imagecolorat($image, $targetX + $ox, $targetY + $oy);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;

                        if (($r + $g + $b) > 150) {
                            $totalGray += self::getGray($rgb);
                            $count++;
                        }
                    }
                }
                $scores[$idx] = ($count > 0) ? ($totalGray / $count) : self::getGray(imagecolorat($image, $targetX, $targetY));
            } else {
                $xCoords = []; 
                $yCoords = [];
                for ($y = 0; $y < $height; $y++) {
                    for ($x = $start; $x < $start + $segW; $x++) {
                        $rgb = imagecolorat($image, $x, $y);
                        if ((($rgb >> 16) & 0xFF) < 220) {
                            $xCoords[] = $x - $start; 
                            $yCoords[] = $y;
                        }
                    }
                }
                if (count($xCoords) < 10) {
                    $scores[$idx] = 0.0;
                    continue; 
                }
                
                $mX = array_sum($xCoords) / count($xCoords);
                $mY = array_sum($yCoords) / count($yCoords);
                $vX = $vY = $cXY = 0.0;
                for ($i = 0; $i < count($xCoords); $i++) {
                    $dx = $xCoords[$i] - $mX; 
                    $dy = $yCoords[$i] - $mY;
                    $vX += $dx * $dx; $vY += $dy * $dy; $cXY += $dx * $dy;
                }
                $scores[$idx] = ($vY == $vX) ? 0.0 : 0.5 * rad2deg(atan2(2 * $cXY, $vY - $vX));
            }
        }
        
        $sorted = $scores; sort($sorted);
        $median = $sorted[intdiv(count($sorted), 2)]; 
        
        $maxDev = -1; $res = 0;
        foreach ($scores as $idx => $val) {
            $dev = abs($val - $median);
            if ($m === 'angle' && $dev > 90) $dev = 180 - $dev;
            if ($dev > $maxDev) {
                $maxDev = $dev;
                $res = $idx;
            }
        }
        @imagedestroy($image);
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

    public static function Pow($salt, $difficulty) {
        $prefix = str_repeat('0', $difficulty);
        $nonce = 0;
        
        while (true) {
            $hash = hash('sha256', $salt . $nonce);
            if (strpos($hash, $prefix) === 0) return $nonce;
            $nonce++;
        }
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
            
            $widgetID = SolveUtils::widgetID();
            $ts = round(microtime(true) * 1000);
            $json = ["payload" => base64_encode(json_encode([
                "widgetId" => $widgetID, 
                "action" => "LOAD", 
                "theme" => "light",
                "token" => $token, 
                "timestamp" => $ts, 
                "initTimestamp" => $ts - 2000
            ]))];
            
            $challengeId = null; 
            for ($retry = 0; $retry < 3; $retry++) {
                $res = Net::X($endpoint, 'POST', $json, $cookie, ["x-iconcaptcha-token: $token"], $host, $ua, false, false, $ip);
                #var_dump($res);
                if (!empty($res) && $res !== 99) {
                    $r = json_decode(base64_decode($res), true);
                    $challengeId = $r['identifier'] ?? null;
                    if ($challengeId) break;
                    _sle(1);
                }
            }
            
            if (!$challengeId) return false;
            
            for ($i = 0; $i < 5; $i++) {
                $x = (int)(($i * 64) + rand(20, 40));
                $ts = round(microtime(true) * 1000);
                $payload = base64_encode(json_encode([
                    "x" => $x,
                    "y" => rand(22,30),
                    "width" => 320,
                    "token" => $token,
                    "action" => "SELECTION",
                    "widgetId" => $widgetID,
                    "timestamp" => $ts,
                    "challengeId" => $challengeId,
                    "initTimestamp" => $ts - 2000
                ]));
                
                $boundary = '';
                $body = SolveUtils::webkitID(["payload" => $payload], $boundary);
                $s = Net::X($endpoint, 'POST', $body, $cookie, ["x-iconcaptcha-token: $token", "Content-Type: multipart/form-data; boundary=$boundary"], $host, $ua, false, false, $ip);
                #var_dump($s);
                if ($s === 99) return 99;
                $r = json_decode(base64_decode($s), true);
                
                if (!empty($r['completed']) || (isset($r['success']) && $r['success'] == true)) {
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
            #var_dump($res);
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
        if (!$apii) return null;
        $api = config::getKeys($apii, $type, 'b64');

        $supportAtb = Api::B64[get_class($api)]['antibot'] ?? false;
        
        if ($type === 'image') {
            #return ' 1 2 3 4';
            if ($supportAtb) {
                return self::atb_I($api, $html); 
            }
            
            logx('err', get_class($api) . " tidak support Antibot");
            return null;
        }
        if ($type === 'emoji') return self::atb_E($html);
        return null;
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

