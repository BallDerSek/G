<?php
/* legacy 
function owmeCamp($html, $type = 'SL') {
    $dom = Scraper::dom($html);
    
    if ($type == 'SL') {
        $campaigns = $dom->query("//div[contains(@class, 'campaign-block')][@data-slid]");
        $result = [];
        
        foreach ($campaigns as $camp) {
            $slid = $camp->getAttribute('data-slid');
            $limit = $camp->getAttribute('data-limit');
            
            $titleNode = $dom->query(".//div[contains(@class, 'fw-bold')]/text()", $camp)->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';
            
            $rewardNode = $dom->query(".//div[contains(@class, 'text-primary')]/text()", $camp)->item(0);
            $reward = $rewardNode ? trim($rewardNode->textContent) : '';
            
            $currentNode = $dom->query(".//span[starts-with(@id, 'limit_')]/text()", $camp)->item(0);
            $current = $currentNode ? (int)$currentNode->textContent : 0;
            
            if ($slid) {
                $result[] = [
                    'id' => (int)$slid,
                    'title' => $title,
                    'reward' => $reward,
                    'limit' => $current . '/' . $limit
                ];
            }
        }
        
        return $result;
        
    } else {
        $_cmpg = $dom->query("//div[contains(@class, 'campaign-block')][not(@data-slid)]");
        $result = ['ptcs' => [], 'prom' => []];
        
        foreach ($_cmpg as $_cp) {
            $_idh = $_cp->getAttribute('data-hash');
            $_sid = $_cp->getAttribute('data-sid');
            $_key = $_cp->getAttribute('data-key');
            $_idt = $_cp->getAttribute('data-type');
            
            $title = trim(Scraper::_xP($dom, ".//div[contains(@class, 'fw-bold')]/text()", $_cp)[0] ?? '');
            
            $timerNodes = Scraper::_xP($dom, ".//span[contains(text(), 'Visit for')]/text()", $_cp);
            $timer = 0;
            if ($timerNodes && preg_match('/(\d+)/', $timerNodes[0], $m)) {
                $timer = (int)$m[1];
            }
            
            $rewardNodes = Scraper::_xP($dom, ".//div[contains(@class, 'text-primary')]/text()", $_cp);
            $reward = trim($rewardNodes[0] ?? '');
            
            $_direct = Scraper::_xP($dom, ".//a/@href", $_cp)[0] ?? '';
            
            if ($_idh && empty($_direct)) {
                $result['ptcs'][] = [
                    'data' => [
                        'hash' => $_idh,
                        'sid' => $_sid,
                        'key' => $_key,
                        'type' => $_idt,
                    ],
                    'info' => [
                        'title' => $title,
                        'timer' => $timer,
                        'reward' => $reward
                    ]
                ];
            } elseif ($_direct) {
                $result['prom'][] = [
                    'url' => $_direct,
                    'info' => [
                        'title' => $title,
                        'reward' => $reward
                    ]
                ];
            }
        }
        
        $result['ptcs_'] = count($result['ptcs']);
        $result['prom_'] = count($result['prom']);
        
        return $result;
    }
}

function OwmeSL($url, $idd, $tkn, $ck, $ua, $api) {
    $payload = [
        'action' => 'getShortlink',
        'data' => $idd,
        'token' => $tkn
    ];
    
    $go = json_decode(Net::X($url, 'POST', $payload, $ck, [], '', $ua)?: '', 1)['link'] ?? null;
    #var_dump($go);
    if (!$go) return false;
    
    $_0 = Net::X($go, 'GET', null, $ck, [], $url, $ua);
    #var_dump($_0);
    if (!empty($_0) && $_0 !== 99) 
        return json_decode(
            Net::X($go,
                   'POST',
                   array_merge(solve::exec($_0, $url, $api), ['action' => 'redirect']),
                   $ck,
                   [],
                   $_0,
                   $ua
            )?: '',  1)['link'] ?? null;
    
    return false;
    
}
*/

class Owme {
    use WorkDir; 
    
