<?php

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