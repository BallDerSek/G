<?php

$thumbmrk_key = "b0fb11eb71e2c393f1162331af6d3efa";
$api = onKeys();

if (!($api instanceof skibidixxx)) die(logx('err', 'pilih api skibidixxx'));

logx('err', "\nneed detailed info to prevent suspicious session, and email otp if possible");
logx('err', "gmxch api is also can get this digital key, (not recommended for prevent soft ban (ip binding))");
$acc = config::credential([], true);

login:
$host = "https://faucetpay.io";
$app = "https://api.faucetpay.io";

$b = Banner::getInstance();
$b->show();
$b->task1('ok', "use with caution");
$b->task2('ok', "");
(function () {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie();
    $acc = config::Credential([], true);
    
    $userAgent = $acc['user_agent'];
    
    $x_key = $acc['x_digital_key'];
    $y_key = $acc['y_digital_key'];
    
    inf::setup($userAgent, $cookieFile);
    
} ) ();

{
    
    $mailPath = __DIR__.'/email.txt';
    if (!is_file($mailPath)) die(logx('err', 'mail.txt not found, create & fill with ur email list. (perline format'));
    $mailList = file($mailPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    $jsonList = null;
    $mailJson = __DIR__.'/email.json';
    if (is_file($mailJson)) $jsonList = json_decode(_get($mailJson), 1);
    
}

{
    
    if (!empty($mailList) && !$jsonList) {
        $mailNumb = count($mailList);
        Logger::X('warn', "total mail: $mailNumb");
        Logger::X('info', "is the entire account use same password?");
        while (true) {
            $conf = strtolower(trim(_rl('[ y/n ]: ')));
        
            if ($conf === 'y') {
                do {
                    $pass = trim(_rl('password: '));
                } while ($pass === '');
        
                foreach ($mailList as $mail) {
                    $jsonList[] = [
                        'mail' => $mail,
                        'pass' => $pass
                    ];
                }
                break;
            }
        
            if ($conf === 'n') {
                foreach ($mailList as $mail) {
                    do {
                        $pass = trim(_rl("pass for $mail: "));
                    } while ($pass === '');
        
                    $jsonList[] = [
                        'mail' => $mail,
                        'pass' => $pass
                    ];
                }
                break;
            }
        
            Logger::X('err', 'pilih y atau n');
        }
    
        _put($mailJson, json_encode($jsonList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        goto login;
    }
    
    if (!empty($mailList) && !empty($jsonList)) {
        $existingMails = array_column($jsonList, 'mail');
        foreach (array_diff($mailList, $existingMails) as $mail) {
            do {
                $pass = trim(_rl("pass for $mail: "));
            } while ($pass === '');
            
            $jsonList[] = ['mail' => $mail, 'pass' => $pass];
        }
        
        if (count($mailList) !== count($jsonList)) goto login;
        
        _put($mailJson, json_encode($jsonList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    
    if (!empty($jsonList)) {
        foreach ($jsonList as &$account) {
            if (empty($account['auth'])) {
                $b->task2('info', "Getting auth for: {$account['mail']}");
                $sol = _getBer($account, $acc, $api, $app);
                if ($sol) {
                    [$auth, $etag] = $sol;
                    $account['auth'] = $auth;
                    $account['etag'] = $etag;
                    _put($mailJson, json_encode($jsonList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $b->task2('ok', "saved Auth for {$account['mail']}");
                }
            }
        }
        unset($account);
        $b->task1('info', "");
        $b->task2('info', "");
    }
    
}

foreach ($jsonList as $acc) {
    $b->task1('info', "Getting RP for: {$acc['mail']}");
    $bearer = ['authorization: Bearer '.$acc['auth']];
    $rp = json_decode(Net::X($app.'/rp/claim-daily-rp', 'POST', null, inf::$cookie, ['authorization: Bearer '.$acc['auth']], $host, inf::$uagent)?: '', 1);
    if ($rp && $rp['success'] !== false) {
        $b->task1('', "claimed ({$rp['reward']} rp) for {$acc['mail']}");
    }
}

while (true) {
    $b->show();
    $b->task1('ok', "use with caution");
    $b->task2('info', 'Input');
    
    Logger::X('info', "[1 Fetch all account", true, true);
    Logger::X('info', "[2 send once", true, true);
    Logger::X('info', "[3 send bulk", true, true);
    
    $rlFP = '3';
    $rlFP = trim(_rl(' input: '));
    switch ($rlFP) {
        case '1':
            _getBal($jsonList, $host, $app);
            break;
        case '2':
            sendO($jsonList, $host, $app);
            break;
            
        case '3':
            sendB($jsonList, $host, $app);
            break;
            
        default:
            continue 2;
        
    }
    
}



function _getBal($akun, $host, $app, $coinsOnly = false) {
    $b = Banner::getInstance();
    $b->task1('ok', '');
    $b->task2('ok', '');
    
    $available = [];
    $filteredAkun = [];
    
    foreach ($akun as &$acc) {
        $b->task1('info', 'fetching balance, please wait');
        
        $bearer = ['authorization: Bearer '.$acc['auth']];
        $info = json_decode(Net::X($app.'/wallet/get-information', 'GET', null, inf::$cookie, $bearer, $host, inf::$uagent)?: '', 1)['data'] ?? null;
        
        if (empty($info)) continue;
        
        if ($coinsOnly) {
            $userBalances = [];
            foreach ($info['coin_balances'] as $coin) {
                $bal = (float)$coin['balance'];
                if ($bal > 0.00000100) {
                    $userBalances[$coin['coin']] = $bal;
                }
            }
            
            if (!empty($userBalances)) {
                $acc['balances'] = $userBalances;
                $acc['total_balance'] = array_sum($userBalances);
                $filteredAkun[] = $acc;
            }
        } else {
            #print_r($info);
            $saldo = (float)($info['statistics']['portfolio_value'] ?? 0);
            if ($saldo > 0) {
                $acc['balance'] = $saldo;
                $filteredAkun[] = $acc;
                
                $emailLen = strlen($acc['mail']);
                $padding = 23 - $emailLen;
                if ($padding < 0) $padding = 0;
                
                Logger::M(" ".$acc['mail'], false);
                Logger::X('info', sprintf(str_repeat(' ', $padding) . "[ balance: %-10.8f USD ]", $saldo), true, true);
            }
        }
    }
    unset($acc);
    
    $akun = $filteredAkun;
    
    if ($coinsOnly) return $akun;

    $b->task1('ok', 'all accounts fetched');
    _rl('    enter to continue...');
    
    return $akun;
}

function sendO($akun, $host, $app) {
    Logger::X('err', 'not stable yet');
    return;
    
    $bal = _getBal($akun, $host, $app, true);
    print_r($bal);
    
    if (!empty($bal)) {
        
        foreach ($bal as $acc => $jjn) {
            var_dump($acc, $jjn);
            
            
        die;
        }
        
    }
    
    
    die;
}

function sendB($akun, $host, $app) {
    
    $b = Banner::getInstance();
    $b->show();
    $bal = _getBal($akun, $host, $app, true);
    $b->task1('info', 'INPUT RECEIVER EMAIL');
    $b->task2('err', 'USE WITH CAUTION, ALWAYS CHECK ADDRESS');
    
    if (!empty($bal)) {
    
        $tf = _rl('INPUT RECEIVER: ');
        foreach ($bal as $acc) {
            $b->show();
            $_M = $acc['mail'];
            $_P = $acc['pass'];
            $_A = $acc['auth'];
            $_E = $acc['etag'];
            $_B = $acc['balances'];
            
            if ($_M === $tf) continue;
            
            foreach ($_B as $_C => $_J) {
                $b->task1('info', "from $_M to $tf");
                
                $jmlh = rtrim(rtrim(sprintf("%.10f", (float)$_J), '0'), '.');
                
                $b->task2('ok', "amount $jmlh ($_C)");
                
                $_H = ["authorization: Bearer $_A"];
                $_P = [
                    'coin' => $_C,
                    'amount' => $jmlh,
                    '2fa_code' => '',
                    'user' => $tf
                ];
                
                $send = json_decode(Net::X($app.'/transfer/send', 'POST', $_P, inf::$cookie, $_H, $host, inf::$uagent, json: true)?: '', 1)['message'] ?? null;
                
                if (!empty($send)) {
                    Logger::M($acc['mail'], false);
                    Logger::X('info', $send, true, true);
                }
                _sle(5);
                
            }
            
        }
    
    }
    
}

function _getBer($akun, $cred, $api, $host) {
    
    $payload = [
        'user_email' => $akun['mail'],
        'password' => $akun['pass'],
        'captcha_response' => _getTKN($api)['token'] ?? '',
        'x_digital_key' => $cred['x_digital_key'],
        'y_digital_key' => $cred['y_digital_key']
    ];
    
    $lo = json_decode(Net::X($host.'/account/login', 'POST', $payload, inf::$cookie, reff: $host.'/login', ua: inf::$uagent, json: true)?: '', 1);
    
    #var_dump($lo);
    
    if ($lo && isset($lo['token'])) {
        logm($akun['mail'], false);
        logx('ok', $lo['message'], true, true);
        return [$lo['token'], $lo['etag']];
    } else {
        var_dump($lo);
        die;
    }
    
    
    
die;
}

function _getTKN($api) {
    $cap = $api->run('faucetpay', [
        'sitekey' => 'a3760bfe5cf4254b2759c19fb2601667',
        'domain' => 'https://faucetpay.io',
    ]);
    
    
    if (str_starts_with($cap, 'cap')) {
        $token = trim(str_replace('cap_res:', '', $cap));
        return ['token' => $token];
    }
    
    return [];
}



function generateTOTP($secret, $digits = 6, $period = 30) {
    $counter = floor(time() / $period);

    $secretKey = base32Decode($secret);

    $binaryCounter = pack('N*', 0) . pack('N*', $counter);

    $hash = hash_hmac('sha1', $binaryCounter, $secretKey, true);

    $offset = ord(substr($hash, -1)) & 0x0F;

    $code =
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF);

    $otp = $code % pow(10, $digits);

    return str_pad($otp, $digits, '0', STR_PAD_LEFT);
}

function base32Decode($secret) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    $secret = strtoupper($secret);

    $binaryString = '';

    foreach (str_split($secret) as $char) {
        $binaryString .= str_pad(
            decbin(strpos($alphabet, $char)),
            5,
            '0',
            STR_PAD_LEFT
        );
    }

    $decoded = '';

    foreach (str_split($binaryString, 8) as $byte) {
        if (strlen($byte) === 8) {
            $decoded .= chr(bindec($byte));
        }
    }

    return $decoded;
}

