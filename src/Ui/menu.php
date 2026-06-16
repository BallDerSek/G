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

            logx('', "SELECT PROVIDER\n");

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
                logx('err', "rejected");
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

        if ($endpoint === '' || $apiKey === '') (logx('err', 'API/KEY required') ?: die);

        $solver = Api::use($endpoint, $apiKey);

        if (!self::viewKeys($solver)) (logx('err', 'rejected') ?: die);

        return $solver;
    }

    private static function _ask($endpoint) {

        echo "\n".BOLD.$endpoint.RSET."\n";

        $apiKey = trim(_rl("  apikeys: "));
        if ($apiKey === '') return '';

        $solver = Api::use($endpoint, $apiKey);

        if (!self::viewKeys($solver)) {
            logx('err', "rejected");
            _sle(1);
            return '';
        }

        $data = $GLOBALS['_CTX']['apikey'];
        $data[$endpoint] = $apiKey;

        _put(self::$file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $GLOBALS['_CTX']['apikey'][$endpoint] = $apiKey;

        logx('ok', "SAVED");

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
            logx('err', $e->getMessage());
            return false;
        }
    }

    public static function newKeys() {

        $providers = array_keys(self::$defaultEndpoints);

        $idx = pickIndex($providers, function($providers, $idx) {

            logx('warn', "UPDATE KEY\n", true, true);

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
    
    public static function getInstalled() {
        $bots = [];
        if (!is_dir(BOTDIR)) return $bots;
        
        foreach (scandir(BOTDIR) as $dir) {
            if ($dir[0] === '.') continue;
            if (is_dir(BOTDIR . "/$dir") && file_exists(BOTDIR . "/$dir/$dir.php")) {
                $bots[] = $dir;
            }
        }
        return $bots;
    }

    public static function exec($selector, $cmd = null) {
        $bots = self::getInstalled();
        
        $bot = null;
        if (ctype_digit((string)$selector)) {
            $idx = (int)$selector - 1;
            $bot = $bots[$idx] ?? null;
        } else {
            $bot = in_array($selector, $bots) ? $selector : null;
        }
        
        if (!$bot) {
            return ['status' => 'error', 'message' => "Bot '$selector' not found", 'bot' => null];
        }
        
        $botDir = BOTDIR . "/$bot";
        
        if ($cmd === 'cre') {
            $deleted = self::clearCredentials($bot);
            return [
                'status' => 'success',
                'message' => $deleted ? "Credentials cleaned for $bot" : "No credentials found for $bot",
                'bot' => $bot,
                'action' => 'cleared_credentials'
            ];
        }
        
        if ($cmd === 'cle') {
            $deleted = self::cleanBot($bot);
            return [
                'status' => 'success',
                'message' => "$bot cleaned up ($deleted items deleted)",
                'bot' => $bot,
                'action' => 'cleaned',
                'deleted_count' => $deleted
            ];
        }
        
        if ($cmd === 'hard') {
            $result = self::hardResetBot($bot, true);
            return [
                'status' => 'success',
                'message' => "Hard reset completed for $bot ({$result['count']} items deleted)",
                'bot' => $bot,
                'action' => 'hard_reset',
                'deleted_count' => $result['count']
            ];
        }
        
        $botFile = $botDir . "/$bot.php";
        if (!file_exists($botFile)) {
            return ['status' => 'error', 'message' => "Bot file not found: $botFile", 'bot' => $bot];
        }
        
        return [
            'status' => 'success',
            'message' => "Loading: $bot...",
            'bot' => $bot,
            'action' => 'run',
            'bot_file' => $botFile
        ];
    }
    
    public static function run($bot) {
        $result = self::exec($bot, null);
        
        if ($result['status'] !== 'success') {
            logx('err', $result['message']);
            _sle(2);
            return;
        }
        
        if ($result['action'] === 'run') {
            $GLOBALS['_CTX']['current_bot'] = $result['bot'];
            logx('info', $result['message']);
            _sle(1);
            _cle();
            require $result['bot_file'];
            exit;
        }
        
        logx('ok', $result['message']);
        _sle(1);
        _cle();
    }

    public static function hardResetAll($includeCredentials = false) {
        $bots = self::getInstalled();
        $result = ['deleted' => 0, 'details' => []];

        foreach ($bots as $bot) {
            $botDir = BOTDIR . "/$bot";
            $deleted = self::cleanBotData($botDir, $includeCredentials);
            $result['deleted'] += $deleted['count'];
            if (!empty($deleted['files'])) {
                $result['details'][$bot] = $deleted['files'];
            }
        }

        return $result;
    }

    public static function hardResetBot(string $bot, bool $includeCredentials = false): array {
        $botDir = BOTDIR . "/$bot";
        if (!is_dir($botDir)) {
            return ['count' => 0, 'files' => [], 'error' => "Bot not found"];
        }
        return self::cleanBotData($botDir, $includeCredentials);
    }

    private static function cleanBotData(string $botDir, bool $includeCredentials = false): array {
        $deleted = ['count' => 0, 'files' => []];
    
        $cookiesDir = $botDir . "/cookies";
        if (is_dir($cookiesDir)) {
            $count = self::recursiveRmdir($cookiesDir);
            $deleted['count'] += $count;
            $deleted['files'][] = "cookies/ (deleted $count items)";
        }
    
        if ($includeCredentials) {
            $crePath = $botDir . "/credentials";
            if (file_exists($crePath)) {
                if (is_dir($crePath)) {
                    $count = self::recursiveRmdir($crePath);
                    $deleted['count'] += $count;
                    $deleted['files'][] = "credentials/ (deleted $count items)";
                } else {
                    @unlink($crePath);
                    $deleted['count']++;
                    $deleted['files'][] = "credentials";
                }
            }
        }
    
        return $deleted;
    }

    public static function clearCredentials(string $bot): bool {
        $crePath = BOTDIR . "/$bot/credentials";
        if (!file_exists($crePath)) return false;
        
        if (is_dir($crePath)) {
            return self::recursiveRmdir($crePath) > 0;
        }
        return @unlink($crePath);
    }

    public static function cleanBot(string $bot): int {
        $botDir = BOTDIR . "/$bot";
        if (!is_dir($botDir)) return 0;
        
        $deleted = 0;
        foreach (scandir($botDir) as $file) {
            if (in_array($file, ['.', '..', "$bot.php", 'credentials'])) continue;
            $p = "$botDir/$file";
            if (is_dir($p)) {
                $deleted += self::recursiveRmdir($p);
            } else {
                @unlink($p);
                $deleted++;
            }
        }
        return $deleted;
    }

    private static function recursiveRmdir(string $dir): int {
        $count = 0;
        if (!is_dir($dir)) {
            return @unlink($dir) ? 1 : 0;
        }
        
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $count += self::recursiveRmdir($path);
            } else {
                @unlink($path);
                $count++;
            }
        }
        
        @rmdir($dir);
        return $count;
    }
}

