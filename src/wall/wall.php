<?php

class Owme {
    use WorkDir; 
    
    private string $cookieFile;
    private string $userAgent;
    private string $email;

    public function __construct($url, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return null;
        $this->userAgent = $ua ?: Config::uagent("desktop");
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        if (!$cookie) {
            $workDir = $this->setupWorkDir('owme', $cleanHost, $mail);
            $this->cookieFile = $workDir . "/" . $this->userdir($mail) . ".tmp";
        } else {
            $this->cookieFile = $cookie;
        }
        $this->email = $mail;

    }
    
    public function exec($url, $timer) {
        $attempt = 0;
        
        #if (!AUTH_KEY || $url) return false;
        
        while ($attempt < 3) {
            $attempt++;

            $adData = $this->_get($url);
            
            if ($adData === 000) return false;
            if (!$adData) {
                _sle(3); 
                continue;
            }
            
            #logx('info', "[ offerwall.me {$timer}s ] ", false, true);
            
            styler("waiting for owme", fn() => _sle((int)$adData['params']['dur']));
            
            $capUrl = $adData['targetHost'] . '/system/libraries/captcha/request.php';
            $capIcons = $this->_getCap($capUrl, $adData['ref']);
            if (!$capIcons) {
                _sle(3); 
                continue;
            }

            foreach ($capIcons as $iconId) {
                if ($this->_verCap($capUrl, $iconId, $adData['ref'])) {
                    
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
        
        if (stripos($body, 'went wrong')) return 000;
        
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

    private function _getCap($capUrl, $ref) {
        $capReq = json_decode(Net::X($capUrl, 'POST', ['cID' => '0', 'rT' => '1', 'tM' => 'light'], $this->cookieFile, [], $ref, $this->userAgent) ?: '', 1);

        if (!$capReq || !is_array($capReq)) return null;

        return $capReq;
    }

    private function _verCap($capUrl, $iconId, $ref) {
        $payload = ['cID' => '0', 'rT' => '2', 'pC' => $iconId];
        $check = Net::X($capUrl, 'POST',$payload , $this->cookieFile, [], $ref, $this->userAgent, d: true);
        return (!empty($check) && isset($check['http_code']) && $check['http_code'] === 200);
    }

    public function cleanup() {
        return @unlink($this->cookieFile);
    }

}

class Zera {
    use WorkDir;
    
    private string $cookieFile;
    private string $userAgent;
    private string $email;
    private $api; 
    private string $zer_h = 'https://zerads.com/';

    public function __construct($url, $api, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return;
        
        $this->userAgent = $ua ?: Config::uagent("desktop");
        $this->api = $api;
        $this->email = $mail;
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        if (!$cookie) {
            $workDir = $this->setupWorkDir('zer', $cleanHost, $mail, 300);
            $this->cookieFile = $workDir . "/" . $this->userdir($mail) . ".tmp";
        } else {
            $this->cookieFile = $cookie;
        }
    }

    public function exec($zer_u, $ip) {

        $retZer = 0;
        $current_ref = '';
        $claimed = 0;
        start:
        $zer = Net::C($zer_u, 'GET', null, $this->cookieFile, [], "", $this->userAgent);
        
        if (empty($zer) || $zer === 99) return false;
        
        #_put('zer.html', $zer);
        $current_ref = $zer_u;

        while (true) {
            if ($retZer >= 3) {
                $this->cleanup();
                break;
            }
            if ($claimed >= 10) break;
            
            $zer_s = null; 

            if (stripos($zer, 'solve captcha')) {
                $zerC_p = $this->_parseImages($zer, $current_ref, 'scid=');
                
                if (!is_array($zerC_p)) {
                    $retZer++;
                    continue;
                }
                
                if ($sol = $this->_solve($zerC_p)) {
                    $target_url = $this->zer_h . $sol;
                    #logx('info', '0: '.$target_url);
                    $zer_s = Net::X($target_url, 'GET', null, $this->cookieFile, [], $current_ref, $this->userAgent);
                    #_put('zerS.html', $zer_s);
                    $current_ref = $target_url;
                }
                
            }
            
            $zer_v = $zer_s ?? $zer ?? '';
            
            if (stripos($zer_v, 'Viewing PTC Ad')) {
                #_put('zerV.html', $zer_v);
                
                $ti = $this->_parseTimer($zer_v);
                #styler("waiting for zerads", fn() => _sle((int)ceil($ti)));
                
                /*
                logx('info', "[ zerads.com {$ti}s ] ", false, true);
                */
                
                $sol = null;
                $zerC_p = $this->_parseImages($zer_v, $current_ref, 'id=');
                if ($zerC_p === 'main_reload') {
                    $retZer++;
                    goto start;
                }
/*
                if (is_string($zerC_p)) {
                    $sol = $zerC_p; 
                } else {
                    $sol = $this->_solve($zerC_p);
                }
*/
                $sol = $this->_solve($zerC_p);
                
                $set = microtime(true);
                if ($sol) {
                    $end = microtime(true);
                    
                    if (($wait = (int)$ti - ($end - $set)) >= 0) {
                        styler("waiting for zera", fn() => _sle((int)ceil($wait)));
                    }
                    
                    
                    $target_url = $this->zer_h . $sol;
                    #logx('info', '1: '.$target_url);
                    $zer_d = Net::X($target_url, 'GET', null, $this->cookieFile, [], $current_ref, $this->userAgent);
                    
                    #_put('zerD.html', $zer_d);

                    if (!empty($zer_d) && $zer_d !== 99) {
                        $zer_r = Scraper::_xP($zer_d, "//div[@id='rwmsgbox']") ?? [];
                        if (!empty($zer_r[0])) {
                            _clr();
                            print(FGd['CYN'] . maskEmail($this->email) . RSET . " ");
                            logx('info', "[ ".__CLASS__." ] ", false);
                            $message = trim(preg_replace('/\s+/', ' ', strip_tags($zer_r[0])));
                            logx('ok', $message, true, true);
                            
                            $claimed++;
                            
                        }
                    }
                    
                    $current_ref = $target_url;
                    $zer = $zer_d; 
                } else {
                    $retZer++;
                    continue;
                }
            } else {
                #_put('zerV.html', $zer_v);
                $retZer++;
            }
        }
        
        return true;
    }

    private function _parseImages($html, $referer, $typePattern) {
        $he = ['Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8'];
        $he = [];
        $xp = Scraper::dom($html);
        $package = ['main' => null, 'rels' => []];

        $zerC_m = Scraper::_xP($xp, "(//td[contains(., 'Click')]/following-sibling::td/img/@src | //font[contains(., 'Click')]/../following-sibling::td/img/@src)[last()]") ?? '';
        #var_dump($zerC_m);
        
        $pattern = ($typePattern === 'scid=') ? 'scid=' : 'ptc.php?id=';
        $zerC_o = Scraper::_xP($xp, "//a[contains(@href, '{$pattern}')]/@href") ?: [];
        $zerC_i = Scraper::_xP($xp, "//a[contains(@href, '{$pattern}')]/img/@src") ?: [];

        if ($zerC_m && $zerC_o && $zerC_i) {
            for ($_r = 0; $_r < 2; $_r++) {
                $M_z = Net::C($this->zer_h . ltrim($zerC_m[0], '/'), 'GET', null, $this->cookieFile, $he, $referer, $this->userAgent);
                
                if (!empty($M_z) && $M_z !== 99) {
                    if ($M_z === "invalid access") {
                        #var_dump($M_z);
                        return 'main_reload';
                    }
                    $package['main'] = base64_encode($M_z);
                    break;
                }
            }
            
            foreach ($zerC_i as $i => $u) {
                $url_key = $zerC_o[$i] ?? '';
                #$I_z = Net::X($this->zer_h . ltrim($u, '/'), 'GET', null, $this->cookieFile, [], $referer, $this->userAgent);
                $I_z = Net::S($this->zer_h.ltrim($u,'/'),'GET', null, $he);
                if (!empty($I_z) && $I_z !== 99) {
                    $package['rels'][$url_key] = base64_encode($I_z);
                }
            }
        }
        return $package;
    }
    
    private function _parseImagess($html, $referer, $typePattern) {
        $he = [];
        $xp = Scraper::dom($html);
        $package = ['main' => null, 'rels' => []];

        $zerC_m = Scraper::_xP($xp, "(//td[contains(., 'Click')]/following-sibling::td/img/@src | //font[contains(., 'Click')]/../following-sibling::td/img/@src)[last()]") ?? '';
        #var_dump($zerC_m);
        
        $pattern = ($typePattern === 'scid=') ? 'scid=' : 'ptc.php?id=';
        $zerC_o = Scraper::_xP($xp, "//a[contains(@href, '{$pattern}')]/@href") ?: [];
        $zerC_i = Scraper::_xP($xp, "//a[contains(@href, '{$pattern}')]/img/@src") ?: [];

        if ($zerC_m && $zerC_o && $zerC_i) {
            $is_invalid = false;

            for ($_r = 0; $_r < 2; $_r++) {
                $M_z = Net::C($this->zer_h . ltrim($zerC_m[0], '/'), 'GET', null, $this->cookieFile, $he, $referer, $this->userAgent);
                
                if (!empty($M_z) && $M_z !== 99) {
                    if ($M_z === "invalid access") {
                        $is_invalid = true;
                        break;
                    }
                    $package['main'] = base64_encode($M_z);
                    break;
                }
            }
            
            if ($is_invalid || empty($package['main'])) {
                $random_key = array_rand($zerC_o);
                $random_sol = $zerC_o[$random_key] ?? null;
                
                if ($random_sol) {
                    return $random_sol; 
                }
            }
            foreach ($zerC_i as $i => $u) {
                $url_key = $zerC_o[$i] ?? '';
                $I_z = Net::S($this->zer_h.ltrim($u,'/'), 'GET', null, $he);
                if (!empty($I_z) && $I_z !== 99) {
                    $package['rels'][$url_key] = base64_encode($I_z);
                }
            }
        }
        return $package;
    }
    
    private function _parseTimer($html) {
        $ti = 5;
        $tmr = Scraper::_jP($html, '/MaxTime\s*=\s*([^;]+);/');
        if (!empty($tmr[1][0])) {
            $cleanFormula = preg_replace('/[^0-9\+\-\*\/\(\)\.]/', '', $tmr[1][0]);
            $ms = eval("return $cleanFormula;");
            if (is_numeric($ms) && $ms > 0) {
                $ti = ceil($ms / 1000);
            }
        }
        return $ti;
    }

    private function _solve($package) {
        if (!empty($package['rels']) && isset($package['main'])) {
            if (count($package['rels']) > 0) {
                $solver = config::getKeys($this->api, 'zercaptcha', 'b64');
                
                $solution = $solver->zer($package);
                if (!method_exists($solver, 'zer')) return null;
                
                if ($solution === 777) {
                    if (!method_exists($this->api, 'zer')) return null;
                    $solution = $this->api->zer($package);
                    
                }
                
                return $solution;
            }
        }
        return null;
    }

    public function cleanup() {
        return @unlink($this->cookieFile);
    }
    
}

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
            $workDir = $this->setupWorkDir('bct', $cleanHost, $mail, 300);
            $this->cookieFile = $workDir . "/" . $this->userdir($mail) . ".tmp";
        } else {
            $this->cookieFile = $cookie;
        }
    }
    
    public function exec($url, $tmr = 5) {
        if (empty($url)) return false;
        
        #logx('info', "[ bitcotasks.com {$tmr}s ] ", false, true);
        
        $set = microtime(true);
        $cc_get = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
        $cc_getG = scraper::_jP($cc_get, "/window\.location\.href\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
        
        $param = null;
        if (!empty($cc_getG)) {
            $cc_pre = Net::C($cc_getG, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
            
            if ($cc_pre === 99) return 99;
            
            if (!empty($cc_pre) && $cc_pre !== 99) {
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
                    'timer' => Scraper::_pP($cc_pre,'duration')[0] ?? $tmr,
                    'target_url' => $target_url,
                    'action' => $action
                ];
                
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
                
                $fjs = $this->_parseJs($cc_js);
                
                $cc_p0 = [
                    't' => round(microtime(true) * 1000),
                    'r' => mt_rand() / mt_getrandmax()
                ];
                
                $cap_get = json_decode(Net::X($this->bct_h . $cc_ep, 'POST', $cc_p0, $this->cookieFile, [], $cc_getG, $this->userAgent, true) ?: '', true);
                if (!empty($cap_get['options']) && !empty($cap_get['pixel'])) {
                    $solution = $this->_solve($cap_get);
                    if ($solution === 010) {
                        return false;
                    }
                }
            }
            
            if ($fjs && $solution) {
                $cc_p1 = $this->_buildPayload($fjs, $param, $solution);
                $cap_tok = json_decode(Net::X($this->bct_h . $cc_p1['url'], 'POST', $cc_p1['payload'], $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', true)[$fjs['cc_ver']] ?? false;
                
                if ($cap_tok) {
                    $cc_p2 = [
                        'hash' => $param['hash'],
                        'sub_id' => $param['sub_id'],
                        'key' => $param['api_key'],
                        'token' => $param['token'],
                        $fjs['cc_Fnm'] => $cap_tok,
                        'action' => $param['action']
                    ];
                    
                    $end = microtime(true);
                    if (($wait = (int)$tmr - ($end - $set)) >= 0) {
                        styler("waiting for bitcotask", fn() => _sle((int)ceil($wait)));
                    }
                    
                    
                    $cc_end = json_decode(Net::X($this->bct_h . "/system/ajax.php", 'POST', $cc_p2, $this->cookieFile, [], $cc_getG, $this->userAgent) ?: '', 1);
                    print(FGd['CYN'] . maskEmail($this->email) . RSET . " ");
                    $msg = strip_tags($cc_end['message'] ?? 'ora tau apa isinya');
                    if ($cc_end && ($cc_end['status'] ?? 0) == 200) {
                        _clr();
                        print(FGd['CYN'] . maskEmail($this->email) . RSET . " ");
                        logx('info', "[ ".__CLASS__." ] ", false);
                        logx('ok', $msg, true, true);
                        return true;
                    }
                    logx('err', $msg);
                }
            }
        }
        
        return false;
    }
    
    private function _parseJs($js) {
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
    
    private function _solve($data) {
        
        if (!method_exists($this->api, 'bct')) {
            logx('err', 'provider not support bitcotask');
            die;
        }
        
        $pow_d = $data['difficulty'] ?? 4;
        $pow_c = $data['challenge'] ?? null;
        
        $main = $this->_parseImages($data['pixel'], 200, 100);
        $captcha['main'] = $main;
        
        foreach ($data['options'] as $i => $opt) {
            $captcha['opsi'][$i] = $this->_parseImages($opt['pixels'], $opt['width'], $opt['height']);
        }
        
        $solver = config::getKeys($this->api, 'bitcotask', 'b64');
        if (method_exists($solver, 'bct')) {
            $solution = $solver->bct($captcha);
            if ($solution === 777) {
                if (method_exists($this->api, 'bct')) {
                    $solution = $this->api->bct($captcha);
                }
            }
        } else {
            return 010;
        }
        
        if (!$solution) return false;
        
        #$pow = SolveUtils::Pow($pow_c, $pow_d);

        return [
            'pow' => array_merge(
                SolveUtils::Pow($pow_c, $pow_d),
                ['ch' => $pow_c, 'di' => $pow_d]
                ),
            'cap' => $solution
        ];
    }
    
    private function _buildPayload($fjs, $param, $solution) {
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
    
    public function cleanup() {
        return @unlink($this->cookieFile);
    }
    
}







function bct($api, $url, $tmr = 5, $host = '') {
    $url = 'https://bitcotasks.com/start/f3c5a822b8ad0ae56d0df4498e744cbb:675741365771472f6153375264667965495355664847393174392b454d6a32426d78626368744468706d774675382f48616e74514e4d774d67546e4e3351687532324967504a356839666e646b496147617063687849504e5a5a7661466c655335444e4e7931366b464d6e4d65725a50544c64524163684157446a73653275646a4a6931383535436376524c2b4c706152455a377733626d47696b482b4f4f6d6e6863317259436262762f7675577869736842616f526c2b31512b47756e64626b622b49377a7879596e2b62584e51676a456f6639644b513547634e4f50666d354f6832764c39783972383d';
    
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
    
    $main = _rndr($param['pixel'],200,100);
    _put($dir.'/main.png', base64_decode($main));
    
    foreach ($param['options'] as $i => $opt) {
        if ($opsi = _rndr($opt['pixels'],$opt['width'],$opt['height'])) {
            _put($dir."/$i.png", base64_decode($opsi));
        }
        
    }
    
}