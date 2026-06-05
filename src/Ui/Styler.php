<?php

/** @file styler.php
 * @function logg
     * @param string $msg
     * @param bool $clock
     * @param bool $n
     * @return void
 * @function logx
     * @param string $i
     * @param string $msg
     * @param bool $n
     * @param bool $b
     * @return void
 * @function styler
     * @param string $text
     * @param callable $task
     * @return mixed
 * @function spinner
     * @param string $text
     * @param callable $task
     * @return mixed
 * @function underline
     * @param string $text
     * @param callable $task
     * @return mixed
 * @function loading
     * @param string $text
     * @param callable $task
     * @return mixed
 * @function gradient
     * @param string $text
     * @param callable $task
     * @return mixed
 * @function moveCursor
     * @param int $r
     * @param int $c
     * @return void
 * @function setCursor
     * @return void
 * @function getCursor
     * @return void
 * @function taskLine
     * @return void
 * @function logLine
     * @return void
 * @function banner
     * @return void
 * @function taskPrintCenter
     * @param string $text
     * @param string $level
     * @return void
 */

function logg($clock = true, $msg = '', $n = true, $check = false) {

    $theme = [
        ['bg' => BG['WHT'], 'bgk' => 'WHT'],
        ['bg' => BG['YLW'], 'bgk' => 'YLW'],
        ['bg' => BG['CYN'], 'bgk' => 'CYN'],
        ['bg' => BG['GRN'], 'bgk' => 'GRN'],
        ['bg' => BG['RED'], 'bgk' => 'RED'],
        ['bg' => BG['BLU'], 'bgk' => 'BLU'],
        ['bg' => BG['MAG'], 'bgk' => 'MAG'],
        ['bg' => BG['BLK'], 'bgk' => 'BLK'],
    ];

    $pick = $theme[array_rand($theme)];

    $fg = FGo['BLK'];
    $fgk = 'BLK';

    if ($pick['bgk'] === 'BLK') {
        $fg = FGo['WHT'];
        $fgk = 'WHT';
    }

    $time = $clock
        ? BOLD.FGo['WHT']."[" . date('H:i:s') . "] ".RSET
        : "";

    if ($check) {
        echo "BG={$pick['bgk']} FG={$fgk}\n";
    }

    $formatted =
        //"\r" .
        $time
        . $pick['bg']
        #. BOLD
        . $fg
        . BOLD
        . " " . trim($msg) . " "
        . RSET;

    if (outTty()) {
        echo $formatted . ($n ? PHP_EOL : "");
        fflush(STDOUT);
    } else {
        logx('', $msg, $n);
    }
}

function logx($in = "", $msg = "\n", $n = true, $b = false) {
    $b = $b ? BOLD : '';
    if (!$n && !animate()) $n = true;
    $i = trim($in);

    switch (strtoupper(trim($i))) {
        case 'ERR':
        case 'ERROR':
            $p = BOLD.FGb['RED'];  break;
            
        case 'INFO': $p = $b.FGb['CYN']; break;
        
        case 'WARN':
        case 'WARNING':
            $p = $b.FGb['YLW']; break;
            
        case 'OK':
        case 'SUC':
        case 'SUCCESS':
            $p = $b.FGb['GRN']; break;
            
        default: $p = $b.FGo['WHT']; break;
    }

    $out = $p.$msg.RSET.($n ? PHP_EOL : '');
    print($out);
    fflush(STDOUT);
}



function moveCursor($r,$c) {
    if (!outTty()) return;
    echo ANN . "{$r};{$c}H";
}

function setCursor() {
    if (!outTty()) return;
    echo ANN . "s";
}

function getCursor() {
    if (!outTty()) return;
    echo ANN . "u";
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
    global $BANNER;
    $botName = $GLOBALS['_CTX']['current_bot'] ?? '71';

    $w     = $BANNER['width'];
    $inner = $w - 2;

    $_co = FGb['MAG'];
    $_tx = FGb['WHT'];

    $lines = [
        strtoupper((string)$botName),
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

