<?php

# Context
function getDeps($deps) {
    if (empty($GLOBALS['_CTX']['deps'])) die(Logger::X('err', 'RUN SCRIPT NORMALLY!!!'));
    if (is_string($deps)) $deps = [$deps];
    foreach ($deps as $dep) if (empty($GLOBALS['_CTX']['deps'][$dep]) || !$GLOBALS['_CTX']['deps'][$dep]) return false;
    return true;
}
function AUTH_API() {
    return $GLOBALS['_CTX']['AUTH_API'];
}
function IP() {
    return $GLOBALS['_CTX']['geo']['ip'] ?? '0.0.0.0';
}
function COUNTRY() {
    return $GLOBALS['_CTX']['geo']['country'] ?? '';
}
function COUNTRY_CODE() {
    return $GLOBALS['_CTX']['geo']['country_code'] ?? 'ID';
}
function LANGUAGE() {
    return $GLOBALS['_CTX']['geo']['language'] ?? 'en-US,en';
}
function TIMEZONE() {
    return $GLOBALS['_CTX']['geo']['timezone'] ?? 'Asia/Jakarta';
}

# Files
function _get($path) {
    $s = @file_get_contents($path);
    return $s === false ? null : $s;
}
function _put($path, $data, $append = false) {
    $flags = $append ? FILE_APPEND : 0;
    return @file_put_contents($path, $data, $flags) !== false;
}
function _lib($type = null, $host = null, $mail = null) {
    $host = $host ?? 'unknown_host';

    $cleanHost = parse_url($host, PHP_URL_HOST) ?: $host;
    $cleanHost = preg_replace('/[^a-zA-Z0-9]/', '_', $cleanHost);

    $user = ($mail && strpos($mail, '@') !== false) ? strstr($mail, '@', true) : ($mail ?? '');

    $user = preg_replace('/[^a-zA-Z0-9]/', '_', $user);

    $workDir = LIBDIR;

    if ($type !== null) $workDir .= "/{$type}";

    $workDir .= "/{$cleanHost}";

    if ($user !== '') $workDir .= "/{$user}";

    $workDir = str_replace('//', '/', $workDir);

    if (!is_dir($workDir)) mkdir($workDir, 0777, true);

    return rtrim($workDir, '/');
}

# CLI
function hasTty() {
    static $tty = null;

    if ($tty !== null) return $tty;

    if (!defined('STDIN') || !is_resource(STDIN)) return $tty = false;

    if (function_exists('stream_isatty')) return $tty = @stream_isatty(STDIN);

    if (function_exists('posix_isatty')) return $tty = @posix_isatty(STDIN);

    return $tty = (PHP_OS_FAMILY === 'Windows');
}
function outTty() {
    static $tty = null;

    if ($tty !== null) return $tty;

    if (getenv('AN') === '0') return $tty = false;

    if (!defined('STDOUT') || !is_resource(STDOUT)) return $tty = false;

    if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_vt100_support')) @sapi_windows_vt100_support(STDOUT, true);

    if (function_exists('stream_isatty')) return $tty = @stream_isatty(STDOUT);

    if (function_exists('posix_isatty')) return $tty = @posix_isatty(STDOUT);

    return $tty = (PHP_OS_FAMILY === 'Windows');
}
function canRaw() {
    static $ok = null;

    if ($ok !== null) return $ok;

    $ok = hasTty() && PHP_OS_FAMILY !== 'Windows' && trim((string)shell_exec('command -v stty 2>/dev/null')) !== '';

    return $ok;
}
function animate() {
    static $ok = null;

    if ($ok !== null) return $ok;

    $ok = outTty() && function_exists('pcntl_async_signals') && function_exists('pcntl_waitpid') && function_exists('pcntl_fork') && function_exists('posix_kill');

    return $ok;
}

# Helpers
function _cle() {
    #return;
    system(PHP_OS_FAMILY === 'Windows' ? 'cls' : 'clear');
}
function _clr() {
    if (!outTty()) return;
    echo ANN . "2K\r";
}
function _rl($prompt = '') {
    $old = null;

    if (function_exists('pcntl_signal_get_handler') && function_exists('pcntl_signal')) {
        $old = pcntl_signal_get_handler(SIGINT);
        pcntl_signal(SIGINT, SIG_DFL);
    }

    $line = readline($prompt);

    if ($old !== null && function_exists('pcntl_signal')) pcntl_signal(SIGINT, $old);

    return $line;
}
function _sle($time) {
    return sleep($time);
}

# Ui
function logg($clock = true, $msg = '', $n = true, $check = false) {
    return Logger::G($clock, $msg, $n);
}
function logx($in = "", $msg = "\n", $n = true, $b = false) {
    return Logger::X($in, $msg, $n, $b);
}
function logm($mail, $mask = true) {
    return Logger::M($mail, $mask);
}
function styler($text, callable $task, $rndr = null) {
    return Animator::exec($text, $task, $rndr);
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

# Utils
function maskEmail($email) {
    $name = explode('@', $email)[0];
    $len = strlen($name);

    if ($len <= 2) return "***{$name}";

    return "****" . substr($name, -2);
}
function checkATB(&$err, $html) {
    if ($html && (stripos($html, 'nvalid Anti-Bot') !== false || stripos($html, 'Invalid AntiBot') !== false)) {
        $err++;
        return true;
    }

    return false;
}
function _die() {
    die(Logger::X('err', 'bloman bener') ?: Logger::X('info', 'tunggu update', true, true));
}

# Boot
function bootApp() {

    _cle();

    Check::Env();
    Check::Dep();
    Proxy::load();
    Check::Geo();

    KEYS::sync();

    $inn = Check::Inn();

    $k = Config::credential()['_authApi_'];
    if (!$k) $k = $inn;

    $a = Api::use('gmxch', $k);

    $GLOBALS['_CTX']['AUTH_API'] = $a;

    if (!defined('AUTH_KEY')) define('AUTH_KEY', $a->getInfo());
}
