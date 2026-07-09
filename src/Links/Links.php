<?php
/*
if (!trait_exists('WorkDir')) {
    require_once SRCDIR . '/config/Workdir.php';
}
*/

final class Links {
    use WorkDir;
    private $url, $host, $path, $cookie, $uagent;
    private $proxied, $proxy, $oldProxy, $oldCtx;

    public function __construct($url) {
        if (!is_string($url) || trim($url) === '') throw new RuntimeException("jangan kosong");
        
        $this->oldProxy = getenv('PROXY');
        $this->oldCtx = $GLOBALS['_CTX']['proxy'] ?? null;
        
        putenv("PROXY=");
        unset($GLOBALS['_CTX']['proxy']);
        
        $part = parse_url($url);
        $this->host = $part['host'] ?? $url;
        $this->path = trim($part['path'] ?? '', '/');
        
        $this->setupWorkDir('shortlinks');
        
        $this->cookie = $this->workDir . '/session.tmp';
        
        $this->url = $url;
        $this->uagent = config::uagent("desktop");
        $this->proxied = false; 
        $this->proxy = "http://gamamoch-rotate:playernoob@p.webshare.io:3128";
    }

    public function exec($api) {
        $rules = [
            'coinclix' => ['coinclix.co'],
            'clk' => ['lnbz.la','tpi.li','oii.la','aii.sh'],
            'low' => ['xut.io','shrinkme.click','link.adlink.click','horrorpay.online','linkpay.top'],
        ];
        try {
            foreach ($rules as $func => $hosts) {
                if (in_array($this->host, $hosts)) {
                    return $this->$func($api);
                }
            }
            throw new RuntimeException("not supported");
        } finally {
            $this->cleanup();
        }
    }

    private function cleanup() {
        $this->rmdir($this->workDir);
        
        putenv("PROXY=" . ($this->oldProxy ?: ""));
        
        if ($this->oldCtx !== null) {
            $GLOBALS['_CTX']['proxy'] = $this->oldCtx;
        }
        Proxy::load();
    }

    private function enableProxy() {
        if ($this->proxied) {
            throw new RuntimeException("blocked"); 
        }

        @unlink($this->cookie); 
        $pTarget = ($this->oldProxy ?: $this->proxy);
        putenv("PROXY=" . $pTarget);
        Proxy::load();
        
        $this->proxied = true;
        _sle(2);
        return true;
    }

    private function low($api) {
        $map = [
            'xut.io' => ['xut.io','https://cryptorex.net/'],
            'link.adlink.click' => ['blog.adlink.click','https://www.diudemy.com/'],
            'shrinkme.click' => ['en.mrproblogger.com','https://themezon.net/'],
            'horrorpay.online' => ['horrorpay.online','https://aradmag.online/'],
            'linkpay.top' => ['linkpay.top','https://coinsimulator.online/'],
        ];
        
        if (!isset($map[$this->host])) throw new RuntimeException("unavailable");
        [$link, $reff] = $map[$this->host];
        $_0 = str_replace($this->host, $link, $this->url);
        
        low_start:
        $html = Net::C($_0, 'GET', null, $this->cookie, [], $reff, $this->uagent);
        if (!$html || $html === 99) {
            $this->enableProxy();
            goto low_start;
        }

        $p = scraper::payload($html)[0]['payload'] ?? null;
        if (!$p) {
            $this->enableProxy();
            goto low_start;
        }
        
        _sle(17);
        $res = Net::X("https://{$link}/links/go", 'POST', $p, $this->cookie, [], $reff, $this->uagent);
        
        $r = json_decode($res, true);
        if (!empty($r['url'])) return $r['url'];

        throw new RuntimeException("totally failed");
    }

