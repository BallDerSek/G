<?php

class Twall {
    use WorkDir;
    use Base;
    
    private string $cookieFile;
    private string $userAgent;
    private string $mail;
    private $api;
    private $ctx;
    private string $tmw_H = 'https://timewall.io';

    public function __construct($url, $api, $mail = null, $cookie = null, $ua = null) {
        if (empty($url)) return;
        
        $this->userAgent = $ua ?: Config::uagent("desktop");
        $this->api = $api;
        $this->mail = $mail;
        
        $targetHost = parse_url($url)['host'] ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');
        
        if (!$cookie) {
            $this->workDir = $this->setupWorkDir('tmw', $cleanHost, $mail, 300);
            $this->cookieFile = $this->workDir . "/" . $this->userdir($mail) . ".tmp";
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
    
    public function exec($url, $setF = null, $until = null) {
        $retTmw = 0;
        
        $tmw = Net::C($this->tmw_H.'/clicks?iframe=1', 'GET', null, $this->cookieFile, [], '', $this->userAgent);
        #var_dump($tmw);
        
        if (empty($tmw) || $tmw === 99) return false;
        
        $tkn = null;
        $uid = null;
        $hsh = '';
        $head = null;
        
        $wall = null;
        while (true) {
            $_0 = null;
            
            if ($setF > 0) {
                $endF = microtime(true);
                $balik = $endF - $setF;
                if ($balik >= $until) return 'claim';
            }
            
            if ($retTmw >= 3) {
                $this->cleanup();
                break;
            }
            
            $rett = 0;
            while (empty($wall)) {
                $retTmw++;
                if ($rett >= 3) continue 2;
                
                if ($_0 = $this->_get($tmw, $url)) {
                    #_put('0.html', $_0);
                    $uid = Scraper::_var($_0, 'data-uid');
                    $tkn = stripslashes(Scraper::_var($_0, 'csrfToken')?: '');
                }
                
                #var_dump($tkn, $uid);
                if ($tkn && $uid && !empty($_0)) {
                    $head = ['x-csrf-token:'.$tkn];
                    
                    $_1 = $this->_set($_0, $url, $head);
                    
                }
                
                if (!empty($_1) && $_1 !== 99) {
                    $hsh = Scraper::_pP($_1, 'userHash')[0]?? '';
                    if (stripos($_1, 'Loading Click') && $hsh) $wall = $_1;
                }
                
            }
            
            #_put('wall.html', $wall);
            
            Net::X(
                $this->tmw_H.'/clicks/actionclicks',
                'POST', ['action' => 'CleanPendingSession'], 
                $this->cookieFile, $head, $url, $this->userAgent
            );
            
            $ad = json_decode(Net::X(
                $this->tmw_H.'/clicks/actionclicks', 'POST',
                ['action' => 'GetUserAds'], $this->cookieFile,
                $head, $url, $this->userAgent
            )?: '', 1)['data'][0] ?? null;
            
            #var_dump($ad); die;
            if (!empty($data = $ad) && isset($ad['checksum'])) {
                
                $po_2 = [
                    'action' => 'StartClicksSession',
                    'adID' => $data['encrypted_ad_id'],
                    'checksum' => $data['checksum']
                ];
                
                $_2 = json_decode(Net::X(
                    $this->tmw_H.'/clicks/actionclicks',
                    'POST', $po_2, $this->cookieFile,
                    $head, $url, $this->userAgent
                )?: '', 1)['sessionid'] ?? null;
                
                if (!empty($_2)) {
                    #var_dump($_2);
                    styler("waiting for timewall", fn() => _sle((int)ceil($data['timer'])));
                    $po_3 = [
                        'sessionId' => $_2,
                        'userHash' => $hsh,
                        'TW' => 1
                    ];
                    #var_dump($po_3);
                    
                    $_3 = Net::S($data['clickUrl'], 'GET', $po_3, $head, false, $this->userAgent, 1);
                    #$_3 = Net::X($data['clickUrl'], 'GET', $po_3, null, $head);
                    
                    if (!empty($_3) && $_3 !== 99) {
                        $this->logger('ok', "[ ".__CLASS__." ]", 'timeWall claimed');
                    }
                    continue;
                }
                
            } else return 'habis';
            
            
        }
        
        
        return true;
    }
    
    private function _get($html = '', $url = '') {
        if (empty($html) || empty($url)) return false;
        
        if (stripos($html, 'Welcome to TimeWall')) {
            
            @unlink($this->cookieFile);
            Net::C($url, 'GET', null, $this->cookieFile, [], $url, $this->userAgent);
            $auth = json_decode(Net::X(
                $this->tmw_H.'/users/session-check',
                'GET', null, $this->cookieFile,
                [], $url, $this->userAgent
            )?: '', 1)['authenticated'] ?? null;
            if ($auth) return Net::C($this->tmw_H.'/clicks?iframe=1', 'GET', null, $this->cookieFile, [], $url, $this->userAgent);
            
        } else return $html;
        
        return false;
    }
    
    private function _set($html, $head, $url) {
        $po = null;
        
        if (stripos($html, 'captchaNotDone') && !stripos($html, 'Loading Click')) return $this->_solve($html, $head, $url);
        
        return $html;
        
    }
    
    private function _solve($html, $url, $head) {
        $ver = false;
        
        $cappp = Capt::cha($html)['cft'] ?? null;
        if (!empty($cappp)) {
            $cap = Solve::exec($html, $this->tmw_H, $this->api, null, false, $this->ctx);
            if (isset($cap['trouble'])) return false;
            
            $po = ['response' => ($cap['cf-turnstile-response'] ?? 'token')];
            $ver = json_decode(Net::X(
                $this->tmw_H.'/clicks/validatecfcaptcha',
                'POST', $po, $this->cookieFile, 
                $head, $url, $this->userAgent
            )?: '', 1)['success'] ?? false;
            
        }
        
        if ($ver) return Net::C($this->tmw_H.'/clicks?iframe=1', 'GET', null, $this->cookieFile, $head, '', $this->userAgent);
        
        return false;
    }
    
    public function cleanup() {
        if (empty($this->workDir)) return;
        return $this->rmdir($this->workDir);
    }
    
}