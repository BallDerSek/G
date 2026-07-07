<?php
if (!defined('ROOT')) { die; }

$api = onKeys();

$acc = config::credential([], false, /*['mail', 'pass', 'PROXY']*/);
$mail = $acc['mail'];
$pass = $acc['pass'];
putenv("PROXY=".$acc['PROXY']);

login:
$host = 'https://ad-doge.com'; 
$domain = parse_url($host, PHP_URL_HOST);
$ip = '45.14.135.47';

(function ($mail, $ip, $host) {
    Proxy::load();
    Check::Geo();
    $cookieFile = config::cookie($mail);
    $userAgent = config::uagent('mobile');
    
    inf::setup($userAgent, $cookieFile, $ip, true, $mail);
    
    $b = Banner::getInstance();
    $b->show();
    $b->task1('ok', "$mail");
    $b->task2('ok', "site: $host");
    
} ) ($mail, $ip, $host);

$limit = false;
$claim = true;
$SLDONE = false;
$ADDONE = false;
$ALLDONE = 0;
$skipped = [];
$can_withdraw = true;

while (true) {
    $dash = null;
    $ret = 0; 
    
    do {
        $ret++;
        $l = inf::check("$host/dashboard", [], '/register');
        
        if ($l['ok']) {
            $dash = $l['html'];
            logx('info', "logged in", false); 
            _sle(3); _clr();
            #var_dump($dash); die;
            break;
        }
        
        if ($ret >= 10) {
            logx('warn', 'RETRY LIMIT REACHED, CHECK BROWSER');
            exit; 
        }
        
        logx('err', "logging in", false); 
        _sle(3); _clr();
        $_0 = Net::C("$host/login", 'GET', null, inf::$cookie, [], '', inf::$uagent, ip: $ip, ins: true);
        
        if ($_0 === 99) {
            logx('warn', 'Proxy issue, wait 30s');
            _sle(60);
            continue;
        }
        if (empty($_0)) continue;

        $f = scraper::payload($_0)[0] ?? null;
        #_put('0.html', $_0);
        $po = null;
        if (!empty($f)) {
            #print_r($f); die;
            $pa = $f['payload'];
            $cre = ['email' => $mail, 'password' => $pass];
            
            $cap = Solve::exec($_0, $host, $api, $pa);
            if (isset($cap['nocaptcha']) && str_contains($_0, 'Loading Captcha...')) $cap = ccaptcha($_0, $host, $api);
            
            if (isset($cap['trouble'])) {
                $tro = $cap['trouble'];
                logx('warn', "Solver trouble: $tro");
                ($tro === 'proxy') ? _sle(30) : _sle(10);
                continue;
            }
            
            $po = array_merge($pa, $cap, $cre);
        }
        
        if (!empty($po)) {
            #print_r($po);
            $ve = Net::X($f['url'], 'POST', $po, inf::$cookie, [], "$host/login", inf::$uagent, ip: $ip, ins: inf::$ins);
            
        }
    } while (empty($dash));
    _put('dash.html', $dash);
    
    
    
    
    
    
die;
}





function ccaptcha($html, $host, $api) {
    $ck = inf::$cookie;
    $ua = inf::$uagent;
    $in = inf::$ins;
    $ip = inf::$ip;
    
    
    $cc_u = scraper::_jP(
        Net::C($host.'/captcha', 'GET', null, $ck, [], $host, $ua, ip: $ip, ins: $in),
        '/src\s*=\s*[\'"`](\/cc\/[\w\d]+\.js\?[^"\'`]+)[\'"`]/'
    )[1][0] ?? null;
    $csrf = scraper::find($html, '_token')[0] ?? null;
    
    $xhr = null;
    $capt = null;
    $cho = null;
    if (!empty($cc_u) && $csrf) {
        
        $cc_js = Net::C($host . $cc_u, 'GET', null, $ck, [], $host, $ua, ip: $ip, ins: $in);
    
        $xhr = [
            'O' => scraper::_jP($cc_js, '/xhr\.open\("POST",\s*"([^"]+)"/')[1][0] ?? null,
            'S' => scraper::_jP($cc_js, '/xhr\.send\((.*?)\);/s')[1][0] ?? null,
        ];
        
        $capt = [
            'ic' => scraper::_jP($cc_js, '/captchaData\s*=\s*\{"options":\s*(\[.*?\])\}/s')[1][0] ?? null,
            'im' => scraper::_jP($cc_js, '/src="data:image\/png;base64,([^"]+)"/i')[1][0] ?? null,
        ];
    
        foreach ([...$xhr, ...$capt] as $v) if ($v === null || $v === '') return ['trouble' => 'reload'];
    
    }
    
    if (!empty($capt)) {
        
        $icons = json_decode($capt['ic'], true);
        $solution = Solve::img($api, $host, 'fa_icon', $capt['im']);
        
        if (is_string($solution) && preg_match('/class:\s*([^,]+),\s*index:\s*(\d+|none)/i', $solution, $m)) {
            $solution = [
                'ans' => 'fa-'.strtolower(trim($m[1])),
                'idx' => is_numeric($m[2]) ? (int)$m[2] : null,
            ];
        }
    
        if (isset($solution['idx']) && $solution['idx'] !== null) {
            $cho = [$solution['idx'], $icons[$solution['idx']] ?? null];
        } else {
            $needle = is_array($solution) ? $solution['ans'] : $solution;
            
            foreach ($icons as $idx => $icon) {
                if (str_contains($icon, $needle)) {
                    $cho = [$idx, $icon];
                    break;
                }
            }
        }
    
    }
    
    if ($cho) {
        
        $payload = trim($xhr['S'], '"');
        
        $payload = str_replace(
            ['csrfToken', 'csrf', 'iconIndex'],
            [urlencode($csrf), urlencode($csrf), $cho[0]],
            $payload
        );
        
        $payload = preg_replace('/\s*\+\s*/', '', $payload);
        $payload = str_replace('"', '', $payload);
        
        $cc_res = Net::C($host.$xhr['O'], 'POST', $payload, $ck, [], $host, $ua, ip: $ip, ins: $in);
        if (!empty($cc_res) && $cc_res !== 99) {
            $post = Scraper::build($html, $cc_js, $cc_res);
            if (!empty($post)) return $post;
            
            
            
        }
        
    }
    
    return ['trouble' => 'reload'];
    
    
}