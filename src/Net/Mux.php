<?php

class Mux {

    private static function build(array $opt) {
        
        if ($opt['type'] === 'GET' && !empty($opt['data']) && is_array($opt['data'])) {
            $qs = http_build_query($opt['data']);
            if ($qs !== '') $opt['url'] .= (str_contains($opt['url'], '?') ? '&' : '?') . $qs;
        }

        if (empty($opt['url'])) return null;

        $opt['http2'] = $opt['http2'] ?? true;
        $opt['head'] = Net::applyHead($opt); 

        $ch = curl_init($opt['url']);
        if (!$ch) return null;

        # 3. PROXY
        if (!empty($opt['proxy'])) {
            $p = $opt['proxy'];
            
            if (is_string($p)) {
                $parsed = parse_url($p);
                $p = [
                    'host' => $parsed['host'] ?? '',
                    'port' => $parsed['port'] ?? 8080,
                    'type' => CURLPROXY_HTTP,
                    'auth' => isset($parsed['user']) ? $parsed['user'] . ':' . ($parsed['pass'] ?? '') : null
                ];
            }
            
            curl_setopt($ch, CURLOPT_PROXY, $p['host']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $p['port']);
            curl_setopt($ch, CURLOPT_PROXYTYPE, $p['type'] ?? CURLPROXY_HTTP);
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
                if (!Net::hasHeader($head, 'Accept')) $head[] = "Accept: application/json, text/javascript, */*";
            } else {
                if (!Net::hasHeader($head, 'Accept')) $head[] = "Accept: */*";
            }

            $actualProxy = $proxy ?? ($GLOBALS['_CTX']['proxy'] ?? null);

            $dns = []; 
            $connect = [];
            if (!empty($ip)) {
                $dom = parse_url($url)['host'] ?: '';
                $scheme = parse_url($url)['scheme'] ?? 'http';
                $port = parse_url($url)['port'] ?? ($scheme === 'https' ? 443 : 80);
                
                if ($dom !== '') {
                    if (!empty($actualProxy)) {
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
                    'http2'   => true 
                ])
            ];
        }

        return self::Exec($queue, 15); 
    }

    public static function K(array $calls) {
        $keys = array_keys($calls);
        $res = self::C(...array_values($calls));
        
        $response = [];
        foreach ($res as $idx => $html) {
            $key = $keys[$idx] ?? $idx;
            $response[$key] = $html;
        }
        
        return $response;
    }

}