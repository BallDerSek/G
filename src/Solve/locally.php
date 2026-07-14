<?php

class locally {
    
    public static function iCaptcha($host, $data, $ctx) {
        $endpoint = $data['endpoint'];
        $token = $data['token'];
        if (!str_starts_with($endpoint, 'http')) {
            $endpoint = rtrim($host, '/') . '/' . ltrim($endpoint, '/');
        }
        
        return styler("SOLVING iCaptcha", function() use ($endpoint, $token, $host, $ctx) {
            if (!$endpoint || !$token) return false;
            
            $ck = $ctx['cookie'];
            $ua = $ctx['uagent'];
            $in = $ctx['ins'];
            $ip = $ctx['ip'];
            
            $widgetID = SolveUtils::widgetID();
            $ts = round(microtime(true) * 1000);
            
            $json = ["payload" => base64_encode(json_encode([
                "widgetId" => $widgetID, "action" => "LOAD", "theme" => "light",
                "token" => $token, "timestamp" => $ts, "initTimestamp" => $ts - 2000
            ]))];
            
            $challengeId = null;
            for ($retry = 0; $retry < 3; $retry++) {
                $he = ["x-iconcaptcha-token: $token"];
                $res = Net::X($endpoint, 'POST', $json, $ck, $he, $host, $ua, false, false, $ip, $in);
                $challengeId = json_decode(base64_decode($res ?: ''), true)['identifier'] ?? null;
                if ($challengeId) break;
            }
            if (!$challengeId) return false;
            #var_dump($res);
            
            // Selection Loop
            for ($i = 0; $i < 5; $i++) {
                $ts = round(microtime(true) * 1000);
                $payload = base64_encode(json_encode([
                    "x" => (int)(($i * 64) + rand(20, 40)), "y" => rand(22, 30),
                    "width" => 320, "token" => $token, "action" => "SELECTION",
                    "widgetId" => $widgetID, "timestamp" => $ts, "challengeId" => $challengeId,
                    "initTimestamp" => $ts - 2000
                ]));
                
                $boundary = '';
                $body = SolveUtils::webkitID(["payload" => $payload], $boundary);
                $head = ["x-iconcaptcha-token: $token", "Content-Type: multipart/form-data; boundary=$boundary"];
                
                $r = json_decode(base64_decode(Net::X($endpoint, 'POST', $body, $ck, $head, $host, $ua, false, false, $ip, $in) ?: ''), true);
                #var_dump($r);
                
                if (!empty($r['completed']) || (isset($r['success']) && $r['success'] == true)) {
                    return [
                        '_iconcaptcha-token' => $token, 'ic-rq' => 1,
                        'ic-wid' => $widgetID, 'ic-cid' => $challengeId, 'ic-hp' => ''
                    ];
                }
                _sle(1);
            }
            return false;
        });
    }

    public static function eCaptcha($host, $ctx) {
        $cookie = inf::$cookie;
        $ua = inf::$uagent;
        $ip = inf::$ip;

        return styler("SOLVING eCaptcha", function() use ($host, $ctx) {
            $ck = $ctx['cookie'];
            $ua = $ctx['uagent'];
            $in = $ctx['ins'];
            $ip = $ctx['ip'];
            
            $res = Net::X($host.'/ecaptcha/get_token', 'GET', null, $ck, [], $host, $ua, ip: $ip, ins: $in);
            #var_dump($res);
            if ($res === 99) return 99;
            $json = json_decode($res ?: '', true);
            $token = $json['token'] ?? null;
            if (!$token) return false;

            $res = Net::X($host.'/ecaptcha/get_captcha', 'GET', null, $ck, [], $host, $ua, ip: $ip, ins: $in);
            if ($res === 99) return 99;
            $task = json_decode($res ?: '', true);
            #print_r($task); die;
            if (empty($task['captcha_key']) || empty($task['question'])) return false;
            
            // 3. Parsing Answer 
            $sel = explode(':', $task['question']);
            $answer = strtolower(trim(end($sel))) . '.gif';
            
            $payload = [
                'key' => $task['captcha_key'],
                'selected' => $answer,
                'token' => $token
            ];
            
            // 4. Validate
            $res = Net::X($host.'/ecaptcha/validate_icon', 'POST', $payload, $ck, [], $host, $ua, ip: $ip, ins: $in);
            #print_r($post); die;
            if ($res === 99) return 99;
            $post = json_decode($res ?: '', true);
            if (($post['status'] ?? '') === 'valid') {
                return [
                    'captcha' => 'emoji_captcha',
                    'captcha_key' => $post['captcha_key'],
                    'captcha_token' => $token,
                    'selected_icon' => $answer
                ];
            } 
            return false;
        });
    }

    public static function smartFP($html) {
        $xpath = Scraper::dom($html);
        $node = $xpath->query("//input[@name='smart_token']")->item(0);
        if ($node) {
            $hasLogic = false;
            $scripts = $xpath->query("//script");
            $id = $node->getAttribute('id');
            foreach ($scripts as $script) {
                $content = $script->textContent;
                if (strpos($content, 'smart_token') !== false || ($id && strpos($content, $id) !== false)) {
                    $hasLogic = true;
                    break;
                }
            }
            if ($hasLogic) {
                $currentValue = $node->getAttribute('value');
                if (empty($currentValue)) {
                    $data = [
                        'ts' => (int)round(microtime(true) * 1000),
                        'cpu' => 8,
                        'mem' => 4,
                        'w' => 1366,
                        'h' => 768,
                        'touch' => 0,
                        'moves' => rand(1, 5)
                    ];
                    return base64_encode(json_encode($data));
                }
            }
        }
        return [];
    }
    
}