<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, ['login', 'PROXY']);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://earnton.online';
$domain = parse_url($host, PHP_URL_HOST);
$r = '/r.php?ref=6';
$ip = null;

(function ($login, $ip) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip);
    _cle();
    banner();
    taskPrintCenter($login, 'info');
} ) ($login, $ip);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$claim = true;
$curr = '';
$habis = [];
$needSL = [];

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host", $headersCF, 'loginForm');
        
        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        if ($ret >= 10) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            
            if (!empty($_0)) _put(__DIR__.'/lo.html', $_0);
            if (isset($ve) && !empty($ve)) _put(__DIR__.'/ve.html', $ve);
            
            exit; 
        }
        
        logx('err', "logging in ", false); 
        _sle(3); _clr();
        $_0 = Net::X($host.$r, 'GET', null, inf::$cookie, $headersCF, '', inf::$uagent);
        
        if (!empty($_0) && $_0 !== 99) {
            
            Net::X($host.'/login.php', 'POST', ['email' => $login], inf::$cookie, $headersCF, $host.$r, inf::$uagent);
            
        }
        
    } while (empty($dash));
    _put('dash.html', $dash);
    
    
    $_fa = Scraper::_xP($dash, "//div[contains(text(), 'Faucets')]/following-sibling::a[contains(@href, 'faucet.php')]/@href");
    
    foreach ($_fa as $faa) {
        
        $fa = $host . $faa;
        parse_str(parse_url($fa)['query'], $_curr);
        $_c = $_curr['coin'] ?? null;
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue;
        print(FGd['CYN']." " . ITAL . UNDR . 'processing' . RSET . "  ");
        logx('err', strtoupper($_c));
        
        while ($claim) {
            
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent);
            _put('fau.html', $fau);
            
            $f = scraper::payload($fau, 'claimForm')[0] ?? [];
            
            $po = null;
            $cap = [];
            if (!empty($f['payload'])) {
                $pa = $f['payload'];
                for ($retry = 0; $retry < 5; $retry++) {
                    $cap = solve::exec($fau, $host, $api);
                    if (!isset($cap['trouble'])) break;
                }
                $po = array_merge($pa, ['_tz' => TIMEZONE()], $cap);
            }
            
            if (!empty($po)) {
                
                
                #print_r($pe);
                $boundary = "";
                $cla = json_decode(
                    Net::C(
                        $host."/claim-ajax.php",
                        'POST',
                        SolveUtils::webkitID($po, $boundary),
                        inf::$cookie,
                        ["Content-Type: multipart/form-data; boundary=$boundary"],
                        $fa,
                        inf::$uagent
                    )?: '', 1);
                
                if (!empty($cla)) {
                    $ok = filter_var($cla['ok'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $stt = $ok ? 'ok' : 'err';
                    $msg = $cla['msg'] ?? 'No message';
                    
                    print(FGd['CYN'] . maskEmail($login) . RSET . " ");
                    logx($stt, 'FAUCET ', false, true);
                    logg(false, $msg);
                    
                    /*
                    */
                    if (stripos($msg, 'Too many claims') !== false) {
                        styler("waiting for next claim", fn() => _sle(100));
                        break;
                    }
                    
                    if (stripos($msg, "nsufficient faucet balance") !== false || stripos($msg, "sufficient funds") !== false) {
                        $habis[$fa] = true;
                        
                        break; 
                    }
                    
                    styler("waiting for next claim", fn() => _sle(60));
                }

            }
            
        }
    }

    if (count($habis) === count($_fa)) {
        print(FGd['CYN'] . maskEmail($login) . RSET . " ");
        logx('err', 'Semua koin sudah habis / tidak bisa claim');
        die;
    }

    
    
    
    die;
    $_sl = Scraper::_xP($dash, "//div[contains(text(), 'Shortlinks')]/following-sibling::a[contains(@href, 'shortlinks.php')]/@href");
    print_r($_sl);

    
    
    
    
    
die;
}


tes:
