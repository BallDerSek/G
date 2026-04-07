<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$cookieFile = getCookie();
$userAgent = getUagent();
$acc = credential([], true);
$login = $acc['login'];

$host = 'https://faucetsamyy.xyz';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?r=gamamoch@gmail.com';


banner(); 
login:

while (true) {
    @unlink($cookieFile);
    
    do {
        $_0 = Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
    } while (empty($_0));

    $f = xScraper::payload($_0)[0];
    $pa = $f['payload']; 

    $c = capt::cha($_0);
    $t = tK($api, $host, $c['keys'][0], $c['type'], $userAgent);
    $pa['g-recaptcha-response'] = $t; 
    $api->getInfo();

    foreach ($pa as $key => $value) {
        if (empty($value)) {
            $pa[$key] = $login;
        }
    }
    
    $_1 = null;
    $_1 = Net::C($host . $f['url'], 'POST', $pa, $cookieFile, [], $host.$r, $userAgent);
    _put('1.html', $_1);

    if (!empty($_1)) {
        $suc = "//div[contains(@class, 'alert')]/text()";
        $msg = xScraper::xPath($_1, $suc);
        if (isset($msg[0])) {
            print(BOLD.FGd['CYN']."[ $login ] ".RSET);
            logx('warn', $msg[0], true, true);
            if (str_contains($msg[0], 'reached the daily claim limit')) exit('daily limit');
        }
    }
}














function tK($api, $host, $key, $type, $ua) {
    if (!$api) {
        logx('err', 'undefined provider'); exit(1);
    }
    
    while (($t = $api->token($key, $host, $type, ['userAgent' => $ua, 'proxy' => ''])) === false);
    if ($t === null) exit(1);
    
    return $t;
}
