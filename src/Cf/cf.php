<?php

class Cloudflare {
    
    public static function parseResult($class, $res) {
        if (!$res) return null;

        $className = strtolower($class);
        
        #print_r($res); die;
        
        if (str_contains($className, 'xevil')) {
            $decoded = json_decode(base64_decode($res), true);
            #print_r($decoded); #die;
            return [
                'token' => $decoded['cf_clearence'] ?? null,
                'ua' => $decoded["user_agent"] ?? null,
            ];
        }

        if (str_contains($className, 'gmxch') || str_contains($className, 'glitch')) {
            return [
                'token' => $res['cf_clearance'] ?? $res['clearance'] ?? null,
                'ua'    => $res['user_agent'] ?? null,
            ];
        }

        if (str_contains($className, 'tertuyul')) {
            $part = explode('::', $res, 2);
            #print_r($part);
            return [
                'token' => $part[0] ?? null,
                'ua' => $part[1] ?? null,
            ];
        }

        return null;
    }

    public static function exec($api, $url, $cookiePath, $uagent, array $data = []) {
        
        if (!$api) die(Logger::X('err', 'undefined provider'));
        
        $param = array_filter([
            'body' => !empty($data['html']) ? base64_encode($data['html']) : null,
            'userAgent' => $uagent,
            'proxy' => $GLOBALS['_CTX']['proxy']['src'] ?? null
        ]);

        $solver = config::getKeys($api, 'interstitial', 'acc');
        $solve = $solver->access($url, 'interstitial', $param);
        
        // Handling node busy
        if ($solve === 777 || (is_array($solve) && ($solve[1] ?? null) === 777)) {
            if (isset(Api::ACC[get_class($api)]['interstitial'])) {
                $solve = $api->access($url, 'interstitial', $param);
                if ($solve === 71) return false;
            }
        }
        
        if (is_array($solve) && !empty($solve[1])) {
            if ($solver instanceof Provider) $api->getInfo();
            
            [$_cl, $_cf] = $solve;
            $solution = self::parseResult($_cl, $_cf);
            
            #print_r($solution); #die;
            
            if ($solution && isset($solution['token'])) {
                self::injectCookie($cookiePath, $solution['token'], $url);
                
                return [
                    inf::netHead(['cf_clearance' => $solution['token']]),
                    $solution['ua']
                ];
            }
        }
        return false;
    }

    public static function injectCookie($cookiePath, $token, $url) {
        if (empty($cookiePath) || !file_exists($cookiePath)) return false;
        
        # cari aman, inject cookie, biar gak boros kalau rerun karna solution headers hanya ada di memory
        
        $domain = parse_url($url, PHP_URL_HOST);
        $cookieDomain = '.' . ltrim($domain, '.');
        $secure = (parse_url($url, PHP_URL_SCHEME) === 'https') ? "TRUE" : "FALSE";
        
        $lines = file($cookiePath, FILE_IGNORE_NEW_LINES);
        $filtered = [];
        
        foreach ($lines as $l) {
            if ($l === '' || $l[0] === '#') { $filtered[] = $l; continue; }
            $cols = explode("\t", $l);
            if (count($cols) >= 7 && $cols[5] === 'cf_clearance') continue;
            $filtered[] = $l;
        }
        
        $filtered[] = implode("\t", [$cookieDomain, "TRUE", "/", $secure, time() + 43200, "cf_clearance", $token]);
        return _put($cookiePath, implode("\n", $filtered) . "\n");
    }
    
}
