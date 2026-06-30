<?php

class gmxch extends Provider {
    
    
    
    protected $baseUrl = "https://route.up.railway.app";

    /** submit job ke API */
    protected function get_api($method, array $params) {

        $s = json_decode(
            Net::S($this->baseUrl."/v3/initTask", "POST", array_merge(["type"=>$method], $params), ["key:".$this->apiKey], json: true) ?: ''
            , 1);
#var_dump($s);
#var_dump($this->apiKey);
        if (!is_array($s) || isset($s['error']) || ($s["status"] ?? '') === 'error') {
            throw new Exception(is_array($s) ? ($s["message"] ?? $s['error']) : 'unknown');
        }

        return $s['taskId'];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(5); 
            $r = json_decode(
                Net::S($this->baseUrl."/v3/pollTask", "POST",["taskId" => $jobId], ["key:".$this->apiKey], json: true) ?: ''
                , 1);
#var_dump($r);
            
            if (($r['status'] ?? '') === 'done') {
                if (!empty($r['debug'])) {
                    _put(ROOT.'/atb.png', base64_decode($r['debug']));
                }
                return $r['token'] ?? $r['solution'] ?? $r;
            }

            
            if (!is_array($r) || Api::errType($q = ($r['status'] ?? 'unknown')) === 'ret') 
                continue;

            throw new Exception($r['message'] ?? 'unknown');

        } while (time() - $start < 300);

        throw new Exception("ERROR_TIMEOUT");
    }
    
    /** shortlink resolver */
    public function shortLink($link) {
        $params = ["url" => $link];
        return $this->run('shortlink', $params, true);
    }

    public function zer(array $data) {
    
        $params = [
            "method" => "zercaptcha",
            "main" => $data['main'],
            "options" => []
        ];
    
        $map = [];
        $i = 0;
    
        foreach ($data['rels'] as $rel => $b64) {
            $params['options'][] = $b64;
            $map[$i++] = $rel;
        }
    
        $res = $this->run('visual', $params);
    
        if (isset($res['fail'])) {
            return $res;
        }
    
        $res = $res['done'];
    
        if (is_numeric($res)) {
            $idx = (int)$res;
    
            if (isset($map[$idx])) {
                return ['done' => $map[$idx]];
            }
        }
    
        return ['fail' => 777];
    }
    
    public function atb(array $data) {
    
        $params = [
            "method" => "antibotlinks",
            "main" => $data['main'],
            "options" => []
        ];
    
        $map = [];
        $i = 0;
    
        foreach ($data['rels'] as $rel => $b64) {
            $params['options'][] = $b64;
            $map[$i++] = $rel;
        }
    
        $res = $this->run('visual', $params);
    
        if (isset($res['fail'])) {
            return $res;
        }
    
        $res = json_decode($res['done'], true);
    
        if (!is_array($res)) {
            return ['fail' => 777];
        }
    
        $links = [];
    
        foreach ($res as $val) {
            $val = trim($val);
    
            if (isset($map[$val])) {
                $links[] = $map[$val];
            }
        }
    
        return !empty($links)
            ? ['done' => ' ' . implode(' ', $links)]
            : ['fail' => 777];
    }
    
    public function bct(array $data) {
        return ['fail' => 777];
        
        $params = [
            "method" => "bitcotasks",
            "main" => $data['main'],
            "options" => []
        ];
    
        foreach ($data['opsi'] as $rel => $b64) {
            $params['options'][] = $b64;
        }
    
        $sol = $this->run('visual', $params, true);
    
        if (isset($sol['fail'])) {
            return $sol;
        }
    
        return [
            'ans' => null,
            'idx' => $sol['done'] ?? null
        ];
    }
    
    /** info saldo */
    public function getInfo(): bool{
        $maxRetry = 3;
        $i = null;
        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            $i = json_decode(
                Net::S($this->baseUrl . "/key","POST",null,["key:" . $this->apiKey],json: true)?: ''
            , 1);
            
            if ($i !== null) break;
        }
        
        #var_dump($i); die;
        if ($i === null) return false;
        
        if (!empty($i['status']) && isset($i['authorized'])) return (bool) $i['authorized'];
        
        Logger::X('info', 'gmxch: ' . ($i['message'] ?? 'unknown'));
        return false;
    }
    
}