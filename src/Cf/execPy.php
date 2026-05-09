<?php

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
            #logx('info', "Seledroid: Proxy mode");
        } else {
            #logx('info', "Seledroid: Direct mode");
        }
    }

    public function run($type, $url = null, $act = null): array|string|null {
        if (!getDeps('seledroid@py')) {
            logx('err', 'seledroid@py missing');
            exit;
        }
        
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
        $filtered[] = implode("\t", [$cookieDomain, "TRUE", "/", $secure, time() + 43200, "cf_clearance", $m[1]]);
        _put($this->cookie, implode("\n", $filtered) . "\n");
        return true;
    }
}
