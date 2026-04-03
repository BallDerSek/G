<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;
 
function credential(array $defaults = [], bool $required = false): ArrayAccess {
    $path = dirname(debug_backtrace()[0]['file']);

    return new class($path, $defaults, $required) implements ArrayAccess {
        private array $cache = [];
        private string $file;
        private array $defaults;
        private bool $required;

        public function __construct($path, array $defaults, bool $required) {
            $this->defaults = $defaults;
            $this->required = $required;
            $this->file = $path . '/credentials';

            if (is_file($this->file)) {
                foreach (file($this->file, FILE_IGNORE_NEW_LINES) as $l) {
                    if ($l === '' || $l[0] === '#') continue;
                    if (strpos($l, '=') === false) continue;
                    [$k, $v] = explode('=', $l, 2);
                    $this->cache[$k] = $v;
                }
            }
        }

        public function offsetExists($key): bool {
            return true;
        }

        public function offsetGet($key): mixed {
            # ENV
            $env = getenv($key);
            if ($env !== false && $env !== '') {
                return $this->cache[$key] = $env;
            }

            # CACHE
            if (array_key_exists($key, $this->cache)) {
                $value = $this->cache[$key];
                if ($value !== null && $value !== '') {
                    return $this->enforce($key, $value);
                }
            }

            # DEFAULT
            if (array_key_exists($key, $this->defaults)) {
                $def = $this->defaults[$key];
                $value = is_callable($def) ? $def() : $def;

                if ($value !== null && $value !== '') {
                    $this->save($key, $value);
                    return $this->cache[$key] = $this->enforce($key, $value);
                }
            }

            # INPUT
            $value = trim(_rl("{$key}: "));

            if ($value === '' && !$this->required) {
                $value = "__{$key}__";
                logx('err', "{$key} empty");
            }

            $this->save($key, $value);

            return $this->cache[$key] = $this->enforce($key, $value);
        }

        public function offsetSet($key, $value): void {
            $this->cache[$key] = $value;
            $this->save($key, $value);
        }

        public function offsetUnset($key): void {
            unset($this->cache[$key]);
        }

        private function enforce($key, $value) {
            if ($this->required && ($value === null || $value === '')) {
                logx('err', "{$key} required");
                die;
            }
            return $value;
        }

        private function save($key, $value): void {
            $lines = is_file($this->file) ? file($this->file, FILE_IGNORE_NEW_LINES) : [];
            $found = false;

            foreach ($lines as &$line) {
                if (strpos($line, $key . '=') === 0) {
                    $line = $key . '=' . $value;
                    $found = true;
                    break;
                }
            }
            unset($line);

            if (!$found) {
                $lines[] = $key . '=' . $value;
            }

            _put($this->file, implode(PHP_EOL, $lines) . PHP_EOL);
        }
    };
}

function getKeys($api) {
    return AUTH_KEY ? AUTH_API : $api;
}

function getCookie() {
    return dirname(debug_backtrace()[0]['file']).'/cookie';
}

function getUagent($type = 'auto') {
    static $ua = null;
    if (!$ua) {
        do {
            $ua = generateUA();
        } while (
            ($type === 'mobile' && stripos($ua, 'Android') === false) ||
            ($type === 'desktop' && stripos($ua, 'Android') !== false)
        );
    }
    return $ua;
}

function generateUA() {
    $rand = mt_rand(1, 100);
    if ($rand <= 60) {
        $os = [
            "Windows NT 10.0; Win64; x64",
            "Windows NT 10.0; WOW64",
            "Macintosh; Intel Mac OS X 13_6",
            "Macintosh; Intel Mac OS X 12_7",
            "X11; Linux x86_64"
        ];
        $ver = rand(115, 123);
        $osPick = $os[array_rand($os)];
        return "Mozilla/5.0 ($osPick) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$ver.0.0.0 Safari/537.36";
    }

    if ($rand <= 75) {
        $ver = rand(115, 123);
        return "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$ver.0.0.0 Safari/537.36 Edg/$ver.0.0.0";
    }

    if ($rand <= 85) {
        $os = [
            "Windows NT 10.0; Win64; x64",
            "Macintosh; Intel Mac OS X 13_6",
            "X11; Linux x86_64"
        ];
        $ver = rand(115, 124);
        $osPick = $os[array_rand($os)];
        return "Mozilla/5.0 ($osPick; rv:$ver.0) Gecko/20100101 Firefox/$ver.0";
    }

    $low = [
        "Redmi 9A","Redmi 9C",
        "SM-A015F","SM-A025F",
        "CPH1909","CPH1803",
        "Vivo 1906","Vivo 1820",
        "X652B","X657",
        "KE5"
    ];

    $mid = [
        "SM-A125F","SM-A315G","SM-A515F",
        "Redmi Note 8","Redmi Note 9","Redmi Note 10",
        "M2003J15SC","M2101K7AG",
        "CPH2239","CPH2269",
        "V2026","V2030",
        "X6812","X6710","X689C"
    ];

    $device = (rand(1,100) <= 70) ? $mid[array_rand($mid)] : $low[array_rand($low)];

    $androidVer = [10,10,11,11,12,13][array_rand([10,10,11,11,12,13])];
    $chromeVer = rand(115, 123);

    return "Mozilla/5.0 (Linux; Android $androidVer; $device) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$chromeVer.0.0.0 Mobile Safari/537.36";
}