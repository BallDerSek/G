<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#DEP 
function checkDeps() {
    $GLOBALS['_CTX']['deps'] = underline("checking deps", function() {
        return [
            'gd@php' => extension_loaded('gd'),
            'python3' => trim(shell_exec('command -v python3') ?? '') !== '',
            'ssh' => trim(shell_exec('command -v ssh') ?? '') !== '',
            'sshpass' => trim(shell_exec('command -v sshpass') ?? '') !== '',
            'nodejs' => trim(shell_exec('command -v node') ?? '') !== '',
            'npm' => trim(shell_exec('command -v npm') ?? '') !== '',
            'synchrony@npm' => trim(shell_exec('command -v synchrony') ?? '') !== '',
            'seledroid@py' => trim(shell_exec('python3 -m pip show seledroid 2>/dev/null') ?? '') !== '',
            'tesseract' => trim(shell_exec('command -v tesseract') ?? '') !== '',
        ];
    });

    $missing = array_keys(array_filter($GLOBALS['_CTX']['deps'], fn($v) => !$v));
    if ($missing) {
        logx('err', "Missing dependencies:\n- " . implode("\n- ", $missing) . "\n");
    }
}

function getDeps($deps) {
    if (empty($GLOBALS['_CTX']['deps'])) {
        logx('err', 'deps missing run script normally');
        exit;
    }
    if (is_string($deps)) $deps = [$deps];
    foreach ($deps as $dep) {
        if (empty($GLOBALS['_CTX']['deps'][$dep]) || !$GLOBALS['_CTX']['deps'][$dep]) {
            return false;
        }
    }
    return true;
}