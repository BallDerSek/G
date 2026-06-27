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
            $_d[$i] = $r;
        }
        
        $res =  $this->run('bitcotasks', $_d, true);
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
        
        if (isset($sol['fail'])) {
            return $sol;
        }
        $ans = $sol['done'];
        
        /*
        var_dump($sol);
        var_dump($ans);
        */
        
        if (!is_string($ans) || !preg_match('/class:([^,]+), array:(\d+)/', $ans, $m)) {
            return ['fail' => 777];
        }
        
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
    
        foreach ($param['opsi'] as $_i => $_o) {
            $_d[$_i] = $_o;
        }
    
        $res = $this->run('bitcotask', $_d, true);
        
        if (isset($res['fail'])) {
            return $res;
        }
    
        $val = $res['done'];
    
        $ans = $val;
        $idx = null;
    
        if (strpos($val, ':') !== false) {
            [$ans, $idx] = explode(':', $val, 2);
        }
    
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
    
        Logger::X('info', 'Tertuyul: ' . $i['balance']);
        return true;
    }
    
}

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









class solverify extends Provider {
    protected $baseUrl = "https://solver.solverify.net";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $c = json_decode(
            Net::S($this->baseUrl . "/createTask", "POST",
                ["clientKey" => $this->apiKey, "task" => array_merge(["type" => $method], $params)], json: true) ?: ''
                , 1);

        if (!is_array($c) || ($c["errorId"] ?? 1) !== 0) {
            throw new Exception(is_array($c) ? ($c["errorCode"] ?? 'unknown') : 'unknown');
        }

        return $c["taskId"];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(5); 
            $res = json_decode(
                Net::S($this->baseUrl . "/GetTaskResult", "POST", ["clientKey" => $this->apiKey, "taskId" => $jobId], json: true) ?: ''
                , 1);
            #var_dump($res);

            if (!is_array($res) ||
                ($res['status'] ?? '') === 'processing' ||
                (($res["errorId"] ?? 0) !== 0 && Api::errType($code = ($res["errorCode"] ?? 'unknown')) === 'ret')) 
                continue;
/*
            if (($res["errorId"] ?? 0) !== 0) {
                throw new Exception($res["errorCode"] ?? 'unknown');
            }
*/
            $val = $res["solution"] ?? ($res["result"]["solution"] ?? null);
            if ($val !== null) 
                return $val;
                
            throw new Exception($res['errorDescription'] ?? 'unknown');

        } while (time() - $start < 200);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** info saldo */
    public function getInfo(): bool {
        info:
        $r = json_decode(
            Net::S($this->baseUrl . "/getBalance", "POST",
                ["clientKey" => $this->apiKey], json: true) ?: ''
            , 1);

        if ($r === null) goto info; 

        if (($r["errorId"] ?? 0) !== 0) {
            Logger::X("err", $r["errorDescription"]);
            return false;
        }

        Logger::X('info', "Solverify: ".$r["balance"]);
        return true;
    }
}

class capsolver extends Provider {
    protected $baseUrl = "https://api.capsolver.com";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        [$apiReal, $paramsReal] = $this->payload($method, $params);

        $c = json_decode(
            Net::S($this->baseUrl."/createTask", "POST", [
                "clientKey"=>$this->apiKey,
                "task"=>array_merge(["type"=>$apiReal], $paramsReal)
            ], null, [], '', null, true) ?: ''
        , 1);

        if (!is_array($c) || ($c["errorId"] ?? 1) !== 0) {
            throw new Exception($c["errorCode"] ?? 'unknown');
        }

        if (($c['status'] ?? '') === 'ready' && isset($c['solution'])) {
            return "@instant=" . json_encode($c['solution']);
        }