class Menu {

    public static function main() {
        while (true) { 
            _cle();
            logx('', "\nMENU", true, true);
            echo FGd['CYN'] . RUNNER . "\n" . RSET;
            logx('info', IP(), true, true);
            echo FGd['CYN'] . TIMEZONE() . "\n" . RSET;
            logx('info', "  [0] SETTINGS\n  [1] RUN BOT\n  [x] EXIT", true, true);

            switch (trim(_rl('  input [boot]: '))) {
                case '0': if (self::tools()) return true; break; 
                case '1': self::runBot(); break;
                case 'x': exit();
                default: _cle(); bootApp(); return true;
            }
        }
    }

    public static function tools() {
        _cle();
        while (true) {
            KEYS::sync(); 
            logx('warn', "\nINFO", true, true);
            echo FGb['BLU'].BOLD."    IP"."    : ".FGd['CYN'].UNDR.IP()."\n".RSET;
            echo FGb['BLU'].BOLD."    TZ"."    : ".FGd['CYN'].UNDR.TIMEZONE()."\n".RSET;
            
            logx('warn', "API STATUS", true, true);
            foreach ($GLOBALS['_CTX']['apikey'] as $p => $k) {
                $status = empty($k) ? FGd['RED']."[NO]" : FGb['GRN']."[ON]";
                printf("    %s %-20s\n", $status, $p. RSET);
            }
    
            logx('', "\nSETTINGS", true, true);
            logx('', "  [0] USAGE INFO");
            logx('', "  [1] UPDATE APIKEY");
            logx('', "  [2] PROXY SETTINGS");
            logx('', "  [3] HARD RESET (ALL BOTS)");
            
            switch (trim(_rl('  input [back]: '))) {
                case '0': _cle(); self::usage(); break;
                case '1': _cle(); KEYS::newKeys(); _cle(); break;
                case '2': if (self::proxy()) return true; break;
                case '3': self::hardReset(); break;
                default: _cle(); return false;
            }
        }
    }

