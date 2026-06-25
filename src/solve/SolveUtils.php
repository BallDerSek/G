<?php


class SolveUtils {
    
    private const NODE_WRAPPER = <<<'JS'
const fs = require('fs');
const jsCode = fs.readFileSync(0, 'utf8');

global.window = { location: { hostname: '' }, screen: {}, clientInformation: {} };
global.document = {
  write: (s='') => { console.log("DUMP_START" + String(s) + "DUMP_END"); },
  getElementById: () => ({ value: '' }),
  querySelector: () => ({ value: '' })
};
global.navigator = { appCodeName: 'Mozilla', platform: 'Win32' };

// hook decodeURIComponent
const _dec = global.decodeURIComponent;
global.decodeURIComponent = function(v) {
  const out = _dec(v);
  if (typeof out === 'string' && (out.includes('function') || out.includes('<') || out.length > 300)) {
    console.log("DUMP_START" + out + "DUMP_END");
  }
  return out;
};

// hook eval
const _eval = global.eval;
global.eval = function(code) {
  if (typeof code === 'string' && code.length > 200) {
    console.log("DUMP_START" + code + "DUMP_END");
  }
  return _eval(code);
};

// hook Function constructor
const _Function = global.Function;
global.Function = function(...args) {
  const body = args[args.length - 1];
  if (typeof body === 'string' && body.length > 200) {
    console.log("DUMP_START" + body + "DUMP_END");
  }
  return _Function(...args);
};

// hook atob 
if (typeof global.atob !== 'function') {
  global.atob = (s) => Buffer.from(String(s), 'base64').toString('binary');
}
const _atob = global.atob;
global.atob = function(s){
  const out = _atob(s);
  if (typeof out === 'string' && out.length > 200) {
    console.log("DUMP_START" + out + "DUMP_END");
  }
  return out;
};

try { _eval(jsCode); } catch (e) {}
JS;
    
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

    public static function webkitID(array $fo, &$bo) {
        if (empty($bo)) $bo = '----WebKitFormBoundary' . bin2hex(random_bytes(8));
        
        $body = '';
        foreach ($fo as $name => $value) {
            $body .= "--{$bo}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= $value . "\r\n"; 
        }
        $body .= "--{$bo}--\r\n";
        return $body;
    }
    
