<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

function logx($i = "", $msg = "\n", $n = true, $b = false) {
    $b = $b ? BOLD : '';

    switch (strtoupper($i)) {
        case 'ERR': $p = BOLD.FGb['RED'];  break;
        case 'INFO': $p = $b.FGb['CYN']; break;
        case 'WARN': $p = $b.FGb['YLW']; break;
        case 'OK': $p = $b.FGb['GRN']; break;
        default: $p = $b.FGo['WHT']; break;
    }

    $out = $p.$msg.RSET.($n ? PHP_EOL : '');
    print($out);
    fflush(STDOUT);
}

function styler($text, callable $task) {
    if (!animate()) {
        echo "\r"; _clr();
        echo $text; fflush(STDOUT);
        return $task();
    }

    $renderers = ['underline', 'loading', 'gradient', 'spinner'];
    $fn = $renderers[random_int(0, count($renderers) - 1)];
    return $fn($text, $task);
}

function spinner($text, callable $task) {
    if (!animate()) {
        echo "\r"; _clr();
        echo $text; fflush(STDOUT);
        return $task();
    }

    $frames = ['⠋','⠙','⠹','⠸','⠼','⠴','⠦','⠧','⠇','⠏'];

    $text = strtoupper($text);
    $len  = strlen($text);
    if ($len === 0) return $task();

    $pid = pcntl_fork();
    if ($pid === -1) { return $task(); }

    if ($pid === 0) {
        $run = true;
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function() use (&$run) { $run = false; });

        $i = 0;
        $base  = BOLD . FGo['BLU'];
        $spin  = BOLD . FGb['CYN'];
        $reset = RSET;

        while ($run) {
            $f = $frames[$i % count($frames)];
            echo "\r" . $spin . $f . $reset . " " . $base . $text . $reset;
            fflush(STDOUT);
            usleep(80_000);
            $i++;
        }
        echo "\r"; _clr(); echo "\r";
        exit(0);
    }

    try {
        return $task();
    } finally {
        if ($pid > 0) posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
        echo "\r"; _clr(); echo "\r";
    }
}

function underline($text, callable $task) {
    if (!animate()) {
        echo "\r"; _clr();
        echo $text; fflush(STDOUT);
        return $task();
    }
    
    $pos = 0; $dir = 1;
    
    $len = strlen($text); if ($len === 0) return $task();
    $pid = pcntl_fork(); if ($pid === -1) { return $task(); }

    if ($pid === 0) {
        $run = true;
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function() use (&$run) { $run = false; });

        while ($run) {
            $buf = "\r";
            for ($j = 0; $j < $len; $j++) {
                $buf .= ($j === $pos) ? (BOLD . UNDR . $text[$j] . RSET) : $text[$j];
            }
            echo $buf;
            fflush(STDOUT);
            usleep(70000);
            $pos += $dir;
            if ($pos >= $len - 1 || $pos <= 0) $dir *= -1;
        }
        echo "\r"; _clr(); echo "\r";
        exit(0);
    }

    try {
        return $task();
    } finally {
        if ($pid > 0) posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
        echo "\r"; _clr(); echo "\r";
    }
}

function loading($text, callable $task) {
    if (!animate()) {
        echo "\r"; _clr();
        echo $text; fflush(STDOUT);
        return $task();
    }
    
    $pos = 0; $dir = 1;
    
    $text = strtoupper($text);
    $len = strlen($text); if ($len === 0) return $task();
    $pid = pcntl_fork(); if ($pid === -1) { return $task(); }

    if ($pid === 0) {
        $run = true;
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function() use (&$run) { $run = false; });

        while ($run) {
            $base = BOLD . FGo['BLU'];
            $buf  = "\r" . $base;
            for ($j = 0; $j < $len; $j++) {
                $buf .= ($j === $pos) ? (BOLD.FGd['CYN'].RVRS.$text[$j].RSET.$base) : $text[$j];
            } $buf .= RSET;
            echo $buf; fflush(STDOUT); usleep(100000);
            $pos += $dir;
            if ($pos >= $len - 1 || $pos <= 0) $dir *= -1;
        }
        echo "\r"; _clr(); echo "\r";
        exit(0);
    }
    try {
        return $task();
    } finally {
        if ($pid > 0) posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
        echo "\r"; _clr(); echo "\r";
    }
}

