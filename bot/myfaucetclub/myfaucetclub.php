<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$cookieFile = getCookie();
$userAgent = getUagent();
$acc  = credential();

$mail = $acc['mail'];
$pass = $acc['pass'];

$host = 'https://myfaucet.club';
$reff = '/ref/gamamoch';
$domain = parse_url($host, PHP_URL_HOST);

banner();
login:
{
    $register = false;

    while (true) {
        $l = checkLogin("$host/account", headers('', $host, $domain), null, '/register');

        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break;
        }

        @unlink($cookieFile);
        taskPrintCenter('logging in', 'err');

        $_0 = Net::C("$host/login", 'GET', null, $cookieFile, [], '', $userAgent);

        $f  = xScraper::payload($_0)[0];
        $pa = $f['payload'];

        $c = capt::cha($_0);
        while (($t = $api->token($c['keys'][0], $host, $c['type'])) === false);
        if ($t === null) exit(1);

        $po = array_merge($pa, [
            'cf-turnstile-response' => $t,
            'username' => $mail,
            'password' => $pass
        ]);

        $_1 = Net::C("$host/login", 'POST', $po, $cookieFile, [], "$host/login", $userAgent);

        $m = rScraper::jPath($_1, "/message\s*:\s*['\"]([^'\"]+)['\"]/i") ?? [];

        if (isset($m[1][0]) && str_contains($m[1][0], 'does not exist')) {
            $register = true;
            logx('info', $m[1][0], true, true);
            break;
        }
        
    }
}

register:
{
    if ($register) {
        @unlink($cookieFile);

        Net::C($host.$reff, 'GET', null, $cookieFile, [], '', $userAgent);

        $_0 = Net::C("$host/register", 'GET', null, $cookieFile, [], $host.$reff, $userAgent);

        $f  = xScraper::payload($_0)[0];
        $pa = $f['payload'];

        $cre = [
            'email' => $mail,
            'username' => strstr($mail, '@', true),
            'password' => $pass,
            'password_confirm' => $pass
        ];

        $c = capt::cha($_0);
        while (($t = $api->token($c['keys'], $host, $c['type'])) === false);
        if ($t === null) exit(1);

        $po = array_merge($pa, ['cf-turnstile-response' => $t], $cre);

        $_1 = Net::C("$host/register", 'POST', $po, $cookieFile, [], "$host/register", $userAgent);

        $m = rScraper::jPath($_1, "/message\s*:\s*['\"]([^'\"]+)['\"]/i") ?? [];

        if (isset($m[1][0]) && str_contains($m[1][0], 'account has been registered')) {
            logx('info', $m[1][0], true, true);
            goto login;
        }
        die;
        
    }
}

ptc:
{
    while (true) {
        $_ptc = Net::C("$host/surf", 'GET', null, $cookieFile, [], "$host/account", $userAgent);

        $_h = xScraper::xPath($_ptc, "//a[starts-with(@href,'/surf/')]/@href") ?? [];

        if (!isset($_h[0])) {
            logx('err', 'ptc abis');
            break;
        }

        $href = $host.$_h[0];
        taskPrintCenter('SURF PTC', 'info');

        $_s = Net::C($href, 'GET', null, $cookieFile, [], "$host/surf", $userAgent);

        $_uid = x($_s,'uid','input','value','id')[0];
        $_cid = xScraper::xPath($_s,"//button/@id")[0];
        $_tmr = rScraper::jPath($_s,'/let count = (\d+)/')[1][0] ?? 5;

        $cval = $_cid . mt_rand(1,9999);

        Net::C("$host/surf?uid=$_uid&c=$cval", 'GET', null, $cookieFile, [], $href, $userAgent);

        $c = capt::cha($_s);
        while (($t = $api->token($c['keys'][0], $host, $c['type'])) === false);
        if ($t === null) exit(1);

        styler("waiting", fn() => _sle($_tmr + mt_rand(1,2)));

        $po = [
            'uid' => $_uid,
            'c'   => $cval,
            'cf-turnstile-response' => $t
        ];

        $cla = json_decode(Net::X("$host/ajax/surf", 'POST', $po, $cookieFile, [], $href, $userAgent), true);

        if (isset($cla['success'])) {
            logx('ok', $cla['message'], true, true);

            if (str_contains($cla['message'],'get back tomorrow.')) {
                break;
            }
        }
    }
}

faucet:
{
    taskPrintCenter('CLAIM FAUCET', 'info');

    while (true) {
        $fau = Net::C("$host/faucet", 'GET', null, $cookieFile, [], "$host/surf", $userAgent);

        $fo = xScraper::payload($fau) ?? [];

        if (empty($fo)) {
            styler("waiting", fn() => _sle(1));
            continue;
        }

        $f  = $fo[0];
        $pa = $f['payload'];

        $c = capt::cha($fau);
        while (($t = $api->token($c['keys'][0], $host, $c['type'])) === false);
        if ($t === null) exit(1);

        $po = array_merge($pa, ['cf-turnstile-response' => $t]);

        $cla = Net::X("$host/faucet", 'POST', $po, $cookieFile, [], "$host/faucet", $userAgent);

        $m = rScraper::jPath($cla, "/message\s*:\s*['\"]([^'\"]+)['\"]/i") ?? [];

        if (isset($m[1][0])) {
            logx('info', $m[1][0], true, true);

            if (str_contains($m[1][0], 'get back tomorrow')) {
                break;
            }
        }
    }
}

withdraw:
{
    $dash = Net::X("$host/account", 'GET', null, $cookieFile, [], '', $userAgent);

    $payloads = xScraper::payload($dash);

    if (!isset($payloads[2])) {
        exit("withdraw payload not found\n");
    }

    $pa = $payloads[2]['payload'];

    // 🔥 LOAD DATA
    $faucets = json_decode(getenv('faucetpay'), true);
    $dottrick = json_decode(getenv('dottrick'), true);

    if (!is_array($faucets) || !is_array($dottrick)) {
        exit("invalid faucetpay/dottrick env\n");
    }

    // ⚡ FAST LOOKUP
    static $map = null;
    if ($map === null) {
        $map = array_flip($dottrick);
    }

    if (!isset($map[$mail])) {
        exit("mail tidak ditemukan: $mail\n");
    }

    $pos = $map[$mail];

    // 🔁 DISTRIBUTION
    $index = $pos % count($faucets);
    $pa['address'] = $faucets[$index];

    logx('info', "MAP: $mail => {$pa['address']}");

    // 🚀 EXECUTE
    print_r($pa);

    $_wd = Net::X("$host/ajax/withdraw", 'POST', $pa, $cookieFile, [], "$host/account", $userAgent);
    $wd  = json_decode($_wd, true);

    print_r($wd);

    @unlink($cookieFile);
}
