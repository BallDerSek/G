<?php

class Check {
    
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
        self::$deps = styler("checking deps", function () {
            $isWin = (PHP_OS_FAMILY === 'Windows');
            $null  = $isWin ? 'NUL' : '/dev/null';
            $pyBin = $isWin ? 'python' : 'python3';

            $hasNode = self::depCmd('node') || self::depCmd('nodejs');
            
            $npmRoot = trim((string)shell_exec("npm root -g 2>$null"));
            $hasSynchrony = self::depCmd('synchrony') || (
                $npmRoot !== '' && 
                is_file($npmRoot . DIRECTORY_SEPARATOR . 'synchrony' . DIRECTORY_SEPARATOR . 'package.json')
            );
            
            $pyCheck = 'import importlib.util; print(importlib.util.find_spec("seledroid") is not None)';
            $pyCmd = "$pyBin -c " . escapeshellarg($pyCheck) . " 2>$null";
            $hasSeledroid = trim((string)shell_exec($pyCmd)) === 'True';
            
            return [
                'gd@php' => extension_loaded('gd'),
                'python3' => self::depCmd($pyBin),
                #'seledroid@py' => $hasSeledroid,
                #'gost' => self::depCmd('gost'),
                'ssh' => self::depCmd('ssh'),
                'sshpass' => self::depCmd('sshpass'),
                'nodejs' => $hasNode,
                'npm' => self::depCmd('npm'),
                'synchrony@npm' => $hasSynchrony,
                'tesseract' => self::depCmd('tesseract'),
            ];
        }, 'underline');
        
        $missing = array_keys(array_filter(self::$deps, fn($v) => !$v));
        if ($missing) {
            Logger::X('err', "Missing dependencies:\n- " . implode("\n- ", $missing) . "\n");
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
        $g = styler("checking nett", fn() => self::geoData(), 'underline');
        if (!is_array($g) || ($g === 99)) {
            Logger::X('err', "unstable network");
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
            'ipapi'   => ['url' => 'http://ip-api.com/json/', 'map' => ['timezone','country','countryCode','query']],
            'geojs'   => ['url' => 'https://get.geojs.io/v1/ip/geo.json', 'map' => ['timezone','country','country_code','ip']],
            'ipinfo'  => ['url' => 'https://ipinfo.io/json', 'map' => ['timezone','country','country','ip']],
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
    
    public static function Inn() {
        
        $url = [
            'http://ip-api.com/json/',
            'https://get.geojs.io/v1/ip/geo.json',
            'https://ipwhois.app/json/'
        ];
        $blk = ['BD', 'IN'];
        
        foreach ($url as $u) {
            $data = json_decode(Net::S($u, 'GET')?: '', 1);
            if (!$data) continue;
            #print_r($data);
            $code = $data['countryCode'] ?? $data['country_code'] ?? '';
            $ngra = $data['country'] ?? '';
            $ip = $data['query'] ?? $data['ip'] ?? '';
            
            if (in_array($code, $blk)) die;
            
            if ($code && $ip && $ngra) return "$ip=$code=$ngra";
            
        }
        
        return null;
    }
    
}
