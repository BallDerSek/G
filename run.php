<?php

$GLOBALS['_CTX'] ??= [
    'geo'   => [],
    'proxy' => [],
    'apikey'=> [],
    'banner'=> [],
];

require_once __DIR__ . '/src/loader.php';

function _rl($prompt = '') {
    $old = null;
    if (function_exists('pcntl_signal_get_handler') && function_exists('pcntl_signal')) {
        $old = pcntl_signal_get_handler(SIGINT);
        pcntl_signal(SIGINT, SIG_DFL);
    }

    $line = readline($prompt);

    if ($old !== null && function_exists('pcntl_signal')) {
        pcntl_signal(SIGINT, $old);
    }
    return $line;
}

startingLib:
    (function () {
        
        _cle();
        proxyLoad();
        checkEnv();
        checkDeps();
        checkGeo();
        
        $key = credential()['_authApi_'];
        $api = Api::use('gmxch', $key);
        
        define('AUTH_KEY', $api->getInfo());
        define('AUTH_API', $api);
        
    })();

#print_r($GLOBALS['_CTX']); die;

if (!hasTty() || getenv('BOT')) {
    $sel = trim((string)getenv('BOT'));
    if ($sel === '') {
        logx('err', "invalid");
        die;
    }
    $bots = viewBot();
    if (!$bots) {
        logx('err', "unknown bot"); 
        die; 
    }

    if (ctype_digit($sel)) {
        $i = (int)$sel - 1;
        if (!isset($bots[$i])) {
            logx('err', "Invalid bot");
            die; 
        }
        $bot = $bots[$i];
    } else {
        if (!in_array($sel, $bots, true)) {
            logx('err', "unknown bot");
            die; 
        }
        $bot = $sel;
    }

    $botFile = BOTDIR . "/$bot/$bot.php";
    _sle(1); _cle(); require $botFile; exit;
}

while (true) { 

    logx('', "\nMENU   ", false, true);
    echo FGd['CYN'].RUNNER."\n".RSET;
    logx('info', IP(), true, true);
    echo FGd['CYN'].TIMEZONE()."\n".RSET;
    logx('', "  [0 settings\n  [1 Run Bot\n  [2 Get bot");

    switch (trim(_rl('number: '))) {
        case '0':
            if (toolsMenu()) {
                goto startingLib; 
                break;
            }

        case '1': $bots = viewBot();
            if (!$bots) {
                logx('err', "unavailable");
                break;
            } 
            logx('', "\nAvailable:");
            foreach ($bots as $i => $bot) {
                printf("    [%d %s\n", $i + 1, $bot);
            } 
            $sel = trim(_rl('number: '));
            if (!ctype_digit($sel) || !isset($bots[(int)$sel - 1])) {
                logx('err', "invalid"); 
                _sle(1); 
                _cle(); 
                break; 
            }
            $bot = $bots[$sel - 1];
            $botFile = BOTDIR . "/$bot/$bot.php";
            _sle(1); 
            _cle(); 
            require $botFile;break; 
        
        case '2':
            $pkgs = viewTxt();
            if (!$pkgs) {
                logx('err', "unavailable");
                break;
            } 
            logx('', "\nAvailable:");
            foreach ($pkgs as $i => $f) {
                printf("    [%d %s\n", $i + 1, $f);
            } 
            $sel = trim(_rl('number: '));
            if (!ctype_digit($sel) || !isset($pkgs[(int)$sel - 1])) {
                logx('err', "invalid"); 
                _sle(1); 
                _cle(); break;
            }
            getBot($pkgs[$sel-1]); 
            break;
        default: 
            _cle(); 
            goto startingLib;
    }
} 