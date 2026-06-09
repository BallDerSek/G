<?php

final class rscaptcha {
    
    private string $host, $ua, $ck, $ip;
    private bool $in;
    private string $html;
    
    public function __construct(array $ctx) {
        
        $this->host = $ctx['host'] ?? '';
        $this->html = $ctx['html'] ?? '';
        $this->ua = $ctx['uagent'] ?? '';
        $this->ck = $ctx['cookie'] ?? null;
        $this->in = $ctx['ins'] ?? false;
        $this->ip = $ctx['ip'] ?? '';
        
    }
    
    public function exec($rss, $api, $html = null) {
        
        $_M = $rss['type'] ?? null;
        $_K = $rss['keys'] ?? null;
        $_T = $rss['extra']['token'] ?? null;
        $_J = $rss['extra']['js'] ?? null;
        
        if (str_starts_with($_M, 'rsc')) return $this->rsc($rss, $api);
        
        if (in_array(null, [$_M, $_K, $_T, $_J], true)) return false;
        if (!filter_var($_K, FILTER_VALIDATE_URL)) {
            $_host = rtrim($this->host, '/');
            $_path = ltrim($_K, '/');
            $_K = (str_starts_with($_host, 'http')) ? "{$_host}/{$_path}" : "https://{$_host}/{$_path}";
        }
        
        $img = Net::C($_K, 'GET', null, $this->ck, [], $this->host, $this->ua, ip: $this->ip, ins: $this->in);
        if (empty($img) || $img === 99) return false;
        
        $co = solve::img($api, $this->host, $_M, $img);
        if (isset($co['trouble'])) return false;
        
        $coords = scraper::_jP($co, '/\d+/');
        $_co = $coords[0] ?? $coords; 
        
        if (is_array($_co) && count($_co) >= 2) {
            [$x, $y] = $_co;
            $token = $this->rss($api, ['html' => $this->html, 'js' => $_J], $x, $y);
            if ($token) {
                return [
                    'rscaptcha_token' => $_T,
                    'rscaptcha_response' => $token
                ];
            }
        }
        
        return false;
    }
    
    private function rsc($rss, $api) {
        # problematic provider need much parameter
        $token = null;
        #print_r($rss); die;
        
        $_D = $rss['extra'] ?? null;
        $_I = $_D['app_id'] ?? null;
        $_T = $_D['version'] ?? null;
        $_K = $_D['public_key'] ?? null;
        
        $_H = 'https://rscaptcha.com';
        
        if (in_array(null, [$_D, $_I, $_T, $_K], true)) return false;
        $rs_R = null;
        $rs_T = null;
        
        if (strtolower(get_class($api)) === 'skibidixxx') {
            $res = $api->rss($_D, $this->host);
            if ($res) {
                parse_str(str_replace([":", ","], ["=", "&"], $res), $out);
                $rs_T = $out['rs_token'] ?? null;
                $rs_R = $out['rs_res'] ?? null;
            }
        } else {
            $_0 = SolveUtils::webkitID($_D, $boundary);
            $head = ["Content-Type: multipart/form-data; boundary=$boundary"];
            
            $_get = json_decode(Net::S($_H."/captcha/$_T/get", 'POST', $_0, $head) ?: '', 1)['data'] ?? null;
            
            $coo = null;
            if (!empty($_get) && isset($_get['captcha_key'])) {
                $rs_T = $_get['captcha_key'];
                if (method_exists($api, 'rss')) $coo = $api->rss($_get, $this->host);
            }
            if ($coo) {
                $coords = scraper::_jP($coo, '/\d+/');
                $_co = $coords[0] ?? $coords;
            }
            if (is_array($_co) && count($_co) >= 2) {
                [$x, $y] = $_co;
                $_P = [
                    'token' => $rs_T,
                    'response' => "$x,$y",
                    #'response' => "200,109",
                ];
                $_1 = SolveUtils::webkitID(array_merge($_P, $_D), $boundary);
                $rs_R = json_decode(Net::S($_H."/captcha/$_T/verify", 'POST', $_1, $head) ?: '', 1)['result'] ?? null;
            }
        }
        
        if ($rs_R && $rs_T) {
            return [
                'rscaptcha_token' => $rs_T,
                'rscaptcha_response' => $rs_R,
            ];
        }
        
        return null;
        
    }
    
