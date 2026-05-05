<?php
    
class _shortlinks {
    private $url;
    private $host;
    private $path;
    private $cookie;
    private $uagent;
    private $proxied;
    private $proxy;
    private $oldProxy;
    private $oldCtx;

    public function __construct($url) {
        
        if (!is_string($url) || trim($url) === '') {
            throw new RuntimeException("jangan kosong");
        }
        
        $this->oldProxy = getenv('PROXY');
        $this->oldCtx = $GLOBALS['_CTX']['proxy'] ?? null;
        putenv("PROXY=");
        unset($GLOBALS['_CTX']['proxy']);
        
        $part = parse_url($url);
        $host = $part['host'] ?? (parse_url($url, PHP_URL_PATH) ?? $url);
        $path = trim($part['path'] ?? '', '/');
        
        $_H = preg_replace('/[^a-zA-Z0-9]/', '_', $host);
        $_P = preg_replace('/[^a-zA-Z0-9]/', '_', str_replace('/', '_', $path));
        
        $_H = preg_replace('/_+/', '_', $_H);
        $_P = preg_replace('/_+/', '_', $_P);
        $_D = _lib('shortlink');
        $key = $_H;
        if ($_P !== '') {
            $key .= '_' . $_P;
        }
        
        $key = trim(preg_replace('/_+/', '_', $key), '_');
        
        $this->url = $url;
        $this->cookie = $_D . '/' . $key . '.tmp';
        $this->host = $host;
        $this->path = $path;
        
        $this->uagent = config::uagent("desktop");
        $this->proxied = Proxy::_enable();
        $this->proxy = "http://gamamoch-rotate:playernoob@p.webshare.io:3128";
    }

    public function links($api) {
        
        $rules = [
            'clk' => ['lnbz.la','tpi.li','oii.la','aii.sh'],
            'low' => ['xut.io','shrinkme.click','link.adlink.click','horrorpay.online','linkpay.top'],
            'nono' => ['earnow.online','shortino.link','shortano.link'],
            'coinclix' => ['coinclix.co']
        ];

        foreach ($rules as $func => $hosts) {
            if (in_array($this->host, $hosts)) {
                try {
                    $result = $this->$func($api);
                    return $result;
                } catch (Exception $e) {
                    throw $e; 
                } finally {
                    $this->cleanup();
                }
            }
        }
        throw new RuntimeException("not supported");
    }

    private function cleanup() {
        @unlink($this->cookie);
        if ($this->oldProxy !== false && $this->oldProxy !== '') {
            putenv("PROXY=" . $this->oldProxy);
        }
        
        if ($this->oldCtx !== null) {
            $GLOBALS['_CTX']['proxy'] = $this->oldCtx;
        }
        
        if (getenv('PROXY')) {
            Proxy::load(); 
        }
        
        unset($this->cookie, $this->uagent, $this->proxied);
    }


    private function clk() {
        $reff = [
            'https://healthmyst.com',
            'https://techbixby.com',
            'https://carensureplan.com',
            'https://blogmystt.com',
        ];
        $reff = $reff[array_rand($reff)];
        
        $_0 = Net::C($this->url, 'GET', null, $this->cookie, [], $reff, $this->uagent);
        if (!$_0) {
            throw new RuntimeException("blocked");
        } 
       # _put("0.html", $_0);
        $f = scraper::payload($_0)[0] ?? null;
        #print_r($f);

        $_1 = Net::C($this->url, 'POST', $f['payload'], $this->cookie, [], $reff, $this->uagent);
        #_put("1.html", $_1);
#die;
        $f = scraper::payload($_1);
        $pa = [];
        foreach ($f as $fo) {
            #print_r($fo);
            if (!empty($fo['url'])) {
                $pa = $fo['payload'];
                break;
            }
        }
        #print_r($pa);
        
        if (!empty($pa)) {
            _sle(15);
            $r = json_decode(Net::X("https://{$this->host}/links/go", 'POST', $pa, $this->cookie, [], $reff, $this->uagent), true);
            #print_r($r);
            #_put("r.html", $r);
            if (!empty($r['url'])) {
                return $r['url'];
            }
        }
        throw new RuntimeException("failed");
    }

