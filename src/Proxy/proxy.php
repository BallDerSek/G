<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#LOADER
function proxyLoad() {
    #complicated checking
    $raw = trim(getenv('PROXY'));

    if ($raw === '') {
        proxyDisable();
        return;
    }

    $u = parse_url($raw);
    if (!$u || empty($u['scheme'])) {
        logx('err', "invalid PROXY");
        return;
    }

    $scheme = strtolower($u['scheme']);

    if (!empty($GLOBALS['_CTX']['proxy']) && ($GLOBALS['_CTX']['proxy']['src'] ?? '') === $raw) {
        if (($GLOBALS['_CTX']['proxy']['mode'] ?? '') === 'ssh') {
            if (proxyIsAlive()) return;
        } else {
            return;
        }
    }

    stopSSH();
    unset($GLOBALS['_CTX']['proxy_http_local']);

    if ($scheme === 'ssh') {
        $host = $u['host'] ?? '';
        $port = $u['port'] ?? 22;
        $user = $u['user'] ?? '';
        $pass = $u['pass'] ?? '';

        if ($host === '' || $user === '' || $pass === '') {
            logx('err', "invalid SSH");
            return;
        }

        $socksPort = setPort();
        $err = '';

        if (!setSSH($host, $port, $user, $pass, $socksPort, $err)) {
            logx('err', "ssh tunnel failed" . ($err ? (": $err") : ""));
            proxyDisable();
            return;
        }

        $GLOBALS['_CTX']['proxy'] = [
            'host' => '127.0.0.1',
            'port' => $socksPort,
            'type' => CURLPROXY_SOCKS5_HOSTNAME,
            'auth' => null,
            'src'  => $raw,
            'mode' => 'ssh',
        ];
        return;
    }

    // normal proxy
    if (empty($u['host']) || empty($u['port'])) {
        logx('err', "invalid PROXY");
        return;
    }

    $ptype = match($scheme) {
        'socks5', 'socks' => CURLPROXY_SOCKS5_HOSTNAME,
        'https' => (defined('CURLPROXY_HTTPS') ? CURLPROXY_HTTPS : CURLPROXY_HTTP),
        'http'  => CURLPROXY_HTTP,
        default => null,
    };

    if ($ptype === null) {
        logx('err', "unsupported scheme");
        return;
    }

    $auth = null;
    if (!empty($u['user'])) $auth = $u['user'] . ':' . ($u['pass'] ?? '');

    $GLOBALS['_CTX']['proxy'] = [
        'host' => $u['host'],
        'port' => $u['port'],
        'type' => $ptype,
        'auth' => $auth,
        'src'  => $raw,
        'mode' => 'direct',
    ];
}

function proxyDisable() {
    putenv("PROXY=");
    $_ENV['PROXY'] = '';
    stopSSH();
    unset($GLOBALS['_CTX']['proxy'], $GLOBALS['_CTX']['proxy_http_local']);
}

#ENSURE
function proxyIsAlive() {
    if (empty($GLOBALS['_CTX']['proxy'])) return false;
    $p = $GLOBALS['_CTX']['proxy'];

    // direct proxy dianggap alive
    if (($p['host'] ?? '') !== '127.0.0.1') return true;

    $port = (int)($p['port'] ?? 0);
    if ($port <= 0) return false;

    return getPort('127.0.0.1', $port, 250);
}

function proxyEnsure() {
    if (empty($GLOBALS['_CTX']['proxy'])) {
        proxyLoad();
        return;
    }

    $p = $GLOBALS['_CTX']['proxy'];
    if (($p['host'] ?? '') !== '127.0.0.1') return;

    if (proxyIsAlive()) return;

    // restart sekali
    proxyLoad();

    // kalau masih dead -> OFF
    if (!proxyIsAlive()) proxyDisable();
}