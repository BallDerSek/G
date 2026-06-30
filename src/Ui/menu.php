<?php

function onKeys() {
    return KEYS::run();
}

function pickIndex(array $items, callable $callback) {
    $count = count($items);

    if ($count === 0) return 0;

    $idx = 0;
    $rawMode = false;

    if (canRaw()) {
        system('stty -icanon -echo min 1 time 0');
        $rawMode = true;
    }

    try {
        while (true) {
            _cle();
            $callback($items, $idx);

            $char = fread(STDIN, 1);
            
            if ($char === "\033") $char .= fread(STDIN, 2)?: '';

            if ($char === "\033[A") {
                $idx = ($idx <= 0) ? $count - 1 : $idx - 1;
                continue;
            }

            if ($char === "\033[B") {
                $idx = ($idx >= $count - 1) ? 0 : $idx + 1;
                continue;
            }

            if ($char === "\n" || $char === "\r") return $idx;

            if (ctype_digit($char)) {
                $n = (int)$char - 1;
                if (isset($items[$n])) return $n;
            }
        }
    } finally {
        if ($rawMode) system('stty sane');
    }
}

class KEYS {

    private static $file = LIBDIR . '/apikey.json';

    private static $defaultEndpoints = [
        'https://solverify.net' => '',
        'http://tertuyul.my.id' => '',
        'Xevil_check_bot.t.me' => '',
        'https://waryono.my.id' => '',
        'http://multibot.in' => '',
        'https://capsolver.com' => '',
        'https://buxads.com/api-token' => '',
    ];

    public static function sync() {
        $data = is_file(self::$file) ? json_decode(_get(self::$file), 1) : [];

        $data = (is_array($data) && !empty($data)) ? $data : self::$defaultEndpoints;

        foreach ($data as $ep => $val) $GLOBALS['_CTX']['apikey'][$ep] = $val;
    }

    public static function run() {
        self::sync();

        if (!hasTty() || getenv('CI') === '1') return self::CI_env();

        return self::CLI_env();
    }

    private static function CLI_env() {

        $providers = array_merge(['no apikey'], array_keys(self::$defaultEndpoints), ['update keys']);

        $idx = pickIndex($providers, function($providers, $idx) {

            Logger::X('', "SELECT PROVIDER\n");

            foreach ($providers as $i => $url) {

                $val = $GLOBALS['_CTX']['apikey'][$url] ?? '';

                $status = empty($val) ? FGo["RED"] . '[NO]' : FGo["GRN"] . '[ON]';

                echo $status.RSET.($i === $idx ? FGo["BLU"]." => " : "    ").$url.RSET."\n";
            }
        });

        $endpoint = $providers[$idx];
        
        if ($endpoint === 'update keys') {
            self::newKeys();
            return self::CLI_env();
        }
        
        if ($endpoint === 'no apikey') { 
            _cle();
            return null;
        }

        if (empty($GLOBALS['_CTX']['apikey'][$endpoint])) {

            $apiKey = self::_ask($endpoint);

            if ($apiKey === '') {
                Logger::X('err', "rejected");
                return self::CLI_env();
            }
            
            _cle();
            return Api::use($endpoint, $apiKey);
        }
        
        _cle();
        return Api::use($endpoint, $GLOBALS['_CTX']['apikey'][$endpoint]);
    }

    private static function CI_env() {

        $endpoint = self::maps((string)getenv('API'));
        $apiKey = trim((string)getenv('KEY'));

        if ($endpoint === '' || $apiKey === '') die(Logger::X('err', 'API/KEY required'));

        $solver = Api::use($endpoint, $apiKey);

        if (!self::viewKeys($solver)) die(Logger::X('err', 'API/KEY rejected'));

        return $solver;
    }

