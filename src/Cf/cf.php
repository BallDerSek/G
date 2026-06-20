<?php

/*
function cfSet($class, $res) {
    if (!$res) return null;
    
    #print_r($res); die;

    switch (strtolower($class)) {
        case str_contains(strtolower($class), 'xevil'):
            $decoded = json_decode(base64_decode($res), true);
            return [
                'token' => $decoded['cf_clearance'] ?? null,
                'ua' => $decoded['user_agent'] ?? null,
            ];

        case str_contains(strtolower($class), 'gmxch'):
        case str_contains(strtolower($class), 'glitch'):
            return [
                'token' => $res['cf_clearance'] ?? $res['clearance'] ?? null,
                'ua'    => $res['user_agent'] ?? null,
            ];

        #case str_contains(strtolower($class), 'multibot'):
        case str_contains(strtolower($class), 'tertuyul'):
            $part = explode(':', $res, 2);
            return [
                'token' => $part[0] ?? null,
                'ua' => $part[1] ?? null,
            ];

        default:
            return null;
    }
}

function execCF($api, $url, $cookie, $uagent, array $data = []) {
    
    if (!$api) (logx('err', 'undefined provider') ?: die);
    
    $param = array_filter([
        'body' => !empty($data['html']) ? base64_encode($data['html']) : null,
        'userAgent' => $uagent,
        'proxy' => $GLOBALS['_CTX']['proxy']['src'] ?? null
    ]);

    #logx('info', 'param for solver');
    #print_r($param);

    $solver = config::getKeys($api, 'interstitial', 'acc');
    $solve = $solver->access($url, 'interstitial', $param);
    
    if ($solve === 777 || (is_array($solve) && ($solve[1] ?? null) === 777)) {
        logx('warn', "Internal Node Busy, fallback direct api", false);
        _clr();
        
        if (isset(Api::ACC[get_class($api)]['interstitial'])) {
            $solve = $api->access($url, 'interstitial', $param);
            if ($solve === 71) {
                return false;
            }
        }
    }
    
    if (is_array($solve) && !empty($solve[1])) {
        if ($solver instanceof Provider) $api->getInfo();

        #logx('info', 'param from solver');
        #print_r($solve); #die;

        [$_cl, $_cf] = $solve;
        $solution = cfSet($_cl, $_cf);
        #print_r($solution);
        return setCF($solution, $cookie, $url);
        
    }
    return false;
    
}

function setCF($r, $c, $host) {
    #print_r($r);
    if (is_array($r) && isset($r['token'])) {
        
        
        $execPy = new execPython($c, $r['ua']);
        $clearance = "cf_clearance={$r['token']}";
        $execPy->cfCookie($clearance, $host);

        $solution = [
            inf::netHead(['cf_clearance' => $r['token']]),
            $r['ua']
        ];
        return $solution;
    }
    
    return false;
}
*/



class Cloudflare {
    
    public static function parseResult($class, $res) {
        if (!$res) return null;

        $className = strtolower($class);

        if (str_contains($className, 'xevil')) {
            $decoded = json_decode(base64_decode($res), true);
            return [
                'token' => $decoded['cf_clearance'] ?? null,
                'ua' => $decoded['user_agent'] ?? null,
            ];
        }

        if (str_contains($className, 'gmxch') || str_contains($className, 'glitch')) {
            return [
                'token' => $res['cf_clearance'] ?? $res['clearance'] ?? null,
                'ua'    => $res['user_agent'] ?? null,
            ];
        }

        if (str_contains($className, 'tertuyul')) {
            $part = explode(':', $res, 2);
            return [
                'token' => $part[0] ?? null,
                'ua' => $part[1] ?? null,
            ];
        }

        return null;
    }

    public static function exec($api, $url, $cookiePath, $uagent, array $data = []) {
        
        if (!$api) (logx('err', 'undefined provider') ?: die);
        
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
