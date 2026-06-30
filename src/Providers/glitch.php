<?php

class glitch extends Provider {
    
    
    
    protected $baseUrl = "https://buxads.com/api-token/api.php";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $s = json_decode(
            Net::S($this->baseUrl, "POST", array_merge(["apikey" => $this->apiKey, "mode" => $method], $params), json: true) ?: ''
            , 1);
#var_dump($s);
        if (!is_array($s) || empty($s["jobId"])) {
            throw new Exception(is_array($s) ? ($s["error"] ?? 'unknown') : 'unknown');
        }

        return $s["jobId"];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(10);
            $r = json_decode(
                Net::S($this->baseUrl, "POST", ["apikey" => $this->apiKey, "id"  => $jobId, 'action' => 'get'], json: true) ?: ''
            , 1);
#var_dump($r);
            if (($r['status'] ?? 0) == 1) {
                $solution = ['token', 'index', 'indices', 'text', 'order', 'original_url'];
                foreach ($solution as $key) {
                    if (isset($r[$key])) {
                        return $r[$key];
                    }
                }
                return $r;
            }

            if (!is_array($r) || Api::errType($q = ($r['message'] ?? 'unknown')) === 'ret') continue;
            
            throw new Exception($q);

        } while (time() - $start < 600);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** shortlink resolver */
    public function shortLink00($link) {
        $params = ["url" => $link];
        $short = $this->run('shortlink', $params);
        if (!$short) return false;
        return $short;
    }
    
    public function shortLink($link) {
        $params = ["url" => $link]; 
        return $this->run('shortlink', $params, true);
    }

    
    /** atb override */
    public function atb00(array $data) {
        
        $params = [
            "mode" => "freeantibot",
            "main" => $data['main'],
            "sub" => []
        ];
        
        foreach ($data['rels'] as $i => $b64) {
            $params['sub'][$i + 1] = $b64;
        }
        $antibot = $this->run('antibot', $params);
        #var_dump($antibot);
        if (!str_starts_with($antibot, ' ')) {
            return " $antibot";
        }
        
        return $antibot;
    }
    
    public function atb(array $data) {
        
        $params = [
            "mode" => "freeantibot",
            "main" => $data['main'],
            "sub" => []
        ];
        
        foreach ($data['rels'] as $i => $b64) {
            $params['sub'][$i + 1] = $b64;
        }
        
        $res = $this->run('antibot', $params);
        
        if (isset($res['fail'])) return $res;
        
        $antibot = $res['done'];
        
        if (!str_starts_with($antibot, ' ')) {
            $antibot = " $antibot";
        }
        
        return ['done' => $antibot];
    }

    
    /** info saldo */
    public function getInfo(): bool{
        $maxRetry = 3;
        $r = null;
        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            $r = json_decode(Net::S($this->baseUrl,"POST",["apikey" => $this->apiKey,"action" => "getbalance",],json: true)?: '', 1);
            
            if ($r !== null) break;
        }
        
        if ($r === null) return false;
        
        if (isset($r['error'])) {
            Logger::X('err', $r['error']);
            return false;
        }
        Logger::X('info', 'glitch: ' . ($r['balance'] ?? 'unknown'));
        return true;
    }
    
}