<?php


function styler($text, callable $task, $rndr = null) {
    return Animator::exec($text, $task, $rndr);
}

final class Animator {
    
    private const RENDERERS = [
        'spinner',
        'loading',
        'underline',
        'gradient',
    ];

    public static function exec($text, callable $task, $renderer = null) {
        
        if (!animate()) {
            
            echo "\r";
            _clr();

            echo $text;
            fflush(STDOUT);

            echo PHP_EOL;
            _clr();

            return $task();
            
        }

        $renderer ??= self::RENDERERS[array_rand(self::RENDERERS)];

        if (!in_array($renderer, self::RENDERERS, true)) {
            $renderer = 'spinner';
        }

        return self::$renderer($text, $task);
    }

    private static function run(callable $renderer, callable $task) {
        $pid = pcntl_fork();

        if ($pid === -1) return $task();

        if ($pid === 0) {

            $run = true;

            pcntl_async_signals(true);

            pcntl_signal(
                SIGTERM,
                function () use (&$run) {
                    $run = false;
                }
            );

            echo "\033[?25l";

            $renderer($run);

            exit(0);
        }

        try {
            return $task();
        } finally {
            posix_kill($pid, SIGTERM);
            pcntl_waitpid($pid, $status);
            
            echo "\033[?25h";
            echo "\r";
            _clr();
            echo "\r";
        }
    }

    private static function spinner($text, callable $task) {
        $frames = [
            '⠋','⠙','⠹','⠸','⠼',
            '⠴','⠦','⠧','⠇','⠏'
        ];

        $text = strtoupper($text);

        return self::run(function (&$run) use ($frames, $text) {

                $i = 0;
                $base = BOLD . FGo['BLU'];
                $spin = BOLD . FGb['CYN'];

                while ($run) {
                    $frame = $frames[$i % count($frames)];

                    echo "\r" . $spin . $frame . RSET . " " . $base . $text . RSET;

                    fflush(STDOUT);

                    usleep(80_000);

                    $i++;
                }
            },
            $task
        );
    }

    private static function underline($text, callable $task) {
        $len = strlen($text);

        if ($len === 0) return $task();

        return self::run(function (&$run) use ($text, $len) {

                $pos = 0;
                $dir = 1;
                while ($run) {
                    $buf = "\r";
                    for ($i = 0; $i < $len; $i++) {
                        
                        $buf .= ($i === $pos)
                            ? BOLD . UNDR . $text[$i] . RSET : $text[$i];

                    }

                    echo $buf;
                    fflush(STDOUT);
                    usleep(70_000);
                    $pos += $dir;
                    if ($pos >= $len - 1 || $pos <= 0) $dir *= -1;
                }
            }, $task);
            
    }

    private static function loading($text, callable $task) {
        $text = strtoupper($text);

        $len = strlen($text);

        if ($len === 0) return $task();

        return self::run(function (&$run) use ($text, $len) {

                $pos = 0;
                $dir = 1;

                while ($run) {

                    $base = BOLD . FGo['BLU'];

                    $buf = "\r" . $base;

                    for ($i = 0; $i < $len; $i++) {

                        $buf .= ($i === $pos)
                            ? BOLD . FGd['CYN'] . RVRS . $text[$i] . RSET . $base : $text[$i];
                    }

                    $buf .= RSET;

                    echo $buf;

                    fflush(STDOUT);

                    usleep(100_000);

                    $pos += $dir;

                    if ($pos >= $len - 1 || $pos <= 0) $dir *= -1;
                }
            }, $task);
    }

    private static function gradient($text, callable $task) {
        $palette = [
            196,202,208,214,220,226,
            190,154,118,82,46,
            47,48,49,51,45,
            39,33,27,21,
            57,93,129,165
        ];

        $text = strtoupper($text);

        $len = strlen($text);

        if ($len === 0) return $task();

        $scale = function (array $palette, int $size) {
            $size = max(1, $size);

            $count = count($palette);

            if ($count === 0) return array_fill(0, $size, 15);

            if ($count === 1) return array_fill(0, $size, $palette[0]);

            $out = [];

            for ($i = 0; $i < $size; $i++) {

                $idx = (int) round($i * ($count - 1) / max(1, $size - 1));

                $out[] = $palette[$idx];
            }

            return $out;
        };

        $palette = $scale($palette, $len);

        return self::run(function (&$run) use ($text, $len, $palette) {

                $trail = 10;

                $head = 0;
                $gap  = 0;

                while ($run) {

                    $buf = "\r";

                    if ($gap > 0) {

                        echo $buf . FGo['WHT'] . $text . RSET;

                        fflush(STDOUT);

                        usleep(75_000);

                        $gap--;

                        continue;
                    }

                    for ($i = 0; $i < $len; $i++) {

                        $d = $head - $i;

                        $buf .= ($d >= 0 && $d < $trail)
                            ? BOLD . FG256 . $palette[$i] . "m" . $text[$i] . RSET : FGd['BLK'] . $text[$i] . RSET;
                    }

                    echo $buf;

                    fflush(STDOUT);

                    usleep(75_000);

                    $head++;

                    if ($head > ($len + $trail) ) {
                        $head = 0;
                        $gap  = 6;
                    }
                }
            }, $task);
    }
}