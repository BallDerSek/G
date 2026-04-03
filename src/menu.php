<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#PROXY MENU
function proxyMenu() {
    while (true) {
        if (!empty($GLOBALS['_CTX']['proxy'])) {
            $p = $GLOBALS['_CTX']['proxy'];
            $t = $p['type'] ?? null;
            $tn = ($t === CURLPROXY_SOCKS5_HOSTNAME) ? 'socks5' : (($t === CURLPROXY_HTTP) ? 'http' : ((defined('CURLPROXY_HTTPS') && $t === CURLPROXY_HTTPS) ? 'https' : 'unknown'));
            $alive = proxyIsAlive();
            $st = $alive ? 'ALIVE' : 'DEAD';
            logx($alive ? 'info' : 'err', "\nPROXY: {$tn} {$p['host']}:{$p['port']} ($st)", true, true);
        } else {
            logx('err', "\nPROXY: OFF");
        }

        logx('', "  [0 usage\n  [1 Enable proxy\n  [2 Disable proxy\n  [3 restart");

        switch (trim(_rl('enter: '))) {
            case '1': {
                $raw = trim(_rl('url: '));
                if ($raw === '') {
                    logx('warn', "empty");
                    _sle(1); _cle();
                    continue 2;
                    }
                putenv("PROXY=$raw");
                $_ENV['PROXY'] = $raw;
                styler("connecting", fn() => proxyLoad());

                if (proxyIsAlive()) {
                    logx('info', "enabled", true, true);
                } else {
                    proxyDisable();
                    logx('err', "failed", true, true);
                }
                _sle(1); _cle();
                break;
            }
            case '2':
                proxyDisable();
                logx('err', "disabled", true, true);
                _sle(1); _cle();
                break;
            case '3':
                logx('info', "refreshing", true, true);
                _sle(1); _cle();
                return true;
            case '0':
                _cle();
                logx('warn', "\nUSAGE", true, true);
                logx('', "  ssh://USER:PASS@HOST:22");
                logx('', "  http://USER:PASS@HOST:8080");
                logx('', "  socks5://USER:PASS@HOST:1080");
                break;
            default: _cle(); return false;
        }
    }
}

#MAIN MENU 
define('USAGE', 'BOT=botname PROXY="type://user:pass@host:port" CI=1 AN=0 API="solvername" KEY="solverkey" mail="mail@mail.com" pass="pass" php lib.php'); 
function usageInfo() {
    logx('warn', "\nUSAGE", true, true);
    
    echo FGo['BLU'].BOLD."    CLI OPTIONS\n".RSET;
    logx('', "\t0: additional settings", true, true);
    logx('', "\t1: choose as shown", true, true);
    logx('', "\t2: adding bot via .txt", true, true);
    logx('', "\t[empty]: restart/back", true, true);
    
    echo FGb['BLU'].BOLD."    CI OPTIONS\n".RSET;
    logx('',  "\tBOT='botname' \ (name or index)", true, true);
    logx('',  "\tproxy='type://user:pass@host:port' \ ", true, true);
    logx('',  "\tCI=1 \ (ci mode) ", true, true);
    logx('',  "\tENV=1 \ (load.env) ", true, true);
    logx('',  "\tAN=0 \ (disable styler) ", true, true);
    logx('',  "\tAPI='provider' \ ", true, true);
    logx('',  "\tKEY='key' \ ", true, true);
    logx('',  "\tmail=1@2.com \ ", true, true);
    logx('',  "\tpass=pass \ ", true, true);
    
    logx('',  "\n\tCI usage:", true, true);
    logx('info', USAGE, true, true);
    logx('', "\tnote: CI input depend on each bot", true, true);

} 

