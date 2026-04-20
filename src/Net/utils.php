<?php

class inf {
    public static $uagent;
    public static $cookie;
    public static $ip;

    public static function setup($ua, $cookie, $ip = null) {
        self::$uagent = $ua;
        self::$cookie = $cookie;
        self::$ip = $ip;
    }

    public static function netHead(array $cookie = []) {
        if (empty($cookie)) return [];
        
        $pairs = [];
        foreach ($cookie as $k => $v) {
            $pairs[] = "$k=$v";
        }
        return ["Cookie: " . implode('; ', $pairs)];
    }

    public static function wssHead($origin = '', $ua = 'Mozilla/5.0', array $cookie = []) {
        $lang = function_exists('LANGUAGE') ? LANGUAGE() : 'id-ID,id;q=0.9';
        
        $h = [
            "User-Agent: $ua",
            "Accept-Language: $lang",
        ];
        
        if ($origin !== '') {
            $h[] = "Origin: " . rtrim($origin, '/');
        }
        
        if (!empty($cookie)) {
            $h = array_merge($h, self::netHead($cookie));
        }
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
        
        $regex = ($url === null) 
            ? '/< location: ([^\r\n]+)/i'
            : '/<\s*location:\s*(https?:\/\/[^\s\r\n]*'. preg_quote($url, '/') .'[^\s\r\n]*)/i';

        $matches = Scraper::_jP($logContent, $regex);
        
        if (!empty($matches[1])) {
            _sle(1); 
            return urldecode(trim(end($matches[1])));
        }
        
        if ($url === null) logx('err', "article unknown");
        return null;
    }
    
    public static function check($host, array $h = [], $pattern = '', $ins = false) {
        $ip = self::$ip; 
        
        $html = Net::C($host, 'GET', null, self::$cookie, $h, $host, self::$uagent, false, false, $ip, true, $ins);
        if (!is_string($html)) {
            return ['ok' => false, 'html' => null, 'err' => 'Network error'];
        }
        $ok = ((str_contains($html, 'Logout') || str_contains($html, 'Dashboard') || str_contains($html, 'Account')) && ($pattern === '' || !str_contains($html, $pattern)));
        return ['ok' => $ok, 'html' => $html];
    }
    
}