    private static function hardReset() {
        _cle();
        logx('warn', "\n  HARD RESET (ALL BOTS)", true, true);
        logx('err', "  This will DELETE ALL COOKIES & SESSION DATA from ALL BOTS!");
        logx('err', "  Action cannot be undone!");
        echo "\n";
        
        $confirm = strtolower(trim(_rl('  Type "yes" to confirm: ')));
        
        if ($confirm !== 'yes') {
            logx('info', "  Cancelled.");
            _sle(1);
            _cle();
            return;
        }
        
        $delCred = strtolower(trim(_rl('  Also delete credentials? (yes/no) [no]: ')));
        $includeCred = ($delCred === 'yes');
        
        echo "\n";
        
        $result = BOTS::hardResetAll($includeCred);
        
        logx('warn', "\n  HARD RESET COMPLETED!", true, true);
        logx('info', "  Total items deleted: {$result['deleted']}");
        
        if (!empty($result['details'])) {
            echo "\n";
            foreach ($result['details'] as $bot => $files) {
                echo "  " . FGb['GRN'] . $bot . RSET . ":\n";
                foreach (array_slice($files, 0, 10) as $file) {
                    echo "    - $file\n";
                }
                if (count($files) > 10) {
                    echo "    ... and " . (count($files) - 10) . " more\n";
                }
            }
        }
        
        _rl("\n  Press Enter to continue...");
        _cle();
    }

    public static function runBot() {
        $bots = BOTS::getInstalled(); 
        if (!$bots) {
            logx('err', "  UNAVAILABLE"); 
            _sle(2); 
            return; 
        }
        
        logx('warn', "\n  AVAILABLE BOTS:", true, true);
        logx('err', "  dont forget check usage on settings");
        logx('err', "  cookie handling is auto passed by email/credential");
        logx('info', "  Tips:");
        logx('info', "    [N]       - Run bot normally");
        logx('info', "    N_cle     - Clean bot (delete all data except credentials)");
        logx('info', "    N_cre     - Delete credentials only");
        logx('info', "    N_hard    - Hard reset (delete cookies & session data)");
        
        foreach ($bots as $i => $bot) {
            printf("    [%d] %s\n", $i + 1, BOLD . FGb['GRN'] . $bot . RSET);
        }
        
        $input = trim(_rl('    input [back]: '));
        if ($input === '' || $input === 'back') return;
        
        $parts = explode('_', $input);
        $sel = $parts[0];
        $cmd = $parts[1] ?? null;
        
        // Special confirmation for hard reset
        if ($cmd === 'hard') {
            _cle();
            logx('warn', "  HARD RESET for bot #$sel", true, true);
            logx('err', "  This will delete ALL COOKIES & SESSION DATA for this bot!");
            $confirm = strtolower(trim(_rl('  Type "yes" to confirm: ')));
            if ($confirm !== 'yes') {
                logx('info', "  Cancelled.");
                _sle(1);
                _cle();
                return;
            }
        }
        
        // Execute via BOTS
        $result = BOTS::exec($sel, $cmd);
        
        if ($result['status'] === 'error') {
            logx('err', $result['message']);
            _sle(2);
            return;
        }
        
        if ($result['action'] === 'run') {
            $GLOBALS['_CTX']['current_bot'] = $result['bot'];
            logx('info', $result['message']);
            _sle(1);
            _cle();
            require $result['bot_file'];
            exit;
        }
        
        // For cle/cre/hard actions
        logx('ok', $result['message']);
        _sle(1);
        _cle();
    }
    
