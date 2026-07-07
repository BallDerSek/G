<?php

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
        
        $useHints = true;
        foreach ($he_manual as $k => $v) {
            if (stripos($v, 'detail-hints') !== false && stripos($v, 'false') !== false) {
                $useHints = false;
                unset($he_manual[$k]); 
                break;
            }
        }
        
        $head = [];
        
        $host_val = parse_url($url)['host'];
        $manualHost = false;
        foreach ($he_manual as $h) {
            if (stripos($h, 'Host:') === 0) {
                $manualHost = true;
                break;
            }
        }
        if (!$manualHost) $head[] = "Host: " . $host_val;
        
        if (!self::hasHeader($he_manual, 'Accept-Encoding')) {
            $head[] = "Accept-Encoding: gzip, deflate";
        }
        
        $lang = function_exists('LANGUAGE') ? LANGUAGE() : 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7';
        $head[] = "Accept-Language: $lang";
        
        if ($ua !== '') {
            if ($useHints) {
                $is_mobile = (stripos($ua, 'Android') !== false || stripos($ua, 'Mobile') !== false);
                $platform = (stripos($ua, 'Android') !== false) ? "Android" : "Windows";
                preg_match('/Chrome\/(\d+)/', $ua, $m);
                $v_chrome = $m[1] ?? '122';
                
                $head[] = 'Sec-CH-UA: "Google Chrome";v="' . $v_chrome . '", "Chromium";v="' . $v_chrome . '", "Not?A_Brand";v="99"';
                $head[] = 'Sec-CH-UA-Mobile: ' . ($is_mobile ? '?1' : '?0');
                $head[] = 'Sec-CH-UA-Platform: "' . $platform . '"';
            }
            $head[] = "User-Agent: $ua";
        }
        
        if (!empty($opt['fresh'])) {
            $head[] = "Cache-Control: no-cache, no-store, must-revalidate";
            $head[] = "Pragma: no-cache";
            $head[] = "Expires: 0";
        } else {
            $head[] = "Cache-Control: max-age=0";
        }
        
        if (!self::hasHeader($he_manual, 'Connection')) {
            $head[] = "Connection: keep-alive";
        }
        
        if (!$ajx && $useHints) {
            $head[] = "Upgrade-Insecure-Requests: 1";
        }
        
        if ($useHints) {
            $head[] = "Sec-GPC: 1";
        }
        
        if ($useHints) {
            $head[] = "Priority: u=0, i";
        }
        
        $he_cookie = null;
        foreach ($he_manual as $h) {
            $h = trim($h);
            if ($h === '' || stripos($h, 'detail-hints') !== false) continue; 
            
            if (stripos($h, 'Cookie:') === 0) {
                $he_cookie = $h;
                continue;
            }
            if (stripos($h, 'Host:') === 0) continue;
            if (stripos($h, 'Accept:') === 0) continue;
            if (stripos($h, 'Accept-Encoding:') === 0) continue;
            if (stripos($h, 'Accept-Language:') === 0) continue;
            if (stripos($h, 'Cache-Control:') === 0) continue;
            if (stripos($h, 'Connection:') === 0) continue;
            if (stripos($h, 'User-Agent:') === 0) continue;
            
            $head[] = $h;
        }
        
        if ($useHints) {
            $he_fetchs = "none";
            if (!empty($ref)) {
                $he_t = parse_url($url)['host'] ?? '';
                $he_r = parse_url($ref)['host'] ?? '';
                $he_fetchs = ($he_t === $he_r) ? "same-origin" : "cross-site";
            }
            $head[] = "Sec-Fetch-Site: $he_fetchs";
            $head[] = "Sec-Fetch-Mode: " . ($ajx ? "cors" : "navigate");
            if (!$ajx) $head[] = "Sec-Fetch-User: ?1";
            $head[] = "Sec-Fetch-Dest: " . ($ajx ? "empty" : "document");
        }

        if (!empty($ref)) {
            $head[] = "Referer: $ref";
        
            $method = strtoupper($opt['type']);
            if (
                ($ajx || in_array($method, ['POST','PUT','PATCH','DELETE'], true))
                && !self::hasHeader($head, 'Origin')
                && !self::hasHeader($he_manual, 'Origin')
            ) {
                $u = parse_url($ref);
                $origin = $u['scheme'].'://'.$u['host'];
                if (!empty($u['port'])) {
                    $origin .= ':'.$u['port'];
                }
                $head[] = "Origin: $origin";
            }
        }
        
        if ($he_cookie) $head[] = $he_cookie;
        
        $head[] = "Expect:";
        
        return $head;
    }
    
    public static function hasHeader(array $he, $name) {
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
            Logger::X('err', 'invalid url'); return null;
        }

        # HEADERS
        $opt['head'] = self::applyHead($opt);
        
        $ch = curl_init($opt['url']);
        if (!$ch) { Logger::X('err', 'init failed'); return null; }

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
            CURLOPT_TIMEOUT => $opt['timeout'] ?? 30,
            CURLOPT_HTTPHEADER => $opt['head'], 
            #CURLOPT_REFERER => $opt['ref'],
            CURLOPT_SSL_VERIFYPEER => !$insecure,
            CURLOPT_SSL_VERIFYHOST => $insecure ? 0 : 2,
            CURLOPT_HTTP_VERSION => $httpVer,
            CURLOPT_FORBID_REUSE => $fresh,
            CURLOPT_FRESH_CONNECT => $fresh,
            #CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROXY_SSL_VERIFYPEER => false,
            CURLOPT_PROXY_SSL_VERIFYHOST => 0,
            CURLOPT_HTTP09_ALLOWED => true,
            
            CURLOPT_SSLVERSION => CURL_SSLVERSION_DEFAULT,
            #CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3,
            CURLOPT_SSL_ENABLE_ALPN => true,
            CURLOPT_SSL_ENABLE_NPN => true,
            #CURLOPT_SSL_CIPHER_LIST => 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-RSA-AES128-SHA:ECDHE-RSA-AES256-SHA',
            CURLOPT_SSL_OPTIONS => CURLSSLOPT_NO_REVOKE | CURLSSLOPT_NO_PARTIALCHAIN,
            CURLOPT_TCP_FASTOPEN => true,
            
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
        #print_r($opt['head']);
        
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
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        } 

        try {
            $proxyFailCount = 0; 
            for ($attempt = 0; $attempt < 10; $attempt++) {
                #Logger::X('info', "ATTEMPT " . ($attempt+1) . $opt['url']);
                $body = curl_exec($ch);
                $info = curl_getinfo($ch);
                $errno = curl_errno($ch);
                $err = curl_error($ch);
                
/*
var_dump($info);
var_dump($body);
var_dump($errno);
var_dump($err);
*/
                
                if ($body !== false) {
                    if (($info['http_code'] ?? 0) === 407) {
                        Logger::X('err', "Proxy Auth Failed (407)");
                        return 99; 
                    }
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
                
                $isUsingProxy = !empty($GLOBALS['_CTX']['proxy']) && empty($opt['no_proxy']);
                #Logger::X('info', " => err ($errno):$err");
                if ($isUsingProxy) {
                    $proxyFatalErrors = [7, 52, 56, 97];
                    if (in_array($errno, $proxyFatalErrors, true)) {
                        $proxyFailCount++;
                        
                        if ($proxyFailCount >= 7) {
                            Logger::X('warn', "\rUnhealthy Proxy ($errno)");
                            return 99; 
                        }
                        
                        usleep(random_int(30, 60) * 10000);
                        continue; 
                    }
                }
                
                if ($attempt > 0 && in_array($errno, [56, 92, 97], true)) {
                    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                }
                
                $retryCodes = [7, 52, 28, 35, 92, 56];
                if (!in_array($errno, $retryCodes, true) || $attempt === 9) {
                    throw new Exception("Net($errno): $err");
                }
                
                _sle(1);
            } 
            throw new Exception("unstable connection");
        } catch (Throwable $e) {
            _clr();
            Logger::X('info', " \r {$e->getMessage()}", true, true);
            return null;
        
        } finally { 
            if (is_resource($logFile)) fclose($logFile);
            $ch = null;
        }


    }

    public static function C($url, $type, $data = null, $cookie = null, array $head = [], $reff = '', $ua = 'Mozilla/5.0', $d = false, $v = false, $ip = null, $foll = true, $ins = false, $f= false) {
        $dns = []; 
        $connect = [];
        if (!empty($ip)) {
            $dom = parse_url($url)['host'] ?? '';
            $scheme = parse_url($url)['scheme'] ?? 'http';
            $port = parse_url($url)['port'] ?? ($scheme === 'https' ? 443 : 80);
            if ($dom !== '') {
                if (!empty($GLOBALS['_CTX']['proxy'])) {
                    $connect = ["$dom:$port:$ip:$port"];
                    if ($port === 443) $connect[] = "$dom:80:$ip:80";
                    if ($port === 80)  $connect[] = "$dom:443:$ip:443";
                } else {
                    $dns = ["$dom:80:$ip", "$dom:443:$ip"];
                    if ($port !== 80 && $port !== 443) {
                        $dns[] = "$dom:$port:$ip";
                    }
                }
            }
        }
        
        if (!self::hasHeader($head, 'Accept')) {
            $head[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8";
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
        $dns = []; 
        $connect = [];
        if (!empty($ip)) {
            $dom = parse_url($url)['host'] ?? '';
            $scheme = parse_url($url)['scheme'] ?? 'http';
            $port = parse_url($url)['port'] ?? ($scheme === 'https' ? 443 : 80);
            if ($dom !== '') {
                if (!empty($GLOBALS['_CTX']['proxy'])) {
                    $connect = ["$dom:$port:$ip:$port"];
                    if ($port === 443) $connect[] = "$dom:80:$ip:80";
                    if ($port === 80)  $connect[] = "$dom:443:$ip:443";
                } else {
                    $dns = ["$dom:80:$ip", "$dom:443:$ip"];
                    if ($port !== 80 && $port !== 443) {
                        $dns[] = "$dom:$port:$ip";
                    }
                }
            }
        }
        
        if ($type === 'GET') {
            if (!self::hasHeader($head, 'Accept')) {
                $head[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8";
            }
        } elseif ($json && in_array($type, ['POST','PUT','PATCH'], true)) {
            if (!self::hasHeader($head, 'Accept')) $head[] = 'Accept: application/json, text/javascript';
            if (!self::hasHeader($head, 'Content-Type')) $head[] = 'Content-Type: application/json';
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
        
        if (!self::hasHeader($head, 'Connection')) $head[] = "Connection: keep-alive";
        if ($json && !self::hasHeader($head, 'Content-Type')) $head[] = "Content-Type: application/json";
        
        $res = self::Http([
            'url' => $url,
            'type' => $type,
            'data' => $data,
            'head' => $head,
            'isJson' => $json,
            'follow' => true,
            'verbose' => false,
            'timeout' => 120,
            'speed' => 10,
            'no_proxy' => true
        ], false, true);
        
        return $res;
    }

}