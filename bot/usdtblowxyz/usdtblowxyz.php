<?php
if (!defined('ROOT')) { die; }

$cookieFile = getCookie();
$userAgent = getUagent();
$acc = credential([], true);
$login = $acc['login'];

$urls = [
    'https://usdtblow.xyz',
    'https://tronblow.site'
];

$r = '/?ref=gamamoch%40gmail.com';

banner();

$set = microtime(true); // Inisialisasi awal agar tidak 0
$_wait = 0;

while (true) {
    $calls = [];
    $keys = []; 

    // --- STEP 1: AMBIL FORM (GET) ---
    foreach ($urls as $host) {
        $domain = parse_url($host, PHP_URL_HOST);
        $cFile = dirname($cookieFile) . "/" . str_replace('.', '_', $domain);
        
        $keys[] = "{$host}|{$cFile}"; 
        $calls[] = [$host . $r, 'GET', null, $cFile, [], '', $userAgent];
    }
    
    $_0 = Mux::C(...$calls);
    
    $postCalls = [];
    $postKeys = []; 

    // --- STEP 2: PARSING & SOLVING (PROSES PENGERJAAN) ---
    foreach ($_0 as $i => $html) {
        if (empty($html)) continue;
        
        list($host, $cFile) = explode('|', $keys[$i]);
        $f = xScraper::payload($html);
        
        if (!empty($f)) {
            $pa = $f[0]['payload'];
            if (isset($pa['math_answer'])) {
                $pa['math_answer'] = mA($pa['math_q1'], $pa['math_q2'], $pa['math_op']);
                $pa['email'] = $login;
            }
            $postKeys[] = "{$host}|{$cFile}";
            $postCalls[] = [$host, 'POST', $pa, $cFile, [], $host . $r, $userAgent];
        }
    }
    
    // --- STEP 3: HITUNG & DELAY SEBELUM POST ---
    if (!empty($postCalls)) {
        // Hitung berapa lama waktu yang sudah habis untuk pengerjaan (parsing/captcha)
        $end = microtime(true) - $set;
        $wait = $_wait - (int)ceil($end); 

        // Jalankan delay hanya jika sisa waktu tunggu masih ada
        if ($wait > 0 && $set > 0) {
            styler("waiting $wait", fn() => _sle($wait));
        }
        
        // --- STEP 4: EKSEKUSI CLAIM (POST) ---
        $_1 = Mux::C(...$postCalls);
        
        foreach ($_1 as $j => $res) {
            list($host, $cFile) = explode('|', $postKeys[$j]);
            $domain = parse_url($host, PHP_URL_HOST);
            $mail_short = explode('@', $login)[0];

            if (!empty($res)) {
                $_suc = xScraper::xPath($res, "//div[contains(@class,'alert-success')]");
                $_err = xScraper::xPath($res, "//div[contains(@class,'alert-error')]");
                
                print(BOLD.FGb['BLU'].sprintf("%-10s", explode('.', $domain)[0]).FGd['CYN']." [ $mail_short ] ".RSET);

                if (!empty($_suc)) {
                    logx('ok', trim($_suc[0]));
                    $_wait = 60; // Default wait setelah sukses jika web tidak memberi info
                } elseif (!empty($_err)) {
                    logx('err', trim($_err[0]));
                    if (preg_match('/(\d+)s/', $_err[0], $_w)) {
                        $_wait = (int)$_w[1]; 
                    } else {
                        logx();
                        $_wait = 60; 
                    }
                }
            }
        }
        // SET START: Tandai waktu claim terakhir selesai di sini
        $set = microtime(true);

    } else {
        logx('warn', "No forms found. Retrying...");
        _sle(5);
        // Tetap update $set agar kalkulasi di loop depan tidak raksasa
        $set = microtime(true);
    }
}


function mA($q1, $q2, $op) {
    switch ($op) {
        case '+': return $q1 + $q2;
        case '-': return $q1 - $q2;
        case '*': return $q1 * $q2;
        case '/': return $q2 != 0 ? $q1 / $q2 : 0;
        default:  return 0;
    }
}
