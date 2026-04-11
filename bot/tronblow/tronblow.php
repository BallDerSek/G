<?php
if (!defined('ROOT')) { die; }

$userAgent = getUagent();

$login = (int)credential([], true)['login'];
$r = '/?ref=gamamoch%40gmail.com';

if (!is_file(LIBDIR.'/mail.txt')) {
    logx('err', 'mail.txt not found');
    die;
}
$emails = file(LIBDIR.'/mail.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$urls = [
    'https://usdtblow.xyz',
    'https://tronblow.site'
];

banner();

$chunks = array_chunk($emails, 5);

$batchIndex = $login - 1;

if (!isset($chunks[$batchIndex])) {
    logx('err', "Batch-{$login} not found");
    die;
}

$batch = $chunks[$batchIndex];

logx('info', " Batch-{$login}  " . count($batch) . " email");

$accs = [];
foreach ($batch as $mail) {
    $userOnly = explode('@', $mail)[0];
    $accs[] = [
        'mail' => $mail,
        'user' => $userOnly,
        'wait' => 0,
        'set'  => microtime(true)
    ];
}

while (!empty($accs)) {
    $calls = [];
    $keys = [];

    foreach ($accs as $i => $acc) {
        foreach ($urls as $host) {
            $domain = parse_url($host, PHP_URL_HOST);
            $siteName = str_replace('.', '_', $domain);
            
            $dirPath = __DIR__ . "/cookies/" . $acc['user'];
            if (!is_dir($dirPath)) mkdir($dirPath, 0777, true);
            $cFile = $dirPath . "/" . $siteName;

            $keys[] = ['idx' => $i, 'host' => $host, 'cFile' => $cFile];
            $calls[] = [$host . $r, 'GET', null, $cFile, [], '', $userAgent];
        }
    }

    $_0 = Mux::C(...$calls);
    
    $postCalls = [];
    $postKeys = [];

    foreach ($_0 as $j => $html) {
        if (empty($html)) continue;
        
        $info = $keys[$j];
        $f = xScraper::payload($html);
        
        if (!empty($f)) {
            $pa = $f[0]['payload'];
            if (isset($pa['math_answer'])) {
                $pa['math_answer'] = mA($pa['math_q1'], $pa['math_q2'], $pa['math_op']);
                $pa['email'] = $accs[$info['idx']]['mail'];
            }
            $postKeys[] = $info;
            $postCalls[] = [$info['host'], 'POST', $pa, $info['cFile'], [], $info['host'] . $r, $userAgent];
        }
    }

    if (!empty($postCalls)) {
        $maxWait = 0;
        foreach ($accs as $acc) {
            $end = microtime(true) - $acc['set'];
            $sisa = $acc['wait'] - (int)ceil($end);
            $maxWait = max($maxWait, $sisa);
        }

        if ($maxWait > 0) {
            styler("waiting $maxWait", fn() => _sle($maxWait));
        }

        $_1 = Mux::C(...$postCalls);
        
        $batchWait = 0;
        foreach ($_1 as $k => $res) {
            $info = $postKeys[$k];
            $idx = $info['idx'];
            $domain = parse_url($info['host'], PHP_URL_HOST);
            
            print(BOLD.FGb['BLU'].sprintf("%-10s", explode('.', $domain)[0]).FGd['CYN']." [ {$accs[$idx]['user']} ] ".RSET);

            if (!empty($res)) {
                $_suc = xScraper::xPath($res, "//div[contains(@class,'alert-success')]");
                $_err = xScraper::xPath($res, "//div[contains(@class,'alert-error')]");

                if (!empty($_suc)) {
                    logx('ok', trim($_suc[0]));
                    $batchWait = max($batchWait, 60);
                } elseif (!empty($_err)) {
                    logx('err', trim($_err[0]));
                    if (preg_match('/(\d+)s/', $_err[0], $_w)) {
                        $batchWait = max($batchWait, (int)$_w[1]);
                    } else {
                        $batchWait = max($batchWait, 60);
                    }
                }
            }
        }

        foreach ($accs as $i => $acc) {
            $accs[$i]['set'] = microtime(true);
            $accs[$i]['wait'] = $batchWait;
        }
        
        _sle(5); 
    } else {
        logx('warn', "No forms found for batch {$login}. Retrying in 10s...");
        _sle(10);
    }
}

function mA($q1, $q2, $op) {
    switch ($op) {
        case '+': return $q1 + $q2;
        case '-': return $q1 - $q2;
        case '*': return $q1 * $q2;
        case '/': return $q2 != 0 ? (int)($q1 / $q2) : 0;
        default:  return 0;
    }
}
