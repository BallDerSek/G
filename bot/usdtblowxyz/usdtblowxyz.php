<?php
if (!defined('ROOT')) { die; }


$cookieFile = getCookie();
$userAgent = getUagent();
$acc = credential([], true);
$login = $acc['login'];

$host = 'https://usdtblow.xyz';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?ref=gamamoch%40gmail.com';


banner(); 
login:

while (true) {
    $_0 = Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
    if (empty($_0)) continue;
    
    $f = xScraper::payload($_0);
    if (!empty($f)) {
        $pa = $f[0]['payload'];
        if (isset($pa['math_answer'])) {
            $pa['math_answer'] = mA($pa['math_q1'], $pa['math_q2'], $pa['math_op']);
            $pa['email'] = $login;
        }
    }
    
    print_r($pa);
    $_1 = Net::C($host, 'POST', $pa, $cookieFile, [], $host.$r, $userAgent);
    _put('1.html', $_1);
    if (!empty($_1)) {
        $_suc = xScraper::xPath($_1, "//div[contains(@class,'alert-success')]");
        $_err = xScraper::xPath($_1, "//div[contains(@class,'alert-error')]");
        if (!empty($_suc)) {
            logx('ok', $_suc[0]);
        } elseif (!empty($_err)) {
            logx('err', $_err[0]);
        }
        styler("waiting", fn() => _sle(57));
    }
    
}



function mA($q1, $q2, $op) {
    switch ($op) {
        case '+': return $q1 + $q2;
        case '-': return $q1 - $q2;
        case '*': return $q1 * $q2;
        case '/': return $q2 != 0 ? $q1 / $q2 : null;
        default:  return null;
    }
}