function toolsMenu() {
    _cle();
    while (true) {

        logx('warn', "LOG", true, true);
        echo FGb['BLU'].BOLD."    patched"."     ".RSET;
        echo FGd['CYN'].UNDR."2022.07\n".RSET;
        echo FGb['BLU'].BOLD."    updated"."     ".RSET;
        echo FGd['CYN'].UNDR."2026.03\n".RSET;
        
        logx('warn', "GEO", true, true);
        echo FGb['BLU'].BOLD."    IP"."          ".RSET;
        echo FGd['CYN'].UNDR.IP()."\n".RSET;
        echo FGb['BLU'].BOLD."    TIMEZONE"."    ".RSET;
        echo FGd['CYN'].UNDR.TIMEZONE()."\n".RSET;
        
        logx('warn', "API provider", true, true);
        foreach ($GLOBALS['_CTX']['apikey'] as $p => $k) {
            echo FGb['BLU'].BOLD.sprintf("    %-22s:\n", $p).RSET;
            echo FGd['CYN'].UNDR.sprintf("\t\t%s\n", $k).RSET;
        } echo RSET;
        
        logx('', "SETTINGS", true, true);
        logx('', "  [0 USAGE\n  [1 APIKEY\n  [2 PROXY");
        switch (trim(_rl('enter: '))) {
            case '0': _cle(); usageInfo(); return;
            case '1': _cle(); newKeys(); _sle(1); _cle(); break;
            case '2': _cle(); if (proxyMenu()) return true; break;
            default: _cle(); return;
        }
    }
}

//BOT scanning 
function viewBot(): array {
    $bots = [];
    if (!is_dir(BOTDIR)) { 
        mkdir(BOTDIR, 0777, true); 
        return $bots;
    }
    foreach (scandir(BOTDIR) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $botFile = BOTDIR . "/$dir/$dir.php";
        if (is_dir(BOTDIR . "/$dir") && file_exists($botFile)) {
            $bots[] = $dir;
        }
    }
    return $bots;
}

function pickIndex(array $items, callable $render): int {
    $idx = 0; $count = count($items);

    system('stty -icanon -echo min 0 time 1');
    try {
        while (true) {
            _cle();
            $render($items, $idx);

            $key = fread(STDIN, 3);
            if ($key === '' || $key === false) {
                continue;
            }

            if ($key === "\033[A") $idx = ($idx - 1 + $count) % $count;
            elseif ($key === "\033[B") $idx = ($idx + 1) % $count;
            elseif ($key === "\n" || $key === "\r") break;
        }
        _cle();
    } finally {
        @system('stty sane');
    }
    return $idx;
}

function CLI_env() {
    if (!hasTty()) return CI_env();

    $keys = $GLOBALS['_CTX']['apikey'];
    $providers = array_merge(['no apikey'], array_keys($keys), ['new apikey']);

    $idx = pickIndex($providers, function($providers, $idx) use ($keys) {
        echo "SELECT\n\n";
        foreach ($providers as $i => $url) {
            $status = array_key_exists($url, $keys) ? (empty($keys[$url]) ? '[NO]' : '[ON]') : '';
            echo BOLD.$status.($i === $idx ? FGo["BLU"]." => " : "    ").$url.RSET."\n";
        }
    });

    $endpoint = $providers[$idx];

    if ($endpoint === 'no apikey') { echo "without apikey\n"; return null; }
    if ($endpoint === 'new apikey') { newKeys(); return CLI_env(); }

    if (empty($keys[$endpoint])) {
        echo "{$endpoint}\n";
        $apiKey = trim(_rl("apikeys: "));
        if ($apiKey === '') die("can't be empty");

        $solver = selKeys($endpoint, $apiKey);
        if (!viewKeys($solver)) die("rejected");

        $keys[$endpoint] = $apiKey;
        putKeys($keys);
        echo "VALID\n";
        return $solver;
    }

    return selKeys($endpoint, $keys[$endpoint]);
}

function newKeys() {
    if (!hasTty()) { logx('warn', "requires TTY"); die; }

    $keys =& $GLOBALS['_CTX']['apikey'];
    $providers = array_keys($keys);

    $idx = pickIndex($providers, function($providers, $idx) use ($keys) {
        echo "UPDATE:\n\n";
        foreach ($providers as $i => $url) {
            $status = empty($keys[$url]) ? '[NO]' : '[ON]';
            echo BOLD.$status.($i === $idx ? FGo["BLU"]." => " : "    ").$url.RSET."\n";
        }
    });

    $endpoint = $providers[$idx];
    echo "{$endpoint}\n\n";

    $apiKey = trim(_rl("apikeys: "));
    if ($apiKey === '') { logx('warn', "Cancelled"); return; }

    $solver = selKeys($endpoint, $apiKey);
    if (!viewKeys($solver)) { logx('err', "rejected"); return; }

    $keys[$endpoint] = $apiKey;
    putKeys($keys);
    logx('info', 'UPDATED');
}