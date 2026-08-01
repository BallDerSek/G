<?php

final class rsCaptcha {
    
    private string $host, $ua, $ck, $ip;
    private bool $in;
    private string $html, $id;
    
    public function __construct(array $ctx) {
        
        $this->host = $ctx['host'] ?? '';
        $this->html = $ctx['html'] ?? '';
        $this->ua = $ctx['uagent'] ?? '';
        $this->ck = $ctx['cookie'] ?? null;
        $this->in = $ctx['ins'] ?? false;
        $this->ip = $ctx['ip'] ?? '';
        $this->id = $ctx['id'] ?? '';
        
    }
    
    public function exec($rss, $api, $html = null) {
        
        $_M = $rss['type'] ?? null;
        $_K = $rss['keys'] ?? null;
        $_T = $rss['extra']['token'] ?? null;
        $_J = $rss['extra']['js'] ?? null;
        
        if (str_starts_with($_M, 'rsc')) return $this->rsc($rss, $api);
        
        if (in_array(null, [$_M, $_K, $_T, $_J], true)) return false;
        if (!filter_var($_K, FILTER_VALIDATE_URL)) {
            $_host = rtrim($this->host, '/');
            $_path = ltrim($_K, '/');
            $_K = (str_starts_with($_host, 'http')) ? "{$_host}/{$_path}" : "https://{$_host}/{$_path}";
        }
        
        $img = Net::C($_K, 'GET', null, $this->ck, [], $this->host, $this->ua, ip: $this->ip, ins: $this->in);
        if (empty($img) || $img === 99) return false;
        
        $co = Solve::img($api, $this->host, $_M, $img);
        if (isset($co['trouble'])) return false;
        
        $coords = Scraper::_jP($co, '/\d+/');
        $_co = $coords[0] ?? $coords; 
        
        if (is_array($_co) && count($_co) >= 2) {
            [$x, $y] = $_co;
            $token = $this->rss($api, ['html' => $this->html, 'js' => $_J], $x, $y);
            if ($token) {
                return [
                    'rscaptcha_token' => $_T,
                    'rscaptcha_response' => $token
                ];
            }
        }
        
        return false;
    }
    
    private function rsc00($rss, $api) {
        # problematic provider need much parameter
        $token = null;
        #print_r($rss); die;
        
        $_D = $rss['extra'] ?? null;
        $_I = $_D['app_id'] ?? null;
        $_T = $_D['version'] ?? null;
        $_K = $_D['public_key'] ?? null;
        
        $_H = 'https://rscaptcha.com';
        
        if (in_array(null, [$_D, $_I, $_T, $_K], true)) return false;
        $rs_R = null;
        $rs_T = null;
        
        if (strtolower(get_class($api)) === 'skibidixxx') {
            $res = $api->rss($_D, $this->host);
            if ($res) {
                parse_str(str_replace([":", ","], ["=", "&"], $res), $out);
                $rs_T = $out['rs_token'] ?? null;
                $rs_R = $out['rs_res'] ?? null;
            }
        } else {
            $_0 = SolveUtils::webkitID($_D, $boundary);
            $head = ["Content-Type: multipart/form-data; boundary=$boundary"];
            
            $_get = json_decode(Net::S($_H."/captcha/$_T/get", 'POST', $_0, $head) ?: '', 1)['data'] ?? null;
            
            $coo = null;
            if (!empty($_get) && isset($_get['captcha_key'])) {
                $rs_T = $_get['captcha_key'];
                if (method_exists($api, 'rss')) $coo = $api->rss($_get, $this->host);
            }
            if ($coo) {
                $coords = Scraper::_jP($coo, '/\d+/');
                $_co = $coords[0] ?? $coords;
            }
            if (is_array($_co) && count($_co) >= 2) {
                [$x, $y] = $_co;
                $_P = [
                    'token' => $rs_T,
                    'response' => "$x,$y",
                    #'response' => "200,109",
                ];
                $_1 = SolveUtils::webkitID(array_merge($_P, $_D), $boundary);
                $rs_R = json_decode(Net::S($_H."/captcha/$_T/verify", 'POST', $_1, $head) ?: '', 1)['result'] ?? null;
            }
        }
        
        if ($rs_R && $rs_T) {
            return [
                'rscaptcha_token' => $rs_T,
                'rscaptcha_response' => $rs_R,
            ];
        }
        
        return null;
        
    }
    
