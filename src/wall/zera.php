<?php

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
                
                if (!method_exists($solver, 'zer')) return null;
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