<?php

/** @class Proxy
 * @method ensure
     * @return void
 * @method load
     * @return void
 * @method _ssh
     * @param array $u
     * @param string $raw
     * @return void
 * @method _base
     * @param array $u
     * @param string $raw
     * @param string $scheme
     * @return void
 * @method setSSH
     * @param string $h
     * @param int $p
     * @param string $u
     * @param string $pa
     * @param int $localPort
     * @param string $err
     * @return bool
 * @method stopSSH
     * @return void
 * @method _unable
     * @return void
 * @method _enable
     * @return bool
 * @method setPort
     * @param string $host
     * @param int $port
     * @param int $ms
     * @return bool
 * @method getPort
     * @return int
 */
class Proxy {
    private static $ctx_key = 'proxy';
    private static $ssh_key = 'ssh_tunnel';

    public static function ensure() {
        if (empty($GLOBALS['_CTX'][self::$ctx_key])) {
            self::load();
            return;
        }

        $p = $GLOBALS['_CTX'][self::$ctx_key];
        
        if (($p['host'] ?? '') !== '127.0.0.1') return;

        if (!self::_enable()) {
            logx('warn', "SSH Tunnel lost connection");
            self::load();
            
            if (!self::_enable()) {
                logx('err', "Destroying SSH Tunnel");
                self::_unable();
            }
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
            return;
        }

        $scheme = strtolower($u['scheme']);

        if (!empty($GLOBALS['_CTX'][self::$ctx_key]) && ($GLOBALS['_CTX'][self::$ctx_key]['src'] ?? '') === $raw) {
            if (($GLOBALS['_CTX'][self::$ctx_key]['mode'] ?? '') === 'ssh') {
                if (self::_enable()) return;
            } else {
                return;
            }
        }

        self::stopSSH();
        unset($GLOBALS['_CTX']['proxy_http_local']);

        if ($scheme === 'ssh') {
            if (!getDeps(['ssh', 'sshpass'])) {
                logx('err', "Ssh or Sshpass missing");
                self::_unable();
                return;
            }
            self::_ssh($u, $raw);
        } else {
            self::_base($u, $raw, $scheme);
        }
    }

    private static function _ssh($u, $raw) {
        $host = $u['host'] ?? '';
        $port = $u['port'] ?? 22;
        $user = $u['user'] ?? '';
        $pass = $u['pass'] ?? '';

        if ($host === '' || $user === '' || $pass === '') {
            logx('err', "SSH credentials missing");
            return;
        }

        $socksPort = self::getPort();
        $err = '';

        if (!self::setSSH($host, $port, $user, $pass, $socksPort, $err)) {
            logx('err', "SSH failed" . ($err ? ": $err" : ""));
            self::_unable();
            return;
        }

        $GLOBALS['_CTX'][self::$ctx_key] = [
            'host' => '127.0.0.1',
            'port' => $socksPort,
            'type' => CURLPROXY_SOCKS5_HOSTNAME,
            'auth' => null,
            'src'  => $raw,
            'mode' => 'ssh',
        ];
    }

    private static function _base($u, $raw, $scheme) {
        if (empty($u['host']) || empty($u['port'])) return;

        $ptype = match($scheme) {
            'socks5', 'socks' => CURLPROXY_SOCKS5_HOSTNAME,
            'https' => (defined('CURLPROXY_HTTPS') ? CURLPROXY_HTTPS : CURLPROXY_HTTP),
            'http'  => CURLPROXY_HTTP,
            default => null,
        };

        if ($ptype === null) {
            logx('err', "Unsupported scheme: $scheme");
            return;
        }

        $auth = null;
        if (!empty($u['user'])) $auth = $u['user'] . ':' . ($u['pass'] ?? '');

        $GLOBALS['_CTX'][self::$ctx_key] = [
            'host' => $u['host'],
            'port' => $u['port'],
            'type' => $ptype,
            'auth' => $auth,
            'src'  => $raw,
            'mode' => 'direct',
        ];
    }

    private static function setSSH($h, $p, $u, $pa, $localPort, &$err = '') {
        $cmd = "sshpass -p " . escapeshellarg($pa) . " ssh -N -T " .
               "-D 127.0.0.1:{$localPort} -p {$p} " .
               
               "-o ConnectTimeout=15 " . 
               "-o ExitOnForwardFailure=yes " .
               "-o StrictHostKeyChecking=no " . 
               "-o UserKnownHostsFile=/dev/null " .
               "-o KbdInteractiveAuthentication=no " .
               "-o PreferredAuthentications=password " .
               "-o PasswordAuthentication=yes " .
               "-o PubkeyAuthentication=no " .
               "-o NumberOfPasswordPrompts=1 " .
               "-o ServerAliveInterval=10 " . 
               "-o ServerAliveCountMax=2 " .
               
               escapeshellarg($u.'@'.$h);
       
        $f = "nohup $cmd >/dev/null 2>&1 & echo $!";
        $pid = trim(shell_exec($f) ?: '');

        if ($pid <= 0) {
            $err = "Could not spawn ssh process";
            return false;
        }

        $GLOBALS['_CTX'][self::$ssh_key] = ['pid' => $pid, 'port' => $localPort];

        return self::setPort('127.0.0.1', $localPort, 6000);
    }

    public static function stopSSH() {
        $ctx = $GLOBALS['_CTX'][self::$ssh_key] ?? null;
        if (!$ctx) return;

        $pid = (int)($ctx['pid'] ?? 0);
        if ($pid > 0) {
            @shell_exec("kill $pid >/dev/null 2>&1");
            usleep(100000);
            @shell_exec("kill -9 $pid >/dev/null 2>&1");
        }
        unset($GLOBALS['_CTX'][self::$ssh_key]);
    }

    public static function _unable() {
        putenv("PROXY=");
        self::stopSSH();
        unset($GLOBALS['_CTX'][self::$ctx_key], $GLOBALS['_CTX']['proxy_http_local']);
    }

    public static function _enable() {
        if (empty($GLOBALS['_CTX'][self::$ctx_key])) return false;
        $p = $GLOBALS['_CTX'][self::$ctx_key];
        if ($p['mode'] !== 'ssh') return true;

        $port = (int)($p['port'] ?? 0);
        return ($port > 0) ? self::setPort('127.0.0.1', $port, 250) : false;
    }

    private static function setPort($host, $port, $ms) {
        $s = microtime(true);
        do {
            $fp = @fsockopen($host, $port, $errno, $errstr, 0.35);
            if ($fp) { fclose($fp); return true; }
            usleep(50000);
        } while ((microtime(true) - $s) * 1000 < $ms);
        return false;
    }

    private static function getPort() {
        for ($i=0; $i<100; $i++) {
            $p = random_int(20000, 40000);
            $sock = @stream_socket_server("tcp://127.0.0.1:$p");
            if ($sock) { fclose($sock); return $p; }
        }
        return 20001; // Fallback
    }
}