    private string $cookieFile;
    private string $userAgent;
    private string $email;
    private $api; 
    private string $owm_h = 'https://offerwall.me/';

    public function __construct($url, $api, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return;
        
        $this->userAgent = $ua ?: Config::uagent("desktop");
        $this->api = $api;
        $this->email = $mail;
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        if (!$cookie) {
            $workDir = $this->setupWorkDir('owme', $cleanHost, $mail, 200);
            $this->cookieFile = $workDir . "/" . $this->userdir($mail) . ".tmp";
        } else {
            $this->cookieFile = $cookie;
        }
    }
    
    private function camp($html, $type = 'SL') {
        $dom = Scraper::dom($html);
        
        if ($type == 'SL') {
            $campaigns = $dom->query("//div[contains(@class, 'campaign-block')][@data-slid]");
            $result = [];
            
            foreach ($campaigns as $camp) {
                $slid = $camp->getAttribute('data-slid');
                $limit = $camp->getAttribute('data-limit');
                
                $titleNode = $dom->query(".//div[contains(@class, 'fw-bold')]/text()", $camp)->item(0);
                $title = $titleNode ? trim($titleNode->textContent) : '';
                
                $rewardNode = $dom->query(".//div[contains(@class, 'text-primary')]/text()", $camp)->item(0);
                $reward = $rewardNode ? trim($rewardNode->textContent) : '';
                
                $currentNode = $dom->query(".//span[starts-with(@id, 'limit_')]/text()", $camp)->item(0);
                $current = $currentNode ? (int)$currentNode->textContent : 0;
                
                if ($slid) {
                    $result[] = [
                        'id' => (int)$slid,
                        'title' => $title,
                        'reward' => $reward,
                        'limit' => $current . '/' . $limit
                    ];
                }
            }
            
            return $result;
            
        } else {
            $_cmpg = $dom->query("//div[contains(@class, 'campaign-block')][not(@data-slid)]");
            $result = ['ptcs' => [], 'prom' => []];
            
            foreach ($_cmpg as $_cp) {
                $_idh = $_cp->getAttribute('data-hash');
                $_sid = $_cp->getAttribute('data-sid');
                $_key = $_cp->getAttribute('data-key');
                $_idt = $_cp->getAttribute('data-type');
                
                $title = trim(Scraper::_xP($dom, ".//div[contains(@class, 'fw-bold')]/text()", $_cp)[0] ?? '');
                
                $timerNodes = Scraper::_xP($dom, ".//span[contains(text(), 'Visit for')]/text()", $_cp);
                $timer = 0;
                if ($timerNodes && preg_match('/(\d+)/', $timerNodes[0], $m)) {
                    $timer = (int)$m[1];
                }
                
                $rewardNodes = Scraper::_xP($dom, ".//div[contains(@class, 'text-primary')]/text()", $_cp);
                $reward = trim($rewardNodes[0] ?? '');
                
                $_direct = Scraper::_xP($dom, ".//a/@href", $_cp)[0] ?? '';
                
                if ($_idh && empty($_direct)) {
                    $result['ptcs'][] = [
                        'data' => [
                            'hash' => $_idh,
                            'sid' => $_sid,
                            'key' => $_key,
                            'type' => $_idt,
                        ],
                        'info' => [
                            'title' => $title,
                            'timer' => $timer,
                            'reward' => $reward
                        ]
                    ];
                } elseif ($_direct) {
                    $result['prom'][] = [
                        'url' => $_direct,
                        'info' => [
                            'title' => $title,
                            'reward' => $reward
                        ]
                    ];
                }
            }
            
            $result['ptcs_'] = count($result['ptcs']);
            $result['prom_'] = count($result['prom']);
            
            return $result;
        }
    }
    
