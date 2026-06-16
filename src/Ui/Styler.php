<?php











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