    public static function autoRun() {
        $sel = trim((string)getenv('BOT'));
        
        if (empty($sel)) {
            logx('err', "BOT environment variable not set");
            die();
        }
        
        $result = BOTS::exec($sel, null);
        
        if ($result['status'] === 'error') {
            logx('err', $result['message']);
            die();
        }
        
        logx('info', "Auto-running: {$result['bot']}");
        KEYS::sync();
        
        $GLOBALS['_CTX']['current_bot'] = $result['bot'];
        require $result['bot_file'];
        exit;
    }

    public static function proxy() {
        while (true) {
            
            logx('warn', "\n  FORMATS:", true, true);
            logx('err', '    also support from environment variable');
            logx('err', "    dont forget check usage on settings");
            logx('', '    ssh://USER:PASS@HOST:22');
            logx('', '    http://USER:PASS@HOST:8080');
            logx('', '    socks5://USER:PASS@HOST:1080');
            
            if (!empty($GLOBALS['_CTX']['proxy'])) {
                $p = $GLOBALS['_CTX']['proxy'];
                $alive = Proxy::_enable();
                $st = $alive ? FGd['GRN'].'ALIVE' : FGd['RED'].'DEAD';
                logx('info', "\n    PROXY ACTIVE: {$p['host']}:{$p['port']} $st", true, true);
            } else {
                logx('err', "\n    PROXY: OFF");
            }
            print('    [1]');
            logx('ok', " ENABLE PROXY");
            print('    [2]');
            logx('ok', " DISABLE PROXY");
            print('    [3]');
            logx('ok', " RESTART/REFRESH");

            switch (trim(_rl('    input [back]: '))) {
                case '1':
                    $raw = trim(_rl('    url: '));
                    if ($raw === '') continue 2;
                    putenv("PROXY=$raw");
                    $_ENV['PROXY'] = $raw;
                    styler("connecting", fn() => Proxy::Load());
                    _sle(1); _cle();
                    break;
                case '2':
                    Proxy::_unable();
                    logx('err', "    disabled");
                    _sle(1); _cle();
                    break;
                case '3':
                    bootApp();
                    return true;
                default: _cle(); return false;
            }
        }
    }

    public static function usage() {
        logx('warn', "\n USAGE", true, true);
        echo FGo['BLU'].BOLD."    CI / ENV OPTIONS\n".RSET;
        echo "    BOT='name'   : Run specific bot\n";
        echo "    PROXY='url'  : Use specific tunnel\n";
        echo "    API='name'   : Solver api\n";
        echo "    KEY='key'    : Solver key\n";
        echo "    ENV='1'      : load .env\n";
        echo "    CI='1'       : ci ready auto run\n";
        echo "    AN='0'       : disable animation\n";
        echo "\n    eg:\n";
        logx('info', "BOT=feyorratop mail=xxx pass=xxx php run.php", true, true);
        logx('info', "BOT=botname API=tertuyul KEY=abc login=xxx  CI=1 PROXY=type://host:port php run.php", true, true);
        logx('info', "BOT=botname API=tertuyul KEY=321 CI=1 login=xxx php run.php", true, true);
        logx('info', "ENV=1 php run.php", true, true);
        _rl("\n Enter to back...");
        _cle();
    }
}