    private function processShortlink($url, $idd, $tkn, $ck, $ua, $api) {
        $payload = [
            'action' => 'getShortlink',
            'data' => $idd,
            'token' => $tkn
        ];
        
        $go = json_decode(Net::X($url, 'POST', $payload, $ck, [], '', $ua)?: '', 1)['link'] ?? null;
        
        if (!$go) return false;
        
        $_0 = Net::X($go, 'GET', null, $ck, [], $url, $ua);
        
        if (!empty($_0) && $_0 !== 99) 
            return json_decode(
                Net::X($go,
                       'POST',
                       array_merge(solve::exec($_0, $url, $api), ['action' => 'redirect']),
                       $ck,
                       [],
                       $_0,
                       $ua
                )?: '', 1)['link'] ?? null;
        
        return false;
    }
    
    public function wall($url, $menu = false) {
        $_0 = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
        $tkn = Scraper::_pP($_0, 'token')[0] ?? null;
        
        if ($tkn) {
            
            $adsType = ['ptc', 'window'];
            foreach ($adsType as $_type) {
                $adsList = null;
                $po = ['type' => $_type,'token' => $tkn,'action' => 'switch_cat'];
                $_1 = json_decode(Net::X($url, 'POST', $po, $this->cookieFile, [], '', $this->userAgent)?: '', 1)['content'] ?? null;
                
                if (!empty($_1)) $adsList = $this->camp($_1, 'AD');  // ← pakai $this->camp()
                
                if ($adsList && !$menu) {
                    if (!empty($adsList['ptcs']) && $adsList['ptcs_'] !== 0) {
                        
                        foreach ($adsList['ptcs'] as $_ptc) {
                            $info = $_ptc['info'];
                            $data = $_ptc['data'];
                            $pa = array_merge($data, ['token' => $tkn, 'action' => 'init_transaction']);
                            $_2 = json_decode(Net::X($url, 'POST', $pa, $this->cookieFile, [], '', $this->userAgent)?: '', 1);
                            
                            if (isset($_2['status']) && $_2['status'] === 200) {
                                $this->exec($_2['offer'], $info['timer']); 
                            }
                        }
                    }
                }
                
            }
            
            return false;
            $_1 = null;
            $shoList = null;
            $pa = ['type' => 'shortlinks','token' => $tkn,'action' => 'switch_cat'];
            $_1 = json_decode(Net::X($url, 'POST', $pa, $this->cookieFile, [], '', $this->userAgent)?: '', 1)['content'] ?? null;
            if (!empty($_1)) $shoList = $this->camp($_1, 'SL');  // ← pakai $this->camp()
            
            if ($shoList && !$menu) {
                
                foreach ($shoList as $sl) {
                    $idSL = $sl['id'];
                    
                    $get = $this->processShortlink($url, $idSL, $tkn, $this->cookieFile, $this->userAgent, $this->api);  // ← pakai $this->processShortlink()
                    
                    if (stripos($get, 'coinclix') !== false) continue;
                    
                    if (!$get) continue;
                    
                    $start = microtime(true);
                    $bakk = links($this->api, $get, true);
                    
                    if ($bakk) {
                        $wait = 100 - (int)(microtime(true) - $start);
                        if ($wait > 0) styler("waiting {$wait}.s for SL", fn() => _sle((int)ceil($wait)));
                        
                        $ver = Net::C($bakk, 'GET', null, $this->cookieFile, [], $get, $this->userAgent);
                        if (!empty($ver) && $ver !== 99) {
                            
                            _clr(); 
                            print(FGd['CYN'].maskEmail($this->email).RSET." ");
                            
                            $_als = scraper::_xP($ver, "//div[contains(@class, 'alert-success')]");
                            if (!empty($_als[0])) {
                                logg(false, $_als[0]);
                                continue;
                            }
                            $_ald = scraper::_xP($ver, "//div[contains(@class, 'alert-danger')]")[0] ?? 'gatau error apa';
                            logx('err', $_als[0]);
                        }
                    }
                }
                
                return true;
            }
            
        }
        
        return false;
    }
    