    private static function _ask($endpoint) {

        echo "\n".BOLD.$endpoint.RSET."\n";

        $apiKey = trim(_rl("  apikeys: "));
        if ($apiKey === '') return '';

        $solver = Api::use($endpoint, $apiKey);

        if (!self::viewKeys($solver)) {
            Logger::X('err', "rejected");
            _sle(1);
            return '';
        }

        $data = $GLOBALS['_CTX']['apikey'];
        $data[$endpoint] = $apiKey;

        _put(self::$file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $GLOBALS['_CTX']['apikey'][$endpoint] = $apiKey;

        Logger::X('ok', "SAVED");

        return $apiKey;
    }

    private static function viewKeys($solver) {
        try {
            return styler("CHECK " . get_class($solver), function () use ($solver) {
                _sle(2);
                ob_start();
                try {
                    $ok = $solver->getInfo();
                } finally {
                    ob_end_clean();
                }
                return (bool)$ok;
            });
        } catch (Throwable $e) {
            Logger::X('err', $e->getMessage());
            return false;
        }
    }

    public static function newKeys() {

        $providers = array_keys(self::$defaultEndpoints);

        $idx = pickIndex($providers, function($providers, $idx) {

            Logger::X('warn', "UPDATE KEY\n", true, true);

            foreach ($providers as $i => $url) {

                $val = $GLOBALS['_CTX']['apikey'][$url] ?? '';

                $status = empty($val) ? FGo["RED"].'[NO]' : FGo["GRN"].'[ON]';

                echo $status.RSET.($i === $idx ? FGo["BLU"]." => " : "    ").$url.RSET."\n";
            }
        });

        self::_ask($providers[$idx]);
    }

    public static function maps($v) {
        $v = trim($v);
        $cfg = Api::KEY[$v] ?? Api::KEY[strtolower($v)] ?? null;
        return $cfg['ep'] ?? $v;
    }

}


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
            if (is_dir(self::getBotDir($dir)) && file_exists($botFile)) {
                $bots[] = $dir;
            }
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

class Menu {
    
    private static $banner;
    
    private static function initBanner() {
        return self::$banner ??= Banner::getInstance();
    }
    
    public static function main() {
        self::initBanner();
        
        while (true) { 
            self::$banner->show();
            
            self::$banner->task1('info', 'MENU');
            
            Logger::X('info', "\n [0 SETTINGS\n [1 RUN BOT\n [x EXIT", true, true);
            
            $rlM = trim(_rl(' input [boot]: '));
            switch ($rlM) {
                case '0': 
                    if (self::tools()) return true; 
                    break; 
                case '1': 
                    self::runBot(); 
                    break;
                case 'x': 
                    exit();
                default: 
                    _cle(); 
                    bootApp(); 
                    return true;
            }
        }
    }

    public static function tools() {
        
        while (true) {
            
            KEYS::sync();
            
            self::$banner->show();
            self::$banner->task1('info', 'SETTINGS');
            self::$banner->task2('info', 'Manage configuration');
            
            Logger::X('warn', "\n API STATUS", true, true);
            foreach ($GLOBALS['_CTX']['apikey'] as $p => $k) {
                $status = empty($k) ? FGd['RED'] : FGo['GRN'];
                printf("  %s %-20s\n", $status, $p.RSET);
            }
            
            Logger::X('info', "\n [0 USAGE INFO\n [1 UPDATE APIKEY\n [2 PROXY SETTINGS\n [3 HARD RESET (ALL BOTS)", true, true);
            
            switch (trim(_rl(' input [back]: '))) {
                case '0':
                    self::usage();
                    break;
                case '1':
                    KEYS::newKeys();
                    break;
                case '2':
                    if (self::proxy()) return true;
                    break;
                case '3':
                    self::hardReset();
                    break;
                default:
                    return false;
            }
        }
    }
    
