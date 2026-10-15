<?php

class gmxch extends Provider {
    
    
    
    protected $baseUrl = "https://api.gsectr.my.id";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $payload = $this->payload($method, $params);

        $s = json_decode(
            Net::S($this->baseUrl."/v4/getSolution?time=3", "POST", $payload, ["key:".$this->apiKey], json: true) ?: ''
            , 1);
#var_dump($s);

        if (!is_array($s) || ($s['errorId'] ?? 0) != 0 || ($s["status"] ?? '') === 'error') {
            throw new Exception(is_array($s) ? ($s["errorCode"]) : 'unknown');
        }

        return [$payload['task']['type'], $s['taskId']];
    }

    /** polling hasil job */
    protected function res_api($task) {
        
        [$taskType, $taskId] = $task;
        $start = time();
        do {
            _sle(5); 
            $r = json_decode(
                Net::S($this->baseUrl."/v4/getTaskResult", "POST",["taskId" => $taskId], ["key:".$this->apiKey], json: true) ?: ''
                , 1);
#var_dump($r);
            
            if (($r['status'] ?? '') === 'ready') {
                if (!empty($r['debug']['img'])) _put(ROOT.'/img.png', base64_decode($r['debug']['img']));
                
                if ($taskType == 'rscaptcha') return "@TOKEN::{$r['solution']['token']}";
                
                return $r['solution']['token'] ?? $r['solution']['answer'] ?? $r['solution'];
            }

            
            if (!is_array($r) || Api::errType($q = ($r['status'] ?? 'unknown')) === 'ret') 
                continue;

            throw new Exception($r['errorCode'] ?? 'unknown');

        } while (time() - $start < 300);

        throw new Exception("ERROR_TIMEOUT");
    }
    
    public function zer(array $data) {
        
        $params = [
            "type" => "zercaptcha",
            "websiteImg" => $data['main'],
            "optionsList" => []
        ];
    
        $map = [];
        $i = 0;
    
        foreach ($data['rels'] as $rel => $b64) {
            $params['optionsList'][] = $b64;
            $map[$i++] = $rel;
        }
    
        $res = $this->run('VisualCaptchaTask', $params);
    
        if (isset($res['fail'])) return $res;
    
        $res = $res['done'];
    
        if (is_numeric($res)) {
            $idx = (int)$res;
    
            if (isset($map[$idx])) return ['done' => $map[$idx]];
            
        }
    
        return ['fail' => 777];
    }
    
    public function atb(array $data) {
    
        $params = [
            "type" => "antibotlinks",
            "websiteImg" => $data['main'],
            "optionsList" => []
        ];
    
        $map = [];
        $i = 0;
    
        foreach ($data['rels'] as $rel => $b64) {
            $params['optionsList'][] = $b64;
            $map[$i++] = $rel;
        }
    
        $res = $this->run('VisualCaptchaTask', $params);
    
        if (isset($res['fail'])) return $res;
    
        $res = json_decode($res['done'], true);
    
        if (!is_array($res)) return ['fail' => 777];
    
        $links = [];
    
        foreach ($res as $val) {
            $val = trim($val);
    
            if (isset($map[$val])) $links[] = $map[$val];
            
        }
    
        return !empty($links) ? ['done' => ' ' . implode(' ', $links)] : ['fail' => 777];
    }
    
    private function payload(string $api, array $extra): array {
        $payload = [
            'method' => (string)$api,
            'task' => $extra
        ];
    
        if (isset($extra['proxy']) && is_string($extra['proxy'])) {
            $prx = parse_url($extra['proxy']);
            
            if ($prx !== false) {
                unset($payload['task']['proxy']);
                
                $proxyArr = [
                    'type' => $prx['scheme'] ?? 'http',
                    'host' => $prx['host'] ?? '',
                    'port' => $prx['port'] ?? 80,
                ];
                
                if (isset($prx['user'])) $proxyArr['user'] = $prx['user'];
                if (isset($prx['pass'])) $proxyArr['pass'] = $prx['pass'];
                
                $payload['task']['proxy'] = $proxyArr;
            }
        }
        
        #$payload['data']['debug'] = 1;
        #var_dump($payload);
    
        return $payload;
    }
    
    /** info saldo */
    public function getInfo(): bool {
        $maxRetry = 3;
        $i = null;
        
        for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
            $i = json_decode(
                Net::S(
                    $this->baseUrl . "/v4/getBalance", "POST",
                    ["key" => $this->apiKey], json: true
                )?: ''
            , true);
            
            if ($i !== null) break;
        }
        
        if ($i === null) return false;
        
        Logger::X('info', 'gmxch: ' . ($i['balance'] ?? 'unknown'));
        
        return ($i['errorId'] ?? 1) === 0;
    }
    
}