    public function exec($url, $timer) {
        $attempt = 0;
        
        #if (!AUTH_KEY || $url) return false;
        
        while ($attempt < 3) {
            $attempt++;

            $adData = $this->_get($url);
            
            if (!$adData) {
                _sle(3); 
                continue;
            }
            
            #logx('info', "[ offerwall.me {$timer}s ] ", false, true);
            
            styler("waiting for owme", fn() => _sle((int)$adData['params']['dur']));
            
            $capUrl = $adData['targetHost'] . '/system/libraries/captcha/request.php';
            
            $capIcons = json_decode(Net::X($capUrl, 'POST', ['cID' => '0', 'rT' => '1', 'tM' => 'light'], $this->cookieFile, [], $adData['ref'], $this->userAgent) ?: '', 1);
            if (!$capIcons) {
                _sle(3); 
                continue;
            }

            foreach ($capIcons as $iconId) {
                if ($this->_solve($capUrl, $iconId, $adData['ref'])) {
                    
                    $ajaxUrl = $adData['targetHost'] . '/system/ajax.php';
                    if ($this->_set($ajaxUrl, $adData['params'], $iconId, $adData['ref'])) {
                        return true; 
                    } else {
                        _sle(3);
                        continue 2;
                    }
                }
            }
            
            logx('err', "error gak jelas");
            _sle(3);
        }

        return false;
    }

    private function _get($url) {
        $view = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent, true);
        if (empty($view) || !isset($view['url'])) return null;

        $ref = $view['url'];
        $body = $view['body'];
        $targetHost = 'https://' . parse_url($ref)['host'];
        
        if (stripos($body, 'went wrong')) return null;
        
        $params = [
            'tkn' => Scraper::_jP($body, "/var\s+token\s*=\s*'([^']+)';/")[1][0] ?? null,
            'ids' => Scraper::_jP($body, "/var\s+sub_id\s*=\s*'([^']+)';/")[1][0] ?? null,
            'idh' => Scraper::_jP($body, "/var\s+hash\s*=\s*'([^']+)';/")[1][0] ?? null,
            'key' => Scraper::_jP($body, "/var\s+key\s*=\s*'([^']+)';/")[1][0] ?? null,
            'dur' => Scraper::_jP($body, "/var\s+duration\s*=\s*(\d+);/")[1][0] ?? null,
            'act' => Scraper::_jP($body, "/'action'\s*:\s*'([^']+)'/")[1][0] ?? 'proccessLead',
        ];

        if (in_array(null, $params, true)) {
            #logx('err', "ada perubahan kayaknya");
            #_put('owme_err.html', $body);
            return null;
        }

        return [
            'ref' => $ref,
            'targetHost' => $targetHost,
            'params' => $params
        ];
    }
    
    private function _set($url, $par, $iconId, $ref) {
        $payload = [
            'hash' => $par['idh'], 
            'sub_id' => $par['ids'], 
            'key' => $par['key'],
            'token' => $par['tkn'], 
            'captcha-idhf' => '0', 
            'captcha-hf' => $iconId,
            'action' => $par['act']
        ];

        $res = json_decode(Net::X($url, 'POST', $payload, $this->cookieFile, [], $ref, $this->userAgent) ?: '', 1);
        
        if (empty($res)) return false;

        if (!empty($res) && is_array($res)) {
            $msg = isset($res['message']) ? trim(strip_tags($res['message'])) : 'ora tau apa isinya';
            
            if (isset($res['status']) && $res['status'] == 200) {
                _clr(); 
                print(FGd['CYN'].maskEmail($this->email).RSET." ");
                logx('info', "[ ".__CLASS__." ] ", false);
                logx('ok', $msg, true, true);
                
                return true;
            } else {
                logx('err', $msg);
            }
        }

        return false;
    }

    private function _solve($capUrl, $iconId, $ref) {
        $payload = ['cID' => '0', 'rT' => '2', 'pC' => $iconId];
        $check = Net::X($capUrl, 'POST',$payload , $this->cookieFile, [], $ref, $this->userAgent, d: true);
        return (!empty($check) && isset($check['http_code']) && $check['http_code'] === 200);
    }

    public function cleanup() {
        return @unlink($this->cookieFile);
    }

}






    