    private function low() {
        $map = [
            'xut.io' => ['xut.io','https://cryptorex.net/'],
            'link.adlink.click' => ['blog.adlink.click','https://www.diudemy.com/'],
            'shrinkme.click' => ['en.mrproblogger.com','https://themezon.net/'],
            'horrorpay.online' => ['horrorpay.online','https://aradmag.online/'],
            'linkpay.top' => ['linkpay.top','https://coinsimulator.online/'],
        ];
        if (!isset($map[$this->host])) {
            throw new RuntimeException("unavailable");
        }

        [$link, $reff] = $map[$this->host];
        $_0 = str_replace($this->host, $link, $this->url);
        low:
        $html = Net::C($_0, 'GET', null, $this->cookie, [], $reff, $this->uagent);
        #_put('0.html', $html); die;
        if (!$html) {
            if (!$this->proxied) {
                putenv("PROXY=".$this->oldProxy ?? $this->proxy);
                Proxy::Load();
                $this->proxied = true;
                goto low;
            }
            throw new RuntimeException("blocked");
        }

        $p = scraper::payload($html)[0]['payload'] ?? null;
        #print_r($p);
        
        _sle(15);
        $r = json_decode(Net::X("https://{$link}/links/go", 'POST', $p, $this->cookie, [], $reff, $this->uagent), true);
        if (empty($r['url'])) {
            throw new RuntimeException("failed");
        }
        return $r['url'];
    }
    
