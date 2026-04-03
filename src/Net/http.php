<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

class Mux {

    private static function build(array $opt) {

        # GET
        if ($opt['type'] === 'GET' && !empty($opt['data']) && is_array($opt['data'])) {
            $qs = http_build_query($opt['data']);
            if ($qs !== '') {
                $opt['url'] .= (str_contains($opt['url'], '?') ? '&' : '?') . $qs;
            }
        }

        if (empty($opt['url'])) return null;

        $ch = curl_init($opt['url']);
        if (!$ch) return null;

        # PROXY
        if (!empty($opt['proxy'])) {
            $p = $opt['proxy'];

            curl_setopt($ch, CURLOPT_PROXY, $p['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $p['port']);
            curl_setopt($ch, CURLOPT_PROXYTYPE, $p['type']);

            if (!empty($p['auth'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $p['auth']);
            }

            $isHttps = stripos($opt['url'], 'https://') === 0;
            if ($p['type'] === CURLPROXY_HTTP || (defined('CURLPROXY_HTTPS') && $p['type'] === CURLPROXY_HTTPS)) {
                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, $isHttps);
            }

        } else {
            Net::applyProxy($ch, $opt['url']); 
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $opt['follow'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => $opt['ua'],
            CURLOPT_REFERER => $opt['ref'],
            CURLOPT_HTTPHEADER => $opt['head'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '',
        ]);

        # COOKIE
        if (!empty($opt['cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $opt['cookie']);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $opt['cookie']);
        }

        # DNS / CONNECT
        if (!empty($opt['connect'])) {
            curl_setopt($ch, CURLOPT_CONNECT_TO, $opt['connect']);
        }

        if (!empty($opt['resolve'])) {
            curl_setopt($ch, CURLOPT_RESOLVE, $opt['resolve']);
        }

        # METHOD
        if ($opt['type'] !== 'GET') {

            if ($opt['type'] === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $opt['type']);
            }

            if (isset($opt['data'])) {
                $payload = is_array($opt['data'])
                    ? http_build_query($opt['data'])
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

            $id = (int)$ch;

            $map[$id] = $key;
            $active[$id] = $ch;

            curl_multi_add_handle($mh, $ch);
        };

        # INIT
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
     * [url, type, data, cookie, head, ref, ua, ip, follow, proxy]
     */
    public static function C(array ...$calls) {

        $queue = [];

        foreach ($calls as $key => $args) {

            [
                $url,
                $type,
                $data,
                $cookie,
                $head,
                $ref,
                $ua,
                $ip,
                $follow,
                $proxy
            ] = array_pad($args, 10, null);

            $type = strtoupper($type ?? 'GET');
            $head = $head ?? [];

            $head[] = "Accept: */*";

            if (in_array($type, ['POST','PUT','PATCH'], true)) {
                $head[] = "Content-Type: application/x-www-form-urlencoded";
            }

            $dns = [];
            $connect = [];

            if (!empty($ip)) {
                $dom = parse_url($url, PHP_URL_HOST) ?: '';
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
                    'url' => $url,
                    'type' => $type,
                    'data' => $data,
                    'cookie' => $cookie,
                    'head' => $head,
                    'ref' => $ref,
                    'ua' => $ua ?: 'Mozilla/5.0',
                    'follow' => $follow ?? true,
                    'resolve' => $dns,
                    'connect' => $connect,
                    'proxy' => $proxy,
                ])
            ];
        }

        return self::Exec($queue, 20);
    }
}

class Net {

    public static function applyProxy($ch, $url) {
        proxyEnsure();
        if (!empty($GLOBALS['_CTX']['proxy'])) {
            #logx('info', 'proxied', true, true);
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

    private static function Http(array $opt, $in = false, $fresh = false) {
        
        #GET
        $type = strtoupper($opt['type']);
        if ($type === 'GET' && !empty($opt['data']) && is_array($opt['data'])) {
            $qs = http_build_query($opt['data']);
            if ($qs !== '') {
                $opt['url'] .= (str_contains($opt['url'], '?') ? '&' : '?') . $qs;
            }
        }

        #URL
        if (empty($opt['url']) || !is_string($opt['url'])) {
            logx('err', 'invalid url'); return null;
        }
        $ch = curl_init($opt['url']);
        #var_dump($opt['url']);
        if (!$ch) { logx('err', 'init failed'); return null; }

        #PROXY
        self::applyProxy($ch, $opt['url']);

        #HTTP2
        $insecure = $in;
        $httpVer = CURL_HTTP_VERSION_1_1;
        if (!empty($opt['http2']) && !$insecure) {
            $httpVer = CURL_HTTP_VERSION_2TLS;
        }

        #INIT
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $opt['follow'],
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $opt['ua'],
            CURLOPT_REFERER => $opt['ref'],
            CURLOPT_HTTPHEADER => $opt['head'],
            CURLOPT_SSL_VERIFYPEER => !$insecure,
            CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
            CURLOPT_HTTP_VERSION => $httpVer,
            CURLOPT_FORBID_REUSE => $fresh,
            CURLOPT_FRESH_CONNECT => $fresh,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME  => 30,
            CURLOPT_ENCODING => '',
        ]);

        #VERBOSE
        $logFile = null;
        if (!empty($opt['verbose'])) {
            $logFile = fopen(LIBDIR . "/verbose.log", "a");
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $logFile);
        }
        
        #DNS_CONNECT
        if (!empty($opt['connect'])) {
            curl_setopt($ch, CURLOPT_CONNECT_TO, $opt['connect']);
        }
        
        #DNS_RESOLVE
        if (!empty($opt['resolve'])) {
            curl_setopt($ch, CURLOPT_RESOLVE, $opt['resolve']);
        }

        #HEADERS
        $headr = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $line) use (&$headr) {
            $len = strlen($line); $line = trim($line);
            if ($line === '' || stripos($line, 'HTTP/') === 0) return $len;
            if (!str_contains($line, ':')) return $len;
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $headr[strtolower($k)][] = $v; return $len;
        });

        #COOKIE
        if (!empty($opt['cookie'])) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $opt['cookie']);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $opt['cookie']);
        }

        #METHOD
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
                #var_dump($payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        } 

        #EXEC
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
                } usleep(random_int(25, 50) * 10000);
            } throw new Exception("failed");
        } catch (Throwable $e) {
            logx('info', "{$e->getMessage()}", true, true);
            return null;
        } finally { 
            if (is_resource($logFile)) { fclose($logFile); }
            $ch = null; #unset($ch); #curl_close($ch)
        }
        
    }

    public static function C($url, $type, $data = null, $cookie = null, array $head = [], $reff = '', $ua = 'Mozilla/5.0', $d = false, $v = false, $ip = null, $foll = true, $ins = false, $f= false) {
        
        $dns = [];
        $connect = [];
        if (!empty($ip)) {
            $dom = parse_url($url, PHP_URL_HOST);
            if (!empty($GLOBALS['_CTX']['proxy'])) {
                $connect = ["$dom:443:$ip:443"];
            } else {
                $dns = ["$dom:80:$ip", "$dom:443:$ip"];
            }
        }
        $head[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
        if (in_array($type, ['POST','PUT','PATCH'], true)) {
            $head[] = "Content-Type: application/x-www-form-urlencoded";
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

    public static function X($url, $type, $data = null, $cookie = null, array $head = [], $reff = '', $ua = 'Mozilla/5.0', $json = false, $foll = true, $ip = null, $ins = false) {
                
        $dns = [];
        $connect = [];
        if (!empty($ip)) {
            $dom = parse_url($url, PHP_URL_HOST);
            if (!empty($GLOBALS['_CTX']['proxy'])) {
                $connect = ["$dom:443:$ip:443"];
            } else {
                $dns = ["$dom:80:$ip", "$dom:443:$ip"];
            }
        }
        
        if ($json && in_array($type, ['POST','PUT','PATCH'], true)) {
            $head[] = 'Content-Type: application/json';
            $head[] = 'Accept: application/json, text/javascript';
        } else {
            $head[] = 'Accept: */*';
            $head[] = 'Content-Type: application/x-www-form-urlencoded';
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
            'http2' => true,
            #'debug' => $d,
        ], $ins, false);
    }
}







function headers($o = '', $r = '', $h = '', array $cookie = [], $ua_param = '', $is_ajax = false) {
    global $userAgent, $uagent;
    $ua = trim($userAgent ?: $uagent ?: $ua_param ?: '');

    $head = [];

    // --- User Agent & Sec-CH-UA ---
    if ($ua !== '') {
        $is_A = (stripos($ua, 'Android') !== false || stripos($ua, 'Mobile') !== false);
        $_p = (stripos($ua, 'Android') !== false) ? "Android" : ((stripos($ua, 'Mac') !== false) ? "macOS" : "Windows");
        
        preg_match('/Chrome\/(\d+)/', $ua, $m);
        $v = $m[1] ?? '144';

        $head[] = 'Sec-CH-UA: "Not(A:Brand";v="8", "Chromium";v="'.$v.'", "Brave";v="'.$v.'"';
        $head[] = 'Sec-CH-UA-Mobile: '.($is_A ? '?1' : '?0');
        $head[] = 'Sec-CH-UA-Platform: "'.$_p.'"';
    }

    if (!empty($r)) {
        $head[] = "Referer: $r";
    }

    $lang = function_exists('LANGUAGE') ? LANGUAGE() : 'id-ID,id;q=0.9';
    $head[] = "Accept-Language: $lang";

    // --- Logic Sec-Fetch & Origin ---
    if (!empty($o)) {
        $origin_clean = rtrim($o, '/');
        $o_host = parse_url($origin_clean, PHP_URL_HOST);
        $r_host = !empty($r) ? parse_url($r, PHP_URL_HOST) : '';

        if ($is_ajax) $head[] = "Origin: $origin_clean";
        
        $site = (!empty($r_host) && $r_host !== $h) ? "cross-site" : "same-origin";

        if ($is_ajax) {
            $head[] = "Sec-Fetch-Dest: empty";
            $head[] = "Sec-Fetch-Mode: cors";
        } else {
            $head[] = "Sec-Fetch-Dest: document";
            $head[] = "Sec-Fetch-Mode: navigate";
            $head[] = "Sec-Fetch-User: ?1";
            $head[] = "Upgrade-Insecure-Requests: 1";
        }
        $head[] = "Sec-Fetch-Site: $site";
    }

    if (!empty($h)) $head[] = "Host: $h";

    // --- Cookie Logic ---
    if (!empty($cookie)) {
        $c = [];
        foreach ($cookie as $k => $v) { $c[] = "$k=$v"; }
        $head[] = "Cookie: " . implode('; ', $c);
    }

    return $head;
}