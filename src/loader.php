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
    
    $dirs = glob(SRCDIR.'/*',GLOB_ONLYDIR);
    spl_autoload_register(function ($class) use ($dirs) {
        
        foreach ($dirs as $dir) {
            $file = $dir.DIRECTORY_SEPARATOR."{$class}.php";
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
        
    });

}
