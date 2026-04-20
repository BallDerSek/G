<?php

/** @function cfGet
 * @param string $url
 * @param string &$cookie
 * @param string &$uagent
 * @return string|null
 */
function cfGet($url, &$cookie, &$uagent) {
    $att = 0;
    while ($att < 10) {
        $html = Net::C($url, 'GET', null, $cookie, [], '', $uagent);
        if (!$html) { $att++; _sle(2); continue; }

        $titles = xScraper::xPath($html, "//title");
        $title = isset($titles[0]) ? strtolower($titles[0]) : '';

        if ($title !== '' && stripos($title, 'just a moment') === false) return $html;

        logx('err', "Cloudflare detected");

        $execPy = new execPython($cookie, $uagent);
        $r = $execPy->run('inter', $url);

        if ($r === null || empty($r['user_agent']) || empty($r['cookie_file'])) {
            logx('err', "Solver failed");
            $att++; _sle(2); continue;
        }

        $ua = (string)$r['user_agent'];
        $ck = (string)$r['cookie_file'];

        $html_fix = Net::C($url, 'GET', null, $ck, [], $url, $ua);
        if (!$html_fix) { $att++; _sle(2); continue; }

        if (stripos($html_fix, 'challenge-platform') === false && stripos($html_fix, 'just a moment') === false) {
            $cookie = $ck;
            $uagent = $ua;
            return $html_fix;
        }
        $att++; _sle(2);
    }
    return $html;
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

    public function __construct($cookie = null, $ua = null) {
        if (!getDeps('seledroid@py')) {
            logx('err', 'seledroid@py missing');
            exit(9);
        }
        
        $this->cookie = $cookie;
        $this->uagent = $ua;

        if (($py = realpath(LIBDIR . '/execPy.py')) === false) {
            logx('err', "execPy.py not found");
            exit(9);
        }
        $this->scriptPath = $py;
    }

    public function run($type, $url = null, $act = null): array|string|null {
        $m = strtolower($type);
        if (!in_array($m, ['turnstile', 'inter', 'recaptcha3', 'build', 'ua'], true)) return null;
        if (!in_array($m, ['build', 'ua'], true) && empty($url)) return null;

        $sync = ($this->cookie !== null && $this->uagent !== null);
        $out = $this->exec($m, $url, $sync, $act);

        if (empty(trim($out))) return null;
        $trim = trim($out);

        if ($m === 'ua') return $trim;
        if ($m === 'build') {
            $json = json_decode($trim, true);
            return is_array($json) ? $json : $trim;
        }

        $json = json_decode($trim, true);
        if (!is_array($json)) return null;

        switch ($m) {
            case 'turnstile':
            case 'recaptcha3':
                return (strlen($json['token'] ?? '') > 20) ? (string)$json['token'] : null;

            case 'inter':
                if (empty($json['cf_clearance']) || empty($json['user_agent'])) return null;
                if ($sync) {
                    if (!$this->cfCookie((string)$json['cf_clearance'], (string)$url)) return null;
                    $this->uagent = (string)$json['user_agent'];
                    $GLOBALS['uagent'] = $this->uagent;
                    return ['cookie_file' => (string)$this->cookie, 'user_agent' => (string)$this->uagent];
                }
                return $json;
        }
        return null;
    }

    private function exec($m, $url, $sync, $act = null) {
        $py = escapeshellcmd($this->python);
        $sc = escapeshellarg($this->scriptPath);
        $cmd = "{$py} {$sc} " . escapeshellarg($m);

        if (!in_array($m, ['build','ua'], true)) $cmd .= " " . escapeshellarg($url);
        if ($m === 'recaptcha3') $cmd .= " " . escapeshellarg($act);

        if ($sync) {
            $cmd .= " " . escapeshellarg($this->uagent);
            if (in_array($m, ['turnstile','recaptcha3'], true)) $cmd .= " " . escapeshellarg($this->cookie);
        }
        return shell_exec($cmd);
    }

    private function cfCookie($cfString, $url) {
        if (empty($this->cookie)) return false;
        if (!preg_match('/cf_clearance=([^;]+)/', $cfString, $m)) return false;

        $domain = parse_url($url, PHP_URL_HOST);
        $cookieDomain = '.' . ltrim($domain, '.');
        $secure = (parse_url($url, PHP_URL_SCHEME) === 'https') ? "TRUE" : "FALSE";

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