    private function rss($api, $utils, $x, $y) {
        $provider = strtolower(get_class($api));
        $token = null;
        
        # if some provider got many invalid
        # u can change to use locally fallback
        # uncomment to use by provider, it'll consume few credit
        
        /*
        if ($provider === 'tertuyul') {
            $data = [
                'clickX' => $x,
                'clickY' => $y,
                'script' => base64_encode($utils['js'])
            ];
            $token = $api->run('rstoken', $data);
        } 
        
        if ($provider === 'skibidixxx') {
            $data = [
                "htmlContent" => $utils['html'],
                "clickX" => $x,
                "clickY" => $y
            ];
            for ($retry = 0; $retry < 3; $retry++) {
                usleep(500000);
                $res = json_decode(Net::S('https://api.waryono.my.id/rspayload.php', 'POST', $data, json: true) ?: '', true);
                #var_dump($res);
                if (isset($res['Payload'])) {
                    $token = $res['Payload'];
                    break;
                }
            }
        }
       */
       
        if (!$token) {
            # this is got 2 method and auto pass
            $rss = new rsResponse($this->ua, $this->host);
            $token = $rss->exec($utils, $x, $y);
            
        }
        return $token;

    }
    
}

class rsResponse {
    use WorkDir;
    
    /*
    what a different between other?
    dont know what, but this class is using nodeJs pipeline.
    as the js is valid, the map will be as it given.
    especially for icon type (i recommend this method).
    dont forget to install nodejs and synchrony deobfuscator
    */
    
    private ?string $uagent;
    private ?string $host;
    
    public function __construct(?string $ua, ?string $host) {
        $this->uagent = $ua;
        $this->host = $host;
        $this->workDir = $this->setupWorkDir('rscaptcha', $host);
    }
    
    public function exec(array $data, $x, $y) {
        
        $html = $data['html'];
        $jsContent = $data['js'];
        
        $nod = getDeps('nodejs');
        $npm = getDeps('npm');
        $syn = getDeps('synchrony@npm');
        
        $token = null; 
        
        if (in_array(false, [$nod, $npm, $syn], true)) {
            $this->rmdir($this->workDir);
            return $this->fallback($x, $y, $html);
        }
        
        #$hasil = $this->_dump($jsContent);
        $i = $this->workDir . '/i.js';
        $o = $this->workDir . '/o.js';
        $hasil = solveUtils::dumpJs($jsContent, $i);
        if ($hasil && is_file($i)) exec("synchrony $i -o $o");
        
        if ($hasil && is_file($o)) $token = $this->_token($o, $x, $y, $this->uagent);
        
        $this->rmdir($this->workDir);
        
        return $token ?: $this->fallback($x, $y, $html);
    }
/*
    private function _dump($_input, $unlink = false) {

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

        $cmd = 'node -e ' . escapeshellarg($nodeWrapper);

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

        if (isset($this->workDir) && is_dir($this->workDir)) {
            _put($this->workDir . '/i.js', $best);
        }

        return $best;
    }
*/
    private function _token($jsFile, $x, $y, $ua) {
        if (!file_exists($jsFile)) return false;
        $jsContent = _get($jsFile);

        /** Dumbass RSSHORT with Auto-Scaling */
        $startPos = strpos($jsContent, 'btoa(');
        if ($startPos === false) return false;
        
        $start = $startPos + 5;
        $end = strpos($jsContent, ')', $start);
        $btoaBody = substr($jsContent, $start, $end - $start);
        $rawVars = explode(',', str_replace(['+', "'", '"', ' ', "\n", "\r"], '', $btoaBody));
        $platform = (stripos($ua ?? '', 'Windows') !== false) ? 'Win32' : 'Linux x86_64';
        
        $payloadArray = [];
        $timestamp = time();
        foreach ($rawVars as $v) {
            $v = trim($v);
            $qv = preg_quote($v, '/');
            
            if (preg_match('/'. $qv .'\s*=\s*Math\.round\(.*?\.pageX\s*-\s*.*?\)/', $jsContent)) {
                $payloadArray[] = (int)$x;
            } elseif (preg_match('/'. $qv .'\s*=\s*Math\.round\(.*?\.pageY\s*-\s*.*?\)/', $jsContent)) {
                $payloadArray[] = (int)$y;
            } elseif (preg_match('/'. $qv .'\s*=\s*~~\(Date\.now/', $jsContent)) {
                $payloadArray[] = (int)$timestamp;
            } elseif (preg_match('/'. $qv .'\s*=\s*screen\.width/', $jsContent)) {
                $payloadArray[] = 1440; 
            } elseif (preg_match('/'. $qv .'\s*=\s*screen\.height/', $jsContent)) {
                $payloadArray[] = 900;
            } elseif (preg_match('/'. $qv .'\s*=\s*navigator\.platform/', $jsContent)) {
                $payloadArray[] = $platform;
            } elseif (preg_match('/'. $qv .'\s*=\s*Math\.round\(window\.pageXOffset\)/', $jsContent)) {
                $payloadArray[] = 0;
            } elseif (preg_match('/'. $qv .'\s*=\s*Math\.round\(window\.pageYOffset\)/', $jsContent)) {
                $payloadArray[] = rand(0, 30);
            } elseif (preg_match('/'. $qv .'\s*=\s*navigator\.onLine/', $jsContent)) {
                $payloadArray[] = 1;
            } elseif (preg_match('/'. $qv .'\s*=\s*document\.hasFocus\(\)/', $jsContent)) {
                $payloadArray[] = 1;
            } else {
                if (strpos($v, 'Depth') !== false) $payloadArray[] = 24;
                else $payloadArray[] = rand(1, 10);
            }
        }
        return base64_encode(implode(',', $payloadArray));
    }
    