    private function rsc($rss, $api) {
        $token = null;
    
        $_D = $rss['extra'] ?? null;
        $_I = $_D['app_id'] ?? null;
        $_T = $_D['version'] ?? null;
        $_K = $_D['public_key'] ?? null;
    
        $_H = 'https://rscaptcha.com';
    
        if (in_array(null, [$_D, $_I, $_T, $_K], true)) return false;
        $rs_R = null;
        $rs_T = null;
    
        if (strtolower(get_class($api)) === 'skibidixxx') {
            $res = $api->rss($_D, $this->host);
            if (isset($res['done'])) {
                parse_str(str_replace([":", ","], ["=", "&"], $res['done']), $out);
                $rs_T = $out['rs_token'] ?? null;
                $rs_R = $out['rs_res'] ?? null;
            }
        } else {
            $_0 = SolveUtils::webkitID($_D, $boundary);
            $head = ["Content-Type: multipart/form-data; boundary=$boundary"];
    
            $_get = json_decode(Net::S($_H."/captcha/$_T/get", 'POST', $_0, $head) ?: '', 1)['data'] ?? null;
    
            $coo = null;
            if (!empty($_get) && isset($_get['captcha_key'])) {
                $rs_T = $_get['captcha_key'];
                if (method_exists($api, 'rss')) $coo = $api->rss($_get, $this->host);
            }
            if (isset($coo['done'])) {
                $coords = Scraper::_jP($coo['done'], '/\d+/');
                $_co = $coords[0] ?? $coords;
            }
            if (is_array($_co) && count($_co) >= 2) {
                [$x, $y] = $_co;
                $_P = [
                    'token' => $rs_T,
                    'response' => "$x,$y",
                ];
                $_1 = SolveUtils::webkitID(array_merge($_P, $_D), $boundary);
                $rs_R = json_decode(Net::S($_H."/captcha/$_T/verify", 'POST', $_1, $head) ?: '', 1)['result'] ?? null;
            }
        }
    
        if ($rs_R && $rs_T) {
            return [
                'rscaptcha_token' => $rs_T,
                'rscaptcha_response' => $rs_R,
            ];
        }
    
        return null;
    }

    
    private function rss($api, $utils, $x, $y) {
        $provider = strtolower(get_class($api));
        $token = null;
        
        # if some provider got many invalid
        # u can change to use locally fallback
        # uncomment to use by provider, it'll consume few credit
        
        /*
        if ($provider === 'tertuyul') {
            $data = [
                'clickX' => $x,
                'clickY' => $y,
                'script' => base64_encode($utils['js'])
            ];
            $token = $api->run('rstoken', $data);
        } 
        
        if ($provider === 'skibidixxx') {
            $data = [
                "htmlContent" => $utils['html'],
                "clickX" => $x,
                "clickY" => $y
            ];
            for ($retry = 0; $retry < 3; $retry++) {
                usleep(500000);
                $res = json_decode(Net::S('https://api.waryono.my.id/rspayload.php', 'POST', $data, json: true) ?: '', true);
                #var_dump($res);
                if (isset($res['Payload'])) {
                    $token = $res['Payload'];
                    break;
                }
            }
        }
       */
       
        if (!$token) {
            # this is got 2 method and auto pass
            $rss = new rsResponse($this->ua, $this->host, $this->id);
            $token = $rss->exec($utils, $x, $y);
            
        }
        return $token;

    }
    
}
