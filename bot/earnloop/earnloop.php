<?php
if (!defined('ROOT')) { die; }
#$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent('mobile');

$host = 'https://earnloop.online';
$ip = null; 

inf::setup($userAgent, $cookieFile, $ip);

banner();
login:

logx('', "Pilih Mode:", true, true);
logx('info', "1. Normal ( limit web )");
logx('info', "2. Dobrak ( limit+100 ) klo stop saat dobrak, limitnya reset saat rerun. awas keban");
$pilih = trim(_rl("mode [dobrak]: "));

$mode_dobrak = in_array($pilih, ["", "2"]);

while (true) {
    do {
        $l = inf::check($host.'/dashboard', [], 'login-panel');
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            break; 
        }
        
        @unlink(inf::$cookie);
        taskPrintCenter('logging in', 'err');
        
        $_0 = Net::C($host, 'GET', null, inf::$cookie, [], '', inf::$uagent);
        
        if (!empty($_0)) {
            Net::C($host, 'POST', ['email' => $login, 'login' => ''], inf::$cookie, [], $host, inf::$uagent);
        }
    } while (empty($dash));
    
    $list = _coin($dash);
    if (empty($list)) {
        logx('err', 'kayaknya limit semua');
        exit;
    }
    
    foreach ($list as $coinName => $_fa) {
        $fa = $host . $_fa['link']; 
        $set = 0;
        $claimCount = 0;
        $maxClaims = ($mode_dobrak) ? ((!AUTH_API) ? 100 : PHP_INT_MAX) : 0; 
        
        $txtMode = $mode_dobrak ? (AUTH_API ? "DOBRAK UNLIMITED" : "DOBRAK 100") : "NORMAL";
        logx('info', "$coinName [Mode: $txtMode]");

       # goto tes_ilmu; 

        while (true) {
            if ($mode_dobrak && $claimCount >= $maxClaims) {
                logx('ok', "Limit dobrak.");
                goto tes_ilmu;
            }

            $fau = Net::C($fa, 'GET', null, $cookieFile, [], $host, $userAgent);
            if (empty($fau)) continue;
            
            $limit = _info($fau);
            
            // JIKA SALDO WEB HABIS
            if ($limit === "EMPTY") {
                logx('err', "$coinName habis");
                goto tes_ilmu; 
            }
            
            if ($limit === 0) {
                if (!$mode_dobrak) {
                    logx('warn', "$coinName limit daily");
                    goto tes_ilmu; 
                } else {
                    $displayLimit = (AUTH_API) ? "∞" : $maxClaims;
                    logx('warn', "$coinName: [$claimCount/$displayLimit]");
                }
            }
            
            if (str_contains($fau, 'Sponsor verification require')) {
                $_p = scraper::_pP($fau, 'data-slot')[0] ?? '';
                $_t = scraper::_pP($fau, 'data-token')[0] ?? '';
                if ($_t && $_p) {
                    styler('Waiting for ads', fn() => _sle(rand(16, 25)));
                    $po = ['token' => $_t, 'slot'  => $_p];
                    Net::X($host . "/promo_gateway.php", "POST", $po, $cookieFile, [], $fa, $userAgent, true);
                    continue;
                }
            }

            // 4. Eksekusi Klaim
            $f = scraper::payload($fau)[0] ?? null;
            if (!$f) { _sle(2); continue; }
            $pa = $f['payload'];
            
            $wait = 17 - (int)ceil(microtime(true) - $set);
            if ($wait > 0) styler("waiting $wait", fn() => _sle($wait));
            
            $cla = Net::C($fa, 'POST', array_merge($pa, ['claim' => '']), $cookieFile, [], $fa, $userAgent);
            if (!empty($cla)) {
                $_suc = scraper::_xP($cla, "//div[contains(@class, 'alert-success')]");
                $_err = scraper::_xP($cla, "//div[contains(@class, 'alert-danger')]");
                
                if (!empty($_suc)) {
                    if ($mode_dobrak) $claimCount++; 
                    $msg = trim($_suc[0]);
                    logx("ok", ($mode_dobrak ? "[$claimCount] " : "") . trim(explode('(', $msg)[0]), false, true);
                } elseif (!empty($_err)) {
                    $msg = trim($_err[0]);
                    logx("err", trim($msg), false);
                    if (stripos($msg, 'sponsor') !== false) { _sle(2); continue; }
                    if (stripos($msg, 'insufficient') !== false) goto tes_ilmu;
                }

                $_b = scraper::_xP($cla, "//div[contains(@class, 'faucet-wallet-balance')]");
                logx("", '  [ ' . trim($_b[0] ?? '0') . ' ]');
                $set = microtime(true);
            }
            _sle(2);
        } 

        tes_ilmu:
        $fau = Net::C($fa, 'GET', null, $cookieFile, [], $host, $userAgent);
        if (_canWD($fau)) {
            $f = scraper::payload($fau)[0]['payload'] ?? null;
            if ($f) {
                logx('ok', " WD $coinName...");
                $wd = Net::C($fa, 'POST', array_merge($f, ['withdraw' => '']), $cookieFile, [], $fa, $userAgent);
                if (!empty($wd)) {
                    $_suc = scraper::_xP($wd, "//div[contains(@class, 'alert-success')]");
                    $msg = trim($_suc[0] ?? 'Withdraw process done');
                    logx('info', "WD: $msg");
                }
            }
        }
    }


    logx('ok', "done");
    exit;
}








