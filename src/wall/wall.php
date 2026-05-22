<?php


class Owme {
    private string $cookieFile;
    private string $userAgent;

    public function __construct($url, $mail = null) {
        if (empty($url)) {
            throw new InvalidArgumentException("URL target tidak boleh kosong.");
        }

        $this->userAgent = Config::uagent("desktop");

        $targetHost = parse_url($url, PHP_URL_HOST) ?: $url;
        $cleanHost  = trim(preg_replace('/[^a-zA-Z0-9]/', '_', $targetHost), '_');

        $user = ($mail && str_contains($mail, '@')) ? strstr($mail, '@', true) : ($mail ?: 'default');

        $this->cookieFile = _lib('owme') . "/{$cleanHost}/{$user}.tmp";

        if (!is_dir(dirname($this->cookieFile))) {
            mkdir(dirname($this->cookieFile), 0777, true);
        }
    }
    
    public function exec($url, $timer) {
        $maxFullRetry = 5;
        $attempt = 0;

        while ($attempt < $maxFullRetry) {
            $attempt++;
            logx('info', "   [ wait {$timer}s for offerwall.me ]   ", false, true);

            $adData = $this->_get($url);
            if (!$adData) {
                _sle(3); 
                continue;
            }

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
        if (empty($view) || !isset($view['url'])) {
            logx('err', "Jaringan bermasalah (Visit)");
            return null;
        }

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

    private function _getCap($capUrl, $ref) {
        $capRaw = Net::X($capUrl, 'POST', ['cID' => '0', 'rT' => '1', 'tM' => 'light'], $this->cookieFile, [], $ref, $this->userAgent) ?: '';
        $capReq = json_decode($capRaw, true);

        if (!$capReq || !is_array($capReq)) {
            logx('err', "Gagal ambil icon");
            return null;
        }

        return $capReq;
    }

    private function _verCap($capUrl, $iconId, $ref) {
        $check = Net::X($capUrl, 'POST', ['cID' => '0', 'rT' => '2', 'pC' => $iconId], $this->cookieFile, [], $ref, $this->userAgent, d: true);
        return (!empty($check) && isset($check['http_code']) && $check['http_code'] === 200);
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
        if (empty($res)) {
            return false;
        }

        if (!empty($res) && is_array($res)) {
            $msg = isset($res['message']) ? trim(strip_tags($res['message'])) : 'ora tau apa isinya';
            
            if (isset($res['status']) && $res['status'] == 200) {
                logx('info', $msg, true, true);
                return true;
            } else {
                logx('err', $msg);
            }
        }

        return false;
    }
}


class Owmme {
    private string $cookieFile;
    private string $userAgent;

    public function __construct($host = null, $mail = null) {
        $this->userAgent = (isset(inf::$uagent) && !empty(inf::$uagent)) ? inf::$uagent : Config::uagent('mobile');

        if (isset(inf::$cookie) && !empty(inf::$cookie)) {
            $this->cookieFile = inf::$cookie;
        } else {
            $cleanHost = str_replace('.', '_', parse_url($host, PHP_URL_HOST) ?: $host);
            $user = ($mail && strpos($mail, '@') !== false) ? strstr($mail, '@', true) : ($mail ?: 'default');
            $workDir = LIBDIR . "/owme/{$cleanHost}/{$user}";
            if (!is_dir($workDir)) mkdir($workDir, 0777, true);
            $this->cookieFile = $workDir . '/cookie.txt';
        }
    }

    public function claim($url, $timer) {
        $maxFullRetry = 5;
        $attempt = 0;

        while ($attempt < $maxFullRetry) {
            $attempt++;
            logx('info', "   [ wait {$timer}s for offerwall.me ]   ", false, true);

            // 1. Visit URL Iklan
            $view = Net::C($url, 'GET', null, $this->cookieFile, [], '', $this->userAgent, true);
            if (empty($view) || !isset($view['url'])) {
                logx('err', "Jaringan bermasalah (Visit)");
                _sle(3); continue;
            }

            $ref = $view['url'];
            $targetHost = 'https://' . parse_url($ref)['host'];
            $body = $view['body'];

            $p = [
                'tkn' => Scraper::_jP($body, "/var\s+token\s*=\s*'([^']+)';/")[1][0] ?? null,
                'ids' => Scraper::_jP($body, "/var\s+sub_id\s*=\s*'([^']+)';/")[1][0] ?? null,
                'idh' => Scraper::_jP($body, "/var\s+hash\s*=\s*'([^']+)';/")[1][0] ?? null,
                'key' => Scraper::_jP($body, "/var\s+key\s*=\s*'([^']+)';/")[1][0] ?? null,
                'dur' => Scraper::_jP($body, "/var\s+duration\s*=\s*(\d+);/")[1][0] ?? null,
                'act' => Scraper::_jP($body, "/'action'\s*:\s*'([^']+)'/")[1][0] ?? 'proccessLead',
            ];

            if (in_array(null, $p, true)) {
                logx('err', "ada perubahan kayaknya");
                _put('owme_err.html', $body);
                _sle(3); continue;
            }

            _sle((int)$p['dur']);

            $capUrl = $targetHost . '/system/libraries/captcha/request.php';
            $capRaw = Net::X($capUrl, 'POST', ['cID' => '0', 'rT' => '1', 'tM' => 'light'], $this->cookieFile, [], $ref, $this->userAgent) ?: '';
            $capReq = json_decode($capRaw, true);

            if (!$capReq || !is_array($capReq)) {
                logx('err', "Gagal ambil icon");
                _sle(3); continue;
            }

            foreach ($capReq as $iconId) {
                $check = Net::X($capUrl, 'POST', ['cID' => '0', 'rT' => '2', 'pC' => $iconId], $this->cookieFile, [], $ref, $this->userAgent, d: true);
                
                if (!empty($check) && $check['http_code'] === 200) {
                    $payload = [
                        'hash' => $p['idh'], 'sub_id' => $p['ids'], 'key' => $p['key'],
                        'token' => $p['tkn'], 'captcha-idhf' => '0', 'captcha-hf' => $iconId,
                        'action' => $p['act']
                    ];

                    $ajaxRaw = Net::X($targetHost . '/system/ajax.php', 'POST', $payload, $this->cookieFile, [], $ref, $this->userAgent) ?: '';
                    
                    if (empty($ajaxRaw)) {
                        _sle(3);
                        continue 2; 
                    }

                    $res = json_decode($ajaxRaw, true);
                    if (!empty($res) && is_array($res)) {
                        $msg = isset($res['message']) ? trim(strip_tags($res['message'])) : 'ora tau apa isinya';
                        if (isset($res['status']) && $res['status'] == 200) {
                            logx('info', $msg, true, true);
                            return true;
                        } else {
                            logx('err', $msg);
                        }
                    }
                }
            }
            
            logx('err', "error gak jelas");
            _sle(3);
        }

        return false;
    }
}

# cara pakai tapi bukan iframe, kalau iframe belum dibuat 
/*
    $done = false;
    do { # offerwall.me
        
        $ow = new Owme($host, $login);
        $retryList = 0;
        $off = [];
        while ($retryList < 3) {
            $owme = Net::C($host.'/offerwallme', 'GET', null, $cookieFile, [], '', $userAgent);
            #_put('owme.html', $owme);
            if (!empty($owme)) {
                $clicks = Scraper::_xP($owme, "//div[@id='pane_ptc']//button[contains(@onclick, 'owVisit')]/@onclick");
                $times = Scraper::_xP($owme, "//span[contains(@class, 'ow-badge')][i[contains(@class, 'fa-clock')]]");
                foreach ($clicks as $i => $onclick) {
                    if (preg_match("/owVisit\(this\s*,\s*['\"]([^'\"]+)['\"]/i", $onclick, $match)) {
                        $o_u = str_replace('&amp;', '&', $match[1]);
                        $rawTime = $times[$i] ?? '10';
                        $o_t = (int)filter_var($rawTime, FILTER_SANITIZE_NUMBER_INT);
                        $off[] = [
                            'url' => $o_u,
                            'timer' => $o_t ?: 10
                        ];
                    }
                }
            }
            if (!empty($off)) break;
            
            $retryList++;
            if ($retryList < 3) {
                _sle(3);
            }
        }
        
        if (empty($off)) {
            logx('err', "habis total kaya kayaknya.");
            _put('owme.html', $owme);
            $done = true;
        } else {
            foreach ($off as $ad) {
                $status = $ow->claim($ad['url'], $ad['timer']);
                if ($status) {
                    styler('Waiting', fn() => _sle(5));
                } else {
                    logx('err', "Gagal claim iklan");
                }
            }
        }
        
        
    } while (!$done); 
*/