<?php

final class uCaptcha {
    use WorkDir;

    private string $host, $ua, $ck, $ip;
    private bool $in;
    private string $html;

    public function __construct(array $ctx) {
        $this->host = $ctx['host'] ?? '';
        $this->html = $ctx['html'] ?? '';
        $this->ua = $ctx['uagent'] ?? '';
        $this->ck = $ctx['cookie'] ?? '';
        $this->in = $ctx['ins'] ?? false;
        $this->ip = $ctx['ip'] ?? '';
        
        $this->workDir = $this->setupWorkDir('ucaptcha', $this->host);
    }

    public function exec(array $ucap) {
        try {
            if (!$ucap) return false;

            $_D = $ucap['extra'];
            $_M = $ucap['mods'] ?? '';
            $_K = $ucap['keys'] ?? null;
            $_S = $_D['sec'] ?? null;
            $_A = $_D['app'] ?? null;

            if (in_array(null, [$_K, $_S, $_A], true)) {
                $keys = $this->_keys($_D['js'] ?? []);
                if (!$keys) return false;
                [$_K, $_S, $_A] = $keys;
            }

            $isUc = ($_M === 'upside_captcha');
            $fingerprint = $this->fingerprint($isUc);
            $serverHash  = _enc($fingerprint, $_K, $_S);

            return $isUc
                ? $this->_uC($_A, $fingerprint, $serverHash, $_K, $_S)
                : $this->_aC($_A, $fingerprint, $serverHash, $_K, $_S);
        } finally {
            $this->rmdir($this->workDir);
        }
    }

    private function _solve(array $ch, $app): ?array {
        $key = $ch['anti_captcha_key'];
        $qImg = $ch['question_image'];
        $icons = $ch['icons'];

        $base = $this->workDir;
        $hashFile = LIBDIR.'/anticaptcha.json';
        $hashes = file_exists($hashFile) ? json_decode(_get($hashFile), true) : [];

        $main = Net::X($qImg, 'GET', null, $this->ck, [], $this->host, $this->ua);
        if (empty($main) || $main === 99) return null;
        _put($base . '/main.png', $main);

        $qA = SolveUtils::aHash($base . '/main.png');
        $qD = SolveUtils::dHash($base . '/main.png');
        @unlink($base . '/main.png');

        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($icons as $icon) {
            if (isset($hashes[$icon])) {
                $iA = $hashes[$icon]['a'];
                $iD = $hashes[$icon]['d'];
            } else {
                $iconUrl = "$app/assets/anticap/icons/$icon";
                $iconData = Net::X($iconUrl, 'GET', null, $this->ck, [], $this->host, $this->ua);
                if (empty($iconData) || $iconData === 99) continue;
                _put("$base/$icon", $iconData);
                $iA = SolveUtils::aHash("$base/$icon");
                $iD = SolveUtils::dHash("$base/$icon");
                @unlink("$base/$icon");

                $hashes[$icon] = ['a' => $iA, 'd' => $iD];
                if (count($hashes) > 500) $hashes = array_slice($hashes, -500, 500, true);
                _put($hashFile, json_encode($hashes));
            }

            if (!$iA || !$iD) continue;
            $score = SolveUtils::hamming($qA, $iA) + SolveUtils::hamming($qD, $iD);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $icon;
            }
        }

