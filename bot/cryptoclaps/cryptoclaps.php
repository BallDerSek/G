<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$userAgent  = getUagent();

$host = 'https://cryptoclaps.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = '91.204.209.6';

banner(); 
login:


if (!is_file(LIBDIR.'/mail.txt')) {
    logx('err', 'mail.txt not found');
    die;
}
$emails = file(LIBDIR.'/mail.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$cookies = __DIR__.'/cookies';
if (!is_dir($cookies)) mkdir($cookies, 0777, true);


$chunks = array_chunk($emails, 3);

foreach ($chunks as $batch) {

    $accs = [];

    foreach ($batch as $mail) {
        $username = explode('@', $mail)[0];
        $uname = preg_replace('/[^a-zA-Z0-9\.]/', '', $username);

        $cookie = "{$cookies}/{$uname}_" . md5($mail);
        @unlink($cookie);

        $accs[] = [
            'mail' => $mail,
            'cookie' => $cookie,
            'ua' => $userAgent,
            'retry' => 0,
            'coin_idx' => 0 
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

            $_coin[$i] = xScraper::xPath($html, "//select[@id='coin-select']/option[not(@disabled)]/@value");

            $_csrf = rScraper::jPath($html, "/csrf_token['\"]?\s*,\s*['\"]([a-f0-9]{32,})/i");
            if (!empty($_csrf)) $csrf[$i] = $_csrf[1];
        }

        $calls = [];
        foreach ($accs as $i => $acc) {
            if (!isset($csrf[$i])) continue;

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
            #var_dump($r);
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

        $wait = 0;
        $maxRetry = 3;
        foreach ($res as $i => $r) {
            if (!isset($accs[$i])) continue;
            $v = json_decode($r, true);
            #var_dump($v);
            
            if (!is_array($v)) continue;
            if (!empty($v['channel'])) {
                $channels[$i] = $v['channel'];
                $accs[$i]['retry'] = 0;
                $set = microtime(true);
                $wait = max($wait, $v['channel']['watch_time'] ?? 0);
                logx('info', "[{$accs[$i]['mail']}] got channel");
                continue;
            }
            
            $msg = strtolower($v['message'] ?? '');
            if (str_contains($msg, 'all tasks') ||str_contains($msg, 'no tasks') ||str_contains($msg, 'try again later')) {
                logx('ok', "[{$accs[$i]['mail']}] done (no tasks)");
                unset($accs[$i]);
                continue;
            }
            $accs[$i]['retry']++;
            logx('warn', "[{$accs[$i]['mail']}] channel empty, retry {$accs[$i]['retry']}");
            print_r($v);
            if ($accs[$i]['retry'] > $maxRetry) {
                logx('err', "[{$accs[$i]['mail']}] no channel, remove");
                unset($accs[$i]);
            }
        }

        if (empty($accs)) break;

        $tokens = [];

        foreach ($accs as $i => $acc) {
            $coins = $_coin[$i] ?? [];
            $cap = $_capt[$i] ?? null;
            $ch = $channels[$i] ?? null;

            if (!$coins || !$cap || !$ch) continue;

            $idx = $acc['coin_idx'];

            if (!isset($coins[$idx])) {
                logx('err', "[{$acc['mail']}] no coins left");
                unset($accs[$i]);
                continue;
            }

            $coin = $coins[$idx];

            $retry = 0;
            while (($t = getKeys($api)->token($cap['keys'][0], $host, $cap['type'])) === false && $retry++ < 5);

            if (!$t) continue;

            $tokens[$i] = [
                'token' => $t,
                'coin'  => $coin
            ];
        }

        if (empty($tokens)) {
            _sle(3);
            continue;
        }
        
        $end = microtime(true) - $set;
        $sleep = $wait - $end;
        if ($sleep > 0) {
            #logx('warn', "wait $wait");
            styler("waiting $sleep", fn() => _sle((int)ceil($sleep)));
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
            if (!$res3 || !isset($accs[$i])) continue;

            $v = json_decode($res3, true);
            #var_dump($v);
            if (!is_array($v)) continue;

            if (!empty($v['success']) || !empty($v['status'])) {
                logx('ok', $v['message']);
                continue;
            }

            $msg = strtolower($v['message'] ?? '');

            if (
                str_contains($msg, 'insufficient') ||
                str_contains($msg, 'low balance') ||
                str_contains($msg, 'not enough')
            ) {
                logx('warn', "[{$accs[$i]['mail']}] insufficient");

                $accs[$i]['coin_idx']++; 
                continue;
            }

            if (str_contains($msg, 'captcha')) continue;

            logx('err', $v['message'] ?? 'Unknown error');
        }

        _sle(10);
    }
}