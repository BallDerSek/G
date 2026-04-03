<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$cookieFile = getCookie();
$userAgent = getUagent();
$mail = credential([], true)['mail'];

$host = 'https://earnton.online';
$r = '/r.php?ref=6';
$domain = parse_url($host, PHP_URL_HOST);

$_fa = [
    "/faucet.php?coin=usdt",
    "/faucet.php?coin=ton",
    "/faucet.php?coin=sol",
];

$_ic = ["SOL","TON","USDT"];
$_id = ['1','2','3','4','5','6'];

banner();

$state = 'login';
$nextClaimAt = 0;
$_next = 0;
while (true) {

    if ($state === 'login') {
        $l = checkLogin($host, headers('', $host, $domain), null, "/login.php");
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $state = 'claim';
            continue;
        }
        @unlink($cookieFile);
        taskPrintCenter('logging in', 'err');
        Net::C($host.$r, 'GET', null, $cookieFile, [], '', $userAgent);
        Net::C($host."/login.php", 'POST', ['email'=>$mail], $cookieFile, [], '', $userAgent);
        continue;
    }

    if ($state === 'claim') {

        $shortlinkCount = 0;
        $_start = microtime(true);

        foreach ($_fa as $fa) {

            $fau = Net::C($host.$fa, 'GET', null, $cookieFile, [], $host, $userAgent);
            if (!$fau) continue;

            $fo = xScraper::payload($fau) ?? [];
            if (empty($fo)) {
                if (str_contains($fau, "Shortlink Required")) {
                    logx('err', "$fa [ shortlink ]");
                    $shortlinkCount++;
                    continue;
                }
                logx('err', "$fa [ empty payload ]");
                continue;
            }

            $f = $fo[0];
            if (empty($f['payload'])) continue;

            $ca = [];
            if ($cap = capt::cha($fau)) {
                $t = tK(getKeys($api), $host, $cap['keys'][0], $cap['type'], $userAgent);
                $ca = ['cf-turnstile-response'=>$t];
            }

            $boundary = '';
            $pa = webkitId(array_merge($f['payload'], $ca), $boundary);
            $he = headers($host, $host.$fa, $domain);
            $he[] = "Content-Type: multipart/form-data; boundary=$boundary";

            $_elapse = microtime(true) - $_start;
            $remain  = max(0, $_next - $_elapse);
            if ($remain > 0) {
                styler("waiting", fn() => _sle((int)ceil($remain)));
            }

            $res = Net::C(
                $host."/claim-ajax.php",
                'POST',
                $pa,
                $cookieFile,
                $he,
                '',
                $userAgent
            );
            if (!$res) continue;

            $cla = json_decode($res, true);
            if (!is_array($cla)) {
                logx('err', "$fa [ invalid json ]");
                continue;
            }

            if (!empty($cla['ok']) && !empty($cla['msg'])) {
                logx('ok', $cla['msg'], true, true);
                $_next = 60;
                $_start = microtime(true);
                continue;
            }

            if (!empty($cla['msg']) && stripos($cla['msg'],'captcha') !== false) {
                logx('warn', "$fa [ captcha fail ]");
                continue;
            }

            if (!empty($cla['msg'])) {
                logx('err', $cla['msg']);
            }
        }

        if ($shortlinkCount >= count($_fa)) {
            logx('warn', "ALL COIN REQUIRE SHORTLINK");
            $state = 'shortlink';
            continue;
        }

        continue;
    }

    if ($state === 'shortlink') {
        foreach ($_id as $id) {
            $he = headers($host, $host.'/shortlinks.php', $domain, [],  '', true);
            #print_r($he);
            $go = json_decode(
                Net::X($host.'/shortlink-init.php','POST',['provider_id'=>$id,'coin'=>$_ic[0]],$cookieFile,$he,'',$userAgent) ?: ''
                , true);
            #print_r($go);

            if (!is_array($go) || empty($go['ok']) || empty($go['url'])) continue;

            $finalUrl = null;
            if (!empty($go['token'])) {
                Net::C($go['url'],'GET',null,$cookieFile,[], $host.'/shortlinks.php',$userAgent);
                $finalUrl = $host.'/shortlink-callback.php?token='.$go['token'];
            } else {
                $short = $go['url'];
                try {
                    $bypass = new _shortlinks($short);
                    $finalUrl = $bypass->links($api ?? null);
                    break;
                } catch (RuntimeException $e) {
                    logx('err', $e->getMessage());
                }
            }

            if (!$finalUrl) continue;
            logx('info', $finalUrl);

            $he = headers('', $go['url'], $domain);
            #print_r($he);
            styler("waiting", fn() => _sle(70));
            $ver = Net::C($finalUrl,'GET',null,$cookieFile,$he,'',$userAgent);
            #_put('ver.html', $ver);
            if (!$ver || !str_contains($ver,'Reward Claimed')) continue;

            $state = 'claim';
            break;
        }

        #styler("retry shortlink", fn() => _sle(10));
    }

}


function tK($api, $host, $key, $type, $ua) {
    if (!$api) {
        logx('err', 'undefined provider'); exit(1);
    }
    
    while (($t = $api->token($key, $host, $type, ['userAgent' => $ua])) === false);
    if ($t === null) exit(1);
    
    return $t;
}