    private function nono($api) {
        throw new RuntimeException("maintenance");
        
        #$back = _rl("Backlink: ");
        $back = defined('backlinkTo') ? backlinkTo : _rl("Backlink: ");
        
        $cookie = $this->cookie;
        $uagent = $this->uagent;
        $host = $this->host;
        $path = $this->path;
        
        
        if (!AUTH_KEY) {
            throw new RuntimeException("unauthorized");
        }
        $_fp = AUTH_API->access('earnow', 'fingerprint', ['userAgent' => $uagent]);
        
        $aapi = '1';
        $ip = "45.14.135.47";
        $_0 = Net::C($this->url, 'GET', null, $cookie, [], $this->url, $uagent, false, false, $ip, true, true);
        #_put('00.html', $_0); die;
            
        $_t = scraper::_xP($_0, "//title");
        $t  = isset($_t[0]) ? strtolower(trim($_t[0])) : '';
        $blocked_titles = [
            'traffic block',
            'error',
            'blocked',
            'access denied',
        ];
        foreach ($blocked_titles as $bt) {
            if ($t !== '' && str_contains($t, $bt)) {
                throw new RuntimeException("blocked: $t");
            }
        }
        $n_0 = scraper::_pP($_0, "location.href")[1][0];
        $html = $_0;
        $pat = '';
            
        bbypass:
        $retry = 0;
        $maxRetry = 10;
        $lastUrl = $pat; 
        while (true) {
            $loc = null;
            foreach (['location.href', 'location.replace'] as $key) {
                $m_l = scraper::_pP($html, $key);
                if (!empty($m_l[1][0])) {
                    $loc = stripcslashes($m_l[1][0]);
                    break;
                }
            }
            
            $con = null;
            foreach (["//meta[@http-equiv='refresh']/@content", "//noscript/meta[@http-equiv='refresh']/@content"] as $xpath) {
                $m_c = scraper::_xP($html, $xpath);
                if (!empty($m_c)) {
                    $con = preg_replace('/^0;URL=/i', '', $m_c[0]);
                    break;
                }
            }
            
            $current = scraper::_xP($html, "//span[@id='stepNum']") ?? [];
            if (!empty($current)) {
                $_step = $current[0];
                logx('info', "STEP " . $_step, true, true);
                break;
            }
            
            $nextUrl = $loc ?: $con;
            if ($nextUrl) {
                $nextUrl = stripcslashes($nextUrl);
                $lastUrl = $nextUrl;
            }
            
            $finalLink = inf::lastLocation($_1['headers'] ?? [], $back);
            if ($finalLink) {
                return $finalLink;
            }
            
            if ($nextUrl && strpos($nextUrl, $back) !== false) {
                return $nextUrl;
            }
            
            if (!$nextUrl) {
                if ($retry++ < $maxRetry) {
                    _sle(3);
                    $_1 = Net::C($lastUrl, 'GET', null, $cookie, [], $pat, $uagent, true, false, $ip, true, true);
                    $html = $_1['body'] ?? '';
                    continue;
                }
                #_put('error.html', $html);
                throw new RuntimeException("refresh IP");
            }
            
            $_1 = Net::C($nextUrl, 'GET', null, $cookie, [], $pat, $uagent, true, false, $ip, true, true);
            $html = $_1['body'] ?? '';
        }
        
        $_reload = false;
        reload:
        if ($_reload) {
            $_1 = Net::C($nextUrl, 'GET', null, $cookie, [], $pat, $uagent, true, false, $ip, true, true);
            $html = $_1['body'] ?? '';
            $_reload = false;
        }
        
        $_step = $current[0];
        $pat = inf::lastLocation($_1['headers']);
        $jsUrl = parse_url($pat)['host'];
        $h = headers("https://$jsUrl", '', $jsUrl);
        
        $SLjs = '';
        $sl_u = scraper::_xP($html, "//script[contains(@src,'sl/')]/@src")[0];
        $sl_ur = "https://{$jsUrl}{$sl_u}";
        $SLjs = Net::C($sl_ur, 'GET', null, $cookie, [], $pat, $uagent, false, false, $ip, true, true); 
        
        if (strlen($SLjs) > 1000) {
            #_put("sl-$_step.js", $SLjs); die;
            $cc_t = rScraper::jPath($SLjs, '/\?([a-f0-9]{32})=true/')[0][0];
            $cc_h = rScraper::jPath($SLjs, "/'([a-f0-9]{64})'/")[1][0];
            
            $cc_p = [];
            if ($_step === '0') {
                $pairs = rScraper::jPath($SLjs, '/"([^"]+)"\s*:\s*([a-zA-Z0-9]+)/')[0];
                $fp = 'fingerprint';
                foreach ($pairs as $pair) {
                    if (preg_match('/"([^"]+)"\s*:\s*([a-zA-Z0-9]+)/', $pair, $m)) {
                        $key = trim($m[1]);
                        $val = trim($m[2]);
                        if ($val === 'dataf') {
                            $cc_p[$key] = $fp;
                        } else {
                            $cc_p[$key] = $cc_h;
                        }
                    }
                }
            } else {
                $pairs = rScraper::jPath($SLjs, '/JSON\.stringify\(\{([^}]+)\}\)/s')[1][0];
                preg_match_all('/"([^"]+)"\s*:\s*(dataf|\'[a-f0-9]{64}\')/', $pairs, $matches, PREG_SET_ORDER);
                $fp = $_fp['earnow'];
                foreach ($matches as $m) {
                    $key = $m[1];
                    $val = $m[2];
                    if ($val === 'dataf') {
                        $cc_p[$key] = $fp;
                    } else {
                        $cc_p[$key] = trim($val, "'");
                    }
                }
            }
            $pa = json_encode($cc_p);
        } else {
            $_reload = true;
            logx('err', 'failed fetching sl.js', true, true);
            goto reload;
        }
        #print_r($pa);
        $cc_ur = '';
        if (in_array($_step, ['0', '1'])) {
            $cc_ur = "https://$jsUrl/$path$cc_t";
        } else {
            $cc_ur = "$pat/$cc_t";
        }
        #logx('info', $cc_ur); #die;
        _sle(5);
        $cc_vr = json_decode(Net::X($cc_ur, 'POST', $pa, $cookie, headers($pat), $pat, $uagent, false, false, $ip, true), true);
        
        if (isset($cc_vr['status']) && $cc_vr['status'] === 200) {
            #logx('ok', ($cc_vr['message']), false, true);
            _sle(1);
            $cc_u = rScraper::jPath($SLjs, '/src\s*=\s*[\'"`](\/cc\/[\w\d]+\.js\?onload=[\w\d]+&action=captcha)[\'"`]/')[1][0] ?? null;
            
            if (!empty($cc_u)) {
                $CCjs = Net::C("https://{$jsUrl}$cc_u", 'GET', null, $cookie, [], "https://$jsUrl");
                if (strlen($CCjs) > 100) {
                    $img_u = scraper::_pP($CCjs, "src") ?? [];
                    $icon = rScraper::jPath($CCjs, '/captchaData\s*=\s*\{"options":\s*(\[.*?\])\}/s') ?? [];
                    $xhrs = rScraper::jPath($CCjs, '/xhr\.send\(\s*"([^"]+)"\s*\+\s*(.*?)\)/') ?? [];
                    $xhr = rScraper::jPath($CCjs, '/xhr\.open\("POST",\s*"([^"]+)"/') ?? [];
                    ## DON'T TOUCH
                    if (!empty($img_u) && !empty($icon) && !empty($xhrs) && !empty($xhr)) {
                        parse_str($xhrs[1][0], $pa);
                        $icons = json_decode($icon[1][0], true);
                        $img = Net::C("https://$jsUrl".$img_u[1][0], 'GET', null, $cookie, [], $pat, $uagent, false, false, $ip, true, true);
                    
                        if (!$aapi) {
                            logx('info', "\noptions");
                            _put('captcha.png', $img);
                            foreach ($icons as $i => $icon) {
                                logx('info', "  [$i] $icon");
                            }
                            $inputName = trim(readline("check captcha.png: "));
                            @unlink('captcha.png');
                        } else {
                            do {
                                $inputName = AUTH_API->base64($img, 'fa3_icon');
                                #print_r($inputName);
                            } while ($inputName === false);
                        }
                    
                        #$part = array_map('trim', explode(',', $inputName));
                        $part = $inputName['solution'] ?? [];
                        $sel   = [];
                        foreach ($part as $p) {
                            if (is_numeric($p)) {
                                $idx = (int)$p;
                                if (isset($icons[$idx])) $sel[] = $idx;
                            } else {
                                foreach ($icons as $i => $name) {
                                    if (stripos($name, $p) !== false) {
                                        $sel[] = $i;
                                        break;
                                    }
                                }
                            }
                        }
                        foreach (array_keys($pa, '', true) as $ek) {
                            $pa[$ek] = json_encode($sel);
                        }
                    } else {
                            throw new RuntimeException('blocked');
                    }
                    ## DON'T TOUCH
            
                    #print_r($pa);
                    $cc_res  = Net::X("https://$jsUrl". $xhr[1][0], 'POST', $pa, $cookie, [], $pat, $uagent, false, false, $ip, true);
                    $payload = submit($CCjs, $html, $cc_res);
                } else {
                    $_reload = true;
                    logx('err', 'failed fetching cc.js', true, true);
                    goto reload;
                }
            } else {
                $_c = capt::cha($html);
                
                while (($t = $api->token($_c['keys'][0], $host, $_c['type'], ['userAgent' => $uagent])) === false);
                if ($t === null) throw new RuntimeException('');

                $payload = submittt($html, $SLjs, $t);
                $payload['g-recaptcha-response'] = $t;
                $payload['h-captcha-response']   = $t;
            }
    
        } else {
            var_dump($cc_vr);
            if (!empty($cc_vr)) {
                if (isset($cc_vr['message']) && str_contains($cc_vr['message'], 'as been block')) {
                    throw new RuntimeException($cc_vr['message']);
                }
                logx('err', ($cc_vr['message'] ?? 'Unknown error'));
            }
            goto bbypass;
                
        }
        
        $_1 = Net::C($pat, 'POST', $payload, $cookie, [], $pat, $uagent, true, false, $ip, true, true); 
        $html = $_1['body'];
        goto bbypass;
    }
    
