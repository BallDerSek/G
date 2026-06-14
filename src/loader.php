<?php
/**
 * initialize src
 * => load all mods
 * => setting up definition 
 */

if (!defined('ROOT')) define('ROOT', realpath(__DIR__ . '/../'));
if (!defined('RUNNER')) define('RUNNER', '31.9.2'); 
if (!defined('LIBDIR')) define('LIBDIR', ROOT . '/lib'); 
if (!defined('SRCDIR')) define('SRCDIR', ROOT . '/src');
if (!defined('UPDDIR')) define('UPDDIR', ROOT . '/upd');
if (!defined('BOTDIR')) define('BOTDIR', ROOT . '/bot');
if (!defined('CREDIR')) define('CREDIR', ROOT . '/cre');

(function() {
    if (!is_dir(LIBDIR)) mkdir(LIBDIR, 0777, true);
    if (!is_dir(SRCDIR)) mkdir(SRCDIR, 0777, true);
    if (!is_dir(BOTDIR)) mkdir(BOTDIR, 0777, true);
    if (!is_dir(CREDIR)) mkdir(CREDIR, 0777, true);
} )();

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
        if (file_exists($utils)) require_once $utils;
    }

    foreach ($modules as $m) {
        $it = new RecursiveDirectoryIterator("$src/$m");
        $fs = [];

        foreach (new RecursiveIteratorIterator($it) as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                if ($f->getFilename() === 'utils.php') continue;
                $fs[] = $f->getRealPath();
            }
        }
        sort($fs);
        foreach ($fs as $path) require_once $path;
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