    private static function hardReset() {
        #_cle();
        #self::$banner->show();
        self::$banner->task1('warn', 'HARD RESET ALL BOTS');
        self::$banner->task2('err', 'This will DELETE ALL COOKIES & SESSION DATA!');
        
        Logger::X('err', "   This will DELETE ALL COOKIES & SESSION DATA from ALL BOTS!");
        Logger::X('err', "   Action cannot be undone!");
        echo "\n";
        
        $confirm = strtolower(trim(_rl('   Type "yes" to confirm: ')));
        if ($confirm !== 'yes') {
            self::$banner->task2('info', 'Cancelled');
            _sle(1);
            return;
        }
        
        self::$banner->task1('info', 'PROCESSING...');
        self::$banner->task2('info', 'Deleting data...');
        
        $includeCred = (strtolower(trim(_rl('   delete credentials? (yes/no) [no]: '))) === 'yes');
        
        $result = BOTS::hardResetAll($includeCred);
        
        #self::$banner->show();
        self::$banner->task1('ok', 'HARD RESET COMPLETED!');
        self::$banner->task2('info', "Deleted: {$result['deleted']} items");
        
        // Tampilkan detail
        if (!empty($result['details'])) {
            foreach ($result['details'] as $bot => $files) {
                echo "    " . FGb['GRN'] . $bot . RSET . ":\n";
                foreach (array_slice($files, 0, 10) as $file) {
                    echo "      - $file\n";
                }
                if (count($files) > 10) {
                    echo "      ... and " . (count($files) - 10) . " more\n";
                }
            }
        }
        
        _rl("   Press Enter to continue...");
    }
    
    public static function runBot() {
        $bots = BOTS::getInstalled(); 
        if (!$bots) {
            self::$banner->task2('err', 'UNAVAILABLE (contact owner)');
            _sle(2); 
            return; 
        }
        
        while (true) {
            self::$banner->show();
            self::$banner->task1('info', 'SELECT BOT');
            
            Logger::X('err', "\n  dont forget check usage on settings");
            Logger::X('err', "  cookie handling is auto passed by email/credential");
            Logger::X('info', "  Tips:");
            Logger::X('info', "    [N]       - Run bot normally");
            Logger::X('info', "    N_cle     - Clean bot (delete all data except credentials)");
            Logger::X('info', "    N_cre     - Delete credentials only");
            Logger::X('info', "    N_hard    - Hard reset (delete cookies & session data)");
            
            // ========== DISPLAY BOT LIST 2 KOLOM ==========
            $maxLen = 0;
            foreach ($bots as $bot) {
                $maxLen = max($maxLen, strlen($bot));
            }
            $maxLen = max($maxLen, 15);
            
            $total = count($bots);
            $rows = ceil($total / 2);
            
            echo "\n";
            for ($i = 0; $i < $rows; $i++) {
                $first = $i;
                $second = $i + $rows;
                
                $line = "  ";
                if ($first < $total) {
                    $num = str_pad($first + 1, 2, " ", STR_PAD_LEFT);
                    $name = str_pad($bots[$first], $maxLen, " ");
                    $line .= "[$num " . BOLD . FGb['GRN'] . $name . RSET;
                } else {
                    $line .= str_repeat(" ", $maxLen + 6);
                }
                
                $line .= "  ";
                
                if ($second < $total) {
                    $num = str_pad($second + 1, 2, " ", STR_PAD_LEFT);
                    $name = str_pad($bots[$second], $maxLen, " ");
                    $line .= "[$num " . BOLD . FGb['GRN'] . $name . RSET;
                }
                
                echo $line . "\n";
            }
            echo "\n";
            // =============================================================
            
            $input = trim(_rl('  input [back]: '));
            if ($input === '' || $input === 'back') {
                return;
            }
            
            $parts = explode('_', $input);
            $sel = $parts[0];
            $cmd = $parts[1] ?? null;
            
            if ($cmd === 'hard') {
                self::$banner->task1('warn', 'HARD RESET CONFIRMATION');
                self::$banner->task2('err', 'This will delete ALL saved SESSION & DATA!');
                
                Logger::X('err', "    This will delete ALL COOKIES & SESSION DATA for this bot!");
                $confirm = strtolower(trim(_rl('    Type "yes" to confirm: ')));
                if ($confirm !== 'yes') {
                    self::$banner->task2('info', 'Cancelled');
                    _sle(2);
                    continue;
                }
            }
            
            $result = BOTS::exec($sel, $cmd);
            
            if ($result['status'] === 'error') {
                self::$banner->task2('err', 'ERROR: ' . $result['message']);
                Logger::X('err', $result['message']);
                _sle(2);
                continue;
            }
            
            if ($result['action'] === 'run') {
                $GLOBALS['_CTX']['current_bot'] = $result['bot'];
                self::$banner->task2('ok', 'Starting: ' . $result['bot']);
                _sle(2);
                require $result['bot_file'];
                exit;
            }
            
            self::$banner->task2('ok', $result['message']);
            _sle(2);
        }
    }
    
