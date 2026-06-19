<?php
/**
 * initialize libs
 * => deps/env/geo/key synchronizing
 * => setting up private context ['_CTX']
 */

define('main_menu', true);
require_once __DIR__ . '/src/loader.php';


$GLOBALS['_CTX'] ??= [
    'geo' => [],
    'proxy' => [],
    'apikey' => [],
    #'banner' => [],
];

startingLib:
bootApp();

while (!(!hasTty() || getenv('BOT')) && Menu::main()); (!hasTty() || getenv('BOT')) && Menu::autoRun();

