<?php

class Bctt {
    use WorkDir;
    
    private string $cookieFile;
    private string $userAgent;
    private string $email;
    private $api;
    private string $bct_h = 'https://bitcotasks.com';
    
    public function __construct($url, $api, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return;
        
        $this->userAgent = $ua ?: Config::uagent("desktop");
        $this->api = $api;
        $this->email = $mail;
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        if (!$cookie) {
            $this->workDir = $this->setupWorkDir('bct', $cleanHost, $mail, 400);
            $this->cookieFile = $this->workDir . "/" . $this->userdir($mail) . ".tmp";
        } else {
            $this->cookieFile = $cookie;
            $this->workDir = '';
        }
    }
    
    private function camp($json, $type = 'SL') {
        if (($json === null) || ($json === false)) return null;
        
        if ($type == 'SL') {
            var_dump($json);
            die;
        } else {
            $result = ['ptcs' => [], 'prom' => []];
            foreach ($json as $data) {
                $result['ptcs'][] = [
                    'data' => [
                        'hash' => $data['hash'],
                        'sid' => $data['sid'],
                        'key' => $data['key'],
                        'type' => $data['ad_type'],
                    ],
                    'info' => [
                        'title' => $data['title'],
                        'timer' => $data['duration'],
                        'reward' => $data['reward']
                    ]
                ];
            }
            
            $result['ptcs_'] = count($result['ptcs']);
            $result['prom_'] = count($result['prom']);
            
            return $result;
        }
    }
    
