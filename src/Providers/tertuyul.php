<?php

class tertuyul extends Provider {
    
    
    
    protected $baseUrl = "http://api.tertuyul.my.id";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $s = json_decode(
            Net::S($this->baseUrl."/in.php", "POST", array_merge(["key" => $this->apiKey, "json" => 1, "method" => $method], $params)) ?: ''
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
                Net::S($this->baseUrl."/res.php", "GET", ["key" => $this->apiKey, "id"  => $jobId, "json"=> 1]) ?: ''
                , 1);

            if (($r['status'] ?? 0) == 1) 
                return $r['request'];

            if (!is_array($r) || Api::errType($q = ($r['request'] ?? 'unknown')) === 'ret') 
                continue;

            throw new Exception($q);

        } while (time() - $start < 200);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** shortlink resolver */
    
    public function shortLink($link) {
        $params = ["pageurl" => $link];
        return $this->run('shortlink', $params, true);
    }

    public function bct($param) {
        
        $_d['main'] = $param['main'];
    
        foreach ($param['opsi'] as $_i => $_o) $_d[$_i] = $_o;
    
        $res = $this->run('bitcotask', $_d, true);
        
        if (isset($res['fail'])) return $res;
    
        $val = $res['done'];
    
        $ans = $val;
        $idx = null;
    
        if (strpos($val, ':') !== false) [$ans, $idx] = explode(':', $val, 2);
    
        return [
            'ans' => $ans,
            'idx' => (int)$idx
        ];
    }
    
    public function rss($data, $url) {
        
        if (!$data) return ['fail' => 1];
        $param = [
            'pageurl' => $url,
            'body' => $data['master_image_base64'],
        ];
        
        return $this->run('sliders', array_merge($param, $data), true);
        
    }
    
    /** info saldo */
    public function getInfo(): bool{
        $maxRetry = 3;
        $i = null;
        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            $i = json_decode(
                Net::S($this->baseUrl . "/res.php","GET",["action" => "userinfo","key" => $this->apiKey,"json" => 1,])?: ''
            , 1);
    
            if ($i !== null) break;
            }
    
        if ($i === null) return false;
    
        if (!isset($i['balance'])) {
            Logger::X('err', $i['request'] ?? 'unknown');
            return false;
        }
    
        Logger::X('info', " [ ".static::class.": ".($i['balance'] ?? 'unknown').' ] ', 1, 1);
        return true;
    }
    
}