function bct($api, $url, $tmr = 5, $host = '') {
    $url = "https://bitcotasks.com/view/1d991f2f34c7ad8589194c910189361c:34392f6d7a5556536c726f7358614a726959525a745065635044453347305a6875314e4477443868316a6945314356347a38743257656f4d6b61387647635062454f4a72746868586a704b614a586c3361572f51386a6461622f66302b7564325776717a376a7943794944636c336c327a746c4d46443634557754446e665470784b6a5847553749416277676a7545586d64656933513d3d";
    
    $bct_h = 'https://bitcotasks.com';
    
    $ck = config::cookie($host);
    $ua = config::uagent('mobile');
    
    
    
    $cc_get = Net::C($url, 'GET', null, $ck, [], $host, $ua);
    $cc_getG = scraper::_jP($cc_get, "/window\.location\.href\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
    
    $param = null;
    $cap_u = null;
    if (!empty($cc_getG)) {
        $cc_pre = Net::C($cc_getG, 'GET', null, $ck, [], '', $ua);
        _put('0.html', $cc_pre);
        
        if ($cc_pre === 99) return 99;
        
        if (strlen($cc_pre) < 1000) return false;
        
        if (!empty($cc_pre) && $cc_pre !== 99) {
            $cap_u = scraper::_xP($cc_pre, "//script[contains(@src,'captcha2/')]/@src")[0] ?? null;
            
            $param = [
                'act' => scraper::_jP($cc_pre, '/\.post\(window\.location,\s*\{action:\s*\'([^\']+)\'\}/')[1][0] ?? 'redirect',
                'tmr' => scraper::_jP($cc_pre, '/var time([a-f0-9]+) = (\d+)/')[2][0] ?? 10
                ];
                
        }
        
    }
    
    $fjs = null;
    $solution = null;
    if (!empty($param) && $cap_u) {
        _sle($param['tmr']);
        $cc_js = Net::C($bct_h . $cap_u, 'GET', null, $ck, [], $cc_getG, $ua);
        
        if (!empty($cc_js) && $cc_js !== 99) {
            preg_match('/fetch\("([^"]+captcha[^"]+\.js\?action=captcha)"/', $cc_js, $m);
            $cc_ep = $m[1] ?? $cap_u;
            
            $fjs = parsecc($cc_js);
            
            $cc_p0 = [
                't' => round(microtime(true) * 1000),
                'r' => mt_rand() / mt_getrandmax()
            ];
            
            $cap_get = json_decode(Net::X($bct_h . $cc_ep, 'POST', $cc_p0, $ck, [], $cc_getG, $ua, true) ?: '', true);
            
            if (!empty($cap_get['options']) && !empty($cap_get['pixel'])) {
                $solution = _solveBCT($api,$cap_get);
                if ($solution === 010) {
                    return false;
                }
            }
            
        }
    }
    
    if ($fjs && $solution) {
        print_r($param);
        print_r($fjs);
        print_r($solution);
        
        
        $cc_p1 = _bctPayload($fjs, $param, $solution);
        $cap_tok = json_decode(Net::X($bct_h . $cc_p1['url'], 'POST', $cc_p1['payload'], $ck, [], $cc_getG, $ua) ?: '', true)[$fjs['cc_ver']] ?? false;
        var_dump($cap_tok);
        
        var_dump($cc_getG);
$headers = [
    'Accept: application/json, text/javascript, */*; q=0.01',
    'X-Requested-With: XMLHttpRequest'
];

$cc_end = Net::C($cc_getG, 'POST', 'action=redirect', $ck, $headers, $cc_getG, $ua);
        var_dump($cc_end);
        
        
        
    }
    
    
    
    
die;
}

function parsecc($js) {
    $result = [];
    
    $m = scraper::_jP($js, '/var payload = "([^"]+)"/')[1] ?? null;
    if (!empty($m[0])) {
        parse_str($m[0], $parsed);
        foreach ($parsed as $key => $value) {
            if (!in_array($key, ['_et', '_mv', '_cf', '_pw', '_ch', '_bh'], true)) {
                $result['cc_ran'][$key] = $value;
            }
        }
    }
    
    preg_match('/<input type="hidden" id="([^"]+)" name="([^"]+)">/', $js, $m);
    $result['cc_Fid'] = $m[1] ?? null;
    $result['cc_Fnm'] = $m[2] ?? null;
    
    preg_match('/xhr\.open\("POST",\s*"([^"]+captcha2[^"]+)"/', $js, $m);
    $result['cc_end'] = $m[1] ?? null;
    
    if ($result['cc_Fid']) {
        preg_match('/document\.getElementById\("' . preg_quote($result['cc_Fid'], '/') . '"\)\.value\s*=\s*response\.([a-zA-Z0-9]+)/', $js, $m);
        $result['cc_ver'] = $m[1] ?? null;
    }
    
    if (empty($result['cc_ran']) || empty($result['cc_Fid']) || empty($result['cc_Fnm']) || empty($result['cc_end']) || empty($result['cc_ver'])) {
        return false;
    }
    
    return $result;
}

function _rndr($b64, $w, $h) {
    
    $raw = base64_decode($b64);
    if (strlen($raw) < $w * $h * 4) return false;
    
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    
    $i = 0;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $r = ord($raw[$i++] ?? "\x00");
            $g = ord($raw[$i++] ?? "\x00");
            $b = ord($raw[$i++] ?? "\x00");
            $a = ord($raw[$i++] ?? "\x00");
            
            $alpha = 127 - (int)($a / 255 * 127);
            $color = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
            imagesetpixel($img, $x, $y, $color);
        }
    }
    
    ob_start();
    imagepng($img);
    $imageData = ob_get_clean();
    @imagedestroy($img);
    
    return base64_encode($imageData);
}

