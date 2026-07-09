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
            $this->workDir = $this->setupWorkDir('zer', $cleanHost, $mail, 300);
            $this->cookieFile = $this->workDir . "/" . $this->userdir($mail) . ".tmp";
        } else {
            $this->cookieFile = $cookie;
        }
    }

    public function exec($zer_u, $setF = null, $until = null) {
        $retZer = 0;
        
        start:
        $zer = Net::C($zer_u, 'GET', null, $this->cookieFile, [], "", $this->userAgent);
        #_put('zer.html', $zer); #die;
        if (empty($zer) || $zer === 99) return false;
        
        while (true) {
            
            if ($setF > 0) {
                $endF = microtime(true);
                $balik = $endF - $setF;
                if ($balik >= $until) return 'claim';
            }
            
            if ($retZer >= 3) {
                $this->cleanup();
                break;
            }
            
            $zer_s = null;
            
            if (stripos($zer, 'solve captcha') !== false) {
                $zerC_p = $this->_parseImages($zer, $zer_u, 'scid=');
                #var_dump($zerC_p);
                if (!is_array($zerC_p)) {
                    $retZer++;
                    continue;
                }
                
                if ($sol = $this->_solve($zerC_p)) {
                    $target_url = $this->zer_h . $sol;
                    $zer_s = Net::X($target_url, 'GET', null, $this->cookieFile, [], $zer_u, $this->userAgent);
                }
            }
            
            $zer_v = $zer_s ?? $zer;
            
            if (stripos($zer_v, 'Viewing PTC Ad')) {
                $ti = $this->_parseTimer($zer_v);
                
                $zerC_p = $this->_parseImages($zer_v, $zer_u, 'id=');
                #var_dump($zerC_p);
                if ($zerC_p === 'main_reload') {
                    $retZer++;
                    goto start;
                }
                
                $set = microtime(true);
                $sol = $this->_solve($zerC_p);
                
                if ($sol) {
                    $end = microtime(true);
                    $wait = (int)$ti - ($end - $set);
                    
                    if ($wait >= 0) styler("waiting for zerads", fn() => _sle((int)ceil($wait)));
                    
                    $target_url = $this->zer_h . $sol;
                    $zer_d = Net::X($target_url, 'GET', null, $this->cookieFile, [], $zer_u, $this->userAgent);
                    
                    if (!empty($zer_d) && $zer_d !== 99) {
                        $zer_r = Scraper::_xP($zer_d, "//div[@id='rwmsgbox']") ?? [];
                        if (!empty($zer_r[0])) {
                            _clr();
                            Logger::M($this->email);
                            Logger::X('info', "[ ".__CLASS__." ] ", false);
                            $message = trim(preg_replace('/\s+/', ' ', strip_tags($zer_r[0])));
                            Logger::X('ok', $message, true, true);
                        }
                    }
                    
                    $zer = $zer_d;
                } else {
                    $retZer++;
                    continue;
                }
            } else {
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

    private function _solve00($package) {
        if (!empty($package['rels']) && isset($package['main'])) {
            if (count($package['rels']) > 0) {
                $solver = Config::getKeys($this->api, 'zercaptcha', 'b64');
                
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

    private function _solve($package) {
        if (!empty($package['rels']) && isset($package['main'])) {
            if (count($package['rels']) > 0) {
                $solver = Config::getKeys($this->api, 'zercaptcha', 'b64');
                
                if (!method_exists($solver, 'zer')) return null;
                $solution = $solver->zer($package);
                
                if (isset($solution['fail'])) {
                    if (!method_exists($this->api, 'zer')) return null;
                    $solution = $this->api->zer($package);
                }
                
                if (isset($solution['done'])) {
                    return $solution['done'];
                }
                
                return null;
            }
        }
        return null;
    }

    public function cleanup() {
        if (empty($this->workDir)) return;
        return $this->rmdir($this->workDir);
    }
    
}