    private function fallback($x, $y, $html) {
        $rss = new rss_build();
        return $rss->build($y, $x, $html);
    }
}

class rss_build {
    
    # xevil source
    
    private const FINGERPRINT = [
        'screenWidth'        => '806',
        'screenHeight'       => '320',
        'availWidth'         => '806',
        'availHeight'        => '320',
        'colorDepth'         => '24',
        'pixelDepth'         => '24',
        'innerHeight'        => '320',
        'innerWidth'         => '806',
        'platform'           => 'Linux armv81',
        'appCodeName'        => 'Mozilla',
        'hardwareConcurrency'=> '8',
    ];

    private const SOURCE_TO_VALUE = [
        'screen_0'    => 'screenWidth',
        'screen_1'    => 'screenHeight',
        'screen_2'    => 'availWidth',
        'screen_3'    => 'availHeight',
        'screen_4'    => 'colorDepth',
        'screen_5'    => 'pixelDepth',
        'navigator_0' => 'appCodeName',
        'navigator_1' => 'appCodeName',
        'navigator_2' => 'mozFlag',
        'clientInfo_0'=> 'platform',
        'clientInfo_1'=> 'hardwareConcurrency',
        'window_0'    => 'innerHeight',
        'window_1'    => 'innerWidth',
        'document_0'  => 'hasFocus',
        'click_0'     => 'clickX',
        'click_1'     => 'clickY',
        'timestamp'   => 'timestamp',
    ];

    public function build($x, $y, $html) {
        $js    = $this->deobfuscate($html);
        #_put('ccap1.js', $js);
        $order = $js ? $this->extractFieldOrder($js) : $this->defaultOrder();
        #print_r($order);
        return $this->generateToken($x, $y, $order);
    }

    private function generateToken($x, $y, array $order) {
        $dynamic = [
            'timestamp' => (string) time(),
            'clickX' => (string) $x,
            'clickY' => (string) $y,
        ];

        $static = array_merge(self::FINGERPRINT, [
            'hasFocus' => '1',
            'mozFlag'  => '0',
        ]);

        $values = [];
        foreach ($order as $field) {
            $key = self::SOURCE_TO_VALUE[$field['source']] ?? '';
            $values[] = $dynamic[$key] ?? $static[$key] ?? '0';
        }

        return base64_encode(implode(',', $values));
    }