    private function coinclix($api) {

        $cookie = $this->cookie;
        $uagent = $this->uagent;
        $host = $this->host;
        $path = $this->path;
        
        coinclix:
        if (!AUTH_KEY) {
            throw new RuntimeException("unauthorized");
        }
        
        $_code = null;
        do {
            $_0 = Net::C($this->url, 'GET', null, $cookie, [], '', $uagent);
            if (!empty($_0)) {
                #_put('0.html', $_0);
                if (stripos($_0, ' disable your proxy')) {
                    if (!$this->proxied) {
                        putenv("PROXY=".$this->oldProxy ?? $this->proxy);
                        Proxy::Load();
                        $this->proxied = true;
                        goto coinclix;
                    }
                    throw new RuntimeException("blocked");
                    
                }
                
                $code = _ccCode($_0);
                #print_r($code);
                if (isset($code[1])) {
                    $_code = $code[1];
                }
                
            }
        } while (!$_code);
        
        $_dome = ['vitalityvista.net','geekgrove.net'];
        $ccpayloadcomponents = false;
        $_error = false;
        $errorCount = 0;
        $error = false;
        $code = null;
        $_g1 = null;
        
        foreach ($_dome as $_domain) {
            $dom = "https://".$_domain;
            #logx('', $dom);
            $_1 = json_decode(Net::C($dom.'/link/process', 'POST', ['linkInit' => $_code], $cookie, [], $dom, $uagent), true);
            #print_r($_1);
            $next = scraper::_xP($_1['message'] ?? '', "//a/@href") ?? null;
            if (isset($next[0])) {
                $_g1 = Net::C($dom.$next[0], 'GET', null, $cookie, [], $dom, $uagent);
                $lastreload = $dom.$next[0];
                break;
            }
            $msgs = scraper::_xP($_1['message'] ?? '', "//div[contains(@class,'alert-danger')]");
            if (!empty($msgs[0])) {
                $_error = $msgs[0];
                continue;
            }
        }
        if (!$_g1) {
            throw new RuntimeException($_error ?? "invalid code");
        }
        $dom = "https://".$_domain;
        start:
        if ($error) {
            $errorCount++;
            if ($errorCount >= 5) {
                throw new RuntimeException("captcha failed");
            }
            _sle(2);
            $_g1 = Net::C($lastreload, 'GET', null, $cookie, [], $lastreload, $uagent);
            $error = false;
        }
        
        #_put('g1.html', $_g1); die;
        $st = scraper::_xP($_g1, "//*[@id='linkResHeader']//h4") ?? '';
        if (isset($st[0])) {
            $step = trim(preg_replace('/\s+/', ' ', $st[0]));
            $pis =scraper::find($_g1, 'pissoff', 'input', 'value', 'id')?? null;
            $lpt =scraper::find($_g1, 'lpt', 'input', 'value', 'id') ?? null;
            $ver =scraper::find($_g1, 'linkVer', 'input', 'value', 'id') ?? null;
            $cnn = scraper::_xP($_g1, "//*[contains(@class,'cnnc')]/@id") ?? null;
            $_bg =scraper::find($_g1, 'cpres2', 'input', 'value', 'id') ?? null;
            $_cp =scraper::find($_g1, 'cpobj', 'input', 'value', 'id') ?? null;
            $ccpayloadcomponents = true;
        } 
        #logx('info', $step, false, true);
        logx('info', $step." [".$ver[0]."] ", true, true);
        $po = null;
        if ($ccpayloadcomponents) {
            $start = microtime(true);
            $po = _ccPayload($api, $dom, $ver[0], $pis[0], $cnn[0], $_bg[0] ?? null, $_cp[0] ?? null);
            $end = microtime(true) - $start;
            $wait = (int)$lpt[0] - $end;
            if ($wait > 0) {
                #_sle((int)ceil($wait));
                styler("waiting", fn() => _sle((int)ceil($wait)));
            }
        }
        
        
        $_v1 = json_decode(Net::C($dom.'/link/process', 'POST', $po, $cookie, [], $dom, $uagent), true);
        if (empty($_v1) || ($_v1 === 99)) throw new RuntimeException('totally failed');
        #print_r($_v1['message']); logx();
        $matches = scraper::_jP($_v1['message'], '/<code class="link_code">([A-Za-z0-9]+)<\/code>/i') ?? [];
        if (!empty($matches[1][0])) {
            $code = $matches[1][0];
            goto verify;
            #return "Verification code: ".$code;
        } else {
            $next = scraper::_jP($_v1['message'], '/window\.location\.href\s*=\s*"([^"]+)"/') ?? [];
            $_n = $next[1][0] ?? '';
            if ($_n !== '') {
                $errorCount = 0;
                if (!preg_match('/^https?:\/\//', $_n)) {
                    $_n = $dom.$_n;
                }
                $_g1 = Net::C($_n, 'GET', null, $cookie, [], '', $uagent);
                $lastreload = $_n;
                $matches = scraper::_jP($_g1, '/<a href="([^"]+)"/i');
                if (!empty($matches[1][0])) {
                    $_n = $matches[1][0];
                    $_g1 = Net::C($_n, 'GET', null, $cookie, [], '', $uagent);
                    $lastreload = $_n;
                }
            } else {
                $error = true;
            }
        }
        goto start;
        
        verify:
        $ver = json_decode(Net::X("https://$host/members/shortener/linkprocess/", 'POST', ['linkVerify' => $code], $cookie, [], $this->url, $uagent), true);
        $msg = $ver['message'] ?? '';
        if (str_contains($msg, 'Invalid verification code')) {
            throw new RuntimeException('invalid code');
        }
        $match = scraper::_jP($msg, '/href="([^"]+)"/') ?? [];
        if (!isset($match[1][0])) {
            throw new RuntimeException($msg ?: 'invalid session');
        }
        return $match[1][0];
    }
    
}