function _canWD($html) {
    $balRaw = Scraper::_xP($html, "//div[contains(@class, 'faucet-wallet-balance')]")[0] ?? '0';
    $bal = (float)filter_var($balRaw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    $infoRaw = Scraper::_xP($html, "//div[contains(@class, 'wallet-info')]")[0] ?? '0';
    $min = 0;
    if (preg_match('/Minimum Withdrawal:\s+([\d.]+)/i', $infoRaw, $m)) {
        $min = (float)$m[1];
    }

    $hasToken = Scraper::_xP($html, "//input[@name='csrf_token']/@value")[0] ?? false;

    return ($bal >= $min && $hasToken) ? true : false;
}

function _coin($html) {
    $xp = Scraper::dom($html);
    $cards = $xp->query("//div[contains(@class, 'card')]");
    $ready = [];
    
    foreach ($cards as $card) {
        $_coin = $xp->query(".//h5", $card)->item(0);
        
        if (!$_coin) continue; 
        
        $coin = trim($_coin->textContent);
        $href = $xp->query(".//a[contains(@class, 'stretched-link')]/@href", $card)->item(0)->nodeValue ?? '';
        
        if (!str_contains($href, '/faucet/')) continue;

        $_bars = $xp->query(".//div[contains(@class, 'progress-bar')]", $card)->item(0);
        $stat = 'empty';
        
        if ($_bars) {
            $class = $_bars->getAttribute('class');
            if (str_contains($class, 'bg-success')) {
                $stat = 'ready';
            } elseif (str_contains($class, 'bg-warning')) {
                $stat = 'low';
            }
        }
        
        if ($stat !== 'empty') {
            $ready[$coin] = [
                'link'   => $href,
                'status' => $stat 
            ];
        }
    }
    
    uasort($ready, function($a, $b) {
        if ($a['status'] === 'ready' && $b['status'] !== 'ready') return -1;
        if ($a['status'] !== 'ready' && $b['status'] === 'ready') return 1;
        return 0;
    });
    return $ready;
}

function _info($html, $_limit_health = 1) {
    $health_ = Scraper::_xP($html, "//div[contains(@class, 'text-md-end')]/div[contains(@class, 'text-primary')]")[0] ?? '0%';
    $health = (float)filter_var($health_, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

    if ($health < $_limit_health) return "EMPTY";

    $limit_ = Scraper::_xP($html, "//span[contains(text(), 'Daily limit')]/following-sibling::div[1]")[0] ?? '0';
    $limit = (int)filter_var($limit_, FILTER_SANITIZE_NUMBER_INT);

    if ($limit <= 0) return 0;

    return $limit;
}
