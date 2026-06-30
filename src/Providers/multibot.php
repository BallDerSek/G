<?php

class multibot extends Provider {
    
    
    
    protected $baseUrl = "https://api.multibot.in";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $s = json_decode(
            Net::S($this->baseUrl."/in.php", "POST", array_merge(["key" => $this->apiKey, "method" => $method, "json" => "1"], $params)) ?: ''
            , 1);
#print_r($params);
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
            $r = 
                Net::S($this->baseUrl."/res.php", "GET", ["key" => $this->apiKey, "id" => $jobId, "action"=> 'get']) ?: '';
            if (str_starts_with($r, 'OK|')) 
                return explode('|', $r, 2)[1];
            
            if (empty($r) || Api::errType($r) === 'ret') continue;
            
            throw new Exception($r);

        } while (time() - $start < 300);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** info saldo */
    public function getInfo(): bool{
        $maxRetry = 3;
        $r = null;
        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            $r = json_decode(
                Net::S($this->baseUrl . "/res.php","GET",["action" => "userinfo","key" => $this->apiKey,"json" => 1,])?: ''
            , 1);
            
            if ($r !== null) break;
        }
        
        if ($r === null) return false;
        
        $balance = $r['balance'] ?? null;
        if ($balance !== null && strncmp((string) $balance, '-0.00', 5) !== 0) {
            Logger::X('info', 'multibot: ' . $balance);
            return true;
        }
        
        Logger::X('err', 'multibot: ' . ($r['request'] ?? 'unknown'));
        return false;
    }
    
}