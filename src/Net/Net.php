<?php

/** @class Mux
 * @method build
     * @param array $opt
     * @return resource|null
 * @method Exec
     * @param array $queue
     * @param int $concurrency 
     * @return array
 * @method C
     * Format arg:
     * [
     *   $url,
     *   $type,
     *   $data,
     *   $cookie,
     *   $head,
     *   $ref,
     *   $ua,
     *   $ip,
     *   $json,
     *   $proxy
     * ]
     *
     * @param array ...$calls
     * @return array
 */
class Mux {

    private static function build(array $opt) {
        
        if ($opt['type'] === 'GET' && !empty($opt['data']) && is_array($opt['data'])) {
            $qs = http_build_query($opt['data']);
            if ($qs !== '') {
                $opt['url'] .= (str_contains($opt['url'], '?') ? '&' : '?') . $qs;
            }
        }

        if (empty($opt['url'])) return null;

        $opt['http2'] = $opt['http2'] ?? true;
        $opt['head'] = Net::applyHead($opt); 

        $ch = curl_init($opt['url']);
        if (!$ch) return null;

        # 3. PROXY
        if (!empty($opt['proxy'])) {
            $p = $opt['proxy'];
            curl_setopt($ch, CURLOPT_PROXY, $p['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $p['port']);
            curl_setopt($ch, CURLOPT_PROXYTYPE, $p['type']);
            if (!empty($p['auth'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $p['auth']);
            }
            $isHttps = stripos($opt['url'], 'https://') === 0;
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, $isHttps);
        } else {
            Net::applyProxy($ch, $opt['url']); 
        }

        # 4. CURL OPTIONS
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $opt['follow'],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $opt['head'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2TLS,
            CURLOPT_ENCODING       => '',
        ]);

        # 5. COOKIE
        if (!empty($opt['cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $opt['cookie']);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $opt['cookie']);
        }

        # 6. DNS / CONNECT
        if (!empty($opt['connect'])) curl_setopt($ch, CURLOPT_CONNECT_TO, $opt['connect']);
        if (!empty($opt['resolve'])) curl_setopt($ch, CURLOPT_RESOLVE, $opt['resolve']);

        # 7. METHOD & PAYLOAD
        if ($opt['type'] !== 'GET') {
            if ($opt['type'] === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $opt['type']);
            }

            if (isset($opt['data'])) {
                $payload = is_array($opt['data']) 
                    ? ($opt['isJson'] ? json_encode($opt['data']) : http_build_query($opt['data']))
                    : $opt['data'];
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        }

        return $ch;
    }

    private static function Exec(array $queue, $concurrency) {
        $mh = curl_multi_init();
        $results = [];
        $map = [];
        $active = [];

        $add = function($item) use ($mh, &$map, &$active) {
            [$key, $ch] = $item;
            if (!$ch) return;
            $id = (int)$ch;
            $map[$id] = $key;
            $active[$id] = $ch;
            curl_multi_add_handle($mh, $ch);
        };

        // Fill initial queue
        for ($i = 0; $i < $concurrency && !empty($queue); $i++) {
            $add(array_shift($queue));
        }

        $running = null;
        do {
            do {
                $status = curl_multi_exec($mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $id = (int)$ch;
                $key = $map[$id] ?? $id;

                $results[$key] = curl_multi_getcontent($ch);

                curl_multi_remove_handle($mh, $ch);
                #curl_close($ch);
                unset($map[$id], $active[$id]);

                if (!empty($queue)) {
                    $add(array_shift($queue));
                }
            }

            if ($running) {
                if (curl_multi_select($mh, 1.0) === -1) {
                    usleep(1000);
                }
            }
        } while ($running || !empty($active));

        curl_multi_close($mh);
        return $results;
    }

    /**
     * Parallel Request
     * Params: [url, type, data, cookie, head, ref, ua, ip, json, proxy]
     */
    public static function C(array ...$calls) {
        $queue = [];
        foreach ($calls as $key => $args) {
            [
                $url, $type, $data, $cookie, $head, 
                $ref, $ua, $ip, $json, $proxy
            ] = array_pad($args, 10, null);

            $type = strtoupper($type ?? 'GET');
            $head = $head ?? [];
            
            if ($json) {
                $head[] = "Accept: application/json, text/javascript, */*";
            } else {
                $head[] = "Accept: */*";
            }

            $dns = []; $connect = [];
            if (!empty($ip)) {
                $dom = parse_url($url)['host'] ?: '';
                if ($dom) {
                    if (!empty($GLOBALS['_CTX']['proxy'])) {
                        $connect = ["$dom:443:$ip:443"];
                    } else {
                        $dns = ["$dom:80:$ip", "$dom:443:$ip"];
                    }
                }
            }

            $queue[] = [
                $key,
                self::build([
                    'url'     => $url,
                    'type'    => $type,
                    'data'    => $data,
                    'cookie'  => $cookie,
                    'head'    => $head,
                    'ref'     => $ref,
                    'ua'      => $ua ?: 'Mozilla/5.0',
                    'isJson'  => $json ?? false,
                    'follow'  => true,
                    'resolve' => $dns,
                    'connect' => $connect,
                    'proxy'   => $proxy,
                    'http2'   => true // Mux default to HTTP/2 logic
                ])
            ];
        }

        return self::Exec($queue, 15); 
    }
}

/** @class Net
 * @method applyProxy
     * @param resource $ch 
     * @param string $url 
     * @return void
 * @method applyHead
     * @param array &$opt 
     * @return array
 * @method hasHeader
     * @param array $he 
     * @param string $name 
     * @return bool
 * @method Http
     * @param array $opt 
     * @param bool $in 
     * @param bool $fresh 
     * @return mixed
 * @method C
     * @param string $url
     * @param string $type
     * @param mixed $data
     * @param string|null $cookie
     * @param array $head
     * @param string $reff
     * @param string $ua
     * @param bool $d Debug
     * @param bool $v Verbose
     * @param string|null $ip 
     * @param bool $foll Follow redirect
     * @param bool $ins Insecure SSL
     * @param bool $f Fresh connection
     * @return mixed
 * @method X
     * @param string $url
     * @param string $type
     * @param mixed $data
     * @param string|null $cookie
     * @param array $head
     * @param string $reff
     * @param string $ua
     * @param bool $json 
     * @param bool $foll Follow redirect
     * @param string|null $ip 
     * @param bool $ins Insecure SSL
     * @return mixed
 * @method S
     * @param string $url
     * @param string $type
     * @param mixed $data
     * @param array $head
     * @param bool $json 
 */
class Net {

    public static function applyProxy($ch, $url) {
        Proxy::ensure();
        if (!empty($GLOBALS['_CTX']['proxy'])) {
            $p = $GLOBALS['_CTX']['proxy'];
            curl_setopt($ch, CURLOPT_PROXY, $p['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $p['port']);
            curl_setopt($ch, CURLOPT_PROXYTYPE, $p['type']);
            if (!empty($p['auth'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $p['auth']);
            }
            $i = stripos($url, 'https://') === 0;
            
            if ($p['type'] === CURLPROXY_HTTP || (defined('CURLPROXY_HTTPS') && $p['type'] === CURLPROXY_HTTPS)) {
                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, $i);
            }
        }
    }
    
    public static function applyHead(&$opt) {
        $ua = trim($opt['ua'] ?? '');
        $url = $opt['url'];
        $ref = $opt['ref'] ?? '';
        $ajx = $opt['http2'] ?? false;
        $he_manual = $opt['head'] ?: [];
        
        $head = [];
        
        $head[] = "Host: " . parse_url($url)['host'];
        
        if ($ua !== '') {
            $is_mobile = (stripos($ua, 'Android') !== false || stripos($ua, 'Mobile') !== false);
            $platform = (stripos($ua, 'Android') !== false) ? "Android" : ((stripos($ua, 'Mac') !== false) ? "macOS" : "Windows");
            preg_match('/Chrome\/(\d+)/', $ua, $m);
            $v = $m[1] ?? '144';
            
            $head[] = 'Sec-CH-UA: "Not(A:Brand";v="8", "Chromium";v="'.$v.'", "Brave";v="'.$v.'"';
            $head[] = 'Sec-CH-UA-Mobile: '.($is_mobile ? '?1' : '?0');
            $head[] = 'Sec-CH-UA-Platform: "'.$platform.'"';
        }
        
        if (!$ajx) {
            $head[] = "Upgrade-Insecure-Requests: 1";
        }
        
        if ($ua !== '') {
            $head[] = "User-Agent: $ua";
        }
        
        foreach ($he_manual as $h) {
            if (stripos($h, 'Cookie:') === 0) {
                $he_cookie = $h;
                continue;
            }
            $head[] = $h;
        }
        
        if ($ajx && !empty($ref)) {
            $head[] = "Origin: " . parse_url($ref)['scheme'] . "://" . parse_url($ref)['host'];
        }
        
        $he_fetchs = "none";
        if (!empty($ref)) {
            $he_t = parse_url($url)['host'] ?? '';
            $he_r = parse_url($ref)['host'] ?? '';
            if ($he_t === $he_r) {
                $he_fetchs = "same-origin";
            } else {
                $t_pa = explode('.', $he_t);
                $r_pa = explode('.', $he_r);
                $tRoot = implode('.', array_slice($t_pa, -2));
                $rRoot = implode('.', array_slice($r_pa, -2));
                $he_fetchs = ($tRoot === $rRoot) ? "same-site" : "cross-site";
            }
        }
        $head[] = "Sec-Fetch-Site: $he_fetchs";
        
        if ($ajx) {
            $head[] = "Sec-Fetch-Mode: cors";
            $head[] = "Sec-Fetch-Dest: empty";
        } else {
            $head[] = "Sec-Fetch-Mode: navigate";
            $head[] = "Sec-Fetch-User: ?1";
            $head[] = "Sec-Fetch-Dest: document";
        }
        
        if (!empty($ref)) {
            $head[] = "Referer: $ref";
        }
        
        $lang = function_exists('LANGUAGE') ? LANGUAGE() : 'id-ID,id;q=0.9';
        $head[] = "Accept-Language: $lang";
        
        if (isset($he_cookie)) {
            $head[] = $he_cookie;
        }
        
        #print_r($head);
        return $head;
    }
    
    private static function hasHeader(array $he, $name) {
        $name = strtolower($name) . ':';
        foreach ($he as $header) {
            if (stripos(strtolower($header), $name) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function Http(array $opt, $in = false, $fresh = false) {
        
        # METHOD
        $type = strtoupper($opt['type']);
        if ($type === 'GET' && !empty($opt['data']) && is_array($opt['data'])) {
            $qs = http_build_query($opt['data']);
            if ($qs !== '') {
                $opt['url'] .= (str_contains($opt['url'], '?') ? '&' : '?') . $qs;
            }
        }

        if (empty($opt['url']) || !is_string($opt['url'])) {
            logx('err', 'invalid url'); return null;
        }

        # HEADERS
        $opt['head'] = self::applyHead($opt);

        $ch = curl_init($opt['url']);
        if (!$ch) { logx('err', 'init failed'); return null; }

        # PROXY
        if (empty($opt['no_proxy'])) {
            self::applyProxy($ch, $opt['url']);
        } else {
            curl_setopt($ch, CURLOPT_PROXY, '');
        }

        # HTTP VERSION
        $insecure = $in;
        $httpVer = CURL_HTTP_VERSION_1_1;
        if (!empty($opt['http2']) && !$insecure) {
            $httpVer = CURL_HTTP_VERSION_2TLS;
        }

        # INIT
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $opt['follow'],
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $opt['head'], 
            #CURLOPT_REFERER => $opt['ref'],
            CURLOPT_SSL_VERIFYPEER => !$insecure,
            CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
            CURLOPT_HTTP_VERSION => $httpVer,
            CURLOPT_FORBID_REUSE => $fresh,
            CURLOPT_FRESH_CONNECT => $fresh,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME  => 15,
            CURLOPT_ENCODING => '',
        ]);

        # VERBOSE
        $logFile = null;
        if (!empty($opt['verbose'])) {
            $logFile = fopen(LIBDIR . "/verbose.log", "a");
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $logFile);
        }
        
        # DNS
        if (!empty($opt['connect'])) {
            curl_setopt($ch, CURLOPT_CONNECT_TO, $opt['connect']);
        }
        if (!empty($opt['resolve'])) {
            curl_setopt($ch, CURLOPT_RESOLVE, $opt['resolve']);
        }

        # HEADERS
        $headr = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$headr) {
            $len = strlen($line); $line = trim($line);
            if ($line === '' || stripos($line, 'HTTP/') === 0) return $len;
            if (!str_contains($line, ':')) return $len;
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $headr[strtolower($k)][] = $v; return $len;
        });

        # COOKIE
        if (!empty($opt['cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $opt['cookie']);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $opt['cookie']);
        }

        # PAYLOAD
        if ($type === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        } elseif ($type !== 'GET') {
            if ($type === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
            } 
            if (isset($opt['data'])) {
                $payload = is_array($opt['data']) ? (!empty($opt['isJson']) ? json_encode($opt['data']) : http_build_query($opt['data'])) : $opt['data'];
                #print_r($payload); logx();
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        } 

        # EXEC
        try {
            for ($attempt = 0; $attempt <= 3; $attempt++) {
                $body = curl_exec($ch);
                $info = curl_getinfo($ch);
                $errno = curl_errno($ch);
                $err = curl_error($ch);
                
                if ($body !== false) {
                    if (!empty($opt['debug'])) {
                        return [
                            'http_code' => $info['http_code'] ?? null,
                            'url' => $info['url'] ?? null,
                            'headers' => $headr ?? null,
                            'errno' => $errno ?: null,
                            'error' => $err ?: null,
                            'info' => $info,
                            'body' => $body,
                        ];
                    } return $body;
                }

                if ($attempt > 0 && in_array($errno, [56, 92], true)) {
                    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                }

                $retry = in_array($errno, [28, 35, 52, 56, 92], true);
                if (!$retry || $attempt === 3) {
                    throw new Exception("Net($errno): $err");
                } 
                usleep(random_int(25, 50) * 10000);
            } 
            throw new Exception("failed");
        } catch (Throwable $e) {
            logx('info', " \r {$e->getMessage()} \r", true, true);
            return null;
        } finally { 
            if (is_resource($logFile)) fclose($logFile);
            $ch = null;
        }
    }

    public static function C($url, $type, $data = null, $cookie = null, array $head = [], $reff = '', $ua = 'Mozilla/5.0', $d = false, $v = false, $ip = null, $foll = true, $ins = false, $f= false) {
        $dns = []; $connect = [];
        if (!empty($ip)) {
            $dom = parse_url($url)['host'];
            if (!empty($GLOBALS['_CTX']['proxy'])) {
                $connect = ["$dom:443:$ip:443"];
            } else {
                $dns = ["$dom:80:$ip", "$dom:443:$ip"]; 
            }
        }
        if (!self::hasHeader($head, 'Accept')) {
            $head[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
        }
        if (in_array($type, ['POST','PUT','PATCH'], true)) {
            if (!self::hasHeader($head, 'Content-Type')) {
                $head[] = "Content-Type: application/x-www-form-urlencoded";
            }
        }
            
        return self::Http([
            'url' => $url,
            'type' => $type,
            'data' => $data,
            'cookie' => $cookie,
            'head' => $head, 
            'ref' => $reff, 
            'ua' => $ua,
            'verbose' => $v,
            'debug' => $d,
            'follow' => $foll,
            'resolve' => $dns,
            'connect' => $connect,
        ], $ins, $f);
    }

    public static function X($url, $type, $data = null, $cookie = null, array $head = [], $reff = '', $ua = 'Mozilla/5.0', $json = false, $foll = true, $ip = null, $ins = false, $d = false) {
        $dns = []; $connect = [];
        if (!empty($ip)) {
            $dom = parse_url($url)['host'];
            if (!empty($GLOBALS['_CTX']['proxy'])) {
                $connect = ["$dom:443:$ip:443"]; 
            } else {
                $dns = ["$dom:80:$ip", "$dom:443:$ip"]; 
            }
        }
        
        if ($json && in_array($type, ['POST','PUT','PATCH'], true)) {
            if (!self::hasHeader($head, 'Content-Type')) $head[] = 'Content-Type: application/json';
            if (!self::hasHeader($head, 'Accept')) $head[] = 'Accept: application/json, text/javascript';
        } else {
            if (!self::hasHeader($head, 'Accept')) $head[] = 'Accept: */*';
            if (!self::hasHeader($head, 'Content-Type')) $head[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        
        return self::Http([
            'url' => $url,
            'type' => $type,
            'data' => $data,
            'cookie' => $cookie,
            'head' => array_merge(['X-Requested-With: XMLHttpRequest'], $head),
            'ref' => $reff,
            'ua' => $ua,
            'isJson' => $json,
            'follow' => $foll,
            'resolve' => $dns,
            'connect' => $connect,
            'debug' => $d,
            'http2' => true,
        ], $ins, true);
    }

    public static function S($url, $type = 'POST', $data = null, array $head = [], $json = false) {
        
        if (!self::hasHeader($head, 'Connection')) {
            $head[] = "Connection: keep-alive";
        }
        if ($json && !self::hasHeader($head, 'Content-Type')) {
            $head[] = "Content-Type: application/json";
        }
        
        $oldProxy = getenv('PROXY');
        $oldCtx = $GLOBALS['_CTX']['proxy'] ?? null;
        
        putenv("PROXY=");
        unset($GLOBALS['_CTX']['proxy']);
        
        $res = self::Http([
            'url' => $url,
            'type' => $type,
            'data' => $data,
            'head' => $head,
            'isJson' => $json,
            'follow' => true,
            'verbose' => false,
            'no_proxy' => true 
        ], false, true);
        
        if ($oldProxy !== false) putenv("PROXY=$oldProxy");
        if ($oldCtx !== null) $GLOBALS['_CTX']['proxy'] = $oldCtx;
        
        return $res;
    }

}

/** @class Ws
 * @method Wait
     * Menunggu socket siap dibaca.
     * @param array $c Context 
     * @param int $sec Timeout 
     * @param int $usec Timeout 
     * @return bool 
 * @method SendText
     * @param array $c Context 
     * @param string $text 
     * @return void
 * @method Connect
     * @param string $url 
     * @param array $head 
     * @param bool $insecure 
     * @return array
 * @method Recv
     * Return:
     * - ['type'=>'text','payload'=>string]
     * - ['type'=>'binary','payload'=>string]
     * - ['type'=>'ping','payload'=>string]
     * - ['type'=>'pong','payload'=>string]
     * - ['type'=>'close','code'=>int,'reason'=>string]
     * @param array &$c Context
     * @return array|null
 * @method wsProxy
     * @return string|null
 * @method applyProxy
     * @param string $host 
     * @param int $port 
     * @param bool $tls 
     * @param bool $insecure 
     * @param array $p 
     * @return array
 * @method proxyHttp
     * @param resource $fp 
     * @param string $host 
     * @param int $port Port 
     * @param string|null $auth Auth proxy user:pass
     * @return void
 * @method proxySock
     * @param resource $fp 
     * @param string $host Host 
     * @param int $port Port 
     * @param string|null $auth Auth proxy user:pass
     * @return void
 * @method getHeader
     * @param resource $fp Stream
     * @param string $prebuf  
     * @return array
 * @method readFr
     * @param array &$c Context 
     * @param int $n 
 * @method sendFr
     * @param resource $fp 
     * @param int $op  
     * @param string $pl 
     * @return void
 * @method ApplyMask
     * @param string $d Data
     * @param string $k Mask key 4 byte
     * @return string
 * @method Close
     * @param array &$c Context 
     * @param int $code Close code
     * @param string $reason 
     * @return void
 */
class Ws {
    public static function Wait(array $c, $sec = 1, $usec = 0) {
        $fp = $c['fp'] ?? null;
        if (!is_resource($fp)) return false;

        $r = [$fp];
        $w = null;
        $e = null;

        $n = @stream_select($r, $w, $e, $sec, $usec);
        return ($n > 0);
    }

    public static function SendText(array $c, $text) {
        $fp = $c['fp'] ?? null;
        if (!is_resource($fp)) return;

        self::sendFr($fp, 0x1, $text);
    }

    public static function Connect($url, array $head = [], $insecure = false) {
        $u = parse_url($url);
        if (!$u || empty($u['scheme']) || empty($u['host'])) {
            throw new Exception("invalid url");
        }

        $scheme = strtolower($u['scheme']);
        if ($scheme !== 'ws' && $scheme !== 'wss') {
            throw new Exception("invalid scheme");
        }

        $tls  = ($scheme === 'wss');
        $host = $u['host'];
        $port = (int)($u['port'] ?? ($tls ? 443 : 80));

        $path = $u['path'] ?? '/';
        if (!empty($u['query'])) {
            $path .= '?' . $u['query'];
        }

        Proxy::ensure();
        $p = $GLOBALS['_CTX']['proxy'] ?? null;

        $rest = '';
        if (is_array($p) && isset($p['host'], $p['port'], $p['type']) && $p['host'] !== '' && $p['port'] > 0) {
            [$fp, $rest] = self::applyProxy($host, $port, $tls, $insecure, $p);
        } else {
            // direct
            $ctx = null;
            if ($tls) {
                $ctx = stream_context_create([
                    'ssl' => [
                        'verify_peer' => !$insecure,
                        'verify_peer_name' => !$insecure,
                        'allow_self_signed' => $insecure,
                        'SNI_enabled' => true,
                        #'force TLSv1.1' => true
                        'peer_name' => $host,
                    ]
                ]);
            }

            $remote = ($tls ? "tls://" : "tcp://") . $host . ":" . $port;
            $errno = 0;
            $errstr = '';
            $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
            if (!$fp) {
                throw new Exception("connect failed: $errno $errstr");
            }
        }

        // handshake blocking 
        stream_set_blocking($fp, true);
        stream_set_timeout($fp, 15);

        $key = base64_encode(random_bytes(16));

        $req = [
            "GET $path HTTP/1.1",
            "Host: $host",
            "Connection: Upgrade",
            "Upgrade: websocket",
            "Sec-WebSocket-Version: 13",
            "Sec-WebSocket-Key: $key",
            "Sec-ch-Websocket: ",
        ];
        foreach ($head as $v) {
            $req[] = $v;
        }

        fwrite($fp, implode("\r\n", $req) . "\r\n\r\n");

        [$hdr, $rest2] = self::getHeader($fp, $rest);
        $rest = $rest2;

        if (!preg_match('#^HTTP/1\.[01]\s+101#', $hdr)) {
            fclose($fp);
            throw new Exception("handshake failed:\n$hdr");
        }
        if (!preg_match('/Sec-WebSocket-Accept:\s*(.+)\r\n/i', $hdr, $m)) {
            fclose($fp);
            throw new Exception("missing Sec-WebSocket-Accept");
        }

        $accept = trim($m[1]);
        $expect = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
        if (!hash_equals($expect, $accept)) {
            fclose($fp);
            throw new Exception("Sec-WebSocket-Accept mismatch");
        }

        // non-blocking handshake
        stream_set_timeout($fp, 30);
        stream_set_blocking($fp, false);

        return ['fp' => $fp, 'buf' => $rest];
    }

    public static function Recv(array &$c): ?array{
        $fp = $c['fp'] ?? null;
        if (!is_resource($fp)) return null;

        $h2 = self::readFr($c, 2);
        if ($h2 === null) return null;

        $b1 = ord($h2[0]);
        $b2 = ord($h2[1]);

        $op = ($b1 & 0x0F);
        $masked = (bool)($b2 & 0x80);
        $len = ($b2 & 0x7F);

        if ($len === 126) {
            $ext = self::readFr($c, 2);
            if ($ext === null) return null;
            $len = unpack('n', $ext)[1];
        } elseif ($len === 127) {
            $ext = self::readFr($c, 8);
            if ($ext === null) return null;
            $a = unpack('N2', $ext);
            $len = ($a[1] << 32) | $a[2]; // big lens need 64bit PHP
        }

        $maskKey = '';
        if ($masked) {
            $maskKey = self::readFr($c, 4);
            if ($maskKey === null) return null;
        }

        $pl = ($len > 0) ? self::readFr($c, $len) : '';
        if ($pl === null) return null;

        if ($masked) {
            $pl = self::ApplyMask($pl, $maskKey);
        }

        // control frames
        if ($op === 0x9) { // ping
            self::sendFr($fp, 0xA, $pl); // pong
            return ['type' => 'ping', 'payload' => $pl];
        }
        if ($op === 0xA) {
            return ['type' => 'pong', 'payload' => $pl];
        }
        if ($op === 0x8) {
            $code = null;
            $reason = '';
            if (strlen($pl) >= 2) {
                $code = unpack('n', substr($pl, 0, 2))[1];
                $reason = substr($pl, 2);
            }
            return ['type' => 'close', 'code' => $code, 'reason' => $reason];
        }

        if ($op === 0x1) return ['type' => 'text', 'payload' => $pl];
        if ($op === 0x2) return ['type' => 'binary', 'payload' => $pl];

        return ['type' => 'frame', 'opcode' => $op, 'payload' => $pl];
    }

    // WS-proxy exit IP probe (helper)
    public static function wsProxy() {
        Proxy::ensure();
        $p = $GLOBALS['_CTX']['proxy'] ?? null;

        if (!is_array($p) || !isset($p['host'], $p['port'], $p['type']) || $p['host'] === '' || (int)$p['port'] <= 0) {
            return null; // proxy off / invalid
        }

        $checkHost = 'api.ipify.org';
        $checkPort = 443;

        [$fp, $rest] = self::applyProxy($checkHost, $checkPort, true, false, $p);

        stream_set_blocking($fp, true);
        stream_set_timeout($fp, 10);

        $req =
            "GET /?format=json HTTP/1.1\r\n" .
            "Host: $checkHost\r\n" .
            "User-Agent: Mozilla/5.0\r\n" .
            "Connection: close\r\n\r\n";

        fwrite($fp, $req);

        $resp = '';
        while (!feof($fp)) {
            $ch = fread($fp, 8192);
            if ($ch === false) break;
            if ($ch === '') break;
            $resp .= $ch;
            if (strlen($resp) > 200000) break;
        }
        fclose($fp);

        $pos = strpos($resp, "\r\n\r\n");
        if ($pos === false) return null;

        $body = trim(substr($resp, $pos + 4));
        return ($body !== '') ? $body : null;
    }

    // Proxy layer
    private static function applyProxy($host, $port, $tls, $insecure, array $p) {
        $ph = (string)($p['host'] ?? '');
        $pp = (int)($p['port'] ?? 0);
        $type = $p['type'] ?? null;
        $auth = $p['auth'] ?? null;

        if ($ph === '' || $pp <= 0) {
            throw new Exception("invalid proxy host/port");
        }

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("tcp://{$ph}:{$pp}", $errno, $errstr, 15);
        if (!$fp) {
            throw new Exception("proxy connect failed: $errno $errstr");
        }

        stream_set_blocking($fp, true);
        stream_set_timeout($fp, 15);

        if ($type === CURLPROXY_SOCKS5_HOSTNAME) {
            self::proxySock($fp, $host, $port, is_string($auth) ? $auth : null);
        } elseif ($type === CURLPROXY_HTTP || (defined('CURLPROXY_HTTPS') && $type === CURLPROXY_HTTPS)) {
            // CONNECT tunnel 
            self::proxyHttp($fp, $host, $port, is_string($auth) ? $auth : null);
        } else {
            fclose($fp);
            throw new Exception("unsupported proxy type for WS");
        }

        if ($tls) {
            stream_context_set_option($fp, 'ssl', 'SNI_enabled', true);
            stream_context_set_option($fp, 'ssl', 'peer_name', $host);
            stream_context_set_option($fp, 'ssl', 'verify_peer', !$insecure);
            stream_context_set_option($fp, 'ssl', 'verify_peer_name', !$insecure);
            stream_context_set_option($fp, 'ssl', 'allow_self_signed', $insecure);

            $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($ok !== true) {
                fclose($fp);
                throw new Exception("TLS via proxy failed");
            }
        }

        return [$fp, ''];
    }

    private static function proxyHttp($fp, $host, $port, $auth) {
        $h = [
            "CONNECT {$host}:{$port} HTTP/1.1",
            "Host: {$host}:{$port}",
            "Proxy-Connection: Keep-Alive",
            "Connection: Keep-Alive",
        ];

        if ($auth) {
            $h[] = "Proxy-Authorization: Basic " . base64_encode($auth);
        }

        fwrite($fp, implode("\r\n", $h) . "\r\n\r\n");

        $s = '';
        while (!str_contains($s, "\r\n\r\n")) {
            $ch = fread($fp, 2048);
            if ($ch === false || $ch === '') break;
            $s .= $ch;
            if (strlen($s) > 65536) break;
        }

        if (!preg_match('#^HTTP/1\.[01]\s+200#', $s)) {
            fclose($fp);
            throw new Exception("proxy CONNECT failed:\n" . substr($s, 0, 4000));
        }
    }

    private static function proxySock($fp, $host, $port, $auth) {
        // greeting: methods
        // 0x00 = no auth, 
        // 0x02 = user/pass
        $methods = [0x00];
        if ($auth) $methods[] = 0x02;

        $pkt = chr(0x05) . chr(count($methods));
        foreach ($methods as $m) {
            $pkt .= chr($m);
        }
        fwrite($fp, $pkt);

        $r = fread($fp, 2);
        if ($r === false || strlen($r) !== 2 || ord($r[0]) !== 0x05) {
            fclose($fp);
            throw new Exception("SOCKS5: bad greeting response");
        }

        $m = ord($r[1]);
        if ($m === 0xFF) {
            fclose($fp);
            throw new Exception("SOCKS5: no acceptable auth");
        }

        // username/password auth (RFC1929)
        if ($m === 0x02) {
            [$u, $pw] = array_pad(explode(':', (string)$auth, 2), 2, '');
            $u = (string)$u;
            $pw = (string)$pw;

            if (strlen($u) > 255 || strlen($pw) > 255) {
                fclose($fp);
                throw new Exception("SOCKS5: auth too long");
            }

            $ap = chr(0x01) . chr(strlen($u)) . $u . chr(strlen($pw)) . $pw;
            fwrite($fp, $ap);

            $ar = fread($fp, 2);
            if ($ar === false || strlen($ar) !== 2 || ord($ar[0]) !== 0x01 || ord($ar[1]) !== 0x00) {
                fclose($fp);
                throw new Exception("SOCKS5: auth failed");
            }
        }

        // CONNECT request (ATYP=0x03 domain)
        if (strlen($host) > 255) {
            fclose($fp);
            throw new Exception("SOCKS5: host too long");
        }

        $req = chr(0x05) . chr(0x01) . chr(0x00) . chr(0x03) . chr(strlen($host)) . $host . pack('n', $port);
        fwrite($fp, $req);

        // VER REP RSV ATYP
        $h = fread($fp, 4);
        if ($h === false || strlen($h) !== 4 || ord($h[0]) !== 0x05) {
            fclose($fp);
            throw new Exception("SOCKS5: bad connect response");
        }

        $rep  = ord($h[1]);
        $atyp = ord($h[3]);

        if ($rep !== 0x00) {
            fclose($fp);
            throw new Exception("SOCKS5: connect failed rep=$rep");
        }

        if ($atyp === 0x01) { // IPv4
            fread($fp, 4);
        } elseif ($atyp === 0x03) { // DOMAIN
            $l = fread($fp, 1);
            if ($l === false || $l === '') {
                fclose($fp);
                throw new Exception("SOCKS5: bad domain len");
            }
            $ln = ord($l);
            if ($ln > 0) {
                fread($fp, $ln);
            }
        } elseif ($atyp === 0x04) { // IPv6
            fread($fp, 16);
        } else {
            fclose($fp);
            throw new Exception("SOCKS5: unknown ATYP");
        }

        fread($fp, 2); // port
    }

    // HTTP header read 
    public static function getHeader($fp, $prebuf = '') {
        $s = $prebuf;
        while (!str_contains($s, "\r\n\r\n")) {
            $ch = fread($fp, 2048);
            if ($ch === false || $ch === '') break;
            $s .= $ch;
            if (strlen($s) > 65536) break;
        }

        $p = strpos($s, "\r\n\r\n");
        if ($p === false) return [$s, ''];

        return [substr($s, 0, $p + 4), substr($s, $p + 4)];
    }

    // Frame IO helpers
    public static function readFr(array &$c, int $n) {
        $fp = $c['fp'];
        $buf = $c['buf'] ?? '';

        if (strlen($buf) >= $n) {
            $out = substr($buf, 0, $n);
            $c['buf'] = substr($buf, $n);
            return $out;
        }

        $out = $buf;
        $c['buf'] = '';

        while (strlen($out) < $n) {
            $ch = fread($fp, $n - strlen($out));
            if ($ch === false) return null;

            if ($ch === '') {
                $meta = stream_get_meta_data($fp);
                if (!empty($meta['eof'])) return null;
                if (!empty($meta['timed_out'])) return null;
                return null;
            }

            $out .= $ch;
        }

        return $out;
    }

    public static function sendFr($fp, $op, $pl) {
        $b1 = chr(0x80 | ($op & 0x0F));
        $len = strlen($pl);
        $maskBit = 0x80; // client->server MUST mask

        if ($len <= 125) {
            $hdr = $b1 . chr($maskBit | $len);
        } elseif ($len <= 65535) {
            $hdr = $b1 . chr($maskBit | 126) . pack('n', $len);
        } else {
            $hdr = $b1 . chr($maskBit | 127) . pack('N2', ($len >> 32) & 0xFFFFFFFF, $len & 0xFFFFFFFF);
        }

        $mk = random_bytes(4);
        fwrite($fp, $hdr . $mk . self::ApplyMask($pl, $mk));
    }

    
    public static function ApplyMask($d, $k) {
        $n = strlen($d);
        for ($i = 0; $i < $n; $i++) {
            $d[$i] = $d[$i] ^ $k[$i % 4];
        }
        return $d;
    }

    public static function Close(array &$c, $code = 1000, $reason = "closing") {
        $fp = $c['fp'] ?? null;
        if (is_resource($fp)) {
            self::sendFr($fp, 0x8, pack('n', $code) . $reason);
            fclose($fp);
        }

        $c['fp'] = null;
        $c['buf'] = '';
    }
}
