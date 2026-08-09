<?php

final class alCaptcha {
    
    private static $attempts = [];
    
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
    
    private function _solve($api, $img, $force = false) {
        return Solve::img($api, $this->host, 'adslab', $img, [], $force);
    }
    
    public function exec($alc, $api, $html = null, $ppp = false) {
        
        $_I = $alc['sid'] ?? 'widget_user';
        $_T = $alc['version'] ?? 'v1';
        $_D = $alc['extra'] ?? null;
        $_K = $alc['keys'] ?? null;
        $_W = $_D['js'] ?? null;
        if (!$_K) return false;
        
        $cacheKey = md5($_K . $this->host);
        self::$attempts[$cacheKey] = (self::$attempts[$cacheKey] ?? 0) + 1;
        $force = $ppp ?? (self::$attempts[$cacheKey] >= 3);
        
        $_H = 'https://adslab.me/api/'.$_T;
        
        $cc_po = [
            'sitekey' => $_K,
            'domain' => parse_url($this->host)['host'] ?? '',
            'subid' => $_I
        ];
        $cc_0 = json_decode(Net::X(
            $_H."/alcaptcha/init", 'POST', $cc_po,
            null, [], $this->host, $this->ua, json: 1
        )?: '', 1);
        
        $soll = null;
        if ($cc_0 && isset($cc_0['captchaUrl'])) {
            $sett = microtime(1);
            
            $cc_po = null;
            $img = _get($cc_0['captchaUrl']);
            if ($img !== null) {
                $jawaban = $this->_solve($api, $img, $force);
                
                if (isset($jawaban['trouble'])) {
                    if (self::$attempts[$cacheKey] >= 3) {
                        unset(self::$attempts[$cacheKey]);
                    }
                    return $soll;
                }
                
                if (is_string($jawaban) && str_contains($jawaban, 'idx=')) {
                    $matches = Scraper::_jP($jawaban, '/idx=(\d+)/');
                    $angka = array_map('intval', $matches[1] ?? []);
                } else {
                    $ans = Scraper::_jP($jawaban, '/\d+/');
                    $angka = array_map('intval', ($ans[0] ?? []));
                }
                
                $clk = [];
                $total = count($angka);
                for ($i = 0; $i < $total; $i++) {
                    if ($i === 0) {
                        $clk[] = intval(microtime(true) * 1000);
                    } else {
                        usleep(rand(800, 1500) * 1000);
                        $clk[] = intval(microtime(true) * 1000);
                    }
                }
                
                $endd = microtime(true);
                $hp_time = intval(($endd - $sett) * 1000);
                
                $imgData = getimagesizefromstring($img);
                $imgWidth = $imgData[0] ?? 400;
                $imgHeight = $imgData[1] ?? 300;
                
                $payloadOld = [
                    'token' => $cc_0['token'],
                    'answer' => $angka,
                    'hp_field' => '',
                    'hp_time' => $hp_time,
                    'deviceTimezone' => TIMEZONE(),
                    'mouseMoves' => count($angka) + rand(0, 3),
                    'clickTimes' => $clk
                ];
                
                $cc_1 = json_decode(Net::X(
                    $_H."/alcaptcha/verify-click", 'POST',
                    $payloadOld, null, [],
                    $this->host, $this->ua, json: 1
                ) ?: '', 1);
                
                if (!$cc_1 || !isset($cc_1['success']) || !$cc_1['success']) {
                    
                    $coordinates = $this->indexToCoordinates($angka, $imgWidth, $imgHeight);
                    
                    $p_payload = $this->buildPayload($coordinates, $clk, $hp_time);
                    
                    $payloadNew = [
                        'token' => $cc_0['token'],
                        'p_payload' => $p_payload
                    ];
                    
                    $cc_1 = json_decode(Net::X(
                        $_H."/alcaptcha/verify-click", 'POST',
                        $payloadNew, null, [],
                        $this->host, $this->ua, json: 1
                    ) ?: '', 1);
                }
                
                if ($cc_1 && isset($cc_1['success']) && $cc_1['success']) {
                    $soll = [
                        'alcaptcha-response' => $cc_1['token'] ?? $cc_0['token']
                    ];
                    unset(self::$attempts[$cacheKey]);
                }
            }
            
        } else {
            Logger::X('info', "\rSolve [ ".static::class.' ] ', false, 1);
            Logger::X('err', $cc_1['message'] ?? $cc_0['message'] ?? 'unknown error');
            unset(self::$attempts[$cacheKey]);
        }
        
        if (isset(self::$attempts[$cacheKey]) && self::$attempts[$cacheKey] >= 3) {
            unset(self::$attempts[$cacheKey]);
        }
        
        return $soll;
    }
    
    private function indexToCoordinates(array $indices, $imgWidth, $imgHeight): array {
        $cols = 3;
        $rows = 3;
        $cellWidth = $imgWidth / $cols;
        $cellHeight = $imgHeight / $rows;
        $coords = [];
        
        foreach ($indices as $idx) {
            $col = $idx % $cols;
            $row = floor($idx / $cols);
            
            $x = ($col * $cellWidth) + ($cellWidth / 2) + rand(-8, 8);
            $y = ($row * $cellHeight) + ($cellHeight / 2) + rand(-8, 8);
            
            $x = max(5, min($imgWidth - 5, $x));
            $y = max(5, min($imgHeight - 5, $y));
            
            $coords[] = [
                'x' => (int)$x, 
                'y' => (int)$y,
                'w' => $imgWidth,
                'h' => $imgHeight
            ];
        }
        
        return $coords;
    }
    
    private function buildPayload(array $coordinates, array $clickTimes, $hp_time) {
        
        $positionNames = ['top-left', 'top-center', 'top-right', 'center-left', 'center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right'];
        shuffle($positionNames);
        $positions = array_slice($positionNames, 0, count($coordinates));
        
        
        $mouseMoves = count($coordinates) + rand(0, 6);
        
        $clickTimes = array_map(function($t, $i) {
            return $t + rand(-30, 30);
        }, $clickTimes, array_keys($clickTimes));
        
        $width = 400;
        $height = 300;
        if (!empty($coordinates)) {
            $width = $coordinates[0]['w'] ?? 400;
            $height = $coordinates[0]['h'] ?? 300;
        }
        
        $coordinates = array_map(function($coord) {
            return [
                'x' => $coord['x'] + rand(-2, 2),
                'y' => $coord['y'] + rand(-2, 2),
                'w' => $coord['w'],
                'h' => $coord['h']
            ];
        }, $coordinates);
        
        $payload = [
            'a' => $positions,
            'vt' => array_map(function($coord) use ($width, $height) {
                return base64_encode("{$coord['x']}|{$coord['y']}|{$width}|{$height}");
            }, $coordinates),
            'hf' => '',
            'ht' => $hp_time + rand(-100, 100),
            'dz' => TIMEZONE(),
            'mm' => $mouseMoves,
            'ct' => $clickTimes
        ];
        
        return strrev(base64_encode(json_encode($payload)));
    }

    
}