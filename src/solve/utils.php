<?php

/** @function xtractJs
 * @param string $html
 * @return array
 */
function xtractJs($html): array {
    $m = scraper::_jP($html, '#<script\b[^>]*>(.*?)</script>#is');
    $out = [];

    if (empty($m[1])) return $out;

    foreach ($m[1] as $code) {
        $code = trim($code);
        if ($code === '') continue;

        // heuristik 
        if (
            strlen($code) > 1500 &&
            (stripos($code, '_0x') !== false ||
             stripos($code, 'eval(function') !== false)
        ) {
            $out[] = $code;
            #RAW
        }
    }

    return $out;
}

/** @function dumpJsFlex
 * @param string $_input
 * @param string|null $_putin
 * @param bool $unlink
 * @param string $fakeFile
 * @return string|false
 */
function dumpJsFlex($_input, $_putin = null, $unlink = false, $fakeFile = 'input.js'): string|false {
    
    if (!getDeps('nodejs')) {
        logx('err', 'nodejs missing');
        exit;
    }
    
    if (is_file($_input)) {
        $jsCode = _get($_input);
        if ($jsCode === false) return false;
        if ($unlink) @unlink($_input);
    } else {
        $jsCode = $_input;
    }

    if ($jsCode === '') return false;

    $nodeWrapper = <<<'JS'
const fs = require('fs');
process.argv[2] = process.argv[2] || 'input.js';
const jsCode = fs.readFileSync(0, 'utf8');

global.window = { location: { hostname: '' }, screen: {}, clientInformation: {} };
global.document = {
  write: (s='') => { console.log("DUMP_START" + String(s) + "DUMP_END"); },
  getElementById: () => ({ value: '' }),
  querySelector: () => ({ value: '' })
};
global.navigator = { appCodeName: 'Mozilla', platform: 'Win32' };

// hook decodeURIComponent
const _dec = global.decodeURIComponent;
global.decodeURIComponent = function(v) {
  const out = _dec(v);
  if (typeof out === 'string' && (out.includes('function') || out.includes('<') || out.length > 300)) {
    console.log("DUMP_START" + out + "DUMP_END");
  }
  return out;
};

// hook eval
const _eval = global.eval;
global.eval = function(code) {
  if (typeof code === 'string' && code.length > 200) {
    console.log("DUMP_START" + code + "DUMP_END");
  }
  return _eval(code);
};

// hook Function constructor
const _Function = global.Function;
global.Function = function(...args) {
  const body = args[args.length - 1];
  if (typeof body === 'string' && body.length > 200) {
    console.log("DUMP_START" + body + "DUMP_END");
  }
  return _Function(...args);
};

// hook atob 
if (typeof global.atob !== 'function') {
  global.atob = (s) => Buffer.from(String(s), 'base64').toString('binary');
}
const _atob = global.atob;
global.atob = function(s){
  const out = _atob(s);
  if (typeof out === 'string' && out.length > 200) {
    console.log("DUMP_START" + out + "DUMP_END");
  }
  return out;
};

try { _eval(jsCode); } catch (e) {}
JS;

    $cmd = 'node -e ' . escapeshellarg($nodeWrapper) . ' -- ' . escapeshellarg($fakeFile);

    $proc = proc_open($cmd, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($proc)) return false;

    fwrite($pipes[0], $jsCode);
    fclose($pipes[0]);

    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    proc_close($proc);

    $all = (string)$out.(string)$err;
    if ($all === '') return false;

    if (!preg_match_all('/DUMP_START(.*?)DUMP_END/is', $all, $mm) || empty($mm[1])) return false;

    $cands = array_map(fn($s) => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $mm[1]);

    usort($cands, fn($a, $b) => strlen($b) <=> strlen($a));
    $best = $cands[0];

    if ($_putin !== null && $_putin !== '') {
        _put($_putin, $best);
    }

    return $best;
}

/** @function rsAescipher
 * @param string $jsFile
 * @return array|false
 */
function rsAescipher($jsFile) {
    if (!file_exists($jsFile)) return false;
    $jsContent = _get($jsFile);

    // checking timestamp
    if (!preg_match('/17[0-9]{8}/', $jsContent, $m_seed)) {
        return false;
    } $titikNol = trim($m_seed[0]);

    // checking Password
    if (preg_match('/password\s*=\s*["\']([^"\']+)["\']/', $jsContent, $m_pass)) {
        $password = $m_pass[1];
    } else { $password = "RSKP2903"; }

    // RSKP value
    if (preg_match('/\.value\s*=\s*["\']([a-zA-Z0-9]{10})["\']/', $jsContent, $m_rskp)) {
        $rskp_val = $m_rskp[1];
    } else { $rskp_val = ""; }

    // setting up aes
    $ufid = bin2hex(random_bytes(16));
    $plaintext = $ufid . "|" . $titikNol;
    $iv = random_bytes(16);
    
    // encrypting ( AES-128-CBC )
    $encrypted = openssl_encrypt($plaintext, 'aes-128-cbc', $password, OPENSSL_RAW_DATA, $iv);

    return [
        'titikNol' => $titikNol,
        'uf' => $ufid,
        'iv' => bin2hex($iv),
        'ciphertext' => base64_encode($encrypted),
        'rskp2305' => $rskp_val
    ];
}

/*
function _derive($secret, $salt): array {
    $masterKey = hash('sha512', $secret . $salt, true);
    return [
        'enc'  => hash_hmac('sha256', 'encryption',     $masterKey, true),
        'auth' => hash_hmac('sha256', 'authentication', $masterKey, true),
    ];
}

function _enc($data, $apiKey, $secretKey) {
    $_key = _derive($apiKey, $secretKey);

    if (is_array($data) || is_object($data)) {
        $data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $_ivv = random_bytes(16);
    $_cip = openssl_encrypt($data, 'aes-256-cbc', $_key['enc'], OPENSSL_RAW_DATA, $_ivv);
    $_sgn = hash_hmac('sha256', $_ivv . $_cip, $_key['auth'], true);
    $solution = base64_encode($_ivv . $_sgn . $_cip);

    return rtrim(strtr($solution, '+/', '-_'), '=');
}

function _dec($data, $apiKey, $secretKey) {
    $data = strtr($data, '-_', '+/');
    while (strlen($data) % 4) $data .= '=';
    $raw = base64_decode($data);

    $_key = _derive($apiKey, $secretKey);
    $_ivv = substr($raw, 0, 16);
    $_sgn = substr($raw, 16, 32);
    $_cip = substr($raw, 48);

    $expect = hash_hmac('sha256', $_ivv . $_cip, $_key['auth'], true);
    if (!hash_equals($expect, $_sgn)) return null;

    $decrypt = openssl_decrypt($_cip, 'aes-256-cbc', $_key['enc'], OPENSSL_RAW_DATA, $_ivv);
    $json = json_decode($decrypt, true);
    return $json ?? $decrypt;
}
*/