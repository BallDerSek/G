<?php
if (!defined('ROOT')) { die; }
#$api = onKeys();

$cookieFile = getCookie();
$userAgent = getUagent();
$acc = credential([], true);
$login = $acc['login'];

$host = 'https://earnloop.online';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/?ref=168cd377d0';

banner(); 
login: 
    
$set = 0;
$ads = false;
while (true) {
    
    do {
        $l = checkLogin($host.'/dashboard', headers('', $host, $domain), null, "login-panel");
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            continue;
        }
        
        @unlink($cookieFile);
        taskPrintCenter('logging in', 'err');
        $_0 = Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
        
        if (!empty($_0)) {
            Net::C($host.$r, 'POST', ['email' => $login, 'login' => ''], $cookieFile, [], $host.$r, $userAgent);
        }
    } while (empty($dash));
    
    $_fa = xScraper::xPath($dash, "//div[@id='faucetCollapseMobile']//a/@href");
    $fa = $host.$_fa[0];
    
    while (true) {
        $fau = Net::C($fa, 'GET', null, $cookieFile, [], $host, $userAgent);
        
        if (empty($fau)) continue;
        
        if (str_contains($fau, 'Sponsor verification require')) {
            $ads = true;
            break;
        }
        
        $f = xScraper::payload($fau)[0];
        $pa = $f['payload'];
        
        $end = microtime(true);
        $wait = 17 - (int)ceil($end - $set);
        if ($wait > 0) {
            styler("waiting $wait", fn() => _sle($wait));
        }
        
        $cla = Net::C($fa, 'POST', array_merge($pa, ['claim' => '']), $cookieFile, [], $fa, $userAgent);
        #_put('cla.html', $cla);
        if (!empty($cla)) {
            $_suc = xScraper::xPath($cla, "//div[contains(@class, 'alert-success')]");
            $_err = xScraper::xPath($cla, "//div[contains(@class, 'alert-danger')]");
            if (!empty($_suc)) {
                $msg = trim($_suc[0]);
                logx("ok", trim(explode('(', $msg)[0]), false, true);
            } elseif (!empty($_err)) {
                $msg = trim($_err[0]);
                logx("err", trim($msg), false);
            }
            $_b = xScraper::xPath($cla, "//div[@class='faucet-wallet-balance']");
            logx("", '  [ ' .trim($_b[0]). ' ]');
            $set = microtime(true);
        }
    }
    
    if ($ads) {
        
        $_p = rScraper::pPath($fau, 'data-slot')[1] ?? '';
        $_t = rScraper::pPath($fau, 'data-token')[1] ?? '';
        
        #if ($t_ads) {
        if ($_t && $_p) {
            styler('Waiting for ads', fn() => _sle(rand(15, 45)));

            $po = ['token' => $_t[0], 'slot'  => $_p[0]];
            $cla = Net::X($host . "/promo_gateway.php", "POST", $po, $cookieFile, headers($host, $fa, '', [], $userAgent, true), '', null, true);
        }
    }


}

