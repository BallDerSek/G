<?php
/**
 * 
 * 
 * 
 */
if (!defined('ROOT')) exit;

if (!class_exists('Api', false)) exit(1);

class xevil extends Provider {
    
    protected const ATB_MODE = 'num';

    protected $baseUrl = "http://sctg.xyz";

    protected function get_api($method, array $params) {
        $s = json_decode(
            Net::X($this->baseUrl . "/in.php", "POST",array_merge(["key"=>$this->apiKey.'|SOFTID7745286578', "method"=>$method, "json"=>"1"], $params)) ?: ''
            , true);
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
            _sle(2);
            $r = Net::X($this->baseUrl."/res.php", "GET", ["key" => $this->apiKey.'|SOFTID7745286578', "id"  => $jobId, "action"=> 'get']) ?: '';
                
            if (str_starts_with($r, 'OK|')) return explode('|', $r, 2)[1];
            
            if (Api::errType($r) === 'ret') continue;  
            
            throw new Exception($r);

        } while (time() - $start < 200);

        throw new Exception("ERROR_TIMEOUT");
    }

    public function getInfo(): bool {
        info:
        $r = json_decode(
            Net::C($this->baseUrl."/res.php", "GET", ["action" => "getbalance", "key" => $this->apiKey, "json" => 1]) ?: ''
            , true);
#var_dump($r);
        if ($r === null) goto info; 

        if (isset($r['request']) && strncmp($r['request'], '-0.00', 5) === 0) {
            logx('err', 'xevil: '.$r['request']);
            return false;
        }
        logx('info', "\nxevil: ".$r['request']);
        return true;
    }
    
} 

class skibidixxx extends Provider {
    
    protected const ATB_MODE = 'rel';
    
    protected $baseUrl = "https://waryono.my.id/api";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $s = json_decode(
            Net::X($this->baseUrl."/in.php", "POST", array_merge(["apikey" => $this->apiKey, "methods" => $method], $params), null, [], '', null, true) ?: ''
            , true);

        if (!is_array($s) || ($s["status"] ?? 0) != 1) {
            throw new Exception(is_array($s) ? ($s["request"] ?? 'unknown') : 'unknown');
        }

        return $s["request"];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(2);
            $r = json_decode(
                Net::C($this->baseUrl."/res.php", "GET", ["apikey" => $this->apiKey, "id"  => $jobId]) ?: ''
            , true);
#var_dump($r);
            if (($r['status'] ?? 0) == 1) return $r['result'];

            if (!is_array($r) || Api::errType($q = ($r['message'] ?? 'unknown')) === 'ret') continue;
            
            throw new Exception($q);

        } while (time() - $start < 600);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** shortlink resolver */
    public function shortLink($link) {
        $map = [
            "adlink.click"    => "adlink",
            "oii.la"          => "clksh",
            "linkcut.pro"     => "linkcutpro",
            "aii.sh"          => "shrinkbixby",
            "tpi.li"          => "shrinkearn",
            "shrinkme.click"  => "shrinkme",
            "inbz.la"         => "shrinkpe",
        ];
        $host = parse_url($link, PHP_URL_HOST);
        $type = null;

        foreach ($map as $domain => $method) {
            if (stripos($host, $domain) !== false) {
                $type = $method;
                break;
            }
        }

        if (!$type) {
            logx('err', "unsupported shortlink: $host");
            return false;
        }

        $params = ["url" => $link];
        return $this->run($type, $params);
    }

    /** info saldo */
    public function getInfo(): bool {
        info:
        $r = json_decode(
            Net::C($this->baseUrl."/balance.php", "GET", ["apikey" => $this->apiKey]) ?: ''
            , true);

        if ($r === null) goto info; 

        if (isset($r['error'])) {
            logx('err', $r['error']);
            return false;
        }

        logx('info', "\nwaryono: ".$r['balance']);
        return true;
    }
    
}

class tertuyul extends Provider {
    
    protected const ATB_MODE = 'num';
    
    protected $baseUrl = "http://api.tertuyul.my.id";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $s = json_decode(
            Net::X($this->baseUrl."/in.php", "POST", array_merge(["key" => $this->apiKey, "json" => 1, "method" => $method], $params)) ?: ''
            , true);