        return $c["taskId"];
    }

    /** polling hasil job */
    protected function res_api($jobId) {

        if (str_starts_with($jobId, "@instant=")) {
            return json_decode(substr($jobId, 9), 1);
        }

        $start = time();

        do {
            _sle(5);

            $res = json_decode(
                Net::S($this->baseUrl."/getTaskResult", "POST", ["clientKey"=>$this->apiKey, "taskId"=>$jobId], json: true) ?: ''
            , 1);

            $val = $res["solution"] ?? ($res["result"]["solution"] ?? null);
            if ($val !== null) return $val;

            $errId = $res["errorId"] ?? 0;
            $code  = $res["errorCode"] ?? '';

            if (($res['status'] ?? '') === 'processing') {
                continue;
            }

            if ($errId !== 0) {
                $type = Api::errType($code);

                if (in_array($type, ['fail','ret','con'], true)) {
                    throw new Exception($code);
                }

                throw new Exception($res['errorDescription'] ?? $code);
            }

        } while (time() - $start < 120);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** mapping payload */
    private function payload($api, array $extra): array {
        $api = strtolower($api);
        $apiReal = $api;

        if ($api === 'turnstile') {
            $apiReal = 'AntiTurnstileTaskProxyLess';
            $meta = $extra['metadata'] ?? [];

            if (array_key_exists('action', $extra)) {
                $meta['action'] = $extra['action'];
                unset($extra['action']);
            }

            if (array_key_exists('cdata', $extra)) {
                $meta['cdata'] = $extra['cdata'];
                unset($extra['cdata']);
            }

            if ($meta) $extra['metadata'] = $meta;
            return [$apiReal, $extra];
        }

        if ($api === 'recaptcha2') {
            $ent = (($extra['enterprise'] ?? '0') === '1');
            unset($extra['enterprise']);

            $ds = $extra['data-s'] ?? null;
            unset($extra['data-s']);

            if (array_key_exists('invisible', $extra)) {
                $extra['isInvisible'] = (($extra['invisible'] ?? '0') === '1');
                unset($extra['invisible']);
            }

            $apiReal = $ent ? 'ReCaptchaV2EnterpriseTaskProxyLess' : 'ReCaptchaV2TaskProxyLess';

            if ($ds) {
                if ($ent) {
                    $extra['enterprisePayload']['s'] = $ds;
                } else {
                    $extra['recaptchaDataSValue'] = $ds;
                }
            }

            return [$apiReal, $extra];
        }

        if ($api === 'recaptcha3') {
            $ent = (($extra['enterprise'] ?? '0') === '1');
            unset($extra['enterprise']);

            if (isset($extra['action']) && !isset($extra['pageAction'])) {
                $extra['pageAction'] = $extra['action'];
                unset($extra['action']);
            }

            $apiReal = $ent ? 'ReCaptchaV3EnterpriseTaskProxyLess' : 'ReCaptchaV3TaskProxyLess';
            return [$apiReal, $extra];
        }

        return [$apiReal, $extra];
    }

    /** TOKEN (HYBRID + RETRY) */
    public function token($siteKey, $siteUrl, $type, array $extraParams = []) {

        $cfg = Api::TKN[__CLASS__][$type] ?? null;
        if (!$cfg) return null;

        foreach (($cfg['need'] ?? []) as $k) {
            if (!isset($extraParams[$k])) return null;
        }

        $params = array_merge(
            [$cfg['k'] => $siteKey, $cfg['url'] => $siteUrl],
            ($cfg['defaults'] ?? []),
            $extraParams
        );

        [$apiReal, $paramsReal] = $this->payload($cfg['api'], $params);

        if (in_array($type, ['rc2','rc3'])) {
            $attempt = 0;
            while ($attempt < 3) {
                try {
                    $res = json_decode(
                        Net::S($this->baseUrl."/getToken", "POST", ["clientKey" => $this->apiKey, "task" => array_merge(["type" => $apiReal], $paramsReal)
                        ], json: true) ?: ''
                    , 1);
                    if (!is_array($res) || ($res["errorId"] ?? 1) !== 0) {
                        throw new Exception($res["errorCode"] ?? 'unknown');
                    }

                    if (($res['status'] ?? '') === 'ready') {
                        $sol = $res['solution'];
                    } elseif (($res['status'] ?? '') === 'processing') {
                        $sol = $this->res_api($res['taskId']);
                    } else {
                        $sol = null;
                    }

                    if (is_array($sol)) break;

                } catch (Throwable $e) {
                    $typeErr = Api::errType($e->getMessage());

                    if (in_array($typeErr, ['fail','ret','con'], true)) {
                        _sle(5);
                        $attempt++;
                        continue;
                    }

                    break;
                }

                $attempt++;
            }

        } else {
            $sol = $this->run($apiReal, $paramsReal);
        }

        if (is_array($sol)) {
            foreach ([
                'token',
                'gRecaptchaResponse',
                'cfTurnstileResponse',
                'cf-turnstile-response',
                'recaptchaResponse',
                'g-recaptcha-response'
            ] as $k) {
                if (!empty($sol[$k])) return $sol[$k];
            }
        }

        return false;
    }

    /** saldo */
    public function getInfo(): bool {
        $r = json_decode(Net::S($this->baseUrl."/getBalance", "POST", ["clientKey"=>$this->apiKey], json: true
        ) ?: '', 1);

        if (($r["errorId"] ?? 0) !== 0) return false;

        Logger::X('info', "capsolver: ".$r["balance"]);
        return true;
    }
}


