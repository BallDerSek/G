<?php

class Cloudflare {
    
    public static function solve($api, $url, $uagent, $data, $force = false) {
        if (!$api) return false;
    
        $param = array_filter([
            'body'  => !empty($data['html']) ? base64_encode($data['html']) : null,
            'proxy' => $GLOBALS['_CTX']['proxy']['src'] ?? null
        ]);
        
        if (empty($param['proxy']) && (getenv("SELEDROID") === '1')) return 'seledroid';
    
        if ($force) {
            $solve = $api->access($url, 'interstitial', $param);
        } else {
            $solver = Config::getKeys($api, 'interstitial', 'acc');
            $solve  = $solver ? $solver->access($url, 'interstitial', $param) : false;
        }
    
        if (is_array($solve) && ($solve['fail'] ?? null) === 777) {
            if (isset(Api::ACC[get_class($api)]['interstitial'])) {
                $solve = $api->access($url, 'interstitial', $param);
            }
        }
    
        if (!is_array($solve) || !isset($solve['done'])) {
            return false;
        }
    
        $class = $solve['class'] ?? null;
        $res   = $solve['done'];
    
        return self::parseResult($class, $res);
    }
    
    public static function parseResult($class, $res) {
        if (!$res) return null;

        $className = strtolower($class);

        if (str_contains($className, 'xevil')) {
            $decoded = json_decode(base64_decode($res), true);

            return [
                'token' => $decoded['cf_clearence'] ?? null,
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
            $part = explode('::', $res, 2);

            return [
                'token' => $part[0] ?? null,
                'ua' => $part[1] ?? null,
            ];
        }

        if (str_contains($className, 'skibidixxx')) {
            if (preg_match('/cf_clearance=([^,]+),\s*user-agent:(.+)/', $res, $matches)) {
                return [
                    'token' => trim($matches[1]),
                    'ua' => trim($matches[2]),
                ];
            }
            
            if (str_contains($res, ', user-agent:')) {
                $parts = explode(', user-agent:', $res, 2);
                $tokenPart = str_replace('cf_clearance=', '', $parts[0]);
                return [
                    'token' => trim($tokenPart),
                    'ua' => trim($parts[1] ?? null),
                ];
            }
        }

        return null;
    }

    public static function exec($api, $url, $cookiePath, $uagent, array $data = [], $force = false) {
        
        $solution = self::solve($api, $url, $uagent, $data, $force);
        
        if ($solution === 'seledroid') $solution = self::seledroid($url, $uagent, $cookiePath);
        
        if (!$solution || empty($solution['token'])) return false;
        #var_dump($solution); die;
        
        self::injectCookie($cookiePath, $solution['token'], $url);

        return [
            ['cf_clearance' => $solution['token']],
            $solution['ua']
        ];
    }

    public static function injectCookie($cookiePath, $token, $url) {
        if (empty($cookiePath) || !file_exists($cookiePath)) return false;

        $domain = parse_url($url, PHP_URL_HOST);
        $cookieDomain = '.' . ltrim($domain, '.');
        $secure = (parse_url($url, PHP_URL_SCHEME) === 'https') ? "TRUE" : "FALSE";

        $lines = file($cookiePath, FILE_IGNORE_NEW_LINES);
        $filtered = [];

        foreach ($lines as $l) {
            if ($l === '' || $l[0] === '#') {
                $filtered[] = $l;
                continue;
            }

            $cols = explode("\t", $l);

            if (count($cols) >= 7 && $cols[5] === 'cf_clearance') continue;

            $filtered[] = $l;
        }

        $filtered[] = implode("\t", [
            $cookieDomain,
            "TRUE",
            "/",
            $secure,
            time() + 43200,
            "cf_clearance",
            $token
        ]);

        return _put($cookiePath, implode("\n", $filtered) . "\n");
    }
    
    private static function seledroid($url, $ua, $ck) {
        
        $solution = (new execPy())->run('interstitial', $url);
        
        if (!empty($solution['token'])) {
            parse_str($solution['token'], $clearance);
            
            $solution['token'] = $clearance['cf_clearance'];
            return $solution;
        }
        
        return null;
    }
    
}