<?php


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
                
                if (!empty($_1)) $adsList = $this->camp($_1, 'AD');
                
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
            if (!empty($_1)) $shoList = $this->camp($_1, 'SL');
            
            if ($shoList && !$menu) {
                
                foreach ($shoList as $sl) {
                    $idSL = $sl['id'];
                    
                    $get = $this->processShortlink($url, $idSL, $tkn, $this->cookieFile, $this->userAgent, $this->api);
                    
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






    


