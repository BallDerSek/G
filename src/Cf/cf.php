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
function execCF($api, $url, $cookie, $uagent, array $data = [], $input = '') {
    
    if ($input === '' || $input === '2') {
        if (!$api) {
            logx('err', 'undefined provider, fallback local');
            $input = '1';
            goto Seledroid;
        }

        $param = array_filter([
            'body' => !empty($data['html']) ? base64_encode($data['html']) : null,
            'proxy' => $GLOBALS['_CTX']['proxy']['src'] ?? null
        ]);

        $solver = config::getKeys($api, 'interstitial', 'acc');
        $solve = $solver->access($url, 'interstitial', $param);
        
        // --- HANDLE SWITCH SIGNAL (777) ---
        if ($solve === 777 || (is_array($solve) && ($solve[1] ?? null) === 777)) {
            logx('warn', "Internal Node Busy, fallback direct api", false);
            _clr();
            
            if (!isset(Api::ACC[get_class($api)]['interstitial'])) {
                $input = '1';
                goto Seledroid;
            }

            _clr();
            $solve = $api->access($url, 'interstitial', $param);
            
            if ($solve === 71) {
                $input = '1';
                goto Seledroid;
            }
        }
        
        if (is_array($solve) && !empty($solve[1])) {
            if ($solver instanceof Provider) $api->getInfo();
            
            [$_cl, $_cf] = $solve;
            return cfSet($_cl, $_cf); 
        }
        
        return false;
    } 
    
    Seledroid:
    if ($input === '1') {
        logx('info', "Starting Seledroid Solver...");
        $execPy = new execPython($cookie, $uagent); 
        $r = $execPy->run('inter', $url);
        
        if (is_array($r) && !empty($r['token'])) {
            logx('ok', "Seledroid Success");
            return [
                'token' => (string)$r['token'],
                'ua' => (string)($r['ua'] ?? $uagent)
            ];
        }
        logx('err', "Direct Seledroid Solver failed");
        return false;
    }
    
    return null;
}


