<?php
if (!defined('ROOT')) { die; }
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent();

$host = 'https://onlyfaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = null;
inf::setup($userAgent, $cookieFile);

banner(); 
login:

while (true) {
    $max = 5;
    $ret = 0;
    do {
        $ret++;
        $l = inf::check("$host/", [], '/auth/login');
        
        if ($l['ok']) {
            taskPrintCenter('logged in', 'ok');
            $dash = $l['html'];
            #var_dump($dash); die;
            break;
        }
        
        @unlink($cookieFile);
        if ($ret >= $max) {
            logx('err', 'ada yang salah');
            exit; 
        }
        taskPrintCenter('logging in', 'err');
        $_0 = Net::C("$host", 'GET', null, inf::$cookie, [], '', inf::$uagent);
        if (empty($_0)) continue;
        #var_dump($_0); die;
        $f = scraper::payload($_0)[0];
        $pa = $f['payload'];
        #print_r($pa);
        
        $cap = null;
        $cap = solve::exec($_0, $host, $api);
        if (($cap['nocaptcha'] === true) && isset($pa['smart_token'])) {
            $cap = ['captcha' => 'smartcaptcha', 'smart_token' => locally::smartFP($_0)];
        } 
        
        $cre = ['wallet' => $login];
        $po = array_merge($pa, $cap, $cre);
        #print_r($po); die;
        
        $ve = Net::C($f['url'], 'POST', $po, inf::$cookie, [], "$host/", inf::$uagent);
        if (!empty($ve)) {
            $_err = Scraper::_jP($ve, "/html: '(.*?)'/");
            #print_r($_err);
            print(BOLD.FGd['CYN']." [ $login ]  ".RSET);
            logx('info', $_err[1][0], true, true);
            if (stripos($_err[1][0], 'elcome back')) continue;
            die;
        }
        
    } while (empty($dash));
    _put('dash.html', $dash);
    
    $_fa = Scraper::_xP($dash, "//ul[@id='faucet']//a/@href");
    foreach ($_fa as $fa) {
        logx('info', '  [ claiming ] ', false, true);
        logx('err', strtoupper(basename($fa)));
        
        $fau = Net::C($fa, 'GET', null, inf::$cookie, [], $host, inf::$uagent);
        _put('fau.html', $fau);
        
        $fo = scraper::payload($fau);
        print_r($fo);
        if (empty($fo)) {
            $isCF = (stripos($fau, 'Cloudflare Ray ID') !== false || stripos($fau, 'Attention Required!') !== false);
            if ($isCF) {
                logx('warn', 'Cloudflare Detected');
                if ($cf = _onlyfansCF($api, $fa)) {
                    [$he, $ua] = $cf;
                    inf::setup($ua, inf::$cookie, inf::$ip);
                    $fauuu = Net::C($fa, 'GET', null, inf::$cookie, $he, $fa, inf::$uagent);
                    _put('faa.html', $fauuu); die;
                } else {
                    logx('err', 'Bypass Gagal');
                    die;
                    #continue; 
                }
                
                
                
            }
        }
        
    
    
    die;
    }
    
/*
    foreach ($_fa as $fa) {
        $fau = Net::C($fa, 'GET', null, $cookieFile, [], $host, $userAgent);
        $isCF = (stripos($fau, 'Cloudflare Ray ID') !== false || stripos($fau, 'Attention Required!') !== false);
        if ($isCF) {
            logx('warn', 'Cloudflare Detected');
            $data = [
                'proxy' => $currentProxy 
            ];
            $res = execCF($api, $fa, $cookieFile, $userAgent, $data);
            if ($res) {
                $fau = Net::C($fa, 'GET', null, $cookieFile, [], $fa, $userAgent);
            } else {
                logx('err', 'API Solver failed to solve.');
                continue;
            }
        }

    _put('fau_after_solve.html', $fau);
    $f = scraper::payload($fau);
    print_r($f);
    die; 
    }
*/

    
    
    
die;
}




$_links = Scraper::_xP($_0, "//ul[@id='links']//a/@href");






function _onlyfansCF($api, $fa) {
    $res = execCF($api, $fa, inf::$cookie, inf::$uagent, []);
    print_r($res);
    if (is_array($res) && isset($res['token'])) {
        logx('success', 'Cloudflare Solved!');
        $h = inf::netHead(['cf_clearance' => $res['token']]);

        inf::setup($res['ua'], inf::$cookie, inf::$ip);

        return [$h, $res['ua']];
    }
    
    return false;
}



function onlyfans($api, $img) {
    $type = 'of_odd';
    
    if ($api) {
        $solver = config::getKeys($api, $type, 'b64');
        if (!isset(Api::B64[get_class($solver)][$type])) {
            logx('err', 'unsupported provider');
            exit;
        }
        $res = $solver->base64($img, $type);
        if ($res === 777) {
            logx('warn', "Failover to Direct API Provider...");
            $res = $api->base64($img, $type);
            if ($res === null) exit;
            if ($res === 77) return 'reload';
            if ($res && $res !== 777) {
                $api->getInfo();
                return $res;
            }
        }
        
        if ($res === null) exit; 
        if ($res === 77) return 'reload';
        if ($res) return $res;
    } else {
        logx('err', 'undefined provider');
        exit;
    }
    
}