        if (!is_array($s) || ($s["status"] ?? 0) != 1) {
            throw new Exception(is_array($s) ? ($s["request"] ?? 'unknown') : 'unknown');
        }

        return $s["request"];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(2); 
            $r = json_decode(
                Net::C($this->baseUrl."/res.php", "GET", ["key" => $this->apiKey, "id"  => $jobId, "json"=> 1]) ?: ''
                , true);

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
        return styler("getting.." . (parse_url($link, PHP_URL_HOST)), function() use($link) {
            $result = json_decode(
                Net::X("https://tertuyul.my.id/apikey/", "POST", ["method" => "result_link", "url" => $link, "apikey" => $this->apiKey], null, [], "", null, true)
                , true);

            if (isset($result['fail'])) 
                return $result['fail'];
            
            return $result['url'];
        });
    }

    /** info saldo */
    public function getInfo(): bool {
        info:
        $i = json_decode(
            Net::C($this->baseUrl."/res.php", "GET", ["action" => "userinfo", "key" => $this->apiKey, "json" => 1]) ?: ''
            , true);

        if ($i === null) goto info; 

        if (!isset($i['balance'])) {
            logx("err", $i["request"]);
            return false;
        }

        logx('info', "\nTertuyul: ".$i['balance']);
        return true;
    }
    
}

class multibot extends Provider {
    
    protected const ATB_MODE = 'rel';
    
    protected $baseUrl = "https://api.multibot.in";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $s = json_decode(
            Net::X($this->baseUrl."/in.php", "POST", array_merge(["key" => $this->apiKey, "method" => $method, "json" => "1"], $params)) ?: ''
            , true);
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
            _sle(2); 
            $r = 
                Net::C($this->baseUrl."/res.php", "GET", ["key" => $this->apiKey, "id" => $jobId, "action"=> 'get']) ?: '';
            if (str_starts_with($r, 'OK|')) 
                return explode('|', $r, 2)[1];
            
            if (Api::errType($r) === 'ret') continue; 
            
            throw new Exception($r);

        } while (time() - $start < 300);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** info saldo */
    public function getInfo(): bool {
        info:
        $r = json_decode(
            Net::C($this->baseUrl."/res.php", "GET", [
                "action" => "userinfo",
                "key"    => $this->apiKey,
                "json"   => 1
            ]) ?: '',
            true
        );

        if ($r === null) { goto info; }

        if (isset($r['request']) && strncmp($r['request'], '-0.00', 5) === 0) {
            logx('err', 'multibot: '.$r['request']);
            return false;
        }

        logx('info', "\nmultibot: ".$r['balance']);
        return true;
    }
    
}

class gmxch extends Provider {
    protected $baseUrl = "https://gmxch-to.hf.space";

    /** submit job ke API */
    protected function get_api($method, array $params) {
#var_dump($params); var_dump($method);
        $s = json_decode(
            Net::X($this->baseUrl."/solve", "POST", array_merge(["type"=>$method], $params), null, ["key:".$this->apiKey], '', null, true) ?: ''
            , true);
#var_dump($s);
        if (!is_array($s) || isset($s['error']) || ($s["status"] ?? '') === 'error') {
            throw new Exception(is_array($s) ? ($s["message"] ?? $s['error']) : 'unknown');
        }

        return $s['taskId'];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(2); 
            $r = json_decode(
                Net::X($this->baseUrl."/task", "POST",["taskId" => $jobId], null, ["key:".$this->apiKey], '', null, true) ?: ''
                    , true);
#var_dump($r);
            if (($r['status'] ?? '') === 'done') 
                return $r['token'] ?? $r;

            if (($r['status'] ?? '') === 'pending' || ($r['status'] ?? '') === 'processing') 
                continue;

            throw new Exception($r['message'] ?? 'unknown');

        } while (time() - $start < 200);

