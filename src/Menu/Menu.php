<?php

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
            
            $rlM = trim(_rl(' input [reboot]: '));
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
            
            Logger::X('info', "\n [0 USAGE INFO\n [1 UPDATE APIKEY\n [2 PROXY SETTINGS\n [3 HARD RESET (ALL BOTS)\n [4 RESET AUTH_KEY", true, true);
            
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
                case '4':
                    @unlink(CREDIR.'/credentials');
                    self::$banner->task2('info', 'dont forget to reboot');
                    _sle(3);
                    return true;
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
        
        self::$banner->task1('ok', 'HARD RESET COMPLETED!');
        self::$banner->task2('info', "Deleted: {$result['deleted']} items");
        
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
                } else $line .= str_repeat(" ", $maxLen + 6);
                
                $line .= "  ";
                
                if ($second < $total) {
                    $num = str_pad($second + 1, 2, " ", STR_PAD_LEFT);
                    $name = str_pad($bots[$second], $maxLen, " ");
                    $line .= "[$num " . BOLD . FGb['GRN'] . $name . RSET;
                }
                
                echo $line . "\n";
            }
            echo "\n";
            
            $input = trim(_rl('  input [back]: '));
            if ($input === '' || $input === 'back') return;
            
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
                self::$banner->task2('info', 'Starting: ' . $result['bot']);
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
            self::$banner->show();
            self::$banner->task1('info', 'PROXY SETTINGS');
            
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
            Logger::X('', '    http://USER:PASS@HOST:8080');
            Logger::X('', '    https://USER:PASS@HOST:8080');
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
