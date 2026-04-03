<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

final class execPython {
    private string $python = 'python3';
    private string $scriptPath;
    private ?string $cookieFile;
    private ?string $userAgent;

    public function __construct($cookie = null, $ua = null) {
        if (!getDeps('seledroid@py')) {
            logx('err', 'seledroid@py missing');
            exit;
        }
        
        $this->cookieFile = $cookie;
        $this->userAgent  = $ua;

        if (($py = realpath(LIBDIR . '/execPy.py')) === false) {
            logx('err', "execPy.py not found");
            $this->scriptPath = '';
            return;
        }

        $this->scriptPath = $py;
    }

    public function run($type, $url = null, $act = null): array|string|null {
        $m = strtolower($type);

        if (!in_array($m, ['turnstile', 'inter', 'recaptcha3', 'build', 'ua'], true)) {
            logx('err', "Invalid method");
            return null;
        }

        if (!in_array($m, ['build', 'ua'], true) && ($url === null || $url === '')) {
            logx('err', "URL required");
            return null;
        }

        if ($this->scriptPath === '') {
            return null;
        }

        $sync = ($this->cookieFile !== null && $this->userAgent !== null);

        $out = $this->exec($m, $url, $sync, $act);

        if ($out === null || trim($out) === '') {
            logx('err', "Python solver empty");
            return null;
        }
        $trim = trim($out);

        if ($m === 'ua') {
            return $trim;
        }

        if ($m === 'build') {
            $f = $trim[0] ?? '';
            if ($f !== '{' && $f !== '[') {
                return $trim;
            }

            $json = json_decode($trim, true);
            if (!is_array($json)) {
                logx('err', "Invalid JSON");
                return null;
            }

            return $json;
        }

        $json = json_decode($trim, true);
        if (!is_array($json)) {
            logx('err', "Invalid JSON");
            return null;
        }

        switch ($m) {
            case 'turnstile':
                if (empty($json['token']) || strlen($json['token']) < 20) {
                    logx('err', "Turnstile token invalid");
                    return null;
                }
                return (string)$json['token'];

            case 'recaptcha3':
                if (empty($json['token']) || strlen($json['token']) < 20) {
                    logx('err', "Recaptcha token invalid");
                    return null;
                }
                return (string)$json['token'];

            case 'inter':
                if (empty($json['cf_clearance']) || empty($json['user_agent'])) {
                    logx('err', "Interstitial failed");
                    return null;
                }

                if ($sync) {
                    if (!$this->cfCookie((string)$json['cf_clearance'], (string)$url)) {
                        return null;
                    }

                    $this->userAgent = (string)$json['user_agent'];
                    $GLOBALS['userAgent'] = $this->userAgent;

                    return [
                        'cookie_file' => (string)$this->cookieFile,
                        'user_agent'  => (string)$this->userAgent
                    ];
                }

                return [
                    'cf_clearance' => (string)$json['cf_clearance'],
                    'user_agent'   => (string)$json['user_agent']
                ];
        }

        return null;
    }

    private function exec($m, $url, $sync, $act = null) {
        $py = escapeshellcmd($this->python);
        $sc = escapeshellarg($this->scriptPath);

        $cmd = "{$py} {$sc} " . escapeshellarg($m);

        if (!in_array($m, ['build','ua'], true)) {
            if ($url === null || $url === '') {
                logx('err', "URL required");
                return null;
            }
            $cmd .= " " . escapeshellarg($url);
        }

        if ($m === 'recaptcha3') {
            if ($act === null || $act === '') {
                logx('err', "Action required for recaptcha3");
                return null;
            }
            $cmd .= " " . escapeshellarg($act);
        }

        if ($sync) {
            if ($this->userAgent === null || $this->cookieFile === null) {
                logx('err', "sync enabled but ua/cookie missing");
                return null;
            }

            if (in_array($m, ['turnstile','recaptcha3'], true)) {
                $cmd .= " " . escapeshellarg($this->userAgent)
                      . " " . escapeshellarg($this->cookieFile);
            } else {
                $cmd .= " " . escapeshellarg($this->userAgent);
            }
        }

        return shell_exec($cmd);
    }

    private function cfCookie($cfString, $url): bool {
        if ($this->cookieFile === null || $this->cookieFile === '') {
            logx('err', "cookieFile not set");
            return false;
        }

        if (!preg_match('/cf_clearance=([^;]+)/', $cfString, $m)) {
            logx('err', "cf_clearance invalid");
            return false;
        }

        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
            logx('err', "host invalid");
            return false;
        }

        $cookieDomain = '.' . ltrim($domain, '.');
        $secure = (parse_url($url, PHP_URL_SCHEME) === 'https') ? "TRUE" : "FALSE";

        $lines = file_exists($this->cookieFile)
            ? file($this->cookieFile, FILE_IGNORE_NEW_LINES)
            : [
                "# Netscape HTTP Cookie File",
                "# https://curl.se/docs/http-cookies.html",
                ""
            ];

        $filtered = [];
        foreach ($lines as $l) {
            if ($l === '' || $l[0] === '#') {
                $filtered[] = $l;
                continue;
            }

            $cols = explode("\t", $l);
            if (count($cols) >= 7 && $cols[5] === 'cf_clearance') {
                continue;
            }

            $filtered[] = $l;
        }

        $filtered[] = implode("\t", [
            $cookieDomain,
            "TRUE",
            "/",
            $secure,
            time() + 1800,
            "cf_clearance",
            $m[1]
        ]);

        _put($this->cookieFile, implode("\n", $filtered) . "\n");
        return true;
    }
}