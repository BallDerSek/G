<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

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

        proxyEnsure();
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

    public static function Recv(array &$c): ?array
    {
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
        proxyEnsure();
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

    public static function ApplyMask(string $d, string $k): string
    {
        $n = strlen($d);
        if ($n === 0) return '';

        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[$i] = $d[$i] ^ $k[$i & 3];
        }

        return implode('', $out);
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

// helper headers for WS 
function ws_headers(string $origin = '', string $ua = 'Mozilla/5.0', string $lang = 'id-ID,id;q=0.8', array $cookie = []): array {
  $h = [
    "User-Agent: $ua",
    "Accept-Language: $lang",
  ];
  if ($origin !== '') $h[] = "Origin: " . rtrim($origin, '/');

  if (!empty($cookie)) {
    $c = [];
    foreach ($cookie as $k => $v) $c[] = "$k=$v";
    $h[] = "Cookie: " . implode('; ', $c);
  }

  // $h[] = "Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits";
  return $h;
} 





