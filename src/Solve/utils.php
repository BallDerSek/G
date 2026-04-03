<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

loader(__DIR__);
require_once(__DIR__ . '/Providers/providers.php');



#HELPER

final class ATBtest {

    private static function xp($html): DOMXPath {
        libxml_use_internal_errors(true);
        $d = new DOMDocument();
        $d->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING|LIBXML_NOERROR|LIBXML_NONET);
        return new DOMXPath($d);
    }

    private static function b64($uri): ?string {
        if (!preg_match('~^data:image/[a-z0-9.+-]+;base64,([a-z0-9+/=\s]+)$~i',$uri,$m)) {
            return null;
        }
        return preg_replace('~\s+~','',$m[1]);
    }

    private static function mainATB($html): ?string {
        $xp = self::xp($html);

        $a = $xp->query("//input[@id='antibotlinks' or @name='antibotlinks']")->item(0)
          ?: $xp->query("//*[contains(concat(' ',normalize-space(@class),' '),' antibotlinks ')]")->item(0);

        if ($a) {
            for ($up=0;$up<=6;$up++) {
                $ctx = $a;
                for ($i=0;$i<$up && $ctx?->parentNode;$i++) $ctx = $ctx->parentNode;

                $n = $ctx ? $xp->query(".//img[starts-with(@src,'data:image')]/@src",$ctx)->item(0) : null;
                if ($n) return $n->nodeValue;
            }

            $p = (string)$xp->evaluate("string((preceding::img[starts-with(@src,'data:image')][1]/@src))",$a);
            if ($p !== '') return $p;

            $f = (string)$xp->evaluate("string((following::img[starts-with(@src,'data:image')][1]/@src))",$a);
            if ($f !== '') return $f;
        }

        if (($pos = stripos($html,'antibotlinks')) !== false) {
            $chunk = substr($html,max(0,$pos-8000),16000);

            if (preg_match('~src\s*=\s*(["\'])(data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)\1~i',$chunk,$m)) {
                return $m[2];
            }
        }

        return null;
    }

    private static function relATB($html): array {
        $out = [];

        $rx = [
            '~\brel\s*=\s*["\'](\d+)["\'][\s\S]{0,8000}?\bsrc\s*=\s*["\'](data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)["\']~i',
            '~\brel\s*=\s*\\\\?"(\d+)\\\\?"[\s\S]{0,8000}?\bsrc\s*=\s*\\\\?"(data:image/[a-z0-9.+-]+;base64,[a-z0-9+/=\s]+)\\\\?"~i',
        ];

        foreach ($rx as $re) {
            if (preg_match_all($re,$html,$mm,PREG_SET_ORDER)) {
                foreach ($mm as $m) {
                    $out[$m[1]] ??= $m[2];
                }
            }
        }

        return $out;
    }

    public static function get($html): array {

        $ret = [
            'main' => null,
            'rels' => []
        ];

        if (($u = self::mainATB($html))) {
            $ret['main'] = self::b64($u);
        }

        foreach (self::relATB($html) as $rel=>$u) {
            if ($b = self::b64($u)) {
                $ret['rels'][$rel] = $b;
            }
        }

        return $ret;
    }
}

function xtractJs($html): array {
    $m = rScraper::jPath($html, '#<script\b[^>]*>(.*?)</script>#is');
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
            $out[] = $code; #RAW
        }
    }

    return $out;
}

function dumpJsFlex($inputOrCode, $putin = null, $unlink = false, $fakeFile = 'input.js'): string|false {

    if (is_file($inputOrCode)) {
        $jsCode = _get($inputOrCode);
        if ($jsCode === false) return false;
        if ($unlink) @unlink($inputOrCode);
    } else {
        $jsCode = $inputOrCode;
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

    if ($putin !== null && $putin !== '') {
        _put($putin, $best);
    }

    return $best;
}

function rsResponse($jsFile, $x, $y, $timestamp, $ua, $_img = null) {
    if (!getDeps('gd@php')) {
        logx('err', 'gd@php missing');
        exit;
    }
    /** Dumbass RSSHORT with Auto-Scaling */
    if (!file_exists($jsFile)) return false;
    $jsContent = file_get_contents($jsFile);
    
    if ($_img) {
        $imgData = (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $_img)) 
                   ? base64_decode($_img) 
                   : $_img;
                   
        $info = getimagesizefromstring($imgData);
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
    $rawVars = explode(',', str_replace(['+', "'", '"', ' ', "\n", "\r"], '', $matches[1]));
    
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
    #print_r($payloadArray);
    return base64_encode(implode(',', $payloadArray));
}

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
        'titikNol'   => $titikNol,
        'uf'         => $ufid,
        'iv'         => bin2hex($iv),
        'ciphertext' => base64_encode($encrypted),
        'rskp2305'   => $rskp_val
    ];
}


function submit($js, $html, $json) {
    $jsContent = is_file($js) ? _get($js) : $js;
    $jsonContent = is_file($json) ? _get($json) : $json;
    $response = json_decode($jsonContent, true);

    preg_match_all('/getElementById\("([^"]+)"\)\.value\s*=\s*response\.([a-zA-Z0-9_]+)/', $jsContent, $matches);

    $mappedInputs = [];
    $count = min(count($matches[1] ?? []), count($matches[2] ?? []));
    for ($i = 0; $i < $count; $i++) {
        $inputName = $matches[1][$i];   
        $jsonKey   = $matches[2][$i];   
        if (isset($response[$jsonKey])) {
            $mappedInputs[$inputName] = $response[$jsonKey];
        }
    }

    $payload = [];

    if (!is_null($html)) {
        
        preg_match('/document\.querySelector\("([^"]+)"\)\.innerHTML\s*=\s*`/', $jsContent, $containerMatch);
        $captchaTag = !empty($containerMatch[1]) ? $containerMatch[1] : null;
        $htmlContent = is_file($html) ? _get($html) : $html;
        $dom   = new DOMDocument();
        @$dom->loadHTML($htmlContent);
        $xpath = new DOMXPath($dom);
        $forms = $xpath->query('//form');
        $payload = [];
        $inputs = $xpath->query('//form//input');
        
        foreach ($forms as $form) {
            if ($captchaTag) {
                $captchaElement = $xpath->query(".//*[contains(@id, '$captchaTag')] | .//$captchaTag", $form);
                if ($captchaElement->length > 0) {
                    $inputs = $xpath->query('.//input', $form);
                    foreach ($inputs as $input) {
                        $name = $input->getAttribute('name');
                        $type = $input->getAttribute('type');
                        if ($name && $type !== 'submit') {
                            $payload[$name] = $input->getAttribute('value');
                        }
                    }
                    foreach ($mappedInputs as $name => $value) {
                        $payload[$name] = $value;
                    }
                    break;
                }
            }
        }
        
    }

    foreach ($mappedInputs as $name => $value) {
        $payload[$name] = $value;
    }

    return $payload;
}