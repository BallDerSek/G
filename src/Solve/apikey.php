<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

/* === APIKEY === */
$GLOBALS['_CTX']['apikey'] = array (
  'https://solverify.net' => '',
  'http://tertuyul.my.id' => '',
  'Xevil_check_bot.t.me' => '',
  'https://waryono.my.id' => '',
  'http://multibot.in' => '',
  'https://capsolver.com' => '',
);
/* === APIKEY === */







function putKeys(array $keys) {
    $src = _get(__FILE__);
    if ($src === false) { logx('err', "file corrupted"); exit; }

    $newBlock = "/* === APIKEY === */\n"
        . '$GLOBALS[\'_CTX\'][\'apikey\'] = ' 
        . var_export($keys, true) 
        . ";\n/* === APIKEY === */";

    $put = preg_replace(
        '/\/\* === APIKEY === \*\/.*?\/\* === APIKEY === \*\//s',
        $newBlock,
        $src,
        1,
        $count
    );

    if ($put === null) exit("error regex");
    if ($count !== 1) exit("error marker");

    $tmp = __DIR__ . '/.' . basename(__FILE__) . '.' . getmypid() . '.' . bin2hex(random_bytes(3)) . '.tmp';

    $ok = _put($tmp, $put); // no LOCK_EX
    if ($ok === false) {
        $e = error_get_last();
        @unlink($tmp);
        logx('err', "error write: " . ($e['message'] ?? 'unknown'));
    }

    if (@rename($tmp, __FILE__)) return;
    if (!@copy($tmp, __FILE__)) { @unlink($tmp); logx('err', "error rename"); }
    @unlink($tmp);
}

function viewKeys($solver) {
    try {
        return styler("CHECK " . get_class($solver), function() use ($solver) {
            ob_start();
            try {
                $ok = $solver->getInfo();
            } finally {
                ob_end_clean();
            }
            return (bool)$ok;
        });
    } catch (Throwable $e) {
        logx('err', "{$e->getMessage()}");
        return false;
    }
}

function selKeys($endpoint, $apiKey) {
    return Api::use($endpoint, $apiKey);
}

function mapProvider($v) {
    $v = trim($v);
    $cfg = Api::KEY[$v] ?? Api::KEY[strtolower($v)] ?? null;
    return $cfg['ep'] ?? $v;
}

function CI_env() {
    $endpoint = mapProvider((string)(getenv('API')));
    $apiKey   = trim((string)getenv('KEY'));

    if ($endpoint === '' || $apiKey === '') {
        logx('info', "Non-interactive: set API and KEY");
        die;
    }

    $solver = selKeys($endpoint, $apiKey);
    if (!viewKeys($solver)) logx('err', "rejected");
    return $solver;
}

function onKeys() {
    return (!hasTty() || (string)getenv('CI') === '1') ? CI_env() : CLI_env();
}