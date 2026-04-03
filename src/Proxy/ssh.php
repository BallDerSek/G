<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

#SSH TUNNEL
function setSSH($h, $p, $u, $pa, $localPort, &$err = '') {
    $cmd =
        "sshpass -p " . escapeshellarg($pa) . " ssh -N -T " .
        "-D 127.0.0.1:{$localPort} " .
        "-p {$p} " .
        "-o ConnectTimeout=15 " .
        "-o ExitOnForwardFailure=yes " .
        "-o StrictHostKeyChecking=no " .
        "-o UserKnownHostsFile=/dev/null " .
        "-o KbdInteractiveAuthentication=no " .
        "-o PasswordAuthentication=yes " .
        "-o PreferredAuthentications=password " .
        "-o PubkeyAuthentication=no " .
        "-o NumberOfPasswordPrompts=1 " .
        "-o ServerAliveInterval=10 " .
        "-o ServerAliveCountMax=2 " .
        escapeshellarg($u.'@'.$h);

    // background
    $f = "nohup $cmd >/dev/null 2>&1 & echo $!";
    $pid = trim(shell_exec($f));
    if ($pid <= 0) {
        $err = "failed to spawn ssh";
        return false;
    }

    $GLOBALS['_CTX']['ssh_tunnel'] = ['pid' => $pid, 'port' => $localPort];

    if (!getPort('127.0.0.1', $localPort, 6000)) {
        stopSSH();
        $err = "can't open: 127.0.0.1:$localPort";
        return false;
    }

    return true;
}

function stopSSH() {
    $ctx = $GLOBALS['_CTX']['ssh_tunnel'] ?? null;
    if (!$ctx) return;

    $pid = $ctx['pid'] ?? 0;
    if ($pid > 0) {
        @shell_exec("kill $pid >/dev/null 2>&1");
        usleep(150000);
        @shell_exec("kill -9 $pid >/dev/null 2>&1");
    }
    unset($GLOBALS['_CTX']['ssh_tunnel']);
}

// =========================
// HELPERS
// =========================
function getPort($host, $port, $ms) {
    $s = microtime(true);
    do {
        $fp = @fsockopen($host, $port, $errno, $errstr, 0.35);
        if ($fp) { fclose($fp); return true; }
        usleep(80000);
    } while ((microtime(true) - $s) * 1000 < $ms);
    return false;
}

function setPort($mi=20000, $mx=40000) {
    for ($i=0; $i<300; $i++) {
        $p = random_int($mi, $mx);
        $sock = @stream_socket_server("tcp://127.0.0.1:$p", $errno, $errstr);
        if ($sock) { fclose($sock); return $p; }
        usleep(2000);
    }
    throw new RuntimeException("no free port");
}