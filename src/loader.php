<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) define('ROOT', realpath(__DIR__ . '/../'));
if (!defined('RUNNER')) define('RUNNER', '14.2.7'); 
if (!defined('LIBDIR')) define('LIBDIR', ROOT . '/lib'); 
if (!defined('SRCDIR')) define('SRCDIR', ROOT . '/src');
if (!defined('UPDDIR')) define('UPDDIR', ROOT . '/upd');
if (!defined('BOTDIR')) define('BOTDIR', ROOT . '/bot');
if (!defined('SLDIR')) define('SLDIR', ROOT . '/sl');

if (!function_exists('loader')) {
    function loader($dir, array $exclude = ['utils.php']) {
        $files = glob(rtrim($dir, "/\\") . '/*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $f) {
            $base = basename($f);
            if (in_array($base, $exclude, true)) continue;
            require_once $f;
        }
    }
}


$mods = ['Config', 'Ui', 'Check', 'Proxy', 'Net', 'Html', 'CF', 'Solve', 'Upd', 'Links'];
foreach ($mods as $m) {
    require_once SRCDIR . "/$m/utils.php";
}
require_once SRCDIR . '/menu.php';


/*

project/
├─ bin/
│  ├─ run.php        # run loader, register shutdown, load menu
│
├─ src/
│  ├─ loader.php     # init loader(), load all modul
│
│  ├─ Config/
│  │  ├─ config.php  # credentials()/getUagent()/getCookie()
│  │  └─ utils.php   # define ROOT/LIB/BOTDIR/RUNNER/dll
│
│  ├─ UI/
│  │  ├─ styler.php  # styler(), etc
│  │  └─ utils.php   # clr/cle/sle/put/get/hasTty/
│
│  ├─ Check/
│  │  ├─ env.php     # checkEnv()
│  │  ├─ deps.php    # checkDeps()
│  │  ├─ geo.php     # checkGeo()
│  │  └─ utils.php   # loader() + helper
│
│  ├─ Net/
│  │  ├─ http.php    # class Net (C/X/Http/applyProxy)
│  │  └─ utils.php   # loader() + helper
│
│  ├─ Html/
│  │  ├─ scraper.php # class rScraper + xScraper
│  │  └─ utils.php   # loader() + capt::cha
│
│  ├─ Proxy/
│  │  ├─ proxy.php   # proxyLoad/Ensure/IsAlive/Disable
│  │  ├─ ssh.php     # setSSH/stopSSH/getPort/setPort
│  │  └─ utils.php   # loader()
│
│  ├─ CF/
│  │  └─ execPy.php  # class execPy + cfGet()
│  │  └─ utils.php   # loader()
│
│  ├─ Solve/
│  │  ├─ apikey.php  # onKeys() + helper
│  │  ├─ local.php   # solveECAPTCHA/solveICAPTCHA/widgetId/webkitId
│  │  ├─ remote.php  # class Api contractor
│  │  ├─ utils.php   # loader() + crypto/payload/ATBtest
│  │  └─ providers/  # classes providers
│  │     ├─ Nopecha.php
│  │     ├─ Solverify.php
│  │     ├─ Tertuyul.php
│  │     ├─ Xevil.php
│  │     ├─ Waryono.php
│  │     ├─ Multibot.php
│  │     ├─ Capsolver.php
│  │     └─ gmxch.php
│  │
│  ├─ Upd/
│  │  ├─ upd.php     # viewTxt/parsePkg/getBot
│  │  └─ utils.php   # loader()
│  │
│  ├─ Links/
│  │  ├─ links.php   # links logic
│  │  └─ utils.php   # loader() + helper
│  │
│  └─ menu.php       # proxyMenu/toolsMenu/usageInfo/viewBot/CLI_env
│
└─ bot/

*/