        return $best ? [$key, $best] : null;
    }
    
    private function _uC($app, array $fp, $hash, $apiKey, $secretKey) {
        $data = json_decode(Net::X(
            $app . 'captcha/get_captcha', 'GET', null, $this->ck,
            ["X-Server-Hash: $hash"],
            $this->host, $this->ua, foll: false, ip: $this->ip, ins: $this->in
        ) ?: '', 1);
        
        $positions = $data['iconPositions'] ?? null;
        if (empty($positions)) return false;
        
        $answer = null;
        foreach ($positions as $pos) {
            if (!empty($pos['flipped'])) {
                $answer = $pos;
                break; 
            }
        }
        if (!$answer) return false;
        
        $validation = [
            'U-Answer' => $answer['index'],
            'U-Hash' => $answer['hash'],
            'U-Full-Res' => $positions,
        ];
        $upsideSecret = _enc($this->fingerprint(true), $apiKey, $secretKey);
        $validationSecret = _enc($validation, $apiKey, $secretKey);
/*
// === DEBUG ===
$decrypted = _dec($upsideSecret, $apiKey, $secretKey);
$json1 = json_encode($fp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$json2 = json_encode($decrypted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
logx('DEBUG', 'FP: ' . $json1);
logx('DEBUG', 'DC: ' . $json2);
logx('DEBUG', 'Match: ' . ($json1 === $json2 ? 'YES' : 'NO'));
// === END ===
*/
        return [
            'answer' => $answer['index'],
            'hash' => $answer['hash'],
            'upside-secret' => $upsideSecret,
            'validation-secret' => $validationSecret,
        ];
    }

    private function _aC($app, array $fp, $hash, $apiKey, $secretKey) {

        $token = json_decode(Net::X(
            $app . 'anticap/get_token', 'GET', null, $this->ck,
            [], $this->host, $this->ua
        ), 1)['token'] ?? null;
        if (!$token) return false;

        $ch = json_decode(Net::X(
            $app . 'anticap/get_challenge', 'GET', null, $this->ck,
            ["X-Server-Hash: $hash"],
            $this->host, $this->ua, foll: false, ip: $this->ip, ins: $this->in
        ) ?: '', 1);
        if (empty($ch['question_image'])) return false;

        $solved = $this->_solve($ch, rtrim($app, '/'));
        if (!$solved) return false;
        [$key, $icon] = $solved;

        $status = json_decode(Net::X(
            $app . 'anticap/validate_choice', 'POST',
            ['selected' => $icon, 'key' => $key, 'token' => $token],
            $this->ck,
            ['X-Captcha-Header: anticap-v1'],
            $this->host, $this->ua
        ) ?: '', 1)['status'] ?? null;

        if ($status !== 'valid') return false;

        return [
            'anti_captcha_token' => $token,
            'anti_captcha_key' => $key,
            'anti_captcha_selected_icon' => $icon,
            'anti_hash' => _enc($fp, $apiKey, $secretKey),
        ];
    }

    private function _keys(array $jsUrls): array|false {
        $urls = [];
        foreach ($jsUrls as $u) {
            if (empty($u)) continue;
            if (!preg_match('#^https?://#', $u)) {
                $u = rtrim($this->host, '/') . '/' . ltrim($u, '/');
            }
            if (filter_var($u, FILTER_VALIDATE_URL)) $urls[] = $u;
        }
        if (empty($urls)) return false;
        
        #print_r($urls); die;
        foreach ($urls as $url) {
            $js = Net::X($url, 'GET', null, $this->ck, [], $this->host, $this->ua, foll: false, ip: $this->ip, ins: $this->in);
            if ($js === 99 || empty($js)) continue;
            if (!str_contains($js, 'litoshi_api_key')) $js = dumpJsFlex($js);
            #_put('u.js', $js);
            $api = Scraper::_jP($js, "/litoshi_api_key\s*=\s*['\"]([^'\"]+)['\"]/");
            $sec = Scraper::_jP($js, "/litoshi_secret_key\s*=\s*['\"]([^'\"]+)['\"]/");
            $app = Scraper::_jP($js, "/app_url\s*=\s*['\"]([^'\"]+)['\"]/");

            if (!empty($api[1][0]) && !empty($sec[1][0])) {
                return [$api[1][0], $sec[1][0], $app[1][0] ?? null];
            }
        }
        return false;
    }

    private function fingerprint($isUc): array {
        $base = [
            'X-Uid' => md5(IP() . $this->ua),
            'X-Ai' => $isUc ? 'LitoshiPay' : 'AntiCaptcha',
            'X-Agent' => $this->ua,
            'X-Screen-Width' => 437,
            'X-Screen-Height' => 973,
            'X-Color-Depth' => 24,
            'X-Device-Pixel-Ratio' => 2.1,
            'X-Lang' => LANGUAGE(),
            'X-Langs' => LANGUAGE(),
            'X-Timezone' => TIMEZONE(),
            'X-Referrer' => $this->host,
            'X-Title' => Scraper::title($this->html),
            'X-Timestamp' => time(),
            'X-Page-Url' => $this->host,
            'X-Device' => $this->_devices(),
        ];
        
        if ($isUc) {
            $base += [
                'X-Ip' => IP(),
                'X-Hash' => hash('sha256', $this->ua . LANGUAGE() . '437' . '973' . 'false'),
                'X-Browser' => $this->_browser(),
                'X-Browser-Private-Window' => false,
            ];
        }
    
        return $base;
        
    }
    
    private function _devices() {
        $ua = $this->ua;
        if (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false) return 'Mobile';
        if (stripos($ua, 'Tablet') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'PlayBook') !== false) return 'Tablet';
        return 'Desktop';
    }
    
    private function _browser() {
        $ua = $this->ua;
        if (stripos($ua, 'Edg/') !== false) return 'Edge';
        if (stripos($ua, 'Chrome') !== false && stripos($ua, 'Edg/') === false) return 'Chrome';
        if (stripos($ua, 'Firefox') !== false) return 'Firefox';
        if (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) return 'Safari';
        return 'Chrome';
    }
    
}

