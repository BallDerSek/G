<?php

class xevil extends Provider {
    
    

    protected $baseUrl = "http://sctg.xyz";

    protected function get_api($method, array $params) {
        
        $s = json_decode(
            Net::S($this->baseUrl . "/in.php", "POST",array_merge(["key"=>$this->apiKey.'|SOFTID7745286578', "method"=>$method, "json"=>"1"], $params)) ?: ''
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
            _sle(5);
            $r = Net::S($this->baseUrl."/res.php", "GET", ["key" => $this->apiKey.'|SOFTID7745286578', "id"  => $jobId, "action"=> 'get']) ?: '';
                
            if (str_starts_with($r, 'OK|')) return explode('|', $r, 2)[1];
            
            if (empty($r) || Api::errType($r) === 'ret') continue;
            
            throw new Exception($r);

        } while (time() - $start < 200);

        throw new Exception("ERROR_TIMEOUT");
    }
    
    public function bctt($param, $j) {
        
        $_d['main'] = $param['main'];
        
        foreach ($param['opsi'] as $i => $r) {
            $_d[$i + 1] = $r;
        }
        
        var_dump($_d);
        
        $res = $this->run('bicotasks', $_d, true);
        var_dump($res);
        
        die;
        
        if (!$res) return false;
        
        return $res;
    }
    
    public function getInfo(): bool{
        $max = 3;
        $r = null;
        for ($i = 0; $i < $max; $i++) {
            $r = json_decode(
                Net::S($this->baseUrl."/res.php","GET",["action" => "getbalance","key" => $this->apiKey,"json" => 1,]) ?: ''
            , 1);
            
            if ($r !== null) break;
        }
        
        if ($r === null) return false;
        
        if (isset($r['request']) && strncmp($r['request'], '-0.00', 5) === 0) {
            Logger::X('err', 'xevil: ' . $r['request']);
            return false;
        }
        Logger::X('info', 'xevil: ' . ($r['request'] ?? 'unknown'));
        return true;
    }
    
} 