function gradient($text, callable $task) {
    if (!animate()) {
        echo "\r"; _clr();
        echo $text; fflush(STDOUT);
        return $task();
    }
    
    $basePalette = [196,202,208,214,220,226,190,154,118,82,46,47,48,49,51,45,39,33,27,21,57,93,129,165];

    $trail = 10;

    $scale_palette = function(array $palette, int $n): array {
        $n = max(1, $n);
        $plen = count($palette);
        if ($plen === 0) return array_fill(0, $n, 15);
        if ($plen === 1) return array_fill(0, $n, $palette[0]);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $idx = (int) round($i * ($plen - 1) / max(1, $n - 1));
            $out[] = $palette[$idx];
        }
        return $out;
    };

    $text = strtoupper($text);

    $len = strlen($text); if ($len === 0) return $task();
    $pid = pcntl_fork(); if ($pid === -1) { return $task(); }
    $palette = $scale_palette($basePalette, $len);

    if ($pid === 0) {
        $head = 0; $gap = 0;
        $run = true;
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function() use (&$run) {  $run = false; });
        echo "\033[?25l";
        
        while ($run) {
            $buf = "\r";
            if ($gap > 0) { echo $buf.FGo['WHT'].$text.RSET; usleep(75000); $gap--; continue; }
            for ($j = 0; $j < $len; $j++) { $d = $head - $j; $buf .= ($d >= 0 && $d < $trail) ? (BOLD.FG256.$palette[$j]."m".$text[$j].RSET) : (FGb['BLK'] . $text[$j].RSET); }
            echo $buf; fflush(STDOUT); usleep(75000);
            $head++; if ($head > ($len + $trail)) { $head = 0; $gap = 6; }
        } 
        echo "\033[?25h";
        echo "\r"; _clr(); echo "\r";
        exit(0);
    }

    try {
        return $task();
    } finally {
        if ($pid > 0) posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
        echo "\033[?25h";
        echo "\r"; _clr(); echo "\r";
    }
}



function moveCursor($r,$c) {
    if (!outTty()) return;
    echo ANN . "{$r};{$c}H";
}

function setCursor() {
    if (!outTty()) return;
    echo "\033[s";
}

function getCursor() {
    if (!outTty()) return;
    echo "\033[u";
}

$GLOBALS['_CTX']['banner'] = [
    'topLine' => 1,
    'width'  => 60,
    'taskLine' => 5,
    'bottomLine' => 8,
];

$GLOBALS['BANNER'] =& $GLOBALS['_CTX']['banner'];

function taskLine() {
    global $BANNER;
    moveCursor($BANNER['bottomLine'] + 1, 1); 
    _clr();
    fflush(STDOUT);
}

function logLine() {
    global $BANNER;

    if (!isset($BANNER['logLine'])) {
        $BANNER['logLine'] = ($BANNER['bottomLine'] ?? 0) + 2;
    }

    moveCursor($BANNER['logLine'], 1);
    $BANNER['logLine']++;      
    fflush(STDOUT);
}

function banner() {
    global $BANNER, $bot;

    $w     = $BANNER['width'];
    $inner = $w - 2;

    $_co = FGb['MAG'];
    $_tx = FGb['WHT'];

    $lines = [
        strtoupper($bot),
        IP(),
        TIMEZONE(),
        "",
    ];
    $taskIndex = 3;

    // top border
    moveCursor($BANNER['topLine'], 1);
    echo "\r"; _clr();
    echo $_co . "╔" . str_repeat("═", $inner) . "╗" . RSET . "\n";

    // content
    foreach ($lines as $i => $line) {
        $row = $BANNER['topLine'] + 1 + $i;
        moveCursor($row, 1);
        echo "\r"; _clr();

        $line = preg_replace('/\s+/', ' ', trim($line));
        if (strlen($line) > $inner) $line = substr($line, 0, $inner);
        $line = str_pad($line, $inner, " ", STR_PAD_BOTH);

        echo $_co . "║" . RSET . $_tx . $line . RSET . $_co . "║" . RSET . "\n";
    }

    // bottom border
    $bottom = $BANNER['topLine'] + 1 + count($lines);
    moveCursor($bottom, 1);
    echo "\r"; _clr();
    echo $_co . "╚" . str_repeat("═", $inner) . "╝" . RSET . "\n";

    $BANNER['bottomLine'] = $bottom;
    $BANNER['logLine'] = $BANNER['bottomLine'] + 2;

    // taskLine
    $BANNER['taskLine'] = $BANNER['topLine'] + 1 + $taskIndex;

    fflush(STDOUT);
}

function taskPrintCenter($text, $level='') {
    global $BANNER;
    if (!outTty()) {
        return logx($level ?: "INFO", $text);
    }
    
    $inner = (int)($BANNER['width'] ?? 60) - 2;
    if ($inner < 1) $inner = 58;

    setCursor();
    echo "\033[?25l";

    $text = preg_replace('/\s+/', ' ', trim($text));
    if (strlen($text) > $inner) $text = substr($text, 0, $inner);

    $padL = intdiv($inner - strlen($text), 2);
    $padR = $inner - strlen($text) - $padL;

    moveCursor((int)($BANNER['taskLine'] ?? 5), 1);
    echo "\r"; _clr();

    echo FGb['MAG'] . "║" . RSET;
    logx($level, str_repeat(" ", $padL) . $text . str_repeat(" ", $padR), false, true);
    echo FGb['MAG'] . "║" . RSET;

    fflush(STDOUT);

    getCursor();
    echo "\033[?25h";
    fflush(STDOUT);
}


function blogx($i="", $msg="\n", $n=true, $b=false) {
    if (!outTty()) return logx($i, $msg, $n, $b);

    global $BANNER;
    if (!isset($BANNER['logLine'])) {
        $BANNER['logLine'] = ($BANNER['bottomLine'] ?? 0) + 2;
    }

    moveCursor($BANNER['logLine'], 1);
    logx($i, $msg, $n, $b);

    $linesPrinted = substr_count((string)$msg, "\n") + ($n ? 1 : 0);
    if ($linesPrinted < 1) $linesPrinted = 1;
    $BANNER['logLine'] += $linesPrinted;
}