<?php


class Owme {
    private string $cookieFile;
    private string $userAgent;
    private string $email;

    public function __construct($url, $mail = null) {
        if (empty($url)) return null;

        $this->userAgent = Config::uagent("desktop");

        $targetHost = parse_url($url, PHP_URL_HOST) ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');

        $user = ($mail && str_contains($mail, '@')) ? strstr($mail, '@', true) : ($mail ?: 'default');

        $this->cookieFile = _lib('owme', $cleanHost) . "/{$user}.tmp";
        $this->email = $mail;
        if (!is_dir(dirname($this->cookieFile))) {
            mkdir(dirname($this->cookieFile), 0777, true);
        }
    }
    
    public function exec($url, $timer) {
        $maxFullRetry = 5;
        $attempt = 0;

        while ($attempt < $maxFullRetry) {
            $attempt++;

            $adData = $this->_get($url);
            if (!$adData) {
                _sle(3); 
                continue;
            }
            
            print(FGd['CYN'].maskEmail($this->email).RSET." ");
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
        $targetHost = 'https://' . parse_url($ref, PHP_URL_HOST);

        $params = [
            'tkn' => Scraper::_jP($body, "/var\s+token\s*=\s*'([^']+)';/")[1][0] ?? null,
            'ids' => Scraper::_jP($body, "/var\s+sub_id\s*=\s*'([^']+)';/")[1][0] ?? null,
            'idh' => Scraper::_jP($body, "/var\s+hash\s*=\s*'([^']+)';/")[1][0] ?? null,
            'key' => Scraper::_jP($body, "/var\s+key\s*=\s*'([^']+)';/")[1][0] ?? null,
            'dur' => Scraper::_jP($body, "/var\s+duration\s*=\s*(\d+);/")[1][0] ?? null,
            'act' => Scraper::_jP($body, "/'action'\s*:\s*'([^']+)'/")[1][0] ?? 'proccessLead',
        ];

        if (in_array(null, $params, true)) {
            logx('err', "ada perubahan kayaknya");
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

        $res = json_decode(Net::X($url, 'POST', $payload, $this->cookieFile, [], $ref, $this->userAgent) ?: '', true);
        
        if (empty($res)) return false;

        if (!empty($res) && is_array($res)) {
            $msg = isset($res['message']) ? trim(strip_tags($res['message'])) : 'ora tau apa isinya';
            
            if (isset($res['status']) && $res['status'] == 200) {
                logx('ok', $msg, true, true);
                return true;
            } else {
                logx('err', $msg);
            }
        }

        return false;
    }

    private function _getCap($capUrl, $ref) {
        $capRaw = Net::X($capUrl, 'POST', ['cID' => '0', 'rT' => '1', 'tM' => 'light'], $this->cookieFile, [], $ref, $this->userAgent) ?: '';
        $capReq = json_decode($capRaw, true);

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


