<?php


banner();


$ck = config::cookie();
$ua = config::uagent();

$host = 'https://ad-doge.com';
$ip = '45.14.135.47';

$_0 = Net::C($host.'/login', 'GET', null, $ck, [], '', $ua, ip: $ip, ins: true);

$num = 900;
$icons = [];

while ($num < 1000) {
    $dir = _lib('ccaptcha', $num);
    #$cc = Net::C($host.'/captcha', 'GET', null, $ck, [], '', $ua, ip: $ip, ins: true);

    $cc_u = scraper::_jP(
        Net::C($host.'/captcha', 'GET', null, $ck, [], '', $ua, ip: $ip, ins: true),
        '/src\s*=\s*[\'"`](\/cc\/[\w\d]+\.js\?[^"\'`]+)[\'"`]/'
    );

    $cc_js = Net::C(
        $host.$cc_u[1][0],
        'GET',
        null,
        $ck,
        [],
        $host,
        $ua,
        ip: $ip,
        ins: true
    );

    $cc_im = scraper::_jP(
        $cc_js,
        '/src="data:image\/png;base64,([^"]+)"/i'
    )[1][0];

    $cc_ic = json_decode(
        scraper::_jP(
            $cc_js,
            '/captchaData\s*=\s*\{"options":\s*(\[.*?\])\}/s'
        )[1][0],
        true
    );

    $icons = array_merge($icons, $cc_ic);

    _put($dir.'/main.png', base64_decode($cc_im));

    _put($dir.'/cc.json',json_encode([
        'main' => $cc_im,
        'opsi' => $cc_ic
    ], JSON_PRETTY_PRINT));

    taskPrintCenter("saved $num", 'ok');

    $num++;
}

$icons = array_values(array_unique($icons));

_put(_lib('ccaptcha').'/ic.json',json_encode($icons, JSON_PRETTY_PRINT));

echo "total icon: ".count($icons)."\n";