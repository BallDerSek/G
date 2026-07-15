<?php
if (!defined('ROOT')) { die; }

$login = Config::credential()['login'];
$userAgent = Config::uagent();
$r = '/?ref=gamamoch%40gmail.com';

$mailPath = LIBDIR.'/email.txt';
if (!is_file($mailPath)) {
    logx('err', 'email.txt not found');
    die();
}
$emails = file($mailPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$urls = ['https://usdtblow.xyz', 'https://tronblow.site'];

$b = Banner::getInstance();
$b->show();
$b->task1('ok', "auto multi+batch");

login:
    
$chunks = array_chunk($emails, 50);

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
    }
    
    $b->show();
    $b->task1('ok', "auto multi+batch");
    $b->task2('info', "processing: ". count($batch));
    
    while (!empty($accs)) {
        $maxWait = 0;
        foreach ($accs as $acc) {
            $passed = microtime(true) - $acc['set'];
            $sisa = $acc['wait'] - (int)ceil($passed);
            $maxWait = max($maxWait, $sisa);
        }

        if ($maxWait > 0) styler("Waiting $maxWait s", fn() => _sle($maxWait));

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
            $f = Scraper::payload($html);
            
            if (!empty($f)) {
                $pa = $f[0]['payload'];
                if (isset($pa['math_answer'])) {
                    $pa['math_answer'] = SolveUtils::math($pa['math_q1'], $pa['math_q2'], $pa['math_op']);
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
            $processedUrls = [];

            foreach ($_1 as $k => $res) {
                $info = $postKeys[$k];
                $idx = $info['idx'];
                $domain = parse_url($info['host'])['host'];
                
                $userDisp = maskEmail($accs[$idx]['mail']);
                Logger::M($accs[$idx]['mail']);
                Logger::X('info', "$domain ", false, true);
                
                if (!empty($res)) {
                    
                    $_suc = Scraper::_xP($res, "//div[contains(@class,'alert-success')]");
                    $_err = Scraper::_xP($res, "//div[contains(@class,'alert-error')]");
                    
                    if (!empty($_suc)) {
                        logx('ok', trim($_suc[0]));
                        $batchWait = max($batchWait, 62);
                        $processedUrls[$idx][] = $info['host'];
                    } elseif (!empty($_err)) {
                        $errMsg = trim($_err[0]);
                        $lowErr = strtolower($errMsg);
                        logx('err', $errMsg);

                        if (str_contains($lowErr, 'limit reached') || str_contains($lowErr, 'daily limit')) {
                            $sKey = array_search($info['host'], $accs[$idx]['sites']);
                            if ($sKey !== false) unset($accs[$idx]['sites'][$sKey]);
                            if (empty($accs[$idx]['sites'])) $toRemoveAcc[] = $idx;
                        } else {
                            $processedUrls[$idx][] = $info['host'];
                        }

                        if (preg_match('/(\d+)s/', $errMsg, $_w)) {
                            $batchWait = max($batchWait, (int)$_w[1] + 2);
                        }
                    } else {
                        logx();
                        $processedUrls[$idx][] = $info['host'];
                    }
                    
                } else Logger::X();
                @unlink($info['cFile']);
            }

            foreach ($processedUrls as $idx => $urlsProcessed) {
                foreach ($urlsProcessed as $url) {
                    $sKey = array_search($url, $accs[$idx]['sites']);
                    if ($sKey !== false) unset($accs[$idx]['sites'][$sKey]);
                }
                if (empty($accs[$idx]['sites'])) {
                    $toRemoveAcc[] = $idx;
                }
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
    _cle();
}

logx('ok', "ALL BATCHES FINISHED");
#_rl('mode pesawat'); goto login;

function mA($q1, $q2, $op) {
    return match($op) {
        '+' => $q1 + $q2,
        '-' => $q1 - $q2,
        '*' => $q1 * $q2,
        '/' => $q2 != 0 ? (int)($q1 / $q2) : 0,
        default => 0,
    };
}