    private function deobfuscate(string $html): ?string {
        if (!preg_match('/\}\("([^"]+)",\d+,"([^"]+)",(\d+),(\d+),\d+\)\)/', $html, $m)) return null;

        [$encoded, $alphabet, $shift, $base] = [$m[1], $m[2], (int)$m[3], (int)$m[4]];

        if ($base >= strlen($alphabet)) return null;

        $separator = $alphabet[$base];
        $result = '';

        foreach (explode($separator, $encoded) as $seg) {
            if ($seg === '') continue;

            $converted = $seg;
            for ($j = 0; $j < strlen($alphabet); $j++) {
                $converted = str_replace($alphabet[$j], (string)$j, $converted);
            }

            $charCode = $this->baseConvert($converted, $base) - $shift;
            if ($charCode > 0 && $charCode < 65536) $result .= mb_chr($charCode);
        }

        return $result ?: null;
    }

    private function baseConvert($encoded, $base) {
        $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ+/';
        $src = substr($chars, 0, $base);
        $result = 0;
        $len = strlen($encoded);

        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($src, $encoded[$len - 1 - $i]);
            if ($pos !== false) $result += $pos * (int)pow($base, $i);
        }

        return $result;
    }

    private function extractFieldOrder($js): array {
        $btoaIdx = strpos($js, 'btoa');
        if ($btoaIdx === false) return $this->defaultOrder();

        $section = substr($js, $btoaIdx, 3000);

        preg_match('/\((_0x[a-f0-9]+),/', $section, $first);
        preg_match_all('/\),(_0x[a-f0-9]+)\)/', $section, $rest);

        $order = array_merge(
            $first[1] ? [$first[1]] : [],
            array_slice($rest[1], 0, 16)
        );

        if (count($order) < 17) return $this->defaultOrder();

        $map = [];
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*screen\[/',             $map, 'screen',     6);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*navigator\[/',           $map, 'navigator',  3);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*clientInformation\[/',   $map, 'clientInfo', 2);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*Math\[.*?\]\(window\[.*?\]\)/', $map, 'window', 2);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*document\[/',            $map, 'document',   1);
        $this->mapVars($js, '/(_0x[a-f0-9]+)\s*=\s*Math\[.*?\]\(_0x.*?\[/',$map, 'click',      2);

        if (preg_match('/(_0x[a-f0-9]+)\s*=\s*~~_0x/', $js, $m)) {
            $map[$m[1]] = 'timestamp';
        }

        return array_map(fn($v) => ['source' => $map[$v] ?? 'unknown', 'is_flag' => false], array_slice($order, 0, 17));
    }

    private function mapVars($js, $pattern, array &$map, $prefix, $limit): void {
        preg_match_all($pattern, $js, $m);
        foreach (array_slice($m[1], 0, $limit) as $i => $v) {
            $map[$v] = "{$prefix}_{$i}";
        }
    }

    private function defaultOrder(): array {
        return [
            ['source' => 'screen_4', 'is_flag' => false],
            ['source' => 'navigator_0', 'is_flag' => true ],
            ['source' => 'click_1', 'is_flag' => false],
            ['source' => 'click_0', 'is_flag' => false],
            ['source' => 'document_0', 'is_flag' => true ],
            ['source' => 'screen_1', 'is_flag' => false],
            ['source' => 'navigator_1', 'is_flag' => false],
            ['source' => 'navigator_2', 'is_flag' => true ],
            ['source' => 'window_0', 'is_flag' => false],
            ['source' => 'clientInfo_0','is_flag' => false],
            ['source' => 'screen_0', 'is_flag' => false],
            ['source' => 'window_1', 'is_flag' => false],
            ['source' => 'screen_2', 'is_flag' => false],
            ['source' => 'timestamp', 'is_flag' => false],
            ['source' => 'document_0', 'is_flag' => true ],
            ['source' => 'navigator_2', 'is_flag' => true ],
            ['source' => 'screen_5', 'is_flag' => false],
        ];
    }
}
