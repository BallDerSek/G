<?php
/** @function cfGet
 * @param string $url
 * @param string &$cookie
 * @param string &$uagent
 * @return string|null
 */
function cfGet($url, &$cookie, &$uagent) {
    $att = 0;
    $execPy = null; 
    while ($att < 10) {
        $_00 = Net::C($url, 'GET', null, $cookie, [], '', $uagent);
        
        if (!$_00) { 
            $att++; 
            logx('warn', "Empty response, retry $att");
            _sle(2); 
            continue; 
        }

        $isCloudflare = (
            stripos($_00, 'Cloudflare Ray ID') !== false || 
            stripos($_00, 'just a moment') !== false ||
            stripos($_00, 'challenge-platform') !== false
        );

        if (!$isCloudflare) {
            return $_00;
        }

        logx('warn', "Cloudflare/JS Challenge detected");

        if (!$execPy) $execPy = new execPython($cookie, $uagent);
        $r = $execPy->run('inter', $url);

        if ($r === null) {
            logx('err', "Solver failed to return result");
            $att++; 
            _sle(3); 
            continue;
        }

        $uagent = (string)$r['user_agent'];
        $cookie = (string)$r['cookie_file'];

        $_11 = Net::C($url, 'GET', null, $cookie, [], $url, $uagent);
        
        $isStillCF = (
            stripos($_11, 'challenge-platform') !== false || 
            stripos($_11, 'just a moment') !== false ||
            strpos($_11, 'id="cf-wrapper"') !== false
        );

        if ($_11 && !$isStillCF) {
            logx('success', "Cloudflare bypassed!");
            return $_11;
        }

        logx('err', "Bypass failed, retrying...");
        $att++;
        _sle(2);
    }
    
    return false; 
}

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
            logx('warn', "Internal Node Busy, fallback direct api");
            
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



/** @class execPython
 * @method __construct
     * @param string|null $cookie
     * @param string|null $ua
 * @method run
     * @param string $type
     * @param string|null $url
     * @param string|null $act
     * @return array|string|null
 * @method exec
     * @param string $m
     * @param string|null $url
     * @param bool $sync
     * @param string|null $act
     * @return string|null
 * @method cfCookie
     * @param string $cfString
     * @param string $url
     * @return bool
 */
final class execPython {
    private string $python = 'python3';
    private string $scriptPath;
    private ?string $cookie;
    private ?string $uagent;
    private string $lockFile;

    public function __construct($ck = null, $ua = null) {
        /*
        logx('', ' set: '.$ck);
        logx('', ' set: '.$ua);
        logx();
        #die;
        */
        if (!getDeps('seledroid@py')) {
            logx('err', 'seledroid@py missing');
            exit;
        }
        
        $this->cookie = $ck;
        $this->uagent = $ua;
        $this->lockFile = sys_get_temp_dir() . '/seledroid_global.lock';

        if (($py = realpath(LIBDIR . '/python/execPy.py')) === false) {
            logx('err', "execPy file not found");
            exit;
        }
        $this->scriptPath = $py;

        $proxy = $GLOBALS['_CTX']['proxy']['src'] ?? null;
        
        if (!empty($proxy) && getDeps('gost')) {
            logx('warn', " setting up proxy for seledroid, will consume much execution, USE WITH CAUTION", true, true);
            $check = $this->run('check');
            if (!$check || isset($check['error'])) {
                $err = $check['error'] ?? 'No Response';
                logx('err', "Proxy Tunnel Failed: $err");
                exit;
            }
            logx('success', "Seledroid Proxy: " . ($check['ip'] ?? 'Unknown'));
        } else {
            logx('info', "Seledroid Direct");
        }
    }

    public function run($type, $url = null, $act = null): array|string|null {
        $m = strtolower($type);
        if (!in_array($m, ['turnstile', 'inter', 'recaptcha3', 'check', 'ua'], true)) return null;
        if (!in_array($m, ['check', 'ua'], true) && empty($url)) return null;

        $sync = ($this->cookie !== null && $this->uagent !== null);
        $out = $this->exec($m, $url, $sync, $act);
        #var_dump($out) && die;

        if (empty(trim($out))) return null;
        $trim = trim($out);
        if ($m === 'ua') return $trim;

        $json = json_decode($trim, true);
        if (!is_array($json) || isset($json['error'])) {
            if (isset($json['error'])) logx('err', "Py: " . $json['error']);
            return null;
        }

        switch ($m) {
            case 'check': return $json; 
            case 'turnstile':
            case 'recaptcha3':
                return (strlen($json['token'] ?? '') > 20) ? (string)$json['token'] : null;
            case 'inter':
                if (empty($json['cf_clearance']) || empty($json['user_agent'])) return null;
                $token = str_ireplace('cf_clearance=', '', trim((string)$json['cf_clearance']));
                if ($sync) {
                    if (!$this->cfCookie("cf_clearance=$token", (string)$url)) return null;
                    $this->uagent = $GLOBALS['uagent'] = (string)$json['user_agent'];
                }
                
                return [
                    'token' => $token,
                    'ua' => (string)$json['user_agent']
                ];
        }
        return null;
    }
    
    private function exec($m, $url, $sync, $act = null) {
        $py = escapeshellcmd($this->python);
        $sc = escapeshellarg($this->scriptPath);
        $cmd = "{$py} {$sc} " . escapeshellarg($m);
        
        $proxy = $GLOBALS['_CTX']['proxy']['src'] ?? null;

        if (!empty($proxy) && getDeps('gost')) {
            $cmd .= " --px " . escapeshellarg($proxy);
        }

        if (!in_array($m, ['check','ua'], true)) $cmd .= " " . escapeshellarg($url);
        if ($m === 'recaptcha3') $cmd .= " " . escapeshellarg($act);
        
        if ($sync) {
            $cmd .= " " . escapeshellarg($this->uagent);
            if (in_array($m, ['turnstile','recaptcha3', 'inter'], true)) {
                $cmd .= " " . escapeshellarg($this->cookie);
            }
        }
        
        $fp = fopen($this->lockFile, "w+");
        if ($fp && flock($fp, LOCK_EX)) {
            $out = shell_exec($cmd); 
            flock($fp, LOCK_UN);
            fclose($fp);
            return $out;
        }
        if ($fp) fclose($fp);
        return null;
    }

    public function cfCookie($cfString, $url) {
        if (empty($this->cookie)) return false;
        if (!preg_match('/cf_clearance=([^;]+)/', $cfString, $m)) return false;
        $domain = parse_url($url)['host'];
        $cookieDomain = '.' . ltrim($domain, '.');
        $secure = (parse_url($url)['scheme'] === 'https') ? "TRUE" : "FALSE";
        $lines = file_exists($this->cookie) ? file($this->cookie, FILE_IGNORE_NEW_LINES) : ["# Netscape HTTP Cookie File", ""];
        $filtered = [];
        foreach ($lines as $l) {
            if ($l === '' || $l[0] === '#') { $filtered[] = $l; continue; }
            $cols = explode("\t", $l);
            if (count($cols) >= 7 && $cols[5] === 'cf_clearance') continue;
            $filtered[] = $l;
        }
        $filtered[] = implode("\t", [$cookieDomain, "TRUE", "/", $secure, time() + 1800, "cf_clearance", $m[1]]);
        _put($this->cookie, implode("\n", $filtered) . "\n");
        return true;
    }
}
