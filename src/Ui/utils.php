<?php

/** @file styler/utils.php
 * @constant string ANN
 * @constant string RSET
 * @constant string BOLD
 * @constant string DIMM
 * @constant string ITAL
 * @constant string UNDR
 * @constant string BLNK
 * @constant string RPID
 * @constant string RVRS
 * @constant string HDDN
 * @constant string STRK
 * @constant string FG256
 * @constant string BG256
 * @constant array FGo
 * @constant array FGb
 * @constant array FGd
 * @constant array BG
 * @function _sle
     * @param int|float $time
     * @return int
 * @function _cle
     * @return void
 * @function _clr
     * @return void
 * @function _get
     * @param string $path
     * @return string|null
 * @function _put
     * @param string $path
     * @param string $data
     * @param bool $append
     * @return bool
 * @function animate
     * @return bool
 * @function hasTty
     * @return bool
 * @function outTty
     * @return bool
 * @function _rl
     * @param string $prompt
     * @return string|false
 */
#STYLER
define("ANN", "\033["); 
define("FG256", ANN."38;5;"); define("BG256", ANN."48;5;");

define("RSET", ANN."0m"); define("BOLD", ANN."1m");
define("DIMM", ANN."2m"); define("ITAL", ANN."3m");
define("UNDR", ANN."4m"); define("BLNK", ANN."5m");
define("RPID", ANN."6m"); define("RVRS", ANN."7m");
define("HDDN", ANN."8m"); define("STRK", ANN."9m"); 


#foreround colours origin 
define("FGo", [
  "BLK" => ANN."30m", "RED" => ANN."31m",
  "GRN" => ANN."32m", "YLW" => ANN."33m",
  "BLU" => ANN."34m", "MAG" => ANN."35m",
  "CYN" => ANN."36m", "WHT" => ANN."37m",
]);

#foreround colours bright 
define("FGb", [
  "BLK" => ANN."90m", "RED" => ANN."91m",
  "GRN" => ANN."92m", "YLW" => ANN."93m",
  "BLU" => ANN."94m", "MAG" => ANN."95m",
  "CYN" => ANN."96m", "WHT" => ANN."97m",
]);

#foreround colours dark 
define("FGd", [
  "BLK" => DIMM.FGo["BLK"], "RED" => DIMM.FGo["RED"],
  "GRN" => DIMM.FGo["GRN"], "YLW" => DIMM.FGo["YLW"],
  "BLU" => DIMM.FGo["BLU"], "MAG" => DIMM.FGo["MAG"],
  "CYN" => DIMM.FGo["CYN"], "WHT" => DIMM.FGo["WHT"],
]);

#background colours 
define("BG", [
  "BLK" => ANN."40m", "RED" => ANN."41m",
  "GRN" => ANN."42m", "YLW" => ANN."43m",
  "BLU" => ANN."44m", "MAG" => ANN."45m",
  "CYN" => ANN."46m", "WHT" => ANN."47m",
]);


function _sle($time) {
    return sleep($time);
}

function _cle() {
    system(PHP_OS_FAMILY === 'Windows' ? 'cls' : 'clear');
}

function _clr() {
    if (!outTty()) return;
    echo ANN . "2K\r";
}

function _get($path) {
    $s = @file_get_contents($path);
    return $s === false ? null : $s;
}

function _put($path, $data, $append = false) {
    $flags = $append ? FILE_APPEND : 0;
    return @file_put_contents($path, $data, $flags) !== false;
}

function _lib($host, $mail = null) {
    $cleanHost = parse_url($host, PHP_URL_HOST) ?: $host;
    $cleanHost = preg_replace('/[^a-zA-Z0-9]/', '_', $cleanHost);

    $user = '';

    if ($mail && strpos($mail, '@') !== false) {
        $user = strstr($mail, '@', true);
    } else {
        $user = $mail ?: '';
    }

    $user = preg_replace('/[^a-zA-Z0-9]/', '_', $user);

    $workDir = LIBDIR . "/{$cleanHost}";

    if ($user !== '') {
        $workDir .= "/{$user}";
    }

    if (!is_dir($workDir)) {
        mkdir($workDir, 0777, true);
    }

    return rtrim($workDir, '/');
}

function maskEmail($email) {
    $name = explode('@', $email)[0];
    $len = strlen($name);
    
    if ($len <= 2) {
        return "***" . $name; 
    }
    
    return "****" . substr($name, -2);
}

function animate() {
    static $ok = null;

    if ($ok !== null) {
        return $ok;
    }

    $ok = outTty()
        && function_exists('pcntl_async_signals')
        && function_exists('pcntl_waitpid')
        && function_exists('pcntl_fork')
        && function_exists('posix_kill');

    return $ok;
}

function hasTty() {
    static $tty = null;

    if ($tty !== null) {
        return $tty;
    }

    if (!defined('STDIN') || !is_resource(STDIN)) {
        return $tty = false;
    }

    if (function_exists('stream_isatty')) {
        return $tty = @stream_isatty(STDIN);
    }

    if (function_exists('posix_isatty')) {
        return $tty = @posix_isatty(STDIN);
    }

    return $tty = (PHP_OS_FAMILY === 'Windows');
}

function outTty() {
    static $tty = null;

    if ($tty !== null) {
        return $tty;
    }

    if (getenv('AN') === '0') {
        return $tty = false;
    }

    if (!defined('STDOUT') || !is_resource(STDOUT)) {
        return $tty = false;
    }

    if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_vt100_support')) {
        @sapi_windows_vt100_support(STDOUT, true);
    }

    if (function_exists('stream_isatty')) {
        return $tty = @stream_isatty(STDOUT);
    }

    if (function_exists('posix_isatty')) {
        return $tty = @posix_isatty(STDOUT);
    }

    return $tty = (PHP_OS_FAMILY === 'Windows');
}

function canRaw() {
    static $ok = null;

    if ($ok !== null) {
        return $ok;
    }

    $ok = hasTty()
        && PHP_OS_FAMILY !== 'Windows'
        && trim((string) shell_exec('command -v stty 2>/dev/null')) !== '';

    return $ok;
}

function _color($value) {
    foreach ([BG, FGo, FGb, FGd] as $set) {
        foreach ($set as $key => $val) {
            if ($val === $value) return $key;
        }
    }
    return null;
}

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
