<?php

class rsResponse {
    use WorkDir;
    
    /*
    what a different between other?
    dont know what, but this class is using nodeJs pipeline.
    as the js is valid, the map will be as it given.
    especially for icon type (i recommend this method).
    dont forget to install nodejs and synchrony deobfuscator
    */
    
    private ?string $uagent;
    private ?string $host;
    
    public function __construct(?string $ua, ?string $host, ?string $mail) {
        $this->uagent = $ua;
        $this->host = $host;
        $this->workDir = $this->setupWorkDir('rscaptcha', $host, $mail);
    }
    
    public function exec(array $data, $x, $y) {
        
        $html = $data['html'];
        $jsContent = $data['js'];
        
        $nod = getDeps('nodejs');
        $npm = getDeps('npm');
        $syn = getDeps('synchrony@npm');
        
        $token = null; 
        
        if (in_array(false, [$nod, $npm, $syn], true)) {
            $this->rmdir($this->workDir);
            return $this->fallback($x, $y, $html);
        }
        
        #$hasil = $this->_dump($jsContent);
        $i = $this->workDir . '/i.js';
        $o = $this->workDir . '/o.js';
        $hasil = SolveUtils::dumpJs($jsContent, $i);
        if ($hasil && is_file($i)) exec("synchrony " . escapeshellarg($i) . " -o " . escapeshellarg($o) . " >/dev/null 2>&1");
        
        if ($hasil && is_file($o)) $token = $this->_token($o, $x, $y, $this->uagent);
        
        $this->rmdir($this->workDir);
        
        return $token ?: $this->fallback($x, $y, $html);
    }

    private function _token($_js, $x, $y, $ua) {
        if (!file_exists($_js)) return false;
        $jsContent = _get($_js);

        /** Dumbass RSSHORT with Auto-Scaling */
        $startPos = strpos($jsContent, 'btoa(');
        if ($startPos === false) return false;
        
        $start = $startPos + 5;
        $end = strpos($jsContent, ')', $start);
        $btoaBody = substr($jsContent, $start, $end - $start);
        $rawVars = explode(',', str_replace(['+', "'", '"', ' ', "\n", "\r"], '', $btoaBody));
        $platform = (stripos($ua ?? '', 'Windows') !== false) ? 'Win32' : 'Linux x86_64';
        
        $payloadArray = [];
        $timestamp = time();
        foreach ($rawVars as $v) {
            $v = trim($v);
            $qv = preg_quote($v, '/');
            
            if (preg_match('/'. $qv .'\s*=\s*Math\.round\(.*?\.pageX\s*-\s*.*?\)/', $jsContent)) {
                $payloadArray[] = (int)$x;
            } elseif (preg_match('/'. $qv .'\s*=\s*Math\.round\(.*?\.pageY\s*-\s*.*?\)/', $jsContent)) {
                $payloadArray[] = (int)$y;
            } elseif (preg_match('/'. $qv .'\s*=\s*~~\(Date\.now/', $jsContent)) {
                $payloadArray[] = (int)$timestamp;
            } elseif (preg_match('/'. $qv .'\s*=\s*screen\.width/', $jsContent)) {
                $payloadArray[] = 1440; 
            } elseif (preg_match('/'. $qv .'\s*=\s*screen\.height/', $jsContent)) {
                $payloadArray[] = 900;
            } elseif (preg_match('/'. $qv .'\s*=\s*navigator\.platform/', $jsContent)) {
                $payloadArray[] = $platform;
            } elseif (preg_match('/'. $qv .'\s*=\s*Math\.round\(window\.pageXOffset\)/', $jsContent)) {
                $payloadArray[] = 0;
            } elseif (preg_match('/'. $qv .'\s*=\s*Math\.round\(window\.pageYOffset\)/', $jsContent)) {
                $payloadArray[] = rand(0, 30);
            } elseif (preg_match('/'. $qv .'\s*=\s*navigator\.onLine/', $jsContent)) {
                $payloadArray[] = 1;
            } elseif (preg_match('/'. $qv .'\s*=\s*document\.hasFocus\(\)/', $jsContent)) {
                $payloadArray[] = 1;
            } else {
                if (strpos($v, 'Depth') !== false) $payloadArray[] = 24;
                else $payloadArray[] = rand(1, 10);
            }
        }
        return base64_encode(implode(',', $payloadArray));
    }
    
    private function fallback($x, $y, $html) {
        $rss = new rsBuilders();
        return $rss->build($y, $x, $html);
    }
    
}

