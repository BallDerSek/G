<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

loader(__DIR__); 

function cfGet($url, &$cookieFile, &$userAgent) {
    $att = 0;
    $html = '';

    while ($att < 10) {
        $html = Net::C($url, 'GET', null, $cookieFile, [], '', $userAgent);
        if (!$html) {
            $att++; _sle(2);
            continue;
        }
        $titles = xScraper::xPath($html, "//title");
        $title = isset($titles[0]) ? strtolower($titles[0]) : '';

        if ($title !== '' && stripos($title, 'just a moment') === false) {
            return $html;
        }

        logx('err', "Cloudflare detected");

        $execPy = new execPython($cookieFile, $userAgent);
        $r = $execPy->run('inter', $url);

        if ($r === null) {
            logx('err', "Solver failed");
            $att++; _sle(2);
            continue;
        }

        if (empty($r['user_agent']) || empty($r['cookie_file'])) {
            $att++; _sle(2);
            continue;
        }

        $ua = (string)$r['user_agent'];
        $cookie = (string)$r['cookie_file'];

        $html_fix = Net::C($url, 'GET', null, $cookie, [], $url, $ua);

        if (!$html_fix) {
            $att++; _sle(2);
            continue;
        }

        if (
            stripos($html_fix, 'challenge-platform') === false &&
            stripos($html_fix, 'just a moment') === false
        ) {
            $cookieFile = $cookie;
            $userAgent  = $ua;
            return $html_fix;
        }

        $att++;
        _sle(2);
    }

    return $html;
}