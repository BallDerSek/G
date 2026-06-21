<?php
if (!defined('ROOT')) { die; }
_die();
$api = onKeys();

$acc = config::credential([], false, /*['login', 'PROXY']*/);
$login = $acc['login'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://earnton.online';
$domain = parse_url($host, PHP_URL_HOST);
$r = '';
$ip = null;

(function ($login, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($login);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, false, $login);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$login");
    $b->task2('ok', "site: $host");
    
} ) ($login, $ip, $host);

$headersCF = [];
$skipped = [];
$SLDONE = false;
$claim = true;
$curr = '';
$habis = [];

while (true) {
    $dash = null;
    
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host", $headersCF, 'login.php');

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
        Net::C($host."/login.php", 'GET', null, inf::$cookie, [], '', inf::$uagent);
        Net::C($host."/login.php", 'POST', ['email'=>$login], inf::$cookie, [], '', inf::$uagent);
        
        
    } while (empty($dash));
    #_put('dash.html', $dash);
    
    
    $_fa = scraper::_xP($dash, "//div[text()='Faucets']/following-sibling::a[contains(@href, 'faucet.php')]/@href");
    #print_r($_fa);
    foreach ($_fa as $fa) {
        if (!$claim) break;
        parse_str(parse_url($fa)['query'], $coin);
        $_c = $coin['coin'];
        
        $fa = $host.$fa;
        
        print(FGd['CYN']." ".ITAL.UNDR.'processing'.RSET."  ");
        logx('err', strtoupper($_c));
        
        if (!empty($curr) && stripos($_c, $curr) === false) continue; 
        
        $ret99 = 0;
        while ($claim) {
            $fau = null;
            $fau = Net::X($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent);
            
            if ($fau === 99) {
                $ret99++;
                logx('warn', 'Proxy issue, wait 30s');
                if ($ret99 >= 7) goto login;
                _sle(30);
                continue;
            }
            $ret99 = 0; 
            if (empty($fau)) continue;
            
            $po = null;
            $cap = [];
            $f = scraper::payload($fau)[0] ?? null;
            
            if (!empty($f)) {
                #print_r($f);
                $pa = $f['payload'];
                $pa['_tz'] = TIMEZONE();
                
                $cap = Solve::exec($fau, $fa, $api);
                if (isset($cap['trouble'])) continue;
                $po = array_merge($pa, $cap);
                
                
            } else {
                _put('fau.html', $fau);
                die;
            }
            
            if (!empty($po)) {
                #print_r($po); die;
                
                $bo = '';
                $body = SolveUtils::webkitID($po, $bo);
                $head = ["Content-Type: multipart/form-data; boundary=$bo"];
                
                $cla = json_decode(Net::X($host.'/claim-ajax.php', 'POST', $body, inf::$cookie, $head, $fa, inf::$uagent, foll: false)?: '', 1);
                
                if (!empty($cla) && isset($cla['ok'])) {
                    $stt = $cla['ok'];
                    $msg = $cla['msg'] ?? 'unknown';
                    $is_ok = $stt ? 'success ' : 'error ';
                    
                    logm($login);
                    logx($is_ok, $is_ok, false);
                    logg(false, $msg);
                    
                    if (preg_match('/sufficient|could not be processed/i', $msg)) {
                        $habis[$fa] = true;
                        break;
                    }
                    
                    
                    if (stripos($msg, 'Shortlink')) {
                        if ($SLDONE) (logx('err', 'Gada SL lagi') ?: die);
                        $curr = $_c;
                        break 2;
                    }
                    
                    
                    styler("waiting for next claim", fn() => _sle(30));
                }
                
            }
            
            
            
            
            
            
        die;
        }
        
        
    die;
    }
    
    
    
    
    
die;
}


