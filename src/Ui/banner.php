<?php

class Banner {
    private static $instance = null;
    private $config;
    private $isTty;
    
    private function __construct() {
        $this->isTty = outTty();
        $this->config = [
            'topLine' => 1,
            'width' => null,
            'taskLine' => 5,
            'bottomLine' => 8,
            'logLine' => 10,
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function _width(): int {
        if (!$this->isTty) return 80;
        
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('mode con | find "Columns"');
            if (preg_match('/Columns:\s+(\d+)/', $output, $matches)) {
                return (int)$matches[1];
            }
            return 80;
        } else {
            $width = (int)shell_exec('tput cols 2>/dev/null');
            return $width > 0 ? $width : 80;
        }
    }
    
    public function _setW(int $width): self {
        $this->config['width'] = $width;
        return $this;
    }
    
    public function _getW(): int {
        if ($this->config['width']) {
            return $this->config['width'];
        }
        return $this->_width();
    }
    
    private function _moveC(int $row, int $col): void {
        if (!$this->isTty) return;
        echo "\033[{$row};{$col}H";
    }
    
    private function _setC(): void {
        if (!$this->isTty) return;
        echo "\033[s";
    }
    
    private function _getC(): void {
        if (!$this->isTty) return;
        echo "\033[u";
    }
    
    private function _hideC(): void {
        echo "\033[?25l";
    }
    
    private function _showC(): void {
        echo "\033[?25h";
    }
    
    private function clearLine(): void {
        echo "\r\033[K";
    }
    
    private function printLevel(string $msg, string $level = 'INFO', bool $newLine = true): void {
        $colors = [
            'ERROR' => BOLD . FGb['RED'],
            'WARN' => BOLD . FGb['YLW'],
            'WARNING' => BOLD . FGb['YLW'],
            'SUCCESS' => BOLD . FGb['GRN'],
            'OK' => BOLD . FGb['GRN'],
            'INFO' => BOLD . FGb['CYN'],
        ];
        
        $color = $colors[strtoupper($level)] ?? BOLD.FGb['WHT'];
        $out = $color . $msg . RSET . ($newLine ? PHP_EOL : '');
        print($out);
        fflush(STDOUT);
    }
    
    public function show(string $botName, string $ip, string $timezone): void {
        if (!$this->isTty) {
            $this->printLevel("Bot: $botName", 'INFO');
            $this->printLevel("IP: $ip", 'INFO');
            $this->printLevel("Timezone: $timezone", 'INFO');
            return;
        }
        
        $w = $this->_getW();
        $inner = $w - 2;
        
        $lines = [
            strtoupper($botName),
            $ip,
            $timezone,
            "",
        ];
        
        $colorBorder = FGb['MAG'];
        $colorText = FGb['WHT'];
        
        // Top border
        $this->_moveC($this->config['topLine'], 1);
        $this->clearLine();
        echo $colorBorder . "╔" . str_repeat("═", $inner) . "╗" . RSET . PHP_EOL;
        
        // Content
        foreach ($lines as $i => $line) {
            $row = $this->config['topLine'] + 1 + $i;
            $this->_moveC($row, 1);
            $this->clearLine();
            
            $line = preg_replace('/\s+/', ' ', trim($line));
            if (strlen($line) > $inner) $line = substr($line, 0, $inner);
            $line = str_pad($line, $inner, " ", STR_PAD_BOTH);
            
            echo $colorBorder . "║" . RSET . $colorText . $line . RSET . $colorBorder . "║" . RSET . PHP_EOL;
        }
        
        // Bottom border
        $bottom = $this->config['topLine'] + 1 + count($lines);
        $this->_moveC($bottom, 1);
        $this->clearLine();
        echo $colorBorder . "╚" . str_repeat("═", $inner) . "╝" . RSET . PHP_EOL;
        
        // Update config
        $this->config['bottomLine'] = $bottom;
        $this->config['logLine'] = $bottom + 2;
        $this->config['taskLine'] = $this->config['topLine'] + 1 + 3;
        
        fflush(STDOUT);
    }
    
    public function taskPrint(string $text, string $level = 'INFO'): void {
        if (!$this->isTty) {
            $this->printLevel($text, $level);
            return;
        }
        
        $w = $this->_getW();
        $inner = $w - 2;
        if ($inner < 1) $inner = 58;
        
        $this->_setC();
        $this->_hideC();
        
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (strlen($text) > $inner) $text = substr($text, 0, $inner);
        
        $padLeft = intdiv($inner - strlen($text), 2);
        $padRight = $inner - strlen($text) - $padLeft;
        
        $this->_moveC($this->config['taskLine'], 1);
        $this->clearLine();
        
        echo FGb['MAG'] . "║" . RSET;
        $this->printLevel(str_repeat(" ", $padLeft) . $text . str_repeat(" ", $padRight), $level, false);
        echo FGb['MAG'] . "║" . RSET;
        
        fflush(STDOUT);
        
        $this->_getC();
        $this->_showC();
    }
    
    public function log(string $msg, string $level = 'INFO'): void {
        if (!$this->isTty) {
            $this->printLevel($msg, $level);
            return;
        }
        
        if (!isset($this->config['logLine'])) {
            $this->config['logLine'] = ($this->config['bottomLine'] ?? 0) + 2;
        }
        
        $this->_moveC($this->config['logLine'], 1);
        $this->clearLine();
        $this->printLevel($msg, $level, true);
        
        $this->config['logLine']++;
        fflush(STDOUT);
    }
    
    public function clearLogs(): void {
        $this->config['logLine'] = ($this->config['bottomLine'] ?? 0) + 2;
    }
    
    public function getConfig(): array {
        return $this->config;
    }
    
}


/*

$banner = Banner::getInstance();
// $banner->_setW(100);

$banner->show(
    botName: "SYNDICATE BOT",
    ip: "192.168.1.100",
    timezone: "Asia/Jakarta"
);

$banner->taskPrint("Loading accounts...", "INFO");
sleep(1);
$banner->taskPrint("Login successful!", "SUCCESS");
sleep(1);
$banner->taskPrint("Connection error", "ERROR");

$banner->log("Bot started", "INFO");
$banner->log("Account 1 logged in", "SUCCESS");
$banner->log("Rate limit detected", "WARN");
$banner->log("Something went wrong", "ERROR");

*/
