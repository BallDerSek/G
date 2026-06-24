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
        
        $this->userAgent = $ua ?: Config::uagent("mobile");
        $this->api = $api;
        $this->email = $mail;
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        if (!$cookie) {
            $workDir = $this->setupWorkDir('bct', $cleanHost, $mail, 500);
            $this->cookieFile = $workDir . "/" . $this->userdir($mail) . ".tmp";
        } else {
            $this->cookieFile = $cookie;
        }
    }
    
    public function exec($url, $tmr = 5) {
        #var_dump($url);
        if (empty($url)) return false;
        
        #logx('info', "[ bitcotasks.com {$tmr}s ] ", false, true);
        
        $set = microtime(true);
        $cc_get = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
        $cc_getG = scraper::_jP($cc_get, "/window\.location\.href\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
        
        $param = null;
        if (!empty($cc_getG)) {
            
            Net::X($cc_getG, 'POST', ['action' => 'start_view'], $this->cookieFile, [], $cc_getG, $this->userAgent);
            
            $cc_pre = Net::C($cc_getG, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
            #_put('ccpre.html', $cc_pre); die;
            if (str_contains($cc_pre,'Forbidden')) return false;
            
            if ($cc_pre === 99) return 99;
            
            if (!empty($cc_pre) && $cc_pre !== 99) {
                $tm = Scraper::_jP($cc_pre, '/var\s+duration\s*=\s*(\d+)/');
                
                $cap_u = scraper::_xP($cc_pre, "//script[contains(@src,'captcha2/')]/@src")[0] ?? null;
                
                preg_match("/window\.(?:open|location\.replace)\('([^']+)'\)/", $cc_pre, $m);
                $target_url = $m[1] ?? null;
                if (!$target_url && preg_match("/location\.replace\('([^']+)'\)/", $cc_pre, $m)) {
                    $target_url = $m[1] ?? null;
                }
                
                $action = null;
                if (preg_match("/action:\s*'([^']+)'/", $cc_pre, $m) && $m[1] !== 'start_view') $action = $m[1];
                elseif (preg_match("/'action':\s*'([^']+)'/", $cc_pre, $m) && $m[1] !== 'start_view') $action = $m[1];
                elseif (preg_match("/action\s*=\s*['\"]([^'\"]+)['\"]/", $cc_pre, $m) && $m[1] !== 'start_view') $action = $m[1];
                else $action = 'proccessLead';
                
                $param = [
                    'hash' => Scraper::_pP($cc_pre,'hash')[0] ?? null,
                    'token' => Scraper::_pP($cc_pre,'token')[0] ?? null,
                    'sub_id' => Scraper::_pP($cc_pre,'sub_id')[0] ?? null,
                    'api_key' => Scraper::_pP($cc_pre,'api_key')[0] ?? null,
                    'timer' => !empty($tm[1]) ? (int)$tm[1][0] : $tmr,
                    'target_url' => $target_url,
                    'action' => $action
                ];
                #print_r($param); die;
                if (in_array(null, $param, true)) {
                    return false;
                }
            }
        }
        
        if (!empty($param) && $cc_getG) {
            
            $cc_js = Net::C($this->bct_h . $cap_u, 'GET', null, $this->cookieFile, [], $cc_getG, $this->userAgent);
            
            $fjs = null;
            $solution = null;
            if (!empty($cc_js) && $cc_js !== 99) {
                #styler("waiting for bitcotask", fn() => _sle((int)$tmr));
                preg_match('/fetch\("([^"]+captcha[^"]+\.js\?action=captcha)"/', $cc_js, $m);
                $cc_ep = $m[1] ?? $cap_u;
                
                $fjs = $this->_get($cc_js);
                

                $cc_p0 = [
                    't' => round(microtime(true) * 1000),
                    'r' => mt_rand() / mt_getrandmax()
                ];
                
                $cap_get = json_decode(Net::X($this->bct_h . $cc_ep, 'POST', $cc_p0, $this->cookieFile, [], $cc_getG, $this->userAgent, true) ?: '', true);
                
                /*
                _put('cc.html', $cc_pre);
                _put('cc.json', json_encode($cap_get, JSON_PRETTY_PRINT));
                _put('cc.js', $cc_js);
                */
                
                if (!empty($cap_get['options']) && !empty($cap_get['pixel'])) {
                    $solution = $this->_solve($cap_get);
                    if (!$solution) return false;
                }
            }
            
            if ($fjs && $solution) {
                $cc_p1 = $this->_buildPayload($fjs, $param, $solution);
                $cap_tok = json_decode(Net::X($this->bct_h . $cc_p1['url'], 'POST', $cc_p1['payload'], $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', true)[$fjs['cc_ver']] ?? false;
                if ($cap_tok) {
                    $end = microtime(true);
                    if (($wait = (int)$param['timer'] - ($end - $set)) >= 0) styler("waiting for bitcotask", fn() => _sle((int)ceil($wait)));
                    return $this->_set($fjs, $param, $cap_tok, $cc_getG);
                }
            }
        }
        
        return false;
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
    
    private function _set($fjs, $param, $cap_tok, $cc_getG) {
        
        /*
        print_r($param);
        print_r($fjs);
        print_r($cc_getG);
        */
        
        $cc_p2 = [
            'hash' => $param['hash'],
            'sub_id' => $param['sub_id'],
            'key' => $param['api_key'],
            'token' => $param['token'],
            $fjs['cc_Fnm'] => $cap_tok,
            'action' => $param['action']
        ];
        $cc_end = json_decode(Net::X($this->bct_h . "/system/ajax.php", 'POST', $cc_p2, $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', 1);
        
        print(FGd['CYN'].maskEmail($this->email).RSET." ");
        $msg = strip_tags($cc_end['message'] ?? 'ora tau apa isinya');
        if ($cc_end && ($cc_end['status'] ?? 0) == 200) {
            _clr();
            print(FGd['CYN'].maskEmail($this->email).RSET." ");
            logx('info', "[ ".__CLASS__." ] ", false);
            logx('ok', $msg, true, true);
            return true;
        }
        logx('err', $msg);
        
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
        
        $solver = config::getKeys($this->api,'bitcotask','b64');
        if (method_exists($solver, 'bct')) {
            $solution = $solver->bct($captcha,$data);
            if ($solution === 777 && method_exists($this->api, 'bct')) {
                    $solution = $this->api->bct($captcha,$data);
            }
        } else {
            return 010;
        }
        
        if (!$solution) return false;
        
        return [
            'pow' => array_merge(
                SolveUtils::Pow($pow_c, $pow_d),
                ['ch' => $pow_c, 'di' => $pow_d]
                ),
            'cap' => $solution
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
    
    private function _buildPayload($fjs, $param, $solution) {
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
    
    public function cleanup() {
        return @unlink($this->cookieFile);
    }
    
}