    private function clk() {
        $reffs = [
            'https://healthmyst.com', 'https://techbixby.com',
            'https://carensureplan.com', 'https://blogmystt.com',
        ];
        
        clk_start:
        $reff = $reffs[array_rand($reffs)];
        $get = Net::C($this->url, 'GET', null, $this->cookie, [], $reff, $this->uagent, d: true);
        
        if (!$get || $get === 99) {
            $this->enableProxy();
            goto clk_start;
        }
        
        $code = $get['http_code'];
        $html = $get['body'] ?? '';
        
        if ($code !== 200 && stripos($html, 'just a moment')) {
            $this->enableProxy();
            goto clk_start;
        }
        
        $token = null;
        $fo = scraper::payload($html);
        if ($fo) {
            foreach ($fo as $f) {
                $pa = $f['payload'] ?? [];
                if (!empty($pa) && isset($pa['token'])) {
                    $token = $pa['token'];
                    break;
                }
            }
        }
        
        if ($token) {
            
            $pos = strpos($token, 'aHR0c');
            if ($pos !== false) {
                $rawPart = substr($token, $pos);
                if (preg_match('/(aHR0c[a-zA-Z0-9+\/]+={0,2})/', $rawPart, $match)) {
                    $url = base64_decode($match[1]);
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        return $url;
                    }
                }
            }
            
            if (preg_match('/(aHR0c[a-zA-Z0-9+\/]+={0,2})/', $token, $m)) {
                $url = base64_decode($m[1]);
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    return $url;
                }
            }
        }


        throw new RuntimeException("totally failed");
    }

    private function coinclix($api) {
        #throw new RuntimeException("maintenance");
        $cookie = $this->cookie;
        $uagent = $this->uagent;
        $host = $this->host;
        
        if (!AUTH_KEY) throw new RuntimeException("unauthorized");

        coinclix_init:
        $_code = null;
        $initTry = 0;
        while (!$_code) {
            if ($initTry++ > 5) throw new RuntimeException("failed init");
            $_0 = Net::C($this->url, 'GET', null, $cookie, [], '', $uagent);
            if (!empty($_0) && $_0 !== 99) {
                if (stripos($_0, ' disable your proxy')) {
                    if (!$this->proxied) {
                        putenv("PROXY=" . ($this->oldProxy ?? $this->proxy));
                        Proxy::Load();
                        $this->proxied = true;
                        goto coinclix_init;
                    }
                    throw new RuntimeException("blocked");
                }
                $res_code = CoinClix::_ccCode($_0);
                if (isset($res_code[1])) $_code = $res_code[1];
            }
            if (!$_code) _sle(2);
        }

        $_dome = ['vitalityvista.net', 'geekgrove.net'];
        $dom = '';
        $html = null; 
        foreach ($_dome as $_domain) {
            $dom = "https://" . $_domain;
            $retryCount = 0; 
            
            while (true) {
                $retryCount++;
                if ($retryCount >= 7) break;

                $_1 = json_decode(Net::C($dom.'/link/process', 'POST', ['linkInit' => $_code], $cookie, [], $dom, $uagent), true);
                
                if ($_1 === 99 || empty($_1)) {
                    _sle(30); 
                    continue;
                }

                $next = scraper::_xP($_1['message'] ?? '', "//a/@href") ?? null;
                
                if (isset($next[0])) {
                    $html = Net::C($dom . $next[0], 'GET', null, $cookie, [], $dom, $uagent);
                    
                    if (empty($html) || ($html === 99)) {
                        _sle(30); 
                        continue;
                    }
                    
                    $lastreload = $dom . $next[0];
                    break 2; 
                } else {
                    break;
                }
            }
        }
        
        if (!$html) throw new RuntimeException("unstable net");
        
        $code = null; 
        $errorCount = 0;
        while (true) {
            $matches = scraper::_jP($html, '/<code class="link_code">([A-Za-z0-9]+)<\/code>/i')[1][0] ?? null;
            if (!empty($code)) break; 

            $st = scraper::_xP($html, "//*[@id='linkResHeader']//h4") ?? [];
            $ver = scraper::find($html, 'linkVer', 'input', 'value', 'id')[0] ?? null;;

            if (isset($st[0]) && $ver) {
                $step = trim(preg_replace('/\s+/', ' ', $st[0]));
                Logger::X('info', "\r$step [ {$ver} ]", true, true);
                $start = microtime(true);

                $pis = scraper::find($html, 'pissoff', 'input', 'value', 'id')[0] ?? null;
                $lpt = scraper::find($html, 'lpt', 'input', 'value', 'id')[0] ?? 0;
                $cnn = scraper::_xP($html, "//*[contains(@class,'cnnc')]/@id")[0] ?? null;
                $_bg = scraper::find($html, 'cpres2', 'input', 'value', 'id')[0] ?? null;
                $_cp = scraper::find($html, 'cpobj', 'input', 'value', 'id')[0] ?? null;
                
                $po = CoinClix::_ccForm($api, $dom, $ver, $pis, $cnn, $_bg, $_cp);
                $wait = (int)($lpt) - (int)(microtime(true) - $start);
                if ($wait > 0) {
                    styler("waiting", fn() => _sle((int)ceil($wait)));
                }
                
                $retTry = 0;
                $maxTry = 3;
                while (true) {
                    $retTry++;
                    if ($retTry >= $maxTry) throw new RuntimeException('totally failed');
                    $_v1 = json_decode(Net::C($dom.'/link/process', 'POST', $po, $cookie, [], $dom, $uagent)?: '', true);
                    if (empty($_v1) || ($_v1 === 99)) {
                        _sle(30); 
                        continue;
                    }
                    break; 
                }
                #print_r($_v1);
                
                $matches = scraper::_jP($_v1['message'] ?? '', '/<code class="link_code">([A-Za-z0-9]+)<\/code>/i') ?? [];
                if (!empty($matches[1][0])) {
                    $code = $matches[1][0];
                    break;
                }
                
                $next_url = scraper::_jP($_v1['message'] ?? '', '/window\.location\.href\s*=\s*"([^"]+)"/') ?? [];
                $_n = $next_url[1][0] ?? '';
                
                if ($_n !== '') {
                    if (!preg_match('/^https?:\/\//', $_n)) $_n = $dom.$_n;
                    $html = Net::C($_n, 'GET', null, $cookie, [], '', $uagent);
                    $lastreload = $_n;

                    $m_a = scraper::_jP($html, '/<a href="([^"]+)"/i');
                    if (!empty($m_a[1][0])) {
                        $html = Net::C($m_a[1][0], 'GET', null, $cookie, [], '', $uagent);
                        $lastreload = $m_a[1][0];
                    }
                    $errorCount = 0; 
                } else {
                    $errorCount++;
                    if ($errorCount >= 5) throw new RuntimeException("stuck");
                    _sle(3);
                    $html = Net::C($lastreload, 'GET', null, $cookie, [], $lastreload, $uagent);
                }
                
            } else {
                $errorCount++;
                if ($errorCount >= 5) throw new RuntimeException("totally failed");
                _sle(3);
                $html = Net::C($lastreload, 'GET', null, $cookie, [], $lastreload, $uagent);
            }
        }
        
        if (!$code) throw new RuntimeException("no code found");
        
        $ver_fin = json_decode(Net::X("https://$host/members/shortener/linkprocess/", 'POST', ['linkVerify' => $code], $cookie, [], $this->url, $uagent)?: '', true);
        $msg = $ver_fin['message'] ?? '';
        if (str_contains($msg, 'Invalid verification code')) throw new RuntimeException('invalid code');
        $match = scraper::_jP($msg, '/href="([^"]+)"/') ?? [];
        if (isset($match[1][0])) return $match[1][0];
        
        throw new RuntimeException($msg ?: 'invalid session');
    
    }

    private function nono($api) {
        throw new RuntimeException("maintenance");
        
        #$back = _rl("Backlink: ");
        $back = defined('backlinkTo') ? backlinkTo : _rl("Backlink: ");
        
        $cookie = $this->cookie;
        $uagent = $this->uagent;
        $host = $this->host;
        $path = $this->path;
        
        
        if (!AUTH_KEY) throw new RuntimeException("unauthorized");
        
        $_fp = AUTH_API()->access('earnow', 'fingerprint', ['userAgent' => $uagent]);
        
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
                Logger::X('info', "STEP " . $_step, true, true);
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
            Logger::X('err', 'failed fetching sl.js', true, true);
            goto reload;
        }
        #print_r($pa);
        $cc_ur = '';
        if (in_array($_step, ['0', '1'])) {
            $cc_ur = "https://$jsUrl/$path$cc_t";
        } else {
            $cc_ur = "$pat/$cc_t";
        }
        #Logger::X('info', $cc_ur); #die;
        _sle(5);
        $cc_vr = json_decode(Net::X($cc_ur, 'POST', $pa, $cookie, headers($pat), $pat, $uagent, false, false, $ip, true), true);
        
        if (isset($cc_vr['status']) && $cc_vr['status'] === 200) {
            #Logger::X('ok', ($cc_vr['message']), false, true);
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
                            Logger::X('info', "\noptions");
                            _put('captcha.png', $img);
                            foreach ($icons as $i => $icon) {
                                Logger::X('info', "  [$i] $icon");
                            }
                            $inputName = trim(readline("check captcha.png: "));
                            @unlink('captcha.png');
                        } else {
                            do {
                                $inputName = AUTH_API()->base64($img, 'fa3_icon');
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
                    Logger::X('err', 'failed fetching cc.js', true, true);
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
                Logger::X('err', ($cc_vr['message'] ?? 'Unknown error'));
            }
            goto bbypass;
                
        }
        
        $_1 = Net::C($pat, 'POST', $payload, $cookie, [], $pat, $uagent, true, false, $ip, true, true); 
        $html = $_1['body'];
        goto bbypass;
    }
    
}