function _solveBCT($api, $data) {
    
    $pow_d = $data['difficulty'] ?? 4;
    $pow_c = $data['challenge'] ?? null;
    
    $main = _rndr($data['pixel'],200,100);
    $captcha['main'] = $main;
    #_put('main.png', base64_decode($main));
    foreach ($data['options'] as $i => $opt) {
        $captcha['opsi'][$i] = _rndr($opt['pixels'],$opt['width'],$opt['height']);
    }
    
    if (method_exists($api, 'bct')) {
        $solved = $api->bct($captcha);
    } else $solved = '4';
    
    $pow = SolveUtils::Pow($pow_c, $pow_d);
    
    return [
        'pow' => array_merge($pow, ['ch' => $pow_c, 'di' => $pow_d]),
        'cap' => $solved
    ];
}

function _bctPayload($fjs, $param, $solution) {
    $fieldKeys = array_keys($fjs['cc_ran']);
    $elapsed = rand(5000, 6000);
    
    $ch = $solution['pow']['ch'];
    $nonce = $solution['pow']['nonce'];
    
    $cf = rand(5000, 5500);
    
    $payload = [
        $fieldKeys[0] => $fjs['cc_ran'][$fieldKeys[0]],
        $fieldKeys[1] => json_encode([(int)$solution['cap']]),
        '_et' => $elapsed,
        '_mv' => rand(2, 5),
        '_cf' => $cf,
        '_pw' => json_encode(['nonce' => $nonce, 'hash' => $solution['pow']['hash'] ?? '']),
        '_ch' => $ch,
        '_bh' => hash('sha256', $elapsed . ':' . $nonce . ':' . $ch)
    ];
    
    $payload = array_filter($payload, function($v) {
        return $v !== '' && $v !== null;
    });
    
    return [
        'url' => $fjs['cc_end'],
        'payload' => $payload,
    ];
}

function savedebugbitcotask($num, $param) {
    $dir = _lib('bitcotask', $num);
    _put($dir."/$num.json", json_encode($param, JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES));
    
    $main = _rndr($param['pixel'],200,100);
    _put($dir.'/main.png', base64_decode($main));
    
    foreach ($param['options'] as $i => $opt) {
        if ($opsi = _rndr($opt['pixels'],$opt['width'],$opt['height'])) {
            _put($dir."/$i.png", base64_decode($opsi));
        }
        
    }
    logx('ok', "saved data $num");
}