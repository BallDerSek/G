<?php

final class uCaptcha {
    use WorkDir;

    private string $host, $ua, $ck, $ip;
    private bool $in;
    private string $html;

    private const _UUID1 = 'd84f6b20-3d9e-4a87-9c15-5b8e2f7a4c91';
    private const _UUID3 = '91e70c5f-a2b8-4d64-8fb3-c6a9d1e84f72';
    private const _EINFO = 'encryption';
    private const _AINFO = 'authentication';

    public function __construct(array $ctx) {
        $this->host = $ctx['host'] ?? '';
        $this->html = $ctx['html'] ?? '';
        $this->ua = $ctx['uagent'] ?? '';
        $this->ck = $ctx['cookie'] ?? '';
        $this->in = $ctx['ins'] ?? false;
        $this->ip = $ctx['ip'] ?? '';

        $this->workDir = $this->setupWorkDir('ucaptcha', $this->host, $ctx['id'] ?? null);
    }

    public function exec(array $ucap, $headersOnly = false) {
        if (!getDeps('nodejs')) {
            Logger::X('err', 'nodejs missing');
            exit;
        }
    
        return styler("SOLVING uCaptcha", function () use ($ucap, $headersOnly) {
            try {
                if (!$ucap) return '';
    
                $_D = $ucap['extra'];
                $_M = $ucap['mods'] ?? '';
                $_K = $ucap['keys'] ?? null;
                $_S = $_D['sec'] ?? null;
                $_A = $_D['app'] ?? null;
    
                $isNewFlow   = false;
                $encryptKey  = null;
                $csrfToken   = null;
                $csrfHash    = null;
                $fingerprint = null;    
                if (in_array(null, [$_K, $_S, $_A], true)) {
                    $keys = $this->_keys($_D['js'] ?? []);
                    if (!$keys) return '';
    
                    [$_K, $_S, $_A, $encryptKey] = array_pad($keys, 4, null);
    
                    if (!empty($encryptKey) && (empty($_K) || empty($_S))) {
                        $isNewFlow = true;
                    }
                }
    
                if ($isNewFlow && $encryptKey) {
                    $credentials = $this->_initNewFlow($encryptKey);
                    if (!$credentials) return '';
                    $_K = $credentials['apiKey'];
                    $_S = $credentials['secretKey'];
                    $_A = $credentials['appUrl'] ?? $_A;
                    $csrfToken = $credentials['csrfToken'] ?? null;
                    $csrfHash = $credentials['csrfHash'] ?? null;
                    $fingerprint = $credentials['fingerprint'] ?? null;
                }
    
                $isUc = ($_M === 'upside_captcha');
                $_fp  = $this->fingerprint($isUc);
                $_sh  = $this->_enc($_fp, $_K, $_S);
    
                if ($headersOnly) {
                    if ($isNewFlow) {
                        return [
                            'headers' => [
                                'app-fingerprint-hash' => $fingerprint,
                                'app-server-hash'      => $_sh,
                            ],
                            'solution' => [
                                $csrfToken => $csrfHash,
                            ],
                        ];
                    }
                    
                    return ['headers' => ['x-server-hash' => $_sh]];
                }
    
                $result = $isUc
                    ? $this->_uC($_A, $_fp, $_sh, $_K, $_S)
                    : $this->_aC($_A, $_fp, $_sh, $_K, $_S);
    
                if ($isNewFlow && isset($csrfToken, $csrfHash) && is_array($result)) {
                    $result['solution'][$csrfToken] = $csrfHash;
                    $fpHeader = "app-fingerprint-hash: $fingerprint";
                    $result['headers'] = $result['headers']
                        ? $result['headers'] . "\r\n" . $fpHeader
                        : $fpHeader;
                }
    
                return $result;
            } finally {
                $this->rmdir($this->workDir);
            }
        });
    }

