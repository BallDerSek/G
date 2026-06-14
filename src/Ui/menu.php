<?php

class Menu {

    /**
     * MENU UTAMA
     */
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
                #case '2': self::getBotMenu(); break;
                case 'x': exit();
                default: _cle(); bootApp(); return true;
            }
        }
    }

    /**
     * MENU SETTINGS 
     */
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
            
            switch (trim(_rl('  input [back]: '))) {
                case '0': _cle(); self::usage(); break;
                case '1': _cle(); KEYS::newKeys(); _cle(); break;
                case '2': if (self::proxy()) return true; break;
                default: _cle(); return false;
            }
        }
    }

    /**
     * MENU RUNNER
     */
    public static function runBot() {
        $bots = BOTS::getInstalled(); 
        if (!$bots) {
            logx('err', "  UNAVAILABLE"); 
            _sle(2); 
            return; 
        }
        
        logx('warn', "\n  AVAILABLE:", true, true);
        logx('err', "  dont forget check usage on settings");
        logx('err', "  cookie handling is auto passed by email/credential");
        logx('info', "  Tips: Add suffix '_cle' to clean or '_cre' to reset credentials");
        logx('info', "        1_cle or 1_cre");
        foreach ($bots as $i => $bot) {
            printf("    [%d] %s\n", $i + 1, BOLD.FGb['GRN'].$bot.RSET);
        }
        
        $input = trim(_rl('    number[back]: '));
        
        $parts = explode('_', $input);
        $sel = (int)$parts[0];
        $cmd = $parts[1] ?? null;

        if (isset($bots[$sel - 1])) {
            $bot = $bots[$sel - 1];
            $botDir = BOTDIR . "/$bot";

            if ($cmd === 'cre') {
                $crePath = $botDir . "/credentials";
                if (file_exists($crePath)) {
                    (is_dir($crePath)) ? self::recursiveRmdir($crePath) : @unlink($crePath);
                    logx('ok', "Credentials cleaned");
                }
            }

            if ($cmd === 'cle') {
                foreach (scandir($botDir) as $file) {
                    if (in_array($file, ['.', '..', "$bot.php"])) continue;
                    $p = "$botDir/$file";
                    (is_dir($p)) ? self::recursiveRmdir($p) : @unlink($p);
                }
                logx('ok', "$bot cleaned up");
            }
            
            $tmpFiles = glob($botDir . "/*.tmp"); 
            if ($tmpFiles) {
                array_map('@unlink', $tmpFiles);
            }
            
            $GLOBALS['_CTX']['current_bot'] = $bot;
            logx('info', "Loading: $bot...");
            _sle(1); _cle();
            require $botDir . "/$bot.php";
            exit;
        }
    }

    /**
     * Helper 
     */
    private static function recursiveRmdir($dir) {
        if (!is_dir($dir)) return @unlink($dir);
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $dir . '/' . $file;
            (is_dir($path)) ? self::recursiveRmdir($path) : @unlink($path);
        }
        return @rmdir($dir);
    }
    
    /**
     * override auto-run "export BOT=bot"
     */
    public static function autoRun() {

        $sel = trim((string)getenv('BOT'));
        
        $bots = BOTS::getInstalled(); 
        
        if (!$bots) {
            logx('err', "No bots in " . BOTDIR);
            die();
        }

        $bot = ctype_digit($sel) ? ($bots[(int)$sel - 1] ?? null) : (in_array($sel, $bots) ? $sel : null);
        
        if (!$bot) {
            logx('err', "Bot '$sel' not found");
            die();
        }

        $botFile = BOTDIR . "/$bot/$bot.php";
        
        _sle(1); 
        _cle();
        logx('info', "Auto-running: $bot");
        
        KEYS::sync();
        
        $GLOBALS['_CTX']['current_bot'] = $bot;
        require $botFile;
        exit;
    }

    /**
     * MENU INSTALLER 
    public static function getBotMenu() {
        $pkgs = BOTS::getPackages(); // Ambil dari BOT
        if (!$pkgs) { logx('err', "No packages found"); _sle(2); return; }
        
        _cle();
        logx('warn', "AVAILABLE PACKAGES:");
        foreach ($pkgs as $i => $f) printf("  [%d] %s\n", $i + 1, $f);
        
        $sel = trim(_rl('number: '));
        if (isset($pkgs[(int)$sel - 1])) {
            BOTS::install($pkgs[(int)$sel - 1]);
            _sle(2);
        }
    }
     */

    /**
     * MENU PROXY
     */
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

    /**
     * MENU USAGE
     */
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

class BOTS {
    
    # installed scanner
    public static function getInstalled(): array {
        $bots = [];
        foreach (scandir(BOTDIR) as $dir) {
            if ($dir[0] === '.') continue;
            if (is_dir(BOTDIR."/$dir") && file_exists(BOTDIR."/$dir/$dir.php")) $bots[] = $dir;
        }
        return $bots;
    }

}

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
