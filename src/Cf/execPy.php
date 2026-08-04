<?php

final class execPy {
    private string $python = 'python3';
    private string $scriptPath;
    private ?string $cookie;
    private ?string $uagent;
    private string $lockFile;

    public function __construct($ck = null, $ua = null) {
        
        $this->cookie = $ck;
        $this->uagent = $ua;
        $this->lockFile = sys_get_temp_dir() . '/seledroid_global.lock';
        
        if (($py = realpath(LIBDIR . '/exec/execPy.py')) === false) die(Logger::X('err', "execPy: file not found"));
        
        $this->scriptPath = $py;
        
    }

    public function run($type, $url = null, $act = null) {
        
        if (!getDeps('seledroid@py')) die(Logger::X('err', "seledroid@py missing"));
        
        $m = strtolower($type);
        if (!in_array($m, ['turnstile', 'interstitial', 'recaptcha3', 'check', 'ua'], true)) return null;
        if (!in_array($m, ['check', 'ua'], true) && empty($url)) return null;

        $sync = ($this->cookie !== null && $this->uagent !== null);
        $out = $this->exec($m, $url, $sync, $act);

        if (empty(trim($out))) return null;

        $json = json_decode($out, true);
        
        if (!is_array($json) || isset($json['error'])) {
            if (isset($json['error'])) Logger::X('err', "execPy: " . $json['error']);
            return null;
        }

        switch ($m) {
            case 'check': return $json; 
            case 'turnstile':
            case 'recaptcha3':
                return (strlen($json['token'] ?? '') > 20) ? (string)$json['token'] : null;
            case 'interstitial':
                return [
                    'token' => (string)$json['cf_clearance'],
                    'ua' => (string)$json['user_agent']
                ];
        }
        return null;
    }
    
    private function exec($m, $url, $sync, $act = null) {
        $py = escapeshellcmd($this->python);
        $sc = escapeshellarg($this->scriptPath);
        $cmd = "{$py} {$sc} " . escapeshellarg($m);
        
        if (!in_array($m, ['check','ua'], true)) $cmd .= " " . escapeshellarg($url);
        if ($m === 'recaptcha3') $cmd .= " " . escapeshellarg($act);
        
        if ($sync) {
            $cmd .= " " . escapeshellarg($this->uagent);
            if (in_array($m, ['turnstile','recaptcha3', 'interstitial'], true)) {
                $cmd .= " " . escapeshellarg($this->cookie);
            }
        }
        #var_dump($cmd); die;
        $fp = fopen($this->lockFile, "w+");
        if ($fp && flock($fp, LOCK_EX)) {
            $out = shell_exec($cmd); 
            flock($fp, LOCK_UN);
            fclose($fp);
            return $out;
        }
        if ($fp) fclose($fp);
        return null;
    }

}
