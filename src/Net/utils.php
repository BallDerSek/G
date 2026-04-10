<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

loader(__DIR__); 

function checkLogin($host, array $h, $ip = null, $pattern = '') {
    global $cookieFile, $userAgent;

    $html = Net::C($host, 'GET', null, $cookieFile, $h, '', $userAgent, false, false, $ip);
    
    $ok = is_string($html) && (
        str_contains($html, 'Logout')
        || str_contains($html, 'Dashboard')
        || ($pattern !== '' && !str_contains($html, $pattern))
    );
    return ['ok' => $ok, 'html' => $html];
}

function lastLocation(array $respHeaders, $needle = '', $last = true) {
    $locs = $respHeaders['location'] ?? [];
    if (!$locs) return null;

    if ($needle !== '') {
        $locs = array_values(array_filter($locs, fn($l) => stripos($l, $needle) !== false));
        if (!$locs) return null;
    }

    return $last ? trim(end($locs)) : trim(reset($locs));
}

function artikel() {
    $log = _get(LIBDIR . "/verbose.log");
    $matches = rScraper::jPath($log, '/< location: ([^\r\n]+)/i');
    //@unlink($log);
    if (!empty($matches[1])) {
        _sle(1); return end($matches[1]);
    } else { logx('err', "arcticle unknown"); return null; }
}

function getLog($url) {
    $logContent = _get(LIBDIR . "/verbose.log");
    #$pattern = '/<\s*location:\s*(https?:\/\/[^\s\r\n]*' . preg_quote($url) . '[^\s\r\n]*)/i';

    #$pattern = '/<\s*location:\s*(https?:\/\/[^\s\r\n]*' . preg_quote($url, '/') . '[^\s\r\n]*)/i';
    /*
    if (preg_match_all($pattern, $logContent, $matches)) {
        _sle(1); return urldecode(trim(end($matches[1])));
    }
    */
    
    $matches = rScraper::jPath($logContent, '/<\s*location:\s*(https?:\/\/[^\s\r\n]*'. preg_quote($url, '/') .'[^\s\r\n]*)/i');
    if (!empty($matches[1])) {
        _sle(1); return urldecode(trim(end($matches[1])));
    }
    return null;
}







