<?php
if (!defined('ROOT')) { die; }
$api = onKeys();
$cookieFile = getCookie();
$userAgent = getUagent();
$acc = credential();

$mail = $acc['mail'];
$pass = $acc['pass'];

$host = 'https://freesolana.top';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '213.133.103.168';


banner(); 
login:
while (true) { 
    #@unlink($cookieFile);
    $l = checkLogin("$host/dashboard", headers('', $host, $domain), $ip, '/register');
    logx('info', 'logging in');
    
    if ($l['ok']) {
        taskPrintCenter('logged in', 'ok');
        $dash = $l['html'];
        logx('info', 'logged in');
        break;
    }
    
    @unlink($cookieFile);
    taskPrintCenter('logging in', 'err');
    reload:
    $_0 = Net::C("$host/login", 'GET', null,  $cookieFile, [], '', $userAgent, false, false, $ip);
    #_put('0.html', $_0); die;
    $f = xScraper::payload($_0)[0];
    $pa = $f['payload'];
    
    if (str_contains($_0, 'rscaptcha_token')) {
        #logx('info', 'rscaptcha');
        $_u = xScraper::xPath($_0, "//img[@id='rscaptcha_img']/@src")[0];
        $img = Net::C($_u, 'GET', null, $cookieFile, [], "$host/login", $userAgent);
        #_put('img.png', $img);
        $co = $api->base64($img, 'rs_icon');
        print_r($co); logx();
        if ($co) {
            preg_match_all('/\d+/', $co, $_co);
            if (count($_co[0]) >= 2) {
                $x = $_co[0][0];
                $y = $_co[0][1];
            }
        } else {
            continue;
        }
        $timestamp = time();
        $src = xtractJs($_0);
    
        if (count($src) <= 1) {
            dumpJsFlex($src[0], 'i.js');
            exec("synchrony i.js");
            $pe = $result = rsResponse('i.cleaned.js', $x, $y, $timestamp, $userAgent, $img);
            @unlink('i.js');
            @unlink('i.cleaned.js');
            $pe = ['rscaptcha_response' => $pe];
        } else {
            logx('err', 'much src inline');
            die;
            foreach ($src as $i => $rawJs) {
                $dump = dumpJsFlex($rawJs, "$i.js");
                if ($dump !== false) {
                    logx('ok', "[$i] len=" . strlen($dump));
                } else {
                    logx('err', "[$i] FAIL");
                }
            }
        }
        $cre = ['email' => $mail, 'password' => $pass, 'captcha' => 'rscaptcha'];
        $po = array_merge($pa, $pe, $cre);
        #print_r($po);
    }
    
    Net::C($f['url'], 'POST', $po,  $cookieFile, [], "$host/login", $userAgent, false, false, $ip);
    
}
goto tes;
faucet:
while (true) {
    fau:
    $fau = Net::C("$host/faucet", 'GET', null,  $cookieFile, [], "$host/dashboard", $userAgent, false, false, $ip);
    
    $fo = xScraper::payload($fau) ?? [];
    if (empty($fo)) {
        $msg = xScraper::xPath($fau, "//div[contains(@class,'alert-warning') and contains(.,'shortlink')]/text()");
        if (!empty($msg)) {
            logx('warn', $msg[0], true, true);
            $_shortlink = true;
            break;
        }
        styler("waiting", fn() => _sle(10));
        continue;
    }
    $f = $fo[0];
    $pa = $f['payload'];

    if (str_contains($fau, 'rscaptcha_token')) {
        #logx('info', 'rscaptcha');
        $_u = xScraper::xPath($fau, "//img[@id='rscaptcha_img']/@src")[0];
        $img = Net::C($_u, 'GET', null, $cookieFile, [], "$host/login", $userAgent);
        #_put('img.png', $img);
        $co = $api->base64($img, 'rs_icon');
        #print_r($co);
        if ($co) {
            if (preg_match_all('/\d+/', $co, $_co)) {
                if (count($_co[0]) >= 2) {
                    $x = $_co[0][0];
                    $y = $_co[0][1];
                }
            } else {
                #goto reload;
                continue;
            }
        }
        $timestamp = time() * 1000;
        $src = xtractJs($fau);
    
        if (count($src) <= 1) {
            dumpJsFlex($src[0], 'f.js');
            exec("synchrony f.js");
            $pe = $result = rsResponse('f.cleaned.js', $x, $y, $timestamp, $userAgent);
            @unlink('f.js');
            @unlink('f.cleaned.js');
            $ca = ['rscaptcha_response' => $pe, 'captcha' => 'rscaptcha'];
        } else {
            logx('err', 'much src inline');
            die;
        }
        #$po = array_merge($pa, $ca);
        #print_r($po);
}

    if (str_contains($fau, 'antibotlinks_reset')) {
        $atbData = ATBtest::get($fau);
        $_atb = $api->atb($atbData);
        $atb = ['antibotlinks' => $_atb];
    }
    
    $po = array_merge($pa, $ca, $atb);
    print_r($po);
    $cla = Net::C($f['url'], 'POST', $po,  $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
    _put('cla.html', $cla);
    $m = rScraper::jPath($cla, "/Swal\.fire\([^,]+,\s*'([^']+)'/");
    print_r($m);
    if (!empty($m[1][0])) {
        logx('info', $m[1][0], false, true);
        $pattern = '/class="preview-balance"\s+value="([^"]+)"/';
        $_bal = rScraper::jPath($cla, $pattern)[1];
        logx('ok', ' [ bal:'.$_bal[0].' ] ', true, true);
        
    }
    
}

shortlink:
if ($_shortlink) {
    $links = Net::C("$host/links", 'GET', null, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
    $_sl = sScraper::extract($links);
    $finalUrl = null;
    foreach ($_sl as $name => [$id,$lim]) {
        if ($lim[0] === '0') {
            continue;
        }

        $go = Net::C("$host/links/go/$id", 'GET', null, $cookieFile, [], "$host/links", $userAgent, false, false, $ip, false);

        $r = rScraper::pPath($go,'href');
        $short = $r[1][0] ?? null;
        if (!$short) {
            logx('err','redirect not found');
            continue;
        }
        try {
            $bypass = new _shortlinks($short);
            $finalUrl = $bypass->links($api ?? null);
            break;
        } catch (RuntimeException $e) {
            logx('err', $name. " " .$e->getMessage());
        }
    }

    if (!$finalUrl) {
        logx('warn','all shortlinks failed', true, true);
        goto faucet;
    }
    $sl = Net::C($finalUrl, 'GET', null, $cookieFile, [], "$host/links", $userAgent, false, false, $ip);
    $m = rScraper::jPath($sl, "/Swal\.fire\('[^']+',\s*'([^']+)'/i");
    if (!empty($m)) {
        $msg = $m[1][0] ?? null;
        logx('ok', $msg, true, true);
    }
    #goto faucet;
    goto shortlink;
}



tes:
$links = Net::C("$host/links", 'GET', null, $cookieFile, [], "$host/faucet", $userAgent, false, false, $ip);
_put('0.html', $links);

/*
$_sl = sScraper::extract($links);
print_r($_sl);
*/

$bct_sl = xScraper::xPath($links, "//*[starts-with(@id, 'bct-')]//a/@href");
#print_r($bct_sl);

$bitco_get = Net::C($bct_sl[0], 'GET', null, $cookieFile, [], "$host/faucet", "$host/links");
_put('sl.html', $bitco_get);

$bct_u = rScraper::jPath($bitco_get, "/window\.location\.href\s*=\s*['\"]([^'\"]+)['\"]/");

if (!empty($bct_u[1][0])) {
    $bct_go = $bct_u[1][0];
    logx('info', $bct_go);
    
    goto tes;
    $bitco_go = Net::C($bct_go, 'GET', null, $cookieFile, [], "$host/links");
    _put('sl.html', $bitco_go);
}
