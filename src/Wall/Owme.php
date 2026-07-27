<?php

# 
class Owme {
    use WorkDir; 
    
    private string $cookieFile;
    private string $userAgent;
    private string $email;
    private $cre;
    private $ctx;
    private $api; 
    private string $owm_h = 'https://offerwall.me/';

    public function __construct($url, $api, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return;
        
        $this->cre = ['email' => 'g2tjz6pl8v@lnovic.com', 'password' => 'g2tjz6pl8v@lnovic.com', 'action' => 'login'];
        
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
            $this->workDir = '';
        }
        
        $this->ctx = [
            'id' => (string)$mail,
            'ip' => '',
            'ins' => 0,
            'cookie' => $this->cookieFile,
            'uagent' => $this->userAgent
        ];
        
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
    
    private function processShortlink($url, $idd, $tkn) {
        $payload = [
            'action' => 'getShortlink',
            'data' => $idd,
            'token' => $tkn
        ];
        
        $go = json_decode(Net::X($url, 'POST', $payload, $this->cookieFile, [], '', $this->userAgent)?: '', 1)['link'] ?? null;
        
        if (!$go) return false;
        
        $_0 = Net::X($go, 'GET', null, $this->cookieFile, [], $url, $ua);
        
        if (!empty($_0) && $_0 !== 99) {
            return json_decode(Net::X(
                $go, 'POST',
                array_merge(Solve::exec($_0, $url, $this->api, null, 0, $this->ctx), ['action' => 'redirect']),
                $this->cookieFile, [], $_0, $this->userAgent
            )?: '', 1)['link'] ?? null;
        }
        
        return false;
    }
    
    public function wall($url, $menu = false) {
        
        $_0 = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
        #_put('0.html', $_0); die;
        
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
                    
                    $get = $this->processShortlink($url, $idSL, $tkn);
                    
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
                            
                            $_als = Scraper::_xP($ver, "//div[contains(@class, 'alert-success')]");
                            if (!empty($_als[0])) {
                                logg(false, $_als[0]);
                                continue;
                            }
                            $_ald = Scraper::_xP($ver, "//div[contains(@class, 'alert-danger')]")[0] ?? 'gatau error apa';
                            Logger::X('err', $_als[0]);
                        }
                    }
                }
                
                return true;
            }
            
        } else {
            return false;
            $flogin = Scraper::payload($_0, 'login-tab')[0] ?? null;
            
            if ($flogin && stripos($_0, 'Log in once')) {
                $login = $this->_cap($url);
                var_dump($login);
                
                
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
            
            #Logger::X('info', "[ offerwall.me {$timer}s ] ", false, true);
            
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
            
            Logger::X('err', "error gak jelas");
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
            #Logger::X('err', "ada perubahan kayaknya");
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
                Logger::X('info', "[ ".__CLASS__." ] ", false);
                Logger::X('ok', $msg, true, true);
                
                return true;
            } else {
                Logger::X('err', $msg);
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
        if (empty($this->workDir)) return;
        return $this->rmdir($this->workDir);
    }
    
    private function _cap($url) {
        $_0 = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent);
        _put('0.html', $_0);
        
        _put('thm.js', _get($this->owm_h."/theme-assets/js/vendor/bootstrap.bundle.min.js"));
        
        die;
        
        $po = null;
        if (!empty($_0) && $_0 !== 99) {
            $ff = Scraper::payload($_0, 'login-tab')[0] ?? null;
            $cc = Capt::cha($_0)['cft'] ?? null;
        
            if (!empty($cc) && !empty($ff['payload'] ?? [])) {
                $cft = Solve::exec($_0, $this->owm_h, $this->api, null, 0, $this->ctx)["cf-turnstile-response"] ?? null;
                if (!$cft) return false;
                $po = array_merge($ff['payload'], ["cf-turnstile-response" => $cft], $this->cre);
            }
        }
        
        if (!empty($po)) {
            var_dump($this->cookieFile, $this->userAgent);
            var_dump($po); #die;
            $ver = Net::C($this->owm_h.'/offerwall-account', 'POST', $po, $this->cookieFile, [], $this->owm_h.'/offerwall-account', $this->userAgent);
            _put('ver.html', $ver);
            
            
            
            
            
            
        }
        
        
        die;
        
    }

}






    
/*

token=430d09a44de0e67bc14c6830366824a49cd796e2dddfcee4dd66d0eea54d9d65&
action=login&
email=g2tjz6pl8v%40lnovic.com&
password=g2tjz6pl8v%40lnovic.com&
cf-turnstile-response=1.-8ywsNIhuHFbtI5R7RJOKDISYm1ZaQyVktT1FdTOc7XEsdP6bkdUlJ0N68lX3Vv3LT-s8EGLGXA0GnbTNdA7jaur2vFnWdoSZ7VmitXeUXfaFSuTTfoAwwJehBFw6Fa1-RWkUYNJ0D-NuhJRbxNimDVperSD0VwL43LQQ0FNLJ05fiadtPxkgfEKwgLPO0koYcjrFmmF3RB9AECn6oUoWw5RAfg7n6chQSvDHnobk3ypVuIv96bbIRLTdvwx4e1-CrTKBNAXxTkxy5HEw7AX_FmjbcjEHMW0khylCZh5PR451cldMvxn0RgS1cdi43NUmhcg1AIinhaimED12er115BAuirBvUGGEDj-qkDrIn3bxrPZrCh1kRq9nSiKeuRrcb8jdkkvE5HRp7MtltXTBIRFTMX1MTsENde7tqEP_hOXRr2jWyKmWRl0A0J_alLDrhZ6AhjG4qwkkxRHuG4mEhIX4Tq0r_9WNRlwSp6-wwAnQs2KAKcocA25aQvwkfDNSm2FgjEIjPRE2n5nB5VjNowvl6Vg6T7fKu9KYcmcvKemOGbSIco2igoWqVQj0fecXwn9kRbFhn6b59IioWzHWTVGdzD08e1UtLheV1zXjqXDYYXNxAXgB4O9XmLWMYLROCZpkDBNrJunJVkYwnFAHJKgIV3CZnhkhnvq9ohhgaM.vfSipLQKR-PEN7MiE3Xbaw.8a345150f9ac67576b6050fc0eb01d2811bf183c5e9a911fcb06dcd17cc2c880

*/