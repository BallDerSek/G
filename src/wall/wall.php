<?php


class Owme {
    private string $cookieFile;
    private string $userAgent;
    private string $email;

    public function __construct($url, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return null;
        $this->userAgent = $ua ?: Config::uagent("desktop");
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        $user = ($mail && str_contains($mail, '@')) ? strstr($mail, '@', true) : ($mail ?: 'default');
        
        $this->cookieFile = $cookie ?: (_lib('owme', $cleanHost) . "/{$user}.tmp");
        
        $this->email = $mail;
        
        if (!is_dir(dirname($this->cookieFile))) {
            mkdir(dirname($this->cookieFile), 0777, true);
        }
    }
    
    public function exec($url, $timer) {
        $attempt = 0;

        while ($attempt < 3) {
            $attempt++;

            $adData = $this->_get($url);
            
            if ($adData === 000) return false;
            if (!$adData) {
                _sle(3); 
                continue;
            }
            
            logx('info', "[ offerwall.me {$timer}s ] ", false, true);
            
            _sle((int)$adData['params']['dur']);
            
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
    private string $cookieFile;
    private string $userAgent;
    private string $email;
    private $api; 
    private string $zer_h = 'https://zerads.com/';

    public function __construct($url, $api, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return;
        
        $this->userAgent = $ua ?: Config::uagent("desktop");
        $this->api = $api;
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        $user = ($mail && str_contains($mail, '@')) ? strstr($mail, '@', true) : ($mail ?: 'default');
        
        $this->cookieFile = $cookie ?: (_lib('zer', $cleanHost) . "/{$user}.tmp");
        $this->email = $mail;
        
        if (!is_dir(dirname($this->cookieFile))) {
            mkdir(dirname($this->cookieFile), 0777, true);
        }
    }

    public function exec($zer_u, $ip) {

        $retZer = 0;
        $current_ref = '';
        
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
            $zer_s = null; 

            if (stripos($zer, 'solve captcha')) {
                $zerC_p = $this->_parseImages($zer, $current_ref, 'scid=');
                
                if (!is_array($zerC_p)) {
                    $retZer++;
                    continue;
                }
                
                if ($sol = $this->_solve($zerC_p)) {
                    $target_url = $this->zer_h . $sol;
                    logx('info', '0: '.$target_url);
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
                _sle((int)($ti))
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
                        styler("waiting for zerads", fn() => _sle((int)ceil($wait)));
                    }
                    
                    
                    $target_url = $this->zer_h . $sol;
                    logx('info', '1: '.$target_url);
                    $zer_d = Net::X($target_url, 'GET', null, $this->cookieFile, [], $current_ref, $this->userAgent);
                    
                    #_put('zerD.html', $zer_d);

                    if (!empty($zer_d) && $zer_d !== 99) {
                        $zer_r = Scraper::_xP($zer_d, "//div[@id='rwmsgbox']") ?? [];
                        if (!empty($zer_r[0])) {
                            print(FGd['CYN'] . maskEmail($this->email) . RSET . " ");
                            $message = trim(preg_replace('/\s+/', ' ', strip_tags($zer_r[0])));
                            logx('ok', $message, true, true);
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
        
        logx('info', "error gak danta");
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
                        var_dump($M_z);
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