    public static function autoRun() {
        $sel = trim((string)getenv('BOT'));
        
        if (empty($sel)) die(Logger::X('err', "BOT environment variable not set"));
        
        $result = BOTS::exec($sel, null);
        
        if ($result['status'] === 'error') die(Logger::X('err', $result['message']));
        
        Logger::X('info', "Auto-running: {$result['bot']}");
        KEYS::sync();
        
        $GLOBALS['_CTX']['current_bot'] = $result['bot'];
        require $result['bot_file'];
        exit;
    }

public static function proxy() {
    while (true) {
        // Tampilkan banner dengan status proxy
        self::$banner->show();
        self::$banner->task1('info', 'PROXY SETTINGS');
        
        // Cek status proxy (tanpa ANSI codes)
        if (!empty($GLOBALS['_CTX']['proxy'])) {
            $p = $GLOBALS['_CTX']['proxy'];
            $alive = Proxy::_enable();
            $st = $alive ? 'ALIVE' : 'DEAD';
            $level = $alive ? 'ok' : 'warn';
            self::$banner->task2($level, "{$p['host']}:{$p['port']} | $st");
        } else {
            self::$banner->task2('err', 'DISABLED');
        }
        
        Logger::X('warn', "\n  FORMATS:", true, true);
        Logger::X('err', '    also support from environment variable');
        Logger::X('err', "    dont forget check usage on settings");
        Logger::X('', '    ssh://USER:PASS@HOST:22');
        Logger::X('', '    http://USER:PASS@HOST:8080');
        Logger::X('', '    socks5://USER:PASS@HOST:1080');
        
        if (!empty($GLOBALS['_CTX']['proxy'])) {
            $p = $GLOBALS['_CTX']['proxy'];
            $alive = Proxy::_enable();
            $st = $alive ? FGd['GRN'] . 'ALIVE' : FGd['RED'] . 'DEAD';
            Logger::X('info', "\n  PROXY ACTIVE: {$p['host']}:{$p['port']} $st", true, true);
        } else {
            Logger::X('err', "\n  PROXY: OFF");
        }
        
        Logger::X('info', "  [1] ENABLE PROXY", true, true);
        Logger::X('info', "  [2] DISABLE PROXY", true, true);
        Logger::X('info', "  [3] RESTART/REFRESH", true, true);
        
        switch (trim(_rl('    input [back]: '))) {
            case '1':
                $raw = trim(_rl('    url: '));
                if ($raw === '') continue 2;
                putenv("PROXY=$raw");
                $_ENV['PROXY'] = $raw;
                self::$banner->task2('info', 'Connecting...');
                styler("connecting", fn() => Proxy::Load());
                _sle(1);
                break;
            case '2':
                Proxy::_unable();
                self::$banner->task2('err', 'Disabled');
                Logger::X('err', "    disabled");
                _sle(1);
                break;
            case '3':
                bootApp();
                return true;
            default:
                return false;
        }
    }
}

    public static function usage() {
        Logger::X('warn', "\n   USAGE", true, true);
        echo FGo['BLU'].BOLD."    CI / ENV OPTIONS\n".RSET;
        echo "    BOT='name'   : Run specific bot\n";
        echo "    PROXY='url'  : Use specific tunnel\n";
        echo "    API='name'   : Solver api\n";
        echo "    KEY='key'    : Solver key\n";
        echo "    ENV='1'      : load .env\n";
        echo "    CI='1'       : ci ready auto run\n";
        echo "    AN='0'       : disable animation\n";
        echo "\n    eg:\n";
        Logger::X('info', "    BOT=feyorratop mail=xxx pass=xxx php run.php", true, true);
        Logger::X('info', "    BOT=botname API=tertuyul KEY=abc login=xxx  CI=1 PROXY=type://host:port php run.php", true, true);
        Logger::X('info', "    BOT=botname API=tertuyul KEY=321 CI=1 login=xxx php run.php", true, true);
        Logger::X('info', "    ENV=1 php run.php", true, true);
        _rl("\n   Enter to back...");
        #_cle();
    }

}
