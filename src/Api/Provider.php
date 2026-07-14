<?php

abstract class Provider {

    protected $apiKey;
    #protected $baseUrl;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    final public function run($method, array $params, bool $strict = false) {
        return $this->call($method, $params, $strict);
    }

    final protected function call($method, array $params, bool $strict = false) {

        for ($i = 0; $i < 3; $i++) {
            try {
                return styler(static::class . "=>$method", function() use ($method, $params) {
                    $id = $this->get_api($method, $params);
                    return ['done' => $this->res_api($id)];
                });

            } catch (Throwable $e) {

                $code = $e->getMessage();
                $type = Api::errType($code);

                Logger::X('info', "\rApi [ ".static::class.' ] ', false, 1);
                Logger::X('err', "{$e->getMessage()}");

                if ($strict) return ['fail' => 1];

                if (static::class === 'gmxch' && in_array($code, ['INTERNAL_SERVER_ERROR', 'SERVICE_BUSY'], 1)) return ['fail' => 777];
                
                if (in_array($type, ['ret','con','fail'], 1)) {
                    _sle(3);
                    continue;
                }

                if (static::class === 'gmxch') return ['fail' => 777];

                return ['fail' => 77];
            }
        }

        if (static::class === 'gmxch') return ['fail' => 777];

        return ['fail' => 1];
    }

    public function token($siteKey, $siteUrl, $type, array $extraParams = []) {

        try {
            [$method, $params] = Api::cfgTkn(
                static::class,
                $type,
                $siteKey,
                $siteUrl,
                $extraParams
            );

        } catch (Throwable $e) {
            Logger::X('warn', "\r{$e->getMessage()}", 1, 1);
            return ['fail' => 71];
        }

        return $this->run($method, $params);
    }

    public function base64($img, $type = 'ocr', array $extra = []) {
        
        $raw = is_file($img) ? _get($img) : $img;
        
        $is_base64 = (base64_encode(base64_decode($raw, 1)) === $raw);
    
        if ($is_base64) {
            if (strpos($raw, ',') !== false) $raw = explode(',', $raw, 2)[1];
            $b64 = $raw;
        } else $b64 = base64_encode($raw);
    
        try {
            [$method, $params] = Api::cfgB64(static::class, $type, $b64, $extra);
        } catch (Throwable $e) {
            Logger::X('warn', "\r{$e->getMessage()}", 1, 1);
            return ['fail' => 71];
        }
        
        return $this->run($method, $params, 1);
    }

    public function access($siteUrl, $type, array $extraParams = []) {

        try {
            [$method, $params] = Api::cfgAcc(
                static::class,
                $type,
                $siteUrl,
                $extraParams
            );

            $cfg = Api::ACC[static::class][$type];

            foreach (($cfg['need'] ?? []) as $k) {
                if (!isset($params[$k])) {
                    Logger::X('warn', "\rmissing required arg: $k for $type");
                    return ['fail' => 73];
                }
            }

            return array_merge(
                ['class' => static::class],
                $this->run($method, $params)
            );

        } catch (Exception $e) {
            Logger::X('warn', "\r{$e->getMessage()}", 1, 1);
            return ['fail' => 71];
        }
    }

    public function atb(array $data) {

        $pa  = [];
        $map = [];
        $i   = 0;
        foreach ($data['rels'] as $rel => $b64) {
            $pa[(string)$rel] = $b64;
            $map[(string)$rel] = $rel;
            $map[(string)$i]   = $rel;
            $i++;
        }

        $pa['main'] = $data['main'];
        $res = $this->run('antibot', $pa, 1);

        if (isset($res['fail'])) return $res;
        $in = explode(',', $res['done']);

        $links = [];
        foreach ($in as $val) {
            $val = trim($val);
            if (isset($map[$val])) $links[] = $map[$val];
        }
        if (empty($links)) return ['fail' => 1];

        return [
            'done' => " " . implode(' ', $links)
        ];
    }

    abstract protected function get_api($method, array $params);

    abstract protected function res_api($jobId);
    
}