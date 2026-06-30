<?php

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