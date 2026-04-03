<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#ENV 
function checkEnv() {
    if (getenv('ENV') !== '1') {
        return;
    }
    
    $path = ROOT . "/.env";
    if (file_exists($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            putenv($line);
            [$name, $value] = explode('=', $line, 2);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}