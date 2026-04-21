<?php
if (!defined('ROOT')) { die; }

$login = config::credential()['login'];
$userAgent = config::uagent();
$r = '/?ref=gamamoch%40gmail.com';

$mailPath = LIBDIR.'/mail.txt';
if (!is_file($mailPath)) {
    logx('err', 'mail.txt not found');
    die();
}
$emails = file($mailPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);




$urls = ['https://usdtblow.xyz', 'https://tronblow.site'];

banner();

$chunks = array_chunk($emails, 15);

if (ctype_digit((string)$login)) {
    $idx = (int)$login - 1;
    $chunks = isset($chunks[$idx]) ? [$chunks[$idx]] : [];
}

foreach ($chunks as $cIdx => $batch) {
    $accs = [];
    foreach ($batch as $mail) {
        $accs[] = [
            'mail' => $mail,
            'wait' => 0,
            'set' => microtime(true),
            'base' => Config::cookie($mail),
            'sites' => $urls 
        ];
        logx('info', "Batching: " . maskEmail($mail));
    }

    while (!empty($accs)) {
        $maxWait = 0;
        foreach ($accs as $acc) {
            $passed = microtime(true) - $acc['set'];
            $sisa = $acc['wait'] - (int)ceil($passed);
            $maxWait = max($maxWait, $sisa);
        }

        if ($maxWait > 0) {
            styler("Waiting $maxWait s", fn() => _sle($maxWait));
        }

        $calls = [];
        $keys = [];

        foreach ($accs as $i => $acc) {
            foreach ($acc['sites'] as $host) {
                $domain = parse_url($host)['host'];
                $_cooDir = $acc['base'] . '/' . $domain;
                if (!is_dir($_cooDir)) mkdir($_cooDir, 0755, true);
                
                $cFile = $_cooDir . "/session"; 

                $keys[]  = ['idx' => $i, 'host' => $host, 'cFile' => $cFile];
                $calls[] = [$host . $r, 'GET', null, $cFile, [], '', $userAgent];
            }
        }

        if (empty($calls)) break;

        $_0 = styler("Preparing " . count($calls) . " payloads", function() use ($calls) {
            return Mux::C(...$calls);
        });

        $postCalls = [];
        $postKeys = [];

        foreach ($_0 as $j => $html) {
            if (empty($html)) continue;
            
            $info = $keys[$j];
            $f = scraper::payload($html);
            
            if (!empty($f)) {
                $pa = $f[0]['payload'];
                if (isset($pa['math_answer'])) {
                    $pa['math_answer'] = mA($pa['math_q1'], $pa['math_q2'], $pa['math_op']);
                    $pa['email'] = $accs[$info['idx']]['mail'];
                }
                $postKeys[] = $info;
                $postCalls[] = [$info['host'], 'POST', $pa, $info['cFile'], [], $info['host'] . $r, $userAgent];
            } else {
                @unlink($info['cFile']);
            }
        }

        if (!empty($postCalls)) {
            $_1 = styler("Claiming " . count($postCalls) . " sites", function() use ($postCalls) {
                return Mux::C(...$postCalls);
            });

            $batchWait = 0;
            $toRemoveAcc = [];

            foreach ($_1 as $k => $res) {
                $info = $postKeys[$k];
                $idx = $info['idx'];
                $domain = parse_url($info['host'])['host'];
                
                $userDisp = maskEmail($accs[$idx]['mail']);
                print(BOLD.FGb['BLU'].sprintf("%-10s", explode('.', $domain)[0]).FGd['CYN']." [ $userDisp ] ".RSET);

                if (!empty($res)) {
                    
                    $_suc = scraper::_xP($res, "//div[contains(@class,'alert-success')]");
                    $_err = scraper::_xP($res, "//div[contains(@class,'alert-error')]");

                    if (!empty($_suc)) {
                        logx('ok', trim($_suc[0]));
                        $batchWait = max($batchWait, 62);
                    } elseif (!empty($_err)) {
                        $errMsg = trim($_err[0]);
                        $lowErr = strtolower($errMsg);
                        logx('err', $errMsg);

                        if (str_contains($lowErr, 'limit reached') || str_contains($lowErr, 'daily limit')) {
                            $sKey = array_search($info['host'], $accs[$idx]['sites']);
                            if ($sKey !== false) unset($accs[$idx]['sites'][$sKey]);
                            if (empty($accs[$idx]['sites'])) $toRemoveAcc[] = $idx;
                        }

                        if (preg_match('/(\d+)s/', $errMsg, $_w)) {
                            $batchWait = max($batchWait, (int)$_w[1] + 2);
                        }
                    } else {
                        logx();
                    }
                    
                }
                @unlink($info['cFile']);
            }

            if (!empty($toRemoveAcc)) {
                foreach (array_unique($toRemoveAcc) as $remIdx) unset($accs[$remIdx]);
                $accs = array_values($accs);
            }

            foreach ($accs as $i => $acc) {
                $accs[$i]['set'] = microtime(true);
                $accs[$i]['wait'] = $batchWait;
            }
            
            if (!empty($accs)) _sle(2); 
        } else {
            logx('warn', "No forms found.");
            _sle(30);
        }
    }
}

logx('ok', "ALL BATCHES FINISHED");

function mA($q1, $q2, $op) {
    return match($op) {
        '+' => $q1 + $q2,
        '-' => $q1 - $q2,
        '*' => $q1 * $q2,
        '/' => $q2 != 0 ? (int)($q1 / $q2) : 0,
        default => 0,
    };
}
