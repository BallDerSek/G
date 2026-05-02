<?php
/** @class check 
 * @method Env
     * @return void
 * @method Dep
     * @return void
 *  @method depCmd
     * @param string $cmd
     * @return bool
 * @method Geo
     * @return void
 * @method Geo
     * @param string $cc
     * @return string
 */
class check {
    public static $deps = [];
    public static $geo = [];

    public static function Env() {
        if (getenv('ENV') !== '1') return;
        $path = ROOT . "/.env";
        if (file_exists($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) continue;
                if (str_contains($line, '=')) {
                    putenv($line);
                    [$name, $value] = explode('=', $line, 2);
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    public static function Dep() {
        self::$deps = underline("checking deps", function () {
            $hasNode = self::depCmd('node') || self::depCmd('nodejs');
            $hasSynchrony = self::depCmd('synchrony') || (($npm = trim((string)shell_exec('npm root -g 2>/dev/null'))) !== '' && is_file($npm . DIRECTORY_SEPARATOR . 'synchrony' . DIRECTORY_SEPARATOR . 'package.json'));
            
            $hasSeledroid = trim((string)shell_exec('python3 -c ' . escapeshellarg('import importlib.util; print(importlib.util.find_spec("seledroid") is not None)') . ' 2>/dev/null')) === 'True';
            
            return [
                'gd@php' => extension_loaded('gd'),
                'python3' => self::depCmd('python3'),
                'seledroid@py' => $hasSeledroid,
                'gost' => self::depCmd('gost'),
                'ssh' => self::depCmd('ssh'),
                'sshpass' => self::depCmd('sshpass'),
                'nodejs' => $hasNode,
                'npm' => self::depCmd('npm'),
                'synchrony@npm' => $hasSynchrony,
                'tesseract' => self::depCmd('tesseract'),
            ];
        });
        
        $missing = array_keys(array_filter(self::$deps, fn($v) => !$v));
        if ($missing) {
            logx('err', "Missing dependencies:\n- " . implode("\n- ", $missing) . "\n");
        }
        $GLOBALS['_CTX']['deps'] = self::$deps;
    }

    private static function depCmd($cmd) {
        $cmd = trim($cmd);
        if ($cmd === '' || preg_match('/[^a-zA-Z0-9._-]/', $cmd)) {
            return false;
        }
        
        if (PHP_OS_FAMILY === 'Windows') {
            $out = shell_exec('where ' . escapeshellarg($cmd) . ' 2>NUL');
            return trim((string)$out) !== '';
        }
        
        $out = shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null');
        
        return trim((string)$out) !== '';
    }

    public static function Geo() {
        $g = underline("checking nett", fn() => self::geoData());
        if (!is_array($g) || ($g === 99)) {
            logx('err', "unstable network");
            exit(99);
        }

        $cc = strtoupper($g['country_code'] ?? 'ID'); // Fixed syntax here
        
        self::$geo = [
            'country' => $g['country'] ?? 'Unknown',
            'language' => self::geoLang($cc),
            'country_code' => $cc,
            'timezone' => $g['timezone'] ?? 'Asia/Jakarta',
            'ip'  => $g['ip'] ?? '0.0.0.0',
        ];

        $GLOBALS['_CTX']['geo'] = self::$geo;
    }

    private static function geoData() {
        $services = [
            'ipinfo'  => ['url' => 'https://ipinfo.io/json', 'map' => ['timezone','country','country','ip']],
            'ipapi'   => ['url' => 'http://ip-api.com/json/', 'map' => ['timezone','country','countryCode','query']],
            'geojs'   => ['url' => 'https://get.geojs.io/v1/ip/geo.json', 'map' => ['timezone','country','country_code','ip']],
            'ipwhois' => ['url' => 'https://ipwhois.app/json/', 'map' => ['timezone','country','country_code','ip']],
        ];

        foreach ($services as $s) {
            $res = Net::C($s['url'], 'GET', null, null, [], '', 'Mozilla/5.0');
            $data = json_decode((string)$res, true);
            if (is_array($data)) {
                [$tz, $c, $cc, $ip] = $s['map'];
                if (!empty($data[$ip])) {
                    return [
                        'ip' => $data[$ip],
                        'timezone' => $data[$tz] ?? null,
                        'country' => $data[$c] ?? null,
                        'country_code' => $data[$cc] ?? null
                    ];
                }
            }
        }
        return false;
    }

    private static function geoLang($cc) {
        $map = [
            'ID' => 'id-ID,id', 'MY' => 'ms-MY,ms', 'PH' => 'fil-PH,fil,en-PH,en',
            'AE' => 'ar-AE,ar', 'SA' => 'ar-SA,ar', 'KR' => 'ko-KR,ko',
            'JP' => 'ja-JP,ja', 'CN' => 'zh-CN,zh', 'TW' => 'zh-TW,zh',
            'US' => 'en-US,en', 'GB' => 'en-GB,en', 'NL' => 'nl-NL,nl',
            'CH' => 'de-CH,de', 'BE' => 'nl-BE,nl',
        ];
        $base = $map[$cc] ?? 'en-US,en';
        return (stripos($base, 'en') === false) ? "$base,en-US,en" : $base;
    }
}

/** @function getDeps
 * @param string|array $deps
 * @return bool
 */
function getDeps($deps) {
    if (empty($GLOBALS['_CTX']['deps'])) {
        logx('err', 'deps missing run script normally');
        exit;
    }
    if (is_string($deps)) $deps = [$deps];
    foreach ($deps as $dep) {
        if (empty($GLOBALS['_CTX']['deps'][$dep]) || !$GLOBALS['_CTX']['deps'][$dep]) {
            return false;
        }
    }
    return true;
}

/** @function IP * @return string

 ** @function TIMEZONE * @return string

 ** @function COUNTRY * @return string

 ** @function COUNTRY_CODE * @return string

 ** @function LANGUAGE * @return string
 */
function IP() {
    return $GLOBALS['_CTX']['geo']['ip'] ?? '0.0.0.0';
}

function TIMEZONE() {
    return $GLOBALS['_CTX']['geo']['timezone'] ?? 'Asia/Jakarta';
}

function COUNTRY() {
    return $GLOBALS['_CTX']['geo']['country'] ?? '';
}

function COUNTRY_CODE() {
    return $GLOBALS['_CTX']['geo']['country_code'] ?? 'ID';
}

function LANGUAGE() {
    return $GLOBALS['_CTX']['geo']['language'] ?? 'en-US,en';
}