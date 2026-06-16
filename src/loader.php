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

define("ANN", "\033["); 
define("FG256", ANN."38;5;"); define("BG256", ANN."48;5;");

define("RSET", ANN."0m"); define("BOLD", ANN."1m");
define("DIMM", ANN."2m"); define("ITAL", ANN."3m");
define("UNDR", ANN."4m"); define("BLNK", ANN."5m");
define("RPID", ANN."6m"); define("RVRS", ANN."7m");
define("HDDN", ANN."8m"); define("STRK", ANN."9m"); 


#foreround colours origin 
define("FGo", [
  "BLK" => ANN."30m", "RED" => ANN."31m",
  "GRN" => ANN."32m", "YLW" => ANN."33m",
  "BLU" => ANN."34m", "MAG" => ANN."35m",
  "CYN" => ANN."36m", "WHT" => ANN."37m",
]);

#foreround colours bright 
define("FGb", [
  "BLK" => ANN."90m", "RED" => ANN."91m",
  "GRN" => ANN."92m", "YLW" => ANN."93m",
  "BLU" => ANN."94m", "MAG" => ANN."95m",
  "CYN" => ANN."96m", "WHT" => ANN."97m",
]);

#foreround colours dark 
define("FGd", [
  "BLK" => DIMM.FGo["BLK"], "RED" => DIMM.FGo["RED"],
  "GRN" => DIMM.FGo["GRN"], "YLW" => DIMM.FGo["YLW"],
  "BLU" => DIMM.FGo["BLU"], "MAG" => DIMM.FGo["MAG"],
  "CYN" => DIMM.FGo["CYN"], "WHT" => DIMM.FGo["WHT"],
]);

#background colours 
define("BG", [
  "BLK" => ANN."40m", "RED" => ANN."41m",
  "GRN" => ANN."42m", "YLW" => ANN."43m",
  "BLU" => ANN."44m", "MAG" => ANN."45m",
  "CYN" => ANN."46m", "WHT" => ANN."47m",
]);

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
    
} )();

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