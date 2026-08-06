<?php

if (!defined('ROOT')) {
    
    define('ROOT', realpath(__DIR__.'/../'));
    if (!defined('RUNNER')) define('RUNNER', '31.9.2');
    
    if (!defined('LIBDIR')) {
        define('LIBDIR', ROOT.'/lib');
        is_dir(LIBDIR) || @mkdir(LIBDIR, 0777, true);
    }
    if (!defined('CREDIR')) {
        define('CREDIR', ROOT.'/cre');
        is_dir(CREDIR) || @mkdir(CREDIR, 0777, true);
    }
    if (!defined('SRCDIR')) {
        define('SRCDIR', ROOT.'/src');
        is_dir(SRCDIR) || @mkdir(SRCDIR, 0777, true);
    }
    if (!defined('BOTDIR')) {
        define('BOTDIR', ROOT.'/bot');
        is_dir(BOTDIR) || @mkdir(BOTDIR, 0777, true);
    }

    require_once SRCDIR.'/Ansi.php';
    require_once SRCDIR.'/Func.php';
    
    $classMap = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SRCDIR, RecursiveDirectoryIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') $classMap[$file->getBasename('.php')] = $file->getRealPath();
    }

    spl_autoload_register(function ($class) use ($classMap) {
        if (isset($classMap[$class])) require_once $classMap[$class];
    });

}
