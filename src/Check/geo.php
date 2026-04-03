<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#GEO
function getGeo() {

    $u = [
        'ipinfo'  => 'https://ipinfo.io/json',
        'ipapi'   => 'http://ip-api.com/json/',
        'geojs'   => 'https://get.geojs.io/v1/ip/geo.json',
        'ipwhois' => 'https://ipwhois.app/json/',
    ];

    $mappings = [
        'ipinfo'  => ['timezone','country','country','ip'],
        'ipapi'   => ['timezone','country','countryCode','query'],
        'geojs'   => ['timezone','country','country_code','ip'],
        'ipwhois' => ['timezone','country','country_code','ip'],
    ];

    foreach ($u as $service => $url) {
        $j = Net::C($url, 'GET', null, null, [], '', 'Mozilla/5.0');
        if (!is_string($j) || $j === '') continue;
        $data = json_decode($j, true);
        if (!is_array($data)) continue;
        [$tz, $c, $cc, $ip] = $mappings[$service];
        if (!empty($data[$ip])) {
            return [
                'ip' => $data[$ip] ?? null,
                'timezone' => $data[$tz] ?? null,
                'country' => $data[$c] ?? null,
                'country_code' => $data[$cc] ?? null
            ];
        }
    }

    return false;
}

function geoLanguage($cc) {
    $cc = strtoupper(trim($cc));

    $map = [
        'ID' => 'id-ID,id',
        'MY' => 'ms-MY,ms',
        'PH' => 'fil-PH,fil,en-PH,en',
        'AE' => 'ar-AE,ar',
        'SA' => 'ar-SA,ar',
        'KR' => 'ko-KR,ko',
        'JP' => 'ja-JP,ja',
        'CN' => 'zh-CN,zh',
        'TW' => 'zh-TW,zh',
        'US' => 'en-US,en',
        'GB' => 'en-GB,en',
        'NL' => 'nl-NL,nl',
        'CH' => 'de-CH,de', 
        'BE' => 'nl-BE,nl',  
    ];
    $base = $map[$cc] ?? 'en-US,en';
    if (stripos($base, 'en') === false) {
        $base .= ',en-US,en';
    }

    return $base;
}

function checkGeo(): void {
    $g = underline("checking nett", fn() => getGeo());

    if (!is_array($g)) {
        logx('err', "unstable network");
        die;
    }

    $c = strtoupper((string)($g['country_code'] ?? ''));
    if ($c === '') $c = 'ID';
    $lang = strtolower($c);

    $tz = (string)($g['timezone'] ?? '');
    if ($tz === '') $tz = 'Asia/Jakarta';

    $ip = (string)($g['ip'] ?? '');
    if ($ip === '') $ip = '0.0.0.0';

    $GLOBALS['_CTX']['geo'] = [
        'ip'           => $ip,
        'country'      => (string)($g['country'] ?? ''),
        'country_code' => $c,
        'timezone'     => $tz,
        'language'     => geoLanguage($c),
    ];
} 