    public function wall($url, $menu = false, $setF = null, $until = null) {
        try {
            if (empty($url)) return false;
            
            $cc_get = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
            
            $cc_getG = scraper::_jP($cc_get, "/window\.location\.href\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
            if (!empty($cc_getG)) {
                $cc_pre = Net::C($cc_getG, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
            } else {
                if (str_contains($cc_get, '/captcha2')) {
                    $cc_pre = $cc_get;
                    $cc_getG = $url;
                }
            }
            
            $cc_js = null;
            if (!empty($cc_pre) && $cc_pre !== 99) {
                $cap_u = scraper::_xP($cc_pre, "//script[contains(@src,'captcha2/')]/@src")[0] ?? null;
                
                if (!empty($cap_u)) {
                    $cc_js = Net::C($this->bct_h . $cap_u, 'GET', null, $this->cookieFile, [], $cc_getG, $this->userAgent);
                    if ($cc_js === 99) return 99;
                }
            }
            
            $fjs = null;
            $solution = null;
            if (!empty($cc_js) && $cc_js !== 99) {
                preg_match('/fetch\("([^"]+captcha[^"]+\.js\?action=captcha)"/', $cc_js, $m);
                $cc_ep = $m[1] ?? $cap_u;
                $fjs = $this->_get($cc_js);
                
                $cc_p0 = [
                    't' => round(microtime(true) * 1000),
                    'r' => mt_rand() / mt_getrandmax()
                ];
                
                $cap_get = json_decode(Net::X($this->bct_h . $cc_ep, 'POST', $cc_p0, $this->cookieFile, [], $cc_getG, $this->userAgent, true) ?: '', true);
                
                if (!empty($cap_get['options']) && !empty($cap_get['pixel'])) $solution = $this->_solve($cap_get);
            }
            
            $cc_wall = null;
            if ($fjs && is_array($solution)) {
                $cc_p1 = $this->_buildPayload($fjs, null, $solution);
                $cap_tok = json_decode(Net::X($this->bct_h . $cc_p1['url'], 'POST', $cc_p1['payload'], $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', true)[$fjs['cc_ver']] ?? false;
                if ($cap_tok) {
                    $cc_p2 = [
                        $fjs['cc_Fnm'] => $cap_tok,
                        'action' => 'validate'
                    ];
                    $cc_wall = json_decode(Net::X($cc_getG, 'POST', $cc_p2, $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', 1)['redirect'] ?? null;
                }
            }
            
            $content = null;
            if (!empty($cc_wall)) $content = Net::C($cc_wall, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
            
            $_0 = $content ?? $cc_get ?? null;
            $_0u = $cc_wall ?? $url;
            
            if (!empty($_0)) {
                $param = [
                    'tkn' => Scraper::_pP($_0, 'token')[0] ?? null,
                    'sid' => Scraper::_pP($_0, 'subId')[0] ?? null,
                    'key' => Scraper::_pP($_0, 'apiKey')[0] ?? null
                ];
                
                if (in_array(null, $param, true)) return false;
                
                $adsList = null;
                $tkn = $param['tkn'];
                
                $po = [
                    'type' => 'ptc',
                    'token' => $tkn,
                    'action' => 'switch_cat'
                ];
                
                $_1 = json_decode(Net::X($url, 'POST', $po, $this->cookieFile, [], $_0u, $this->userAgent)?: '', 1)['items'] ?? null;
                
                if (!empty($_1)) $adsList = $this->camp($_1, 'AD');
                elseif (empty($_1)) return 'habis';
                
                if ($adsList && !$menu) {
                    if (!empty($adsList['ptcs']) && $adsList['ptcs_'] !== 0) {
                        foreach ($adsList['ptcs'] as $_ptc) {
                            $info = $_ptc['info'];
                            $data = $_ptc['data'];
                            
                            if ($setF > 0) {
                                $endF = microtime(true);
                                $balik = $endF - $setF;
                                if ($balik >= $until) return 'claim';
                            }
                            
                            $pa = array_merge($data, ['token' => $tkn, 'action' => 'init_transaction']);
                            $_2 = json_decode(Net::X($url, 'POST', $pa, $this->cookieFile, [], $_0u, $this->userAgent)?: '', 1);
                            
                            if (isset($_2['status']) && $_2['status'] === 200) {
                                $_2E = $this->exec($_2['offer'], $info['timer'], true, $data, false);
                                if ($_2E === 'forbidden') return 'forbidden';
                            }
                        }
                    }
                    return true;
                }
            }
            
            return false;
            
        } finally {
            $this->cleanup();
        }
    }
    
    public function exec($url, $tmr = 5, $wall = false, $data = null, $cleanup = true) {
        try {
            return $this->view($url, $tmr, $wall, $data);
        } catch (Throwable $e) {
            return false;
        } finally {
            if ($cleanup) $this->cleanup();
        }
    }
    
    private function view($url, $tmr = 5, $wall = false, $data = null) {
        if (empty($url)) throw new Exception('Empty URL provided');
        
        $cc_get = Net::C($url, 'GET', null, $this->cookieFile, [], $url, $this->userAgent);
        
        $cc_getG = scraper::_jP($cc_get, "/window\.location\.href\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
        $param = null;
        $set = microtime(true);
        
        if (!empty($cc_getG) && !str_contains($cc_get, '/captcha')) {
            $cc_pre = Net::C($cc_getG, 'GET', null, $this->cookieFile, [], $url, $this->userAgent);
        } else {
            if (str_contains($cc_get, '/captcha2')) {
                $cc_pre = $cc_get;
                $cc_getG = $url;
            }
        }
        
        if (!empty($cc_pre) && $cc_pre !== 99) {
            Net::X($cc_getG, 'POST', ['action' => 'start_view'], $this->cookieFile, [], $cc_getG, $this->userAgent);
            
            if (str_contains($cc_pre, 'Forbidden')) return 'forbidden';
            
            $tm = Scraper::_jP($cc_pre, '/var\s+duration\s*=\s*(\d+)/');
            $cap_u = scraper::_xP($cc_pre, "//script[contains(@src,'captcha2/')]/@src")[0] ?? null;
            
            $target_url = null;
            if (preg_match("/(?:window\.open|window\.location\.replace)\s*\(\s*['\"]([^'\"]+)['\"]/s", $cc_pre, $m)) {
                $target_url = $m[1];
                if (strpos($target_url, 'google.com/url') !== false) {
                    parse_str(parse_url($target_url, PHP_URL_QUERY), $params);
                    $target_url = $params['url'] ?? $target_url;
                }
            }
            
            $action = 'proccessLead';
            if (preg_match("/action:\s*'([^']+)'/", $cc_pre, $m) && $m[1] !== 'start_view') {
                $action = $m[1];
            } elseif (preg_match("/'action':\s*'([^']+)'/", $cc_pre, $m) && $m[1] !== 'start_view') {
                $action = $m[1];
            }
            
            $hash = Scraper::_pP($cc_pre, 'hash')[0] ?? $data['hash'] ?? null;
            $token = Scraper::_pP($cc_pre, 'token')[0] ?? $data['token'] ?? null;
            $sub_id = Scraper::_pP($cc_pre, 'sub_id')[0] ?? $data['sid'] ?? null;
            $api_key = Scraper::_pP($cc_pre, 'api_key')[0] ?? $data['key'] ?? null;
            
            if (empty($hash)) {
                preg_match('/hash\s*=\s*[\'"]([^\'"]+)[\'"]/', $cc_pre, $m);
                $hash = $m[1] ?? null;
            }
            if (empty($token)) {
                preg_match('/token\s*=\s*[\'"]([^\'"]+)[\'"]/', $cc_pre, $m);
                $token = $m[1] ?? null;
            }
            if (empty($sub_id)) {
                preg_match('/sub_id\s*=\s*[\'"]([^\'"]+)[\'"]/', $cc_pre, $m);
                $sub_id = $m[1] ?? null;
            }
            if (empty($api_key)) {
                preg_match('/api_key\s*=\s*[\'"]([^\'"]+)[\'"]/', $cc_pre, $m);
                $api_key = $m[1] ?? null;
            }
            
            $param = [
                'hash' => $hash,
                'token' => $token,
                'sub_id' => $sub_id,
                'api_key' => $api_key,
                'timer' => !empty($tm[1]) ? (int)$tm[1][0] : $tmr,
                'target_url' => $target_url,
                'action' => $action
            ];
            
            if (in_array(null, $param, true)) throw new Exception('Missing required parameters');
        }

        if (!empty($param) && $cc_getG) {
            $cc_js = Net::C($this->bct_h . $cap_u, 'GET', null, $this->cookieFile, [], $cc_getG, $this->userAgent);
            
            $fjs = null;
            $solution = null;
            if (!empty($cc_js) && $cc_js !== 99) {
                preg_match('/fetch\("([^"]+captcha[^"]+\.js\?action=captcha)"/', $cc_js, $m);
                $cc_ep = $m[1] ?? $cap_u;
                
                $fjs = $this->_get($cc_js);
                
                $cc_p0 = [
                    't' => round(microtime(true) * 1000),
                    'r' => mt_rand() / mt_getrandmax()
                ];
                
                $cap_get = json_decode(Net::X($this->bct_h . $cc_ep, 'POST', $cc_p0, $this->cookieFile, [], $cc_getG, $this->userAgent, true) ?: '', true);
                
                if (!empty($cap_get['options']) && !empty($cap_get['pixel'])) {
                    $solution = $this->_solve($cap_get);
                }
            }
            
            if ($fjs && is_array($solution)) {
                $cc_p1 = $this->_buildPayload($fjs, $param, $solution);
                $cap_tok = json_decode(Net::X($this->bct_h . $cc_p1['url'], 'POST', $cc_p1['payload'], $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', true)[$fjs['cc_ver']] ?? false;
                
                if ($cap_tok) {
                    $end = microtime(true);
                    if (($wait = (int)$param['timer'] - ($end - $set)) >= 0) {
                        styler("waiting for bitcotask", fn() => _sle((int)ceil($wait)));
                    }
                    
                    $cc_p2 = [
                        'hash' => $param['hash'],
                        'sub_id' => $param['sub_id'],
                        'key' => $param['api_key'],
                        'token' => $param['token'],
                        $fjs['cc_Fnm'] => $cap_tok,
                        'action' => $param['action']
                    ];
                    
                    return $this->_set($cc_p2, $cc_getG, $wall);
                }
            }
        }
        
        throw new Exception('View process failed');
    }
    
    private function _get($js) {
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
    
    private function _set($cc_p2, $cc_getG, $wall) {
        $cc_end = json_decode(Net::X($this->bct_h . "/system/ajax.php", 'POST', $cc_p2, $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', 1);
        
        Logger::M($this->email);
        $msg = strip_tags($cc_end['message'] ?? 'ora tau apa isinya');
        
        if ($cc_end && ($cc_end['status'] ?? 0) == 200) {
            Logger::X('info', "[ ".__CLASS__." ] ", false);
            Logger::X('ok', $msg, true, true);
            return true;
        }
        
        Logger::X('err', $msg);
        return false;
    }
    
    private function _solve($data, $num = null) {
        $pow_d = $data['difficulty'] ?? 4;
        $pow_c = $data['challenge'] ?? null;
        
        $main = $this->_parseImages($data['pixel'], 200, 100);
        $captcha['main'] = $main;
        
        foreach ($data['options'] as $i => $opt) {
            $captcha['opsi'][$i] = $this->_parseImages($opt['pixels'], $opt['width'], $opt['height']);
        }
        
        $solution = null;
        
        $solver = config::getKeys($this->api, 'bitcotask', 'b64');
        if (method_exists($solver, 'bct')) {
            $solution = $solver->bct($captcha, $data);
            
            if (isset($solution['fail'])) {
                if (!method_exists($this->api, 'bct')) return false;
                $solution = $this->api->bct($captcha, $data);
            }
        }
        
        if (!is_array($solution) || isset($solution['fail'])) return false;
        
        return [
            'pow' => array_merge(
                SolveUtils::Pow($pow_c, $pow_d),
                ['ch' => $pow_c, 'di' => $pow_d]
            ),
            'cap' => $solution['idx']
        ];
    }
    
    private function _parseImages($b64, $w, $h) {
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
    
    private function _buildPayload($fjs = null, $param = null, $solution = null) {
        $fieldKeys = array_keys($fjs['cc_ran']);
        $elapsed = rand(3000, 6000);
        
        $ch = $solution['pow']['ch'];
        $nonce = $solution['pow']['nonce'];
        $cf = 1894;
        
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
    
    public function cleanup($flag = false) {
        if (empty($this->workDir)) return $flag;
        $this->rmdir($this->workDir);
        return $flag;
    }
    
}
