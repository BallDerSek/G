<?php

class Config {
    private static array $cred_cache = [];
    private static ?string $ua_static = null;
    
    public static function credential(array $defaults = [], $required = false, array|bool $ask = false): ArrayAccess {
        $bot = empty($GLOBALS['_CTX']['current_bot']) ? '' : $GLOBALS['_CTX']['current_bot'];
        $baseDir = CREDIR . ($bot ? "/$bot" : '');
        $filePath = rtrim($baseDir, '/') . '/credentials';
        return new class($filePath, $defaults, $required, $ask) implements ArrayAccess {
            
            private array $cache = [];
            private string $file;
            private array $defaults;
            private bool $required;
            private array|bool $ask;
            
            public function __construct($file, array $defaults, $required, array|bool $ask) {
                $this->defaults = $defaults;
                $this->required = $required;
                $this->ask = $ask;
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
            
            public function offsetExists($key): bool {
                return true;
            }
            
            private function shouldAsk($key): bool {
                if ($this->ask === false) return false;
                if ($this->ask === true) return true;
                return in_array($key, $this->ask, true);
            }
            
            public function offsetGet($key): mixed {
                
                # ENV
                $env = getenv($key);
                if ($env !== false && $env !== '') {
                    return $this->cache[$key] = $this->enforce($key, $env);
                }
                
                # CACHE
                if (array_key_exists($key, $this->cache) && $this->cache[$key] !== '') {
                    
                    $current = $this->cache[$key];
                    
                    if ($this->shouldAsk($key)) {
                        
                        Logger::X('warn', "found saved {$key} => {$current}, change?", true, true);
                        
                        $change = trim(_rl("[enter=keep, --reset=clear]: "));
                        
                        if (strcasecmp($change, '--reset') === 0) {
                            
                            unset($this->cache[$key]);
                            $this->delete($key);
                            
                            return $this->required ? $this->offsetGet($key) : '';
                        }
                        
                        if ($change !== '') {
                            $current = $change;
                            $this->cache[$key] = $current;
                            $this->save($key, $current);
                        }
                    }
                    
                    return $this->enforce($key, $current);
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
                $value = trim( _rl("{$key}: "));
                
                if ($value === '' && !$this->required) {
                    $value = "__{$key}__";
                    Logger::X('err', "{$key} empty");
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
                $this->delete($key);
            }
            
            private function enforce($key, $value) {
                $isPlaceholder = ($value === "__{$key}__");
                
                if ($this->required && ($value === null || $value === '' || $isPlaceholder)) {
                    Logger::X('err', "{$key} is required!");
                    die;
                }
                
                return $isPlaceholder ? '' : $value;
            }
            
            private function save($key, $value): void {
                $dir = dirname($this->file);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
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
            }
            
            private function delete($key): void {
                $lines = is_file($this->file) ? file($this->file, FILE_IGNORE_NEW_LINES) : [];
                
                $lines = array_filter($lines, fn($line) => strpos($line, $key . '=') !== 0);
                
                _put($this->file, empty($lines) ? '' : implode(PHP_EOL, $lines) . PHP_EOL);
            }
        };
    }
    
    public static function cookie($email = null) {
        $b_dir = CREDIR.'/'.$GLOBALS['_CTX']['current_bot'];

        if (empty($email)) return $b_dir . '/cookies';

        $norm = preg_replace('/[^a-z0-9]+/', '_', strtolower($email));
        $c_dir = $b_dir . '/cookies';
        
        if (!is_dir($c_dir)) @mkdir($c_dir, 0755, true);

        return $c_dir . '/' . $norm . '_cookie';
    }

    public static function uagent($type = 'desktop') {
        if (!self::$ua_static) self::$ua_static = self::genUA($type);
        return self::$ua_static;
    }
    
    private static function genUA($type = 'desktop') {
        if ($type === 'desktop') {
            $os = ["Windows NT 10.0; Win64; x64", "Macintosh; Intel Mac OS X 13_6", "X11; Linux x86_64"];
            $ver = rand(120, 150);
            return "Mozilla/5.0 ({$os[array_rand($os)]}) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$ver.0.0.0 Safari/537.36";
        }
        
        $androidVer = [10, 11, 12, 13, 14][array_rand([10, 11, 12, 13, 14])];
        $chromeVer = rand(120, 150);
        return "Mozilla/5.0 (Linux; Android $androidVer; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/$chromeVer.0.0.0 Mobile Safari/537.36";
    }

    public static function resetUA() {
        self::$ua_static = null;
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
                return AUTH_API();
            }
        }
        
        return $api;
    }

}
