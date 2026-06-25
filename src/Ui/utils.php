<?php

#STYLER



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

function _lib($type = null, $host = null, $mail = null) {    $host = $host ?? 'unknown_host';
    
    $cleanHost = parse_url($host, PHP_URL_HOST) ?: $host;
    $cleanHost = preg_replace('/[^a-zA-Z0-9]/', '_', $cleanHost);

    $user = ($mail && strpos($mail, '@') !== false) ? strstr($mail, '@', true) : ($mail ?? '');
    $user = preg_replace('/[^a-zA-Z0-9]/', '_', $user);

    $workDir = LIBDIR;
    if ($type !== null) $workDir .= "/" . $type;
    
    $workDir .= "/" . $cleanHost;

    if ($user !== '') $workDir .= "/" . $user;

    $workDir = str_replace('//', '/', $workDir);

    if (!is_dir($workDir)) mkdir($workDir, 0777, true);

    return rtrim($workDir, '/');
}


function _die() {
    Logger::X('err', 'bloman bener');
    Logger::X('info', 'tunggu update', true, true);
    die;
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
