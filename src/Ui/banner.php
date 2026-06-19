<?php

class Banner {
    private static $instance = null;
    private bool $isTty;
    private bool $isShown = false;
    private ?int $width = null;

    private const TASK_LINE_1 = 4;
    private const TASK_LINE_2 = 5;

    private function __construct() {
        $this->isTty = outTty();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    private function terminalWidth() {
        if (!$this->isTty) {
            return 80;
        }

        if ($this->width !== null) {
            return $this->width;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('mode con | find "Columns"');

            if (preg_match('/Columns:\s+(\d+)/', $output, $matches)) {
                return $this->width = (int) $matches[1];
            }

            return $this->width = 80;
        }

        $width = (int) shell_exec('tput cols 2>/dev/null');

        return $this->width = ($width > 0 ? $width : 80);
    }

    public function setWidth(int $width): self {
        $this->width = $width;
        return $this;
    }

    public function getWidth() {
        return $this->terminalWidth();
    }

    private function moveCursor(int $row, int $col) {
        if (!$this->isTty) {
            return;
        }

        echo "\033[{$row};{$col}H";
    }

    private function saveCursor() {
        if (!$this->isTty) {
            return;
        }

        echo "\033[s";
    }

    private function restoreCursor() {
        if (!$this->isTty) {
            return;
        }

        echo "\033[u";
    }

    private function hideCursor() {
        if (!$this->isTty) {
            return;
        }

        echo "\033[?25l";
    }

    private function showCursor() {
        if (!$this->isTty) {
            return;
        }

        echo "\033[?25h";
    }

    private function clearLine() {
        if (!$this->isTty) {
            return;
        }

        echo "\r\033[K";
    }

    public function show() {
        _cle();
        $botName = $GLOBALS['_CTX']['current_bot'] ?? "runner version: ".RUNNER;
        $ip = IP();
        $timezone = TIMEZONE();

        if (!$this->isTty) {
            logx('INFO', "Bot: {$botName}");
            logx('INFO', "IP: {$ip}");
            logx('INFO', "Timezone: {$timezone}");
            return;
        }

        $width = $this->getWidth();
        $inner = max(1, $width - 2);

        $borderColor = FGb['MAG'];
        $titleColor  = FGb['GRN'];
        $textColor   = FGb['WHT'];

        $this->moveCursor(1, 1);
        $this->clearLine();
        echo $borderColor . "╔" . str_repeat("═", $inner) . "╗" . RSET . PHP_EOL;

        $this->moveCursor(2, 1);
        $this->clearLine();

        $title = str_pad(
            strtoupper($botName),
            $inner,
            ' ',
            STR_PAD_BOTH
        );

        echo $borderColor . "║" . RSET .
             $titleColor . $title . RSET .
             $borderColor . "║" . RSET . PHP_EOL;

        $this->moveCursor(3, 1);
        $this->clearLine();

        $info = "{$ip} ({$timezone})";

        if (strlen($info) > $inner) {
            $info = substr($info, 0, $inner);
        }

        $info = str_pad(
            $info,
            $inner,
            ' ',
            STR_PAD_BOTH
        );

        echo $borderColor . "║" . RSET .
             $textColor . $info . RSET .
             $borderColor . "║" . RSET . PHP_EOL;

        for ($row = self::TASK_LINE_1; $row <= self::TASK_LINE_2; $row++) {
            $this->moveCursor($row, 1);
            $this->clearLine();

            echo $borderColor . "║" . RSET .
                 str_repeat(' ', $inner) .
                 $borderColor . "║" . RSET . PHP_EOL;
        }

        $this->moveCursor(6, 1);
        $this->clearLine();

        echo $borderColor . "╚" .
             str_repeat("═", $inner) .
             "╝" .
             RSET .
             PHP_EOL;

        $this->isShown = true;

        fflush(STDOUT);
    }

    public function taskPrint($level, $text, $line = 1) {
        if (!$this->isTty) {
            logx($level, $text);
            return;
        }

        $targetRow = ($line === 2)
            ? self::TASK_LINE_2
            : self::TASK_LINE_1;

        $inner = max(1, $this->getWidth() - 2);

        $text = preg_replace('/\s+/', ' ', trim($text));

        if (strlen($text) > $inner) {
            $text = substr($text, 0, $inner);
        }

        $padLeft = intdiv(
            $inner - strlen($text),
            2
        );

        $padRight = $inner - strlen($text) - $padLeft;

        $this->saveCursor();
        $this->hideCursor();

        $this->moveCursor($targetRow, 1);
        $this->clearLine();

        echo FGb['MAG'] . "║" . RSET;

        logx(
            $level,
            str_repeat(' ', $padLeft)
            . $text .
            str_repeat(' ', $padRight),
            false,
            true
        );

        echo FGb['MAG'] . "║" . RSET;

        fflush(STDOUT);

        $this->restoreCursor();
        $this->showCursor();
    }

    public function task1($level, $text) {
        $this->taskPrint($level, $text, 1);
    }

    public function task2($level, $text) {
        $this->taskPrint($level, $text, 2);
    }

    public function isShown() {
        return $this->isShown;
    }


}
