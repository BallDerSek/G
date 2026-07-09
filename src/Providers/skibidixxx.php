<?php

class skibidixxx extends Provider {
    
    
    
    protected $baseUrl = "https://waryono.my.id/api";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        
        $s = json_decode(
            Net::S($this->baseUrl."/in.php", "POST", array_merge(["apikey" => $this->apiKey, "methods" => $method], $params), json: true) ?: ''
            , 1);
#var_dump($s);
        if (!is_array($s) || ($s["status"] ?? 0) != 1) {
            throw new Exception(is_array($s) ? ($s["request"] ?? 'unknown') : 'unknown');
        }

        return $s["request"];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(10);
            $r = json_decode(
                Net::S($this->baseUrl."/res.php", "GET", ["apikey" => $this->apiKey, "id"  => $jobId]) ?: ''
            , 1);
#var_dump($r);
            if (($r['status'] ?? 0) == 1) return $r['request'];

            if (!is_array($r) || Api::errType($q = ($r['request'] ?? 'unknown')) === 'ret') continue;
            
            throw new Exception($q);

        } while (time() - $start < 300);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** shortlink resolver */
    public function shortLink($link) {
        $params = ["url" => $link];
        return $this->run('shortlink', $params, true);
    }
    
    public function rss($data, $url) {
        
        return $this->run('rslider', array_merge($data, ['referer' => $url]), true);
        
    }
    
    public function bct($data, array $json) {
        
        $params = [
            'body' => $json,
            'type' => 'canvas'
        ];
    
        $sol = $this->run('bitcocaptcha', $params, true);
        
        if (isset($sol['fail'])) return $sol;
        $ans = $sol['done'];
        
        if (!is_string($ans) || !preg_match('/class:([^,]+), array:(\d+)/', $ans, $m)) return ['fail' => 777];
        
        return [
            'ans' => $m[1],
            'idx' => (int) $m[2]
        ];
    }

    public function getInfo(): bool{
        $maxRetry = 3;
        $r = null;
        for ($i = 0; $i < $maxRetry; $i++) {
            $r = json_decode(
                Net::S($this->baseUrl . "/balance.php","GET",["apikey" => $this->apiKey],json: true)?: ''
            , 1);
            
            if ($r !== null) break;
        }
        
        if ($r === null) return false;
        
        if (isset($r['error'])) {
            Logger::X('err', $r['error']);
            return false;
        }
        
        Logger::X('info', 'waryono: ' . ($r['balance'] ?? 'unknown'));
        return true;
    }
    
}

/*
class abdulq extends Provider {
    
    
    
    protected $baseUrl = "https://bypassallshortlinks.space";
    
    protected function get_api($method, array $params) {
        
    }
    
    protected function res_api($method, array $params) {
        
    }
    
    
    
    public function getInfo(): bool{
        $maxRetry = 3;
        $b = null;
        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            $b = json_decode(Net::S($this->baseUrl.'/api.php', 'POST', ['action' => 'balance', 'api_key' => $this->apiKey])?: '', 1);
            var_dump($b);
            if ($b !== null) break;
        }
        
        if ($b === null) return false;
        
        if (isset($b['error'])) {
            Logger::X('err', $b['error']);
            return false;
        }
        Logger::X('info', 'bas: ' . ($b['balance'] ?? 'unknown'));
        return true;
    }
    
}
*/