    private function _initNewFlow($encryptKey) {

        $masterKey = $this->_decryptEncryptKey($encryptKey);
        if (!$masterKey) return null;

        $fingerprint = md5(IP() . $this->ua);

        $payload = [
            'fingerprint' => $fingerprint,
            'fingerprintShort' => substr($fingerprint, 0, 8),
            'userAgent' => $this->ua,
            'screenWidth' => 1920,
            'screenHeight' => 1080,
            'lang' => LANGUAGE(),
            'langs' => LANGUAGE(),
            'timezone' => TIMEZONE(),
            'device' => $this->_devices(),
            'pageUrl' => $this->host,
            'timestamp' => time(),
        ];

        $encrypted = $this->_encWebCrypto($payload, $masterKey);
        if (!$encrypted) return null;

        $res = Net::X(
            rtrim($this->host, '/') . '/reverse',
            'GET',
            null,
            $this->ck,
            ['App-Encrypted-Data: ' . $encrypted],
            $this->host,
            $this->ua,
            foll: false,
            ip: $this->ip,
            ins: $this->in
        );
        #var_dump($res);
        if (!$res || $res === 99) return null;

        $data = json_decode($res, true);
        if (empty($data) || ($data['status'] ?? '') === 'error') return null;

        $decrypted = null;
        if (isset($data[$fingerprint])) {
            $decrypted = $this->_decWebCrypto($data[$fingerprint], $masterKey);
            if (!$decrypted) $decrypted = $this->_dec($data[$fingerprint], $masterKey, $masterKey);
        }
        #var_dump($decrypted);
        
        if (!$decrypted) return null;

        return [
            'apiKey' => $decrypted['apiKey'] ?? $masterKey,
            'secretKey' => $decrypted['secretKey'] ?? $masterKey,
            'csrfToken' => $decrypted['csrfToken'] ?? 'csrf_token_name',
            'csrfHash' => $decrypted['csrfHash'] ?? '',
            'appUrl' => $decrypted['appUrl'] ?? null,
            'fingerprint' => $fingerprint,
        ];
    }

