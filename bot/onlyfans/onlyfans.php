<?php
if (!defined('ROOT')) { die; }
logx('err', ' beloman bener'); die;
$api = onKeys();

$acc = config::credential([], true);
$login = $acc['login'];

$cookieFile = config::cookie($login);
$userAgent = config::uagent('mobile');

$host = 'https://onlyfaucet.com';
$domain = parse_url($host, PHP_URL_HOST);
$ip = null;
inf::setup($userAgent, $cookieFile);

banner(); 
login:

$headersCF = [];
$skipped = [];
$SLDONE = false;
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
    #_put('dash.html', $dash);
    
    $_fa = Scraper::_xP($dash, "//ul[@id='faucet']//a/@href");
    foreach ($_fa as $fa) {
        logx('info', '  [ claiming ] ', false, true);
        logx('err', strtoupper(basename($fa)));
        
        $fau = Net::C($fa, 'GET', null, inf::$cookie, $headersCF, $host, inf::$uagent);
        _put('fau.html', $fau); 
        
        $f = scraper::payload($fau)[0] ?? [];
        print_r($f);
        
            if (!empty($f)) {
                $pa = $f['payload'];
                print_r($pa);
                die;
                
                
            } else {
                if ((stripos($fau, 'Just a moment') !== false || stripos($fau, 'Attention Required!') !== false)) {
                    logx('warn', 'Cloudflare Detected '.$fa);
                    
                    #$fa = str_replace('/currency/', '/verify/', $fa);
                    if ($cf = onfCF($api, $fa, '1')) {
                        [$he, $ua] = $cf;
                        inf::setup($ua, inf::$cookie);
                        $headersCF = $he;
                        
                        print_r($headersCF);
                        logx('info', inf::$uagent);
                        
                        $fauuu = Net::C($fa, 'GET', null, inf::$cookie, $headersCF, $fa, inf::$uagent);
                        _put('faa.html', $fauuu); die;
                    } else {
                        logx('err', 'gagal cf');
                        die;
                    }
                }
                _sle(10);
                continue;
            }
        
    
    
    die;
    }
    
    
    
    $_links = Scraper::_xP($_0, "//ul[@id='links']//a/@href");
    
    
    
die;
}






tes:


function onfCF($api, $fa, $mods = '') {
    $res = execCF($api, $fa, inf::$cookie, inf::$uagent, [], $mods);
    #print_r($res);
    if (is_array($res) && isset($res['token'])) {
        logx('', 'Cloudflare Solved!', true, true);
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