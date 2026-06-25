<?php

class Proxy {
    private static $ctx_key = 'proxy';

    public static function ensure() {
        if (empty($GLOBALS['_CTX'][self::$ctx_key])) {
            self::load();
            return;
        }
    }

    public static function load() {
        $raw = trim(getenv('PROXY') ?: '');

        if ($raw === '') {
            self::_unable();
            return;
        }

        $u = parse_url($raw);

        if (!$u || empty($u['scheme'])) {
            logx('err', "Invalid PROXY format");
            self::_unable();
            return;
        }

        $scheme = strtolower($u['scheme']);

        self::_base($u, $raw, $scheme);
    }

    private static function _base($u, $raw, $scheme) {
        if (empty($u['host']) || empty($u['port'])) {
            logx('err', "Invalid proxy host/port");
            return;
        }

        $ptype = match($scheme) {
            'socks5', 'socks' => CURLPROXY_SOCKS5_HOSTNAME,
            'https' => (defined('CURLPROXY_HTTPS') ? CURLPROXY_HTTPS : CURLPROXY_HTTP),
            'http'  => CURLPROXY_HTTP,
            default => null,
        };

        if ($ptype === null) {
            logx('err', "Unsupported proxy scheme: $scheme");
            return;
        }

        $auth = null;
        if (!empty($u['user'])) {
            $auth = $u['user'] . ':' . ($u['pass'] ?? '');
        }

        $GLOBALS['_CTX'][self::$ctx_key] = [
            'host' => $u['host'],
            'port' => $u['port'],
            'type' => $ptype,
            'auth' => $auth,
            'src'  => $raw,
            'mode' => 'direct',
        ];
    }

    public static function _unable() {
        unset($GLOBALS['_CTX'][self::$ctx_key], $GLOBALS['_CTX']['proxy_http_local']);
        putenv("PROXY=");
    }
}