        throw new Exception("ERROR_TIMEOUT");
    }

    /** shortlink resolver */
    public function shortLink($link) {
        return styler("getting.." . (parse_url($link, PHP_URL_HOST)), function() use($link) {
            $result = json_decode(
                Net::C($this->baseUrl."/solve", "POST",
                    ["type" => "shortlink", "url" => $link],
                    null,
                    ["key:".$this->apiKey,"Content-Type: application/json","Accept: application/json"]
                ),
                true
            );

            return $result['url'] ?? ($result['fail'] ?? null);
        });
    }

    /** info saldo */
    public function getInfo(): bool {
        
        info:
        $i = json_decode(
            Net::X($this->baseUrl."/key", "POST", null, null, ["key:".$this->apiKey], '', null, true) ?: ''
            , true);

        if ($i === null) { goto info; }
        
        if (!empty($i['status']) && isset($i['authorized'])) {
            return $i['authorized'];
        }

        logx('info', "\ngmxch: ".$i['message']);
        return true;
    }
}




class solverify extends Provider {
    protected $baseUrl = "https://solver.solverify.net";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        $c = json_decode(
            Net::X($this->baseUrl . "/createTask", "POST",
                ["clientKey" => $this->apiKey, "task" => array_merge(["type" => $method], $params)], null, [], '', null, true) ?: ''
                , true);

        if (!is_array($c) || ($c["errorId"] ?? 1) !== 0) {
            throw new Exception(is_array($c) ? ($c["errorCode"] ?? 'unknown') : 'unknown');
        }

        return $c["taskId"];
    }

    /** polling hasil job */
    protected function res_api($jobId) {
        $start = time();
        do {
            _sle(2); 
            $res = json_decode(
                Net::X($this->baseUrl . "/GetTaskResult", "POST", ["clientKey" => $this->apiKey, "taskId" => $jobId], null, [], "", null, true) ?: ''
                , true);
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
            Net::X($this->baseUrl . "/getBalance", "POST",
                ["clientKey" => $this->apiKey], null, [], '', null, true) ?: ''
            , true);

        if ($r === null) goto info; 

        if (($r["errorId"] ?? 0) !== 0) {
            logx("err", $r["errorDescription"]);
            return false;
        }

        logx('info', "\nSolverify: ".$r["balance"]);
        return true;
    }
}

class capsolver extends Provider {
    protected $baseUrl = "https://api.capsolver.com";

    /** submit job ke API */
    protected function get_api($method, array $params) {
        [$apiReal, $paramsReal] = $this->payload($method, $params);

        $c = json_decode(
            Net::X($this->baseUrl."/createTask", "POST", [
                "clientKey"=>$this->apiKey,
                "task"=>array_merge(["type"=>$apiReal], $paramsReal)
            ], null, [], '', null, true) ?: ''
        , true);

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
            return json_decode(substr($jobId, 9), true);
        }

        $start = time();

        do {
            _sle(2);

            $res = json_decode(
                Net::X($this->baseUrl."/getTaskResult", "POST", [
                    "clientKey"=>$this->apiKey,
                    "taskId"=>$jobId
                ], null, [], '', null, true) ?: ''
            , true);

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
                        Net::X($this->baseUrl."/getToken", "POST", [
                            "clientKey" => $this->apiKey,
                            "task" => array_merge(["type" => $apiReal], $paramsReal)
                        ], null, [], '', null, true) ?: ''
                    , true);
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
                        _sle(2);
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
        $r = json_decode(Net::X($this->baseUrl."/getBalance", "POST",
            ["clientKey"=>$this->apiKey], null, [], '', null, true
        ) ?: '', true);

        if (($r["errorId"] ?? 0) !== 0) return false;

        logx('info', "\ncapsolver: ".$r["balance"]);
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
            _sle(2);
            $s = json_decode(Net::X($this->baseUrl.$t."?id=".$c["data"], 'GET', null, null, ["Authorization: Basic ".$this->apiKey], '', null, true), true);

            var_dump($s);
            
            if (isset($s["data"])) { return $s; }
            $err = $s["error"];
            if (in_array($err, ['11', '14'])) {
                logx('info', $s["message"]);
                continue;
            }

            if (in_array($err, ['10', '9'])) {
                throw new Exception ($s['type']);
            }
        } while (time() - $start < 200);
        } catch (Throwable $e) {
                logx('err', "{$e->getMessage()}");
                #continue;
            }
            
        logx('err', "$t failed"); return false;
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
        $base64Img  = base64_encode(file_get_contents($imgPath));
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
            logx('err', $r["message"]);
            return false;
        }
        logx('info', "\nnopecha: ".$r["credit"]);
        return true;
    }

}