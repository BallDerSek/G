<?php
if (!defined('ROOT')) { die; }
$api = onKeys();
$api = AUTH_API;

$cookieFile = getCookie();
$userAgent  = getUagent();
#$acc = credential();
#$mail = $acc['mail'];

$host = 'https://cryptoclaps.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '91.204.209.6';

banner(); 
login:

{
$emails = ["gaktaugua07@gmail.com","gaktaugua17@gmail.com","gaktaugua37@gmail.com","gaktaugua47@gmail.com","gaktaugua57@gmail.com","gaktaugua000@gmail.com","gaktaugua001@gmail.com","gaktaugua003@gmail.com","gaktaugua004@gmail.com","gaktaugua005@gmail.com","gaktaugua006@gmail.com","gaktaugua007@gmail.com","gaktaugua008@gmail.com","gaktaugua009@gmail.com","gaktaugua010@gmail.com","gaktaugua011@gmail.com","gaktaugua012@gmail.com","gaktaugua013@gmail.com","gaktaugua014@gmail.com","gaktaugua015@gmail.com","gaktaugua017@gmail.com","gaktaugua018@gmail.com","gaktaugua019@gmail.com","gaktaugua020@gmail.com","gaktaugua022@gmail.com","gaktaugua023@gmail.com","gaktaugua024@gmail.com","gaktaugua026@gmail.com","gaktaugua027@gmail.com","gaktaugua028@gmail.com","gaktaugua029@gmail.com","gaktaugua03@gmail.com","gaktaugua23@gmail.com","gaktaugua43@gmail.com","gaktaugua25@gmail.com","gaktaugua35@gmail.com","gaktaugua55@gmail.com","gaktaugua29@gmail.com","gaktaugua39@gmail.com","gaktaugua49@gmail.com","gaktaugua59@gmail.com","gaktaugua109@gmail.com","gaktaugua119@gmail.com","gaktaugua129@gmail.com","gaktaugua139@gmail.com","gaktaugua149@gmail.com","gaktaugua159@gmail.com","gaktaugua169@gmail.com","gaktaugua179@gmail.com","gaktaugua189@gmail.com","gaktaugua199@gmail.com","sevenone.ama@gmail.com","gamamoch@gmail.com","muhgamarqsa@gmail.com","gamgamarqsa@gmail.com","its.gmxch@gmail.com","just.gmxch@gmail.com","reihangntg@gmail.com","gmochlite@gmail.com","gaktau394@gmail.com"];
}

$chunks = array_chunk($emails, 3);

