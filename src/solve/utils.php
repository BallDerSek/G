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

    $all = (string)$out . (string)$err;
    if ($all === '') return false;

    if (!preg_match_all('/DUMP_START(.*?)DUMP_END/is', $all, $mm) || empty($mm[1])) {
        return false;
    }

    $cands = array_map(
        fn($s) => html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        $mm[1]
    );

    usort($cands, fn($a, $b) => strlen($b) <=> strlen($a));
    $best = $cands[0];

    if ($_putin !== null && $_putin !== '') {
        _put($_putin, $best);
    }

    return $best;
}

/** @function rsResponse
 * @param string $jsFile
 * @param int|float $x
 * @param int|float $y
 * @param int|float $timestamp
 * @param string $ua
 * @param string|null $_img
 * @return string|false
 */
function rsResponseORI($jsFile, $x, $y, $timestamp, $ua, $_img = null) {
    if (!getDeps('gd@php')) {
        logx('err', 'gd@php missing');
        exit;
    }
    /** Dumbass RSSHORT with Auto-Scaling */
    if (!file_exists($jsFile)) return false;
    $jsContent = _get($jsFile);
    
    if ($_img) {
        $imgData = (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $_img)) 
                   ? base64_decode($_img) 
                   : $_img;
                   
        $info = getimagesizefromstring($imgData);
        var_dump($info);
        if ($info) {
            $w = $info[0]; 
            $h = $info[1];
            
            if ($x > $w) $x = rand($w/2 - 10, $w/2 + 10);
            if ($y > $h) $y = rand($h/2 - 5, $h/2 + 5);
            
            if ($h < 100 && ($y > $h || $y < 5)) {
                $y = rand(15, $h - 15);
            }
        }
    }

    $delay = rand(2, 5); 
    
    if (!preg_match('/btoa\((.*?)\)/s', $jsContent, $matches)) return false;
    print_r($matches);
    $rawVars = explode(',', str_replace(['+', "'", '"', ' ', "\n", "\r"], '', $matches[1]));
    print_r($rawVars);
    
    if (stripos($ua, 'Windows') !== false) {
        $platform = 'Win32';
        $pdf = (stripos($ua, 'Trident') !== false) ? 0 : 1;
    } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS X') !== false) {
        $platform = 'MacIntel';
        $pdf = 1;
    } else {
        $platform = 'Linux x86_64';
        $pdf = 1; 
    }

    $resList = [['w' => 1920, 'h' => 1080], ['w' => 1366, 'h' => 768], ['w' => 1440, 'h' => 900]];
    $screen = $resList[array_rand($resList)];
    $fingerprint = [
        'sw' => $screen['w'], 'sh' => $screen['h'], 
        'aw' => $screen['w'], 'ah' => $screen['h'] - rand(40, 60),
        'cd' => 24, 'pd' => 24, 'cores' => rand(4, 16),
        'platform' => $platform, 'pdf' => $pdf
    ];

    $payloadArray = [];
    foreach ($rawVars as $v) {
        $v = trim($v);
        
        if (preg_match('/' . $v . '\s*=\s*~~\(Date\.now/', $jsContent)) {
            $payloadArray[] = (int)($timestamp + $delay); // Wajib Integer (10 digit)
        } elseif (preg_match('/' . $v . '\s*=\s*Math\.round\([^,]*?\.pageX\s*-\s*/', $jsContent)) {
            $payloadArray[] = (int)$x;
        } elseif (preg_match('/' . $v . '\s*=\s*Math\.round\([^,]*?\.pageY\s*-\s*/', $jsContent)) {
            $payloadArray[] = (int)$y;
        } elseif (preg_match('/' . $v . '\s*=\s*Math\.round\(window\.pageXOffset\)/', $jsContent)) {
            $payloadArray[] = 0; 
        } elseif (preg_match('/' . $v . '\s*=\s*Math\.round\(window\.pageYOffset\)/', $jsContent)) {
            $payloadArray[] = rand(100, 300); // Simulate scrolling
        } elseif (preg_match('/' . $v . '\s*=\s*screen\.width/', $jsContent)) {
            $payloadArray[] = $fingerprint['sw'];
        } elseif (preg_match('/' . $v . '\s*=\s*screen\.height/', $jsContent)) {
            $payloadArray[] = $fingerprint['sh'];
        } elseif (preg_match('/' . $v . '\s*=\s*screen\.availWidth/', $jsContent)) {
            $payloadArray[] = $fingerprint['aw'];
        } elseif (preg_match('/' . $v . '\s*=\s*screen\.availHeight/', $jsContent)) {
            $payloadArray[] = $fingerprint['ah'];
        } elseif (preg_match('/' . $v . '\s*=\s*navigator\.platform/', $jsContent)) {
            $payloadArray[] = $fingerprint['platform'];
        } elseif (preg_match('/' . $v . '\s*=\s*navigator\.pdfViewerEnabled/', $jsContent)) {
            $payloadArray[] = $fingerprint['pdf'];
        } elseif (preg_match('/' . $v . '\s*=\s*clientInformation\.hardwareConcurrency/', $jsContent)) {
            $payloadArray[] = $fingerprint['cores'];
        } elseif (preg_match('/' . $v . '\s*=\s*screen\.colorDepth/', $jsContent)) {
            $payloadArray[] = $fingerprint['cd'];
        } elseif (preg_match('/' . $v . '\s*=\s*screen\.pixelDepth/', $jsContent)) {
            $payloadArray[] = $fingerprint['pd'];
        } elseif (preg_match('/' . $v . '\s*=\s*clientInformation\.appCodeName/', $jsContent)) {
            $payloadArray[] = "Mozilla";
        } elseif (preg_match('/' . $v . '\s*=\s*navigator\.onLine/', $jsContent)) {
            $payloadArray[] = 1;
        } elseif (preg_match('/' . $v . '\s*=\s*document\.hasFocus\(\)/', $jsContent)) {
            $payloadArray[] = 1;
        } else {
            $payloadArray[] = 0;
        }
    }
    print_r($payloadArray);
    return base64_encode(implode(',', $payloadArray));
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