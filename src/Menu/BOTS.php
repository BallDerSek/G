<?php

class BOTS {
    
    private static function getBotDir($bot) {
        return BOTDIR . "/$bot";
    }
    
    private static function getCredDir($bot) {
        return CREDIR . "/$bot";
    }
    
    private static function getBotFile($bot) {
        return self::getBotDir($bot) . "/$bot.php";
    }
    
    public static function getInstalled(): array {
        $bots = [];
        if (!is_dir(BOTDIR)) return $bots;
        
        $dirs = scandir(BOTDIR);
        if ($dirs === false) return $bots;
        
        foreach ($dirs as $dir) {
            if ($dir[0] === '.') continue;
            $botFile = self::getBotFile($dir);
            if (is_dir(self::getBotDir($dir)) && file_exists($botFile)) $bots[] = $dir;
        }
        return $bots;
    }
    
    private static function recursiveRmdir($dir) {
        $count = 0;
        if (!is_dir($dir)) return @unlink($dir) ? 1 : 0;
        
        $files = scandir($dir);
        if ($files === false) return 0;
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) $count += self::recursiveRmdir($path);
            else {
                @unlink($path);
                $count++;
            }
        }
        
        @rmdir($dir);
        return $count;
    }
    
    private static function cleanBotData($bot, $includeCredentials = false) {
        $deleted = ['count' => 0, 'files' => []];
        $credDir = self::getCredDir($bot);
        
        if (!is_dir($credDir)) return $deleted;
        
        $cookiesDir = $credDir . "/cookies";
        if (is_dir($cookiesDir)) {
            $count = self::recursiveRmdir($cookiesDir);
            $deleted['count'] += $count;
            $deleted['files'][] = "cookies/ (deleted $count items)";
        }
        
        if ($includeCredentials) {
            $crePath = $credDir . "/credentials";
            if (is_dir($crePath)) {
                $count = self::recursiveRmdir($crePath);
                $deleted['count'] += $count;
                $deleted['files'][] = "credentials/ (deleted $count items)";
            } elseif (file_exists($crePath)) {
                @unlink($crePath);
                $deleted['count']++;
                $deleted['files'][] = "credentials";
            }
        }
        
        return $deleted;
    }
    
    public static function clearCredentials($bot) {
        $crePath = self::getCredDir($bot) . "/credentials";
        if (!file_exists($crePath)) return false;
        
        if (is_dir($crePath)) return self::recursiveRmdir($crePath) > 0;
        
        return @unlink($crePath);
    }
    
    public static function cleanBot($bot) {
        $credDir = self::getCredDir($bot);
        if (!is_dir($credDir)) return 0;
        
        $deleted = 0;
        $files = scandir($credDir);
        if ($files === false) return 0;
        
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) continue;
            if ($file === 'credentials' && is_dir($credDir . '/' . $file)) continue;
            
            $path = $credDir . '/' . $file;
            if (is_dir($path)) $deleted += self::recursiveRmdir($path);
            else {
                @unlink($path);
                $deleted++;
            }
        }
        return $deleted;
    }
    
    public static function hardResetBot($bot, $includeCredentials = true) {
        $credDir = self::getCredDir($bot);
        if (!is_dir($credDir)) return ['count' => 0, 'files' => [], 'error' => "Credential directory not found"];
        
        return self::cleanBotData($bot, $includeCredentials);
    }
    
    public static function hardResetAll($includeCredentials = false) {
        $bots = self::getInstalled();
        $result = ['deleted' => 0, 'details' => []];
        
        foreach ($bots as $bot) {
            $deleted = self::hardResetBot($bot, $includeCredentials);
            $result['deleted'] += $deleted['count'];
            if (!empty($deleted['files'])) $result['details'][$bot] = $deleted['files'];
        }
        
        return $result;
    }
    
    public static function exec($selector, $cmd = null) {
        $bots = self::getInstalled();
        
        $bot = null;
        if (ctype_digit((string)$selector)) {
            $idx = (int)$selector - 1;
            $bot = $bots[$idx] ?? null;
        } else $bot = in_array($selector, $bots) ? $selector : null;
        
        if (!$bot) {
            return [
                'status' => 'error',
                'message' => "Bot '$selector' not found",
                'bot' => null
            ];
        }
        
        switch ($cmd) {
            case 'cre':
                $deleted = self::clearCredentials($bot);
                return [
                    'status' => 'success',
                    'message' => $deleted ? "Credentials cleaned for $bot" : "No credentials found for $bot",
                    'bot' => $bot,
                    'action' => 'cleared_credentials'
                ];
                
            case 'cle':
                $deleted = self::cleanBot($bot);
                return [
                    'status' => 'success',
                    'message' => "$bot cleaned up ($deleted items deleted)",
                    'bot' => $bot,
                    'action' => 'cleaned',
                    'deleted_count' => $deleted
                ];
                
            case 'hard':
                $result = self::hardResetBot($bot, true);
                return [
                    'status' => 'success',
                    'message' => "Hard reset completed for $bot ({$result['count']} items deleted)",
                    'bot' => $bot,
                    'action' => 'hard_reset',
                    'deleted_count' => $result['count']
                ];
                
            default:
                $botFile = self::getBotFile($bot);
                if (!file_exists($botFile)) {
                    return [
                        'status' => 'error',
                        'message' => "Bot file not found: $botFile",
                        'bot' => $bot
                    ];
                }
                
                return [
                    'status' => 'success',
                    'message' => "Loading: $bot...",
                    'bot' => $bot,
                    'action' => 'run',
                    'bot_file' => $botFile
                ];
        }
    }
    
    public static function run($bot) {
        $result = self::exec($bot, null);
        
        if ($result['status'] !== 'success') {
            Logger::X('err', $result['message']);
            _sle(2);
            return;
        }
        
        if ($result['action'] === 'run') {
            $GLOBALS['_CTX']['current_bot'] = $result['bot'];
            Logger::X('info', $result['message']);
            _sle(1);
            _cle();
            require $result['bot_file'];
            exit;
        }
        
        Logger::X('ok', $result['message']);
        _sle(1);
        _cle();
    }
}