{

function _ccCode($html) {
    $nodes = scraper::_xP($html, "//div[contains(@class,'accordion-body')]");
    foreach ($nodes as $txt) {
        if (preg_match('/enter\s+this\s+key\s*-\s*([A-Za-z0-9]{5})/i', $txt, $m)) {
            return $m;
        }
    }
    return null;
}

function _ccPayload($api, $dom, $ver, $pis, $cnn, $bg, $cp) {

    $cpobj = $cp ? json_decode(html_entity_decode($cp), true) : null;
    
    switch (strtoupper($ver)) {

        case 'CC':
            $token = bin2hex(random_bytes(15));
            break;

        case 'CT':
            $token = solve::tkn($api, $dom, '0x4AAAAAAB5TRnwvGvH5b2kw', 'cft', ['action' => 'linkSubmit']);
            break;

        case 'HC':
            #$token = _rl('hcaptcha: ');
            $token = solve::tkn($api, $dom, '2a9619f4-43bc-4e64-afc8-7fbc48f2bf34', 'hc', ['invisible'=>1]);
            break;

        case 'PC':
        case 'IC':
            $token = solve::tkn($api, $dom, $cpobj, $ver.'c'); 
            break;

        default:
            return null;
    }

    if ($token === 471) return null;
    return _payloadCC($pis, $cnn, $token, $bg);
}

function _payloadCC($pis, $cnn, $response, $bg) {
    $rand = function($len){
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        for($i = 0; $i<$len; $i++){
            $out .= $chars[random_int(0,strlen($chars)-1)];
        }
        return $out;
    };

    $linkCont = random_int(12345,54321);
    $ttl = $rand(15);
    $t = time();
    $g = $t+587814;
    $v47 = $t-835069;

    $n = hash_hmac('sha256', "bEhInD".$pis."YoU", (string)$v47);

    $key = $cnn."<|>".(987656789-$linkCont)."lIl1l";

    $i = hash_hmac('sha256', '"' . $response . '"', $key);

    $payload = [
        'linkCont' => $linkCont,
        'response' => $response,
        'n' => $n,
        'i' => $i,
        'g' => $g,
        'ttl' => $ttl
    ];

    if ($bg !== null && $bg !== '') {
        $payload['bg'] = $bg;
    }

    return $payload;
}

}




  