foreach ($chunks as $batch) {

    $accs = [];

    foreach ($batch as $mail) {
        $cookie = getCookie() . '_' . md5($mail);
        @unlink($cookie);

        $accs[] = [
            'mail' => $mail,
            'cookie' => $cookie,
            'ua' => $userAgent,
            'retry' => 0,
        ];

        logx('info', "Using $mail");
    }

    while (!empty($accs)) {

        $csrf = [];
        $_coin = [];
        $_capt = [];
        $channels = [];

        $calls = [];
        foreach ($accs as $i => $acc) {
            $calls[$i] = [
                "$host/",
                'GET',
                null,
                $acc['cookie'],
                [],
                '',
                $acc['ua'],
                $ip
            ];
        }

        $res = Mux::C(...$calls);

        foreach ($res as $i => $html) {
            if (!$html || !isset($accs[$i])) continue;

            $_capt[$i] = capt::cha($html);
            $_coin[$i] = xScraper::xPath($html, "//select[@id='coin-select']/option/@value");

            $_csrf = rScraper::jPath($html, "/csrf_token['\"]?\s*,\s*['\"]([a-f0-9]{32,})/i");
            if (!empty($_csrf)) $csrf[$i] = $_csrf[1];
        }

        $calls = [];
        foreach ($accs as $i => $acc) {
            if (!isset($csrf[$i]) || empty($csrf[$i])) continue;

            $_pa = [
                'action' => 'validate_wallet',
                'wallet' => $acc['mail'],
                'csrf_token' => $csrf[$i][0] ?? null
            ];

            $pa = webkitId($_pa, $boundary);
            $he = headers($host, $host, $domain);
            $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];

            $calls[$i] = [
                $host,
                'POST',
                $pa,
                $acc['cookie'],
                array_merge($he, $ha),
                '',
                $acc['ua']
            ];
        }

        $res = Mux::C(...$calls);

        foreach ($res as $i => $r) {
            if (!isset($accs[$i])) continue;

            $v = json_decode($r, true);

            if (empty($v['valid'])) {
                logx('err', "[{$accs[$i]['mail']}] invalid");
                unset($accs[$i]);
            } else {
                logx('ok', "[{$accs[$i]['mail']}] valid");
            }
        }

        if (empty($accs)) break;

        $calls = [];
        foreach ($accs as $i => $acc) {
            $_pe = [
                'email' => $acc['mail'],
                'action' => 'get_channel',
                'csrf_token' => $csrf[$i][1] ?? $csrf[$i][0] ?? null
            ];

            $pe = webkitId($_pe, $boundary);
            $he = headers($host, $host, $domain);
            $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];

            $calls[$i] = [
                $host,
                'POST',
                $pe,
                $acc['cookie'],
                array_merge($he, $ha),
                '',
                $acc['ua']
            ];
        }

        $res = Mux::C(...$calls);

        $start = microtime(true);
        $wait = 0;
        $maxRetry = 3;

        foreach ($res as $i => $r) {
            if (!isset($accs[$i])) continue;

            $v = json_decode($r, true);
            #print_r($v);
            if (!empty($v['channel'])) {
                $channels[$i] = $v['channel'];
                $accs[$i]['retry'] = 0;
                $wait = max($wait, $v['channel']['watch_time'] ?? 0);
                logx('info', "[{$accs[$i]['mail']}] got channel");
                continue;
            }

            $accs[$i]['retry']++;
            logx('warn', "[{$accs[$i]['mail']}] channel empty, retry {$accs[$i]['retry']}");

            if ($accs[$i]['retry'] > $maxRetry) {
                logx('err', "[{$accs[$i]['mail']}] channel empty, max retries reached, removing account");
                unset($accs[$i], $csrf[$i], $_coin[$i], $_capt[$i], $channels[$i]);
            }
        }

        if (empty($accs)) break;

        $tokens = [];
        foreach ($accs as $i => $acc) {
            $coins = $_coin[$i] ?? [];
            $cap   = $_capt[$i] ?? null;
            $ch    = $channels[$i] ?? null;
            if (!$coins || !$cap || !$ch) continue;

            foreach ($coins as $coin) {
                $retry = 0;
                while (($t = getKeys($api)->token($cap['keys'][0], $host, $cap['type'])) === false && $retry++ < 5);
                if (!$t) continue;

                $tokens[$i] = [
                    'token' => $t,
                    'coin'  => $coin
                ];
                break;
            }
        }

        if (empty($tokens)) {
            _sle(3);
            continue;
        }

        $end = microtime(true) - $start;
        $sle = $wait - $end;
        if ($sle > 0) {
            logx('warn', "global wait $sle");
            _sle((int)ceil($sle));
        }

        $postCalls = [];
        foreach ($tokens as $i => $tk) {
            if (!isset($accs[$i], $channels[$i])) continue;

            $acc = $accs[$i];
            $ch  = $channels[$i];

            $_po = [
                'action' => 'send_reward',
                'wallet' => $acc['mail'],
                'email' => $acc['mail'],
                'csrf_token' => $csrf[$i][2] ?? $csrf[$i][0],
                'channel_url' => $ch['url'],
                'cf-turnstile-response' => $tk['token'],
                'coin' => $tk['coin']
            ];

            $po = webkitId($_po, $boundary);
            $he = headers($host, $host, $domain);
            $ha = ["Content-Type: multipart/form-data; boundary=$boundary"];

            $postCalls[$i] = [
                $host,
                'POST',
                $po,
                $acc['cookie'],
                array_merge($he, $ha),
                '',
                $acc['ua']
            ];
        }

        $results = Mux::C(...$postCalls);

        foreach ($results as $i => $res3) {
            if (!$res3) continue;

            $v = json_decode($res3, true);
            if (!is_array($v)) continue;

            if (!empty($v['success']) || !empty($v['status'])) {
                logx('ok', $v['message']);
            } else {
                $msg = strtolower($v['message'] ?? '');
                if (str_contains($msg, 'insufficient')) continue;
                if (str_contains($msg, 'captcha')) continue;

                logx('err', $v['message'] ?? 'Unknown error');
            }
        }

        _sle(3);
    }
}