    private function _decryptEncryptKey($encryptKey) {
        $data = str_replace(['-', '_'], ['+', '/'], $encryptKey);
        while (strlen($data) % 4) $data .= '=';

        $raw = base64_decode($data);
        if (!$raw || strlen($raw) < 48) return null;

        $combined = hash('sha512', self::_UUID1 . self::_UUID3, true);
        $encKey = hash_hmac('sha256', self::_EINFO, $combined, true);
        $authKey  = hash_hmac('sha256', self::_AINFO, $combined, true);

        $iv = substr($raw, 0, 16);
        $hmac = substr($raw, 16, 32);
        $ciphertext = substr($raw, 48);

        $verify = hash_hmac('sha256', $iv . $ciphertext, $authKey, true);
        if (!hash_equals($verify, $hmac)) return null;

        $decrypted = openssl_decrypt(
            $ciphertext,
            'AES-256-CBC',
            $encKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if (!$decrypted) return null;

        $padLen = ord(substr($decrypted, -1));
        if ($padLen > 16 || $padLen === 0) return null;
        $decrypted = substr($decrypted, 0, -$padLen);

        $json = json_decode($decrypted, true);
        if (!$json) return $decrypted;

        if (isset($json['__exp']) && time() > $json['__exp']) return null;

        return $json['value'] ?? $decrypted;
    }

    private function _encWebCrypto(array $data, $masterKey) {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $encKey  = $this->_hkdf($masterKey, self::_EINFO, 32);
        $authKey = $this->_hkdf($masterKey, self::_AINFO, 64);
        if (!$encKey || !$authKey) return null;

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($json, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
        if (!$ciphertext) return null;

        $base64 = base64_encode($iv . $ciphertext);
        $hmac = hash_hmac('sha512', $base64, $authKey, true);

        return base64_encode(bin2hex($hmac) . $base64);
    }

    private function _decWebCrypto($data, $masterKey) {
        $data = base64_decode($data);
        if (!$data) return null;

        $hmacLen = 128;
        if (strlen($data) <= $hmacLen) return null;

        $hmacHex = substr($data, 0, $hmacLen);
        $base64  = substr($data, $hmacLen);

        $hmac = hex2bin($hmacHex);
        if (!$hmac) return null;

        $authKey = $this->_hkdf($masterKey, self::_AINFO, 64);
        if (!$authKey) return null;

        $verify = hash_hmac('sha512', $base64, $authKey, true);
        if (!hash_equals($verify, $hmac)) return null;

        $raw = base64_decode($base64);
        if (!$raw || strlen($raw) < 17) return null;

        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        $encKey = $this->_hkdf($masterKey, self::_EINFO, 32);
        if (!$encKey) return null;

        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $encKey, OPENSSL_RAW_DATA, $iv);
        if (!$decrypted) return null;

        return json_decode($decrypted, true);
    }

    private function _hkdf($ikm, $info, $length) {
        $prk = hash_hmac('sha512', $ikm, '', true);

        $output  = '';
        $block = '';
        $counter = 1;

        while (strlen($output) < $length) {
            $block = hash_hmac('sha512', $block . $info . chr($counter), $prk, true);
            $output .= $block;
            $counter++;
        }

        return substr($output, 0, $length);
    }

    private function _derive($secret, $salt) {
        $masterKey = hash('sha512', $secret . $salt, true);
        return [
            'enc'  => hash_hmac('sha256', 'encryption',     $masterKey, true),
            'auth' => hash_hmac('sha256', 'authentication', $masterKey, true),
        ];
    }

    private function _enc($data, $apiKey, $secKey) {
        $_key = $this->_derive($apiKey, $secKey);

        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $_ivv = random_bytes(16);
        $_cip = openssl_encrypt($data, 'aes-256-cbc', $_key['enc'], OPENSSL_RAW_DATA, $_ivv);
        $_sgn = hash_hmac('sha256', $_ivv . $_cip, $_key['auth'], true);
        $solution = base64_encode($_ivv . $_sgn . $_cip);

        return rtrim(strtr($solution, '+/', '-_'), '=');
    }

    private function _dec($data, $apiKey, $secKey) {
        $data = strtr($data, '-_', '+/');
        while (strlen($data) % 4) $data .= '=';
        $raw = base64_decode($data);

        $_key = $this->_derive($apiKey, $secKey);
        $_ivv = substr($raw, 0, 16);
        $_sgn = substr($raw, 16, 32);
        $_cip = substr($raw, 48);

        $expect = hash_hmac('sha256', $_ivv . $_cip, $_key['auth'], true);
        if (!hash_equals($expect, $_sgn)) return null;

        $decrypt = openssl_decrypt($_cip, 'aes-256-cbc', $_key['enc'], OPENSSL_RAW_DATA, $_ivv);
        $json = json_decode($decrypt, true);
        return $json ?? $decrypt;
    }

    private function _solve(array $ch, $app) {
        $key = $ch['anti_captcha_key'];
        $qImg = $ch['question_image'];
        $icons = $ch['icons'];

        $base = $this->workDir;
        $hashFile = LIBDIR . '/anticaptcha.json';
        $hashes = file_exists($hashFile) ? json_decode(_get($hashFile), true) : [];

        $main = Net::X($qImg, 'GET', null, $this->ck, [], $this->host, $this->ua, false, false, $this->ip, $this->in);
        if (empty($main) || $main === 99) return null;
        _put($base . '/main.png', $main);

        $qA = SolveUtils::aHash($base . '/main.png');
        $qD = SolveUtils::dHash($base . '/main.png');
        @unlink($base . '/main.png');

        $best      = null;
        $bestScore = PHP_INT_MAX;

        foreach ($icons as $icon) {
            if (isset($hashes[$icon])) {
                $iA = $hashes[$icon]['a'];
                $iD = $hashes[$icon]['d'];
            } else {
                $iconUrl  = "$app/assets/anticap/icons/" . rawurlencode($icon);
                $iconData = Net::X($iconUrl, 'GET', null, $this->ck, [], $this->host, $this->ua, false, false, $this->ip, $this->in);
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

    private function _uC($app, array $fp, $hash, $apiKey, $secKey) {
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
        $upsideSecret = $this->_enc($this->fingerprint(true), $apiKey, $secKey);
        $validationSecret = $this->_enc($validation, $apiKey, $secKey);

        $solution = [
            'answer' => $answer['index'],
            'hash' => $answer['hash'],
            'upside-secret' => $upsideSecret,
            'validation-secret' => $validationSecret,
        ];

        return ['solution' => $solution, 'headers' => "X-Server-Hash: $hash"];
    }

    private function _aC($app, array $fp, $hash, $apiKey, $secKey) {
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
            $this->host, $this->ua, foll: false, ip: $this->ip, ins: $this->in
        ) ?: '', 1)['status'] ?? null;

        if ($status !== 'valid') return false;

        $solution = [
            'anti_captcha_token' => $token,
            'anti_captcha_key' => $key,
            'anti_captcha_selected_icon' => $icon,
            'anti_hash' => $this->_enc($fp, $apiKey, $secKey),
        ];

        return ['solution' => $solution, 'headers' => ''];
    }

    private function _keys(array $jsUrls): array|false {
        $urls = [];
        foreach ($jsUrls as $u) {
            if (empty($u)) continue;
            if (!preg_match('#^https?://#', $u)) {
                $u = rtrim($this->host, '/') . '/' . ltrim($u, '/');
            } else {
                if (str_contains($u, $this->host)) {
                    $urls[] = $u;
                    continue;
                }
            }
            if (filter_var($u, FILTER_VALIDATE_URL)) $urls[] = $u;
        }
        if (empty($urls)) return false;

        $api = null;
        $app = null;
        $sec = null;
        $enc = null;

        foreach ($urls as $url) {
            $js = Net::X($url, 'GET', null, $this->ck, [], $this->host, $this->ua, foll: false, ip: $this->ip, ins: $this->in);
            if ($js === 99 || empty($js)) continue;
    
            $keywords = ['litoshi_api_key', 'litoshi_secret_key', 'encryptKey', 'app_url'];
            $hasKey = false;
            foreach ($keywords as $kw) {
                if (str_contains($js, $kw)) {
                    $hasKey = true;
                    break;
                }
            }
    
            if (!$hasKey) $js = solveUtils::dumpJs($js);
    
            $api = Scraper::_jP($js, "/litoshi_api_key\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
            $sec = Scraper::_jP($js, "/litoshi_secret_key\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
            $app = Scraper::_jP($js, "/app_url\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;

            $enc = null;
            if (str_contains($js, 'encryptKey')) $enc = Scraper::_jP($js, "/(?:window\.)?encryptKey\s*=\s*['\"]([^'\"]+)['\"]/")[1][0] ?? null;
    
            if (!empty($api) || !empty($sec) || !empty($enc)) return [$api, $sec, $app, $enc];
        }
    
        return false;
    }

    private function fingerprint($isUc): array {
        $ts = time();
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
            'X-Timestamp' => $ts,
            'X-Page-Url' => $this->host,
            'X-Device' => $this->_devices(),
        ];

        if ($isUc) {
            $base += [
                'X-Ip' => IP(),
                'X-Hash' => hash('sha256', $this->ua . LANGUAGE() . '437' . '973' . 'false'),
                'X-Browser' => $this->_browser(),
                'X-Browser-Private-Window'  => false,
            ];
        }

        return $base;
    }

    private function _devices() {
        $ua = $this->ua;
        if (preg_match('/mobile/i', $ua)) return 'mobile';
        if (preg_match('/tablet|ipad|playbook/i', $ua)) return 'tablet';
        return 'desktop';
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
