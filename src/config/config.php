<?php
/** @class Config
 * @method credential
     * @param array $defaults
     * @param bool $required
     * @param string|null $customPath
     * @return ArrayAccess
     * @method __construct
             * @param string $file
             * @param array $defaults
             * @param bool $required
     * @method offsetGet
             * @param mixed $key
             * @return mixed
     * @method offsetSet
             * @param mixed $key
             * @param mixed $value
             * @return void
     * @method offsetUnset
             * @param mixed $key
             * @return void
     * @method enforce
             * @param mixed $key
             * @param mixed $value
             * @return mixed
     * @method save
             * @param mixed $key
             * @param mixed $value
             * @return void
 * @method cookie
     * @param string $email
 * @method uagent
     * @param string $type
     * @return string
 * @method genUA
     * @return string
 * @method getKeys
     * @param mixed $api
     * @return mixed
 */
class Config {
    private static array $cred_cache = [];
    private static ?string $ua_static = null;

    public static function credential(array $defaults = [], $required = false, $customPath = null): ArrayAccess {
        if ($customPath === null) {
            $trace = debug_backtrace();
            $customPath = dirname($trace[0]['file']);
        }
        
        $filePath = rtrim($customPath, '/') . '/credentials';

        return new class($filePath, $defaults, $required) implements ArrayAccess {
            private array $cache = [];
            private string $file;
            private array $defaults;
            private bool $required;

            public function __construct($file, array $defaults, bool $required) {
                $this->defaults = $defaults;
                $this->required = $required;
                $this->file = $file;

                if (is_file($this->file)) {
                    foreach (file($this->file, FILE_IGNORE_NEW_LINES) as $l) {
                        if ($l === '' || $l[0] === '#') continue;
                        if (strpos($l, '=') === false) continue;
                        [$k, $v] = explode('=', $l, 2);
                        $this->cache[$k] = $v;
                    }
                }
            }

            public function offsetExists($key): bool { return true; }

            public function offsetGet($key): mixed {
                # ENV
                $env = getenv($key);
                if ($env !== false && $env !== '') return $this->cache[$key] = $env;

                # CACHE
                if (array_key_exists($key, $this->cache) && $this->cache[$key] !== '') {
                    return $this->enforce($key, $this->cache[$key]);
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
                    $value = "__{$key}__"; # PLACEHOLDER
                    logx('err', "{$key} empty");
                }

                $this->save($key, $value);
                return $this->cache[$key] = $this->enforce($key, $value);
            }

            public function offsetSet($key, $value): void {
                $this->cache[$key] = $value;
                $this->save($key, $value);
            }

            public function offsetUnset($key): void { unset($this->cache[$key]); }

            private function enforce($key, $value) {
                if ($this->required && ($value === null || $value === '' || $value === "__{$key}__")) {
                    die(logx('err', "{$key} is required!"));
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
                if (!$found) $lines[] = $key . '=' . $value;
                _put($this->file, implode(PHP_EOL, $lines) . PHP_EOL);
                #var_dump($this-file); die;
            }
        };
    }

    public static function cookie($email = null) {
        $trace = debug_backtrace();
        $b_dir = dirname($trace[0]['file']);

        if (empty($email)) return $b_dir . '/cookie';

        $norm = preg_replace('/[^a-z0-9]+/', '_', strtolower($email));
        $c_dir = $b_dir . '/cookies';
        if (!is_dir($c_dir)) mkdir($c_dir, 0755, true);

        return $c_dir . '/' . $norm . '_cookie';
    }

    public static function uagent($type = 'auto') {
        if (!self::$ua_static) {
            do {
                self::$ua_static = self::genUA();
            } while (
                ($type === 'mobile' && stripos(self::$ua_static, 'Android') === false) ||
                ($type === 'desktop' && stripos(self::$ua_static, 'Android') !== false)
            );
        }
        return self::$ua_static;
    }

    private static function genUA() {
        $rand = mt_rand(1, 100);

        if ($rand <= 60) {
            $os = ["Windows NT 10.0; Win64; x64", "Macintosh; Intel Mac OS X 13_6", "X11; Linux x86_64"];
            $ver = rand(115, 123);
            return "Mozilla/5.0 ({$os[array_rand($os)]}) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$ver.0.0.0 Safari/537.36";
        }
        $low = ["Redmi 9A", "SM-A015F", "CPH1909", "Vivo 1906"];
        $mid = ["SM-A515F", "Redmi Note 10", "CPH2239", "V2030"];
        $device = (rand(1,100) <= 70) ? $mid[array_rand($mid)] : $low[array_rand($low)];
        $androidVer = [10, 11, 12, 13][array_rand([10, 11, 12, 13])];
        $chromeVer = rand(115, 123);

        return "Mozilla/5.0 (Linux; Android $androidVer; $device) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$chromeVer.0.0.0 Mobile Safari/537.36";
    }
    
    public static function getKeys($api, $type, $mod = 'tkn') {
        if (defined('AUTH_KEY') && AUTH_KEY) {
            switch ($mod) {
                case 'acc':
                    $targetArray = Api::ACC;
                    break;
                case 'b64':
                    $targetArray = Api::B64;
                    break;
                default:
                    $targetArray = Api::TKN;
                    break;
            }
            if (isset($targetArray[gmxch::class][$type])) {
                return AUTH_API;
            }
        }
        
        return $api;
    }



}

