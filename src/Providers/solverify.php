<?php 

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