    public static function histC($input): ?array {
        
        if (!getDeps('gd@php')) die(Logger::X('err', 'gd@php is missing'));
        
        $im = self::createImg($input);
        if (!$im) return null;
        
        $size = 32;
        $thumb = imagecreatetruecolor($size, $size);
        imagecopyresampled($thumb, $im, 0, 0, 0, 0, $size, $size, imagesx($im), imagesy($im));
        
        $bins = 8;
        $hist = array_fill(0, $bins * 3, 0);
        $total = $size * $size;
        
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                $hist[(int)($r / 256 * $bins)]++;
                $hist[$bins + (int)($g / 256 * $bins)]++;
                $hist[$bins * 2 + (int)($b / 256 * $bins)]++;
            }
        }
        
        @imagedestroy($thumb);
        @imagedestroy($im);
        return array_map(fn($v) => $v / $total, $hist);
    }
    
    public static function histD(array $h1, array $h2) {
        $d = 0.0;
        $len = min(count($h1), count($h2));
        for ($i = 0; $i < $len; $i++) {
            $sum = $h1[$i] + $h2[$i];
            if ($sum > 0) $d += (($h1[$i] - $h2[$i]) ** 2) / $sum;
        }
        return $d;
    }

    public static function pHash($input) {
        
        if (!getDeps('gd@php')) die(Logger::X('err', 'gd@php is missing'));
        
        $im = self::createImg($input);
        if (!$im) return null;
        
        $N = 32;
        $thumb = imagecreatetruecolor($N, $N);
        imagecopyresampled($thumb, $im, 0, 0, 0, 0, $N, $N, imagesx($im), imagesy($im));
        
        $gray = [];
        for ($y = 0; $y < $N; $y++) {
            for ($x = 0; $x < $N; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $gray[$y * $N + $x] = self::getGray($rgb);
            }
        }
        
        $dct = [];
        for ($u = 0; $u < 8; $u++) {
            for ($v = 0; $v < 8; $v++) {
                $sum = 0.0;
                for ($y = 0; $y < $N; $y++) {
                    for ($x = 0; $x < $N; $x++) {
                        $sum += $gray[$y * $N + $x]
                             * cos((2 * $x + 1) * $u * M_PI / (2 * $N))
                             * cos((2 * $y + 1) * $v * M_PI / (2 * $N));
                    }
                }
                $cu = ($u === 0) ? 1 / sqrt(2) : 1.0;
                $cv = ($v === 0) ? 1 / sqrt(2) : 1.0;
                $dct[$u * 8 + $v] = (2.0 / $N) * $cu * $cv * $sum;
            }
        }
        
        $ac = array_slice($dct, 1);
        sort($ac);
        $mid = (int)(count($ac) / 2);
        $median = (count($ac) % 2 === 0) ? ($ac[$mid - 1] + $ac[$mid]) / 2 : $ac[$mid];
        
        $hash = '';
        foreach ($dct as $i => $v) {
            if ($i === 0) continue;
            $hash .= ($v > $median) ? '1' : '0';
        }
        
        @imagedestroy($thumb);
        @imagedestroy($im);
        return $hash;
    }
    
    public static function aHash($input) {
        
        if (!getDeps('gd@php')) die(Logger::X('err', 'gd@php is missing'));
        
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
        
        @imagedestroy($thumb); 
        @imagedestroy($im);
        return $hash;
    }

    public static function dHash($input) {
        
        if (!getDeps('gd@php')) die(Logger::X('err', 'gd@php is missing'));
        
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
        @imagedestroy($thumb);
        @imagedestroy($im);
        return $hash;
    }

    public static function hamming($h1, $h2) {
        $dist = 0; 
        $len = strlen($h1);
        for ($i = 0; $i < $len; $i++) if ($h1[$i] !== $h2[$i]) $dist++;
        return $dist;
    }

    private static function createImg($input) {
        
        if (!getDeps('gd@php')) die(Logger::X('err', 'gd@php is missing'));
        
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
        $u32  = function($n) {
            return $n & 0xFFFFFFFF; 
        };
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
    
    public static function Pow($salt, $di = 4, $de = ':', $max = 2000000) {
        $prefix = str_repeat('0', $di);
        $nonce = 0;
        
        while ($nonce < $max) {
            $test = $salt . $de . $nonce;
            $hash = hash('sha256', $test);
            
            if (strpos($hash, $prefix) === 0) return ['nonce' => $nonce, 'hash' => $hash];
            $nonce++;
        }
        
        return null;
        
    }

    public static function dumpJs($_input, $_putin = null, $unlink = false): string|false {
        
        if (!getDeps('nodejs')) die(Logger::X('err', 'nodeJs is missing'));
        
        if (is_file($_input)) {
            $jsCode = _get($_input);
            if ($jsCode === false) return false;
            if ($unlink) @unlink($_input);
        } else {
            $jsCode = $_input;
        }

        if ($jsCode === '') return false;

        $cmd = 'node -e ' . escapeshellarg(self::NODE_WRAPPER);

        $proc = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($proc)) return false;

        fwrite($pipes[0], $jsCode);
        fclose($pipes[0]);

        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($proc);

        $all = (string)$out.(string)$err;
        if ($all === '') return false;

        if (!preg_match_all('/DUMP_START(.*?)DUMP_END/is', $all, $mm) || empty($mm[1])) return false;

        $cands = array_map(fn($s) => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $mm[1]);

        usort($cands, fn($a, $b) => strlen($b) <=> strlen($a));
        $best = $cands[0];

        if ($_putin !== null && $_putin !== '') _put($_putin, $best);

        return $best;
    }
}
