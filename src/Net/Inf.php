<?php

class Inf {
    public static $uagent;
    public static $cookie;
    public static $ip;
    public static $ins;
    public static $context;

    public static function setup($ua, $ck, $ip = null, $ins = false, $id = null) {
        self::$uagent = $ua;
        self::$cookie = $ck;
        self::$ip = $ip;
        self::$ins = $ins;
        self::$context = [
            'id' => (string)$id,
            'ip' => (string)$ip,
            'ins' => (bool)$ins,
            'cookie' => $ck,
            'uagent' => $ua
        ];
        
    }

    public static function netHead(array $ck = []) {
        $pairs = [];
        
        if (empty($ck)) return $pairs;
        
        foreach ($ck as $k => $v) $pairs[] = "$k=$v";
        
        return ["Cookie: " . implode('; ', $pairs)];
    }

    public static function wssHead($or = '', $ua = 'Mozilla/5.0', array $cookie = []) {
        $lang = function_exists('LANGUAGE') ? LANGUAGE() : 'id-ID,id;q=0.9';
        
        $h = ["User-Agent: $ua", "Accept-Language: $lang",];
        
        if ($or !== '') $h[] = "Origin: ".rtrim($or, '/');
        
        if (!empty($cookie)) $h = array_merge($h, self::netHead($cookie));
        
        #$h[] = "Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits";
        
        return $h;
    }

    public static function lastLocation(array $respHeaders, $needle = '', $last = true) {
        $locs = $respHeaders['location'] ?? [];
        if (!$locs) return null;

        if ($needle !== '') {
            $locs = array_values(array_filter($locs, fn($l) => stripos($l, $needle) !== false));
            if (!$locs) return null;
        }

        return $last ? trim(end($locs)) : trim(reset($locs));
    }

    public static function getLog($url = null) {
        $logPath = LIBDIR . "/verbose.log";
        
        if (!is_file($logPath)) return null;
        
        $logContent = _get($logPath);
        
        $regex = ($url === null)  ? '/< location: ([^\r\n]+)/i' : '/<\s*location:\s*(https?:\/\/[^\s\r\n]*'. preg_quote($url, '/') .'[^\s\r\n]*)/i';

        $matches = Scraper::_jP($logContent, $regex);
        
        if (!empty($matches[1])) return urldecode(trim(end($matches[1])));
        
        if ($url === null) logx('err', "article unknown");
        
        return null;
    }
    
    public static function check($host, array $h = [], $pattern = '', $foll = false) {
        
        $res = Net::X($host, 'GET', null, self::$cookie, $h, $host, self::$uagent, ip: self::$ip, foll: $foll, ins: self::$ins, d: true);
        
        #var_dump($res); _rl('lanjut: ');
        
        $html = $res['body'] ?? null;
        if (!is_string($html)) {
            return ['ok' => false, 'html' => null, 'err' => 'Network error'];
        }
        $ok = (stripos($html, 'logout') !== false || stripos($html, 'dashboard') !== false)
            && ($pattern === '' || stripos($html, $pattern) === false)
            && (!stripos($html, 'Just a moment') !== false || stripos($html, 'Attention Required!') !== false);

        return ['ok' => $ok, 'html' => $html];

    }
    
    public static function injectCookie($cookiePath, $token, $url, $name = 'cf_clearance') {
        if (empty($cookiePath) || !file_exists($cookiePath)) return false;
    
        $domain = parse_url($url, PHP_URL_HOST);
        $cookieDomain = '.' . ltrim($domain, '.');
        $secure = (parse_url($url, PHP_URL_SCHEME) === 'https') ? "TRUE" : "FALSE";
    
        $content = _get($cookiePath);
        $isNetscape = strpos($content, "# Netscape HTTP Cookie File") !== false;
        
        if (!$isNetscape) {
            $pairs = explode('; ', $content);
            $newLines = ["# Netscape HTTP Cookie File"];
            
            foreach ($pairs as $pair) {
                if (empty($pair)) continue;
                $parts = explode('=', $pair, 2);
                if (count($parts) == 2) {
                    $newLines[] = implode("\t", [
                        $cookieDomain,
                        "TRUE",
                        "/",
                        $secure,
                        "0",
                        $parts[0],
                        $parts[1]
                    ]);
                }
            }
            
            _put($cookiePath, implode("\n", $newLines) . "\n");
        }
    
        $lines = file($cookiePath, FILE_IGNORE_NEW_LINES);
        $filtered = [];
    
        foreach ($lines as $l) {
            if ($l === '' || $l[0] === '#') {
                $filtered[] = $l;
                continue;
            }
    
            $col = explode("\t", $l);
            if (count($col) >= 7 && $col[5] === $name) continue;
            $filtered[] = $l;
        }
    
        $filtered[] = implode("\t", [
            $cookieDomain,
            "TRUE",
            "/",
            $secure,
            time() + 43200,
            $name,
            $token
        ]);
    
        return _put($cookiePath, implode("\n", $filtered) . "\n");
    }
    
}
