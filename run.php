<?php

if (!defined('ROOT')) {
    
    define('main_menu', true);
    require_once __DIR__ . '/src/loader.php';
    
    $GLOBALS['_CTX'] ??= [
        'geo' => [],
        'proxy' => [],
        'apikey' => [],
    ];
    
    bootApp();
    while (!(!hasTty() || getenv('BOT')) && Menu::main()); (!hasTty() || getenv('BOT')) && Menu::autoRun();
    
}