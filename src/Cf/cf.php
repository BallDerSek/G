<?php

/** @function cfSet
 * @param string $class
 * @param mixed $res
 * @return array|null
 */
function cfSet($class, $res) {
    if (!$res) return null;

    switch (strtolower($class)) {
        case str_contains(strtolower($class), 'xevil'):
            $decoded = json_decode(base64_decode($res), true);
            return [
                'token' => $decoded['cf_clearance'] ?? null,
                'ua'    => $decoded['user_agent'] ?? null,
            ];

        case str_contains(strtolower($class), 'gmxch'):
        case str_contains(strtolower($class), 'glitch'):
            return [
                'token' => $res['cf_clearance'] ?? null,
                'ua'    => $res['user_agent'] ?? null,
            ];

        #case str_contains(strtolower($class), 'multibot'):
        case str_contains(strtolower($class), 'tertuyul'):
            $part = explode(':', $res, 2);
            return [
                'token' => $part[0] ?? null,
                'ua'    => $part[1] ?? null,
            ];

        default:
            return null;
    }
}

/** @function execCF
 * @param mixed $api
 * @param string $url
 * @param string $cookie
 * @param string $uagent
 * @param array $data
 * @return array|string|bool|null
 */
function execCF($api, $url, $cookie, $uagent, array $data = []) {
    
    if (!$api) die('undefined provider');
    $param = array_filter([
        'body' => !empty($data['html']) ? base64_encode($data['html']) : null,
        'proxy' => $GLOBALS['_CTX']['proxy']['src'] ?? null
    ]);
    
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
        [$_cl, $_cf] = $solve;
        $solution = cfSet($_cl, $_cf);
        #print_r($solution);
        return setCF($solution, $cookie, $url);
        
    }
    return false;
    
}

#$res = execCF($api, $fa, inf::$cookie, inf::$uagent, []);
function setCF($r, $c, $host) {
    #print_r($r);
    if (is_array($r) && isset($r['token'])) {
        logx('ok', 'cloudflare solved', true, true);
        # cari aman, inject cookie, biar gak boros kalau rerun karna solution headers hanya ada di memory
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
