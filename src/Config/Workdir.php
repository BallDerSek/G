<?php

trait WorkDir {
    protected string $workDir;

    protected function setupWorkDir(?string $type = null, ?string $host = null, ?string $mail = null, int $ttl = 120): string {
        $base = _lib($type, $host, $mail);

        if (!is_dir($base)) @mkdir($base, 0755, true);

        $this->cleanOld($base, $ttl);

        $dir = $base . DIRECTORY_SEPARATOR .
               str_replace('.', '', (string)microtime(true)).'_' .
               bin2hex(random_bytes(4));
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            usleep(100000);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                $this->workDir = '';
                return '';
            }
        }

        $this->workDir = $dir;
        return $dir;
    }

    protected function cleanOld(string $base, int $ttl = 120): void {
        $dirs = glob($base . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if (!is_array($dirs)) return;
        $now = microtime(true);
        foreach ($dirs as $dir) {
            $mtime = @filemtime($dir);
            if ($mtime === false) continue;
            if (($now - $mtime) > $ttl) $this->rmdir($dir);
        }
    }

    protected function userdir(?string $mail): string {
        $user = ($mail && str_contains($mail, '@'))
            ? strstr($mail, '@', true)
            : ($mail ?? '');
        $user = preg_replace('/[^a-zA-Z0-9]/', '_', $user);
        return $user !== '' ? $user : 'cookie';
    }

    protected function rmdir(string $path): void {
        if (!is_dir($path)) return;
        $items = @scandir($path);
        if ($items === false) return;
        foreach (array_diff($items, ['.', '..']) as $item) {
            $full = $path.DIRECTORY_SEPARATOR.$item;
            if (is_dir($full)) $this->rmdir($full);
            else @unlink($full);
        }

        @rmdir($path);
    }
}