class nopecha { /* https://nopecha.com/ */
    private $apiKey;
    private $baseUrl = "https://api.nopecha.com/v1";

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    private function solver($t, $params) {
        try {
        
        $c = json_decode(Net::X($this->baseUrl.$t, 'POST', $params, null, ["Authorization: Basic ".$this->apiKey], '', null, true), true);
        var_dump($c);
        if (!is_array($c) || !isset($c["data"]) || $c === null) {
            throw new Exception($c['type'] ?? "Unknown");
            }
        
        $start = time();
        do {
            _sle(5);
            $s = json_decode(Net::X($this->baseUrl.$t."?id=".$c["data"], 'GET', null, null, ["Authorization: Basic ".$this->apiKey], '', null, true), true);

            var_dump($s);
            
            if (isset($s["data"])) { return $s; }
            $err = $s["error"];
            if (in_array($err, ['11', '14'])) {
                Logger::X('info', $s["message"]);
                continue;
            }

            if (in_array($err, ['10', '9'])) {
                throw new Exception ($s['type']);
            }
        } while (time() - $start < 200);
        } catch (Throwable $e) {
                Logger::X('err', "{$e->getMessage()}");
                #continue;
            }
            
        Logger::X('err', "$t failed"); return false;
    }

    private function payload($api, array $extra): array {
        $api = strtolower($api);
        
        $dataKeys = match ($api) {
            'turnstile'  => ['action','cdata'],
            'hcaptcha'   => ['rqdata'],
            'recaptcha2' => ['theme','s'],
            'recaptcha3' => ['action','theme','s'],
            default      => [],
        };
        $data = $extra['data'] ?? [];
        unset($extra['data']);
       
       foreach ($dataKeys as $k) {
           if (array_key_exists($k, $extra)) {
               $data[$k] = $extra[$k];
               unset($extra[$k]);
           }
       } return $data ? ($extra + ['data' => $data]) : $extra;
    }

    public function token($siteKey, $siteUrl, $type, array $extraParams = []) {
        $cfg = Api::TKN[__CLASS__][$type] ?? null;
        
        foreach (($cfg['need'] ?? []) as $k) {
            if (!isset($extraParams[$k])) {
                print("missing arg: $k\n");
                return null; 
            }
        }
        $params = array_merge([$cfg['k'] => $siteKey, $cfg['url'] => $siteUrl], ($cfg['defaults'] ?? []), $extraParams);
        
        $params = $this->payload($cfg['api'], $params);
    return $this->solver("/token/".$cfg['api'], $params);
}

    public function base64($imgPath, $type = 'ocr') {
        $base64Img  = base64_encode(_get($imgPath));
        $mime = mime_content_type($imgPath);
        
        $dataUri = "data:".$mime.";base64,".$base64Img;
        $img = ["image_data" => [$dataUri]];
        
        $cfg = Api::B64[__CLASS__][$type] ?? null;
        $api = $cfg['api'];
        return $this->solver("/recognition/$api", $img);
    }

    public function getInfo() {
        
        $r = json_decode(Net::X($this->baseUrl . "/status", 'GET', null, null, ["Authorization: Basic " . $this->apiKey], '', null, true), true);
        print_r($r);
        if (isset($r["error"])) {
            Logger::X('err', $r["message"]);
            return false;
        }
        Logger::X('info', "nopecha: ".$r["credit"]);
        return true;
    }

}