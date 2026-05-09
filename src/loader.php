<?php
/**
 * initialize src
 * => load all mods
 * => setting up definition 
 */

if (!defined('ROOT')) define('ROOT', realpath(__DIR__ . '/../'));
if (!defined('RUNNER')) define('RUNNER', '21.0.7'); 
if (!defined('LIBDIR')) define('LIBDIR', ROOT . '/lib'); 
if (!defined('SRCDIR')) define('SRCDIR', ROOT . '/src');
if (!defined('UPDDIR')) define('UPDDIR', ROOT . '/upd');
if (!defined('BOTDIR')) define('BOTDIR', ROOT . '/bot');
#if (!defined('SLDIR')) define('SLDIR', ROOT . '/sl');

(function() {
    
    $src = SRCDIR;
    
    $items = scandir($src);
    $modules = [];
    foreach ($items as $item) {
        if ($item[0] === '.' || $item === 'loader.php') continue;
        if (is_dir($src . '/' . $item)) $modules[] = $item;
    }

    foreach ($modules as $m) {
        $utils = "$src/$m/utils.php";
        if (file_exists($utils)) {
            #echo " [UTIL] -> $utils" . PHP_EOL; 
            require_once $utils;
        }
    }

    foreach ($modules as $m) {
        $dir = "$src/$m";
        $it = new RecursiveDirectoryIterator($dir);
        $fs = [];

        foreach (new RecursiveIteratorIterator($it) as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                if ($f->getFilename() === 'utils.php') continue;
                $fs[] = $f->getRealPath();
            }
        }

        sort($fs);

        foreach ($fs as $path) {
            #echo " [MOD]  -> $path" . PHP_EOL; 
            require_once $path;
        }
    }
    
})();

function bootApp() {
    
    _cle();
    
    check::Env();
    check::Dep();
    Proxy::load();
    check::Geo();
    KEYS::sync();
    
    $k = Config::credential()['_authApi_'];
    $a = Api::use('gmxch', $k);
    $GLOBALS['_CTX']['AUTH_API'] = $a;
    if (!defined('AUTH_KEY')) define('AUTH_KEY', $